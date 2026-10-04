<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/archive-lib.php';

$apply = in_array('--apply', $argv, true);
$manualDate = '';
$manualFile = '';
$votesFile = '';
$standaloneFile = '';
$standaloneTitle = '';
$sourceUrl = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--date=')) $manualDate = substr($argument, 7);
    if (str_starts_with($argument, '--file=')) $manualFile = substr($argument, 7);
    if (str_starts_with($argument, '--votes-file=')) $votesFile = substr($argument, 13);
    if (str_starts_with($argument, '--standalone-file=')) $standaloneFile = substr($argument, 18);
    if (str_starts_with($argument, '--title=')) $standaloneTitle = substr($argument, 8);
    if (str_starts_with($argument, '--source-url=')) $sourceUrl = substr($argument, 13);
}
if ($standaloneFile !== '') {
    if ($manualFile !== '' || $votesFile !== '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $manualDate) || !is_file($standaloneFile) || trim($standaloneTitle) === '') {
        fwrite(STDERR, "Usage: php archive-protocol-import.php --date=YYYY-MM-DD --standalone-file=/path/to/vote.txt --title=... --source-url=https://drive.google.com/file/d/.../view [--apply]\n");
        exit(2);
    }
    $raw = file_get_contents($standaloneFile);
    if (!is_string($raw) || strlen($raw) > 250000) throw new RuntimeException('Файл голосования не прочитан или слишком велик.');
    $vote = archive_protocol_sections($raw)['votes'];
    echo "$manualDate: " . count($vote) . " отдельное голосование.\n";
    if (!$apply) { echo "Пробный запуск: изменений нет.\n"; exit; }
    $content = cms_content();
    if (!archive_add_standalone_vote($content, $manualDate, $standaloneTitle, $raw, $sourceUrl)) {
        throw new RuntimeException('Дата, источник или итог не прошли проверку; данные не менялись.');
    }
    $content['archive']['drive_url'] = $sourceUrl;
    cms_write('content', $content);
    echo "Голосование опубликовано отдельной карточкой. CMS сделала резервную копию прежних данных.\n";
    exit;
}
if ($votesFile !== '') {
    if ($manualFile !== '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $manualDate) || !is_file($votesFile)) {
        fwrite(STDERR, "Usage: php archive-protocol-import.php --date=YYYY-MM-DD --votes-file=/path/to/votes.txt --source-url=https://drive.google.com/file/d/.../view [--apply]\n");
        exit(2);
    }
    $raw = file_get_contents($votesFile);
    if (!is_string($raw) || strlen($raw) > 250000) throw new RuntimeException('Файл голосований не прочитан или слишком велик.');
    $votes = archive_protocol_sections($raw)['votes'];
    echo "$manualDate: " . count($votes) . " результатов голосований.\n";
    if (!$apply) { echo "Пробный запуск: изменений нет.\n"; exit; }
    $content = cms_content();
    $count = archive_append_verified_votes($content, $manualDate, $raw, $sourceUrl);
    if (!$count) throw new RuntimeException('Проверка даты, источника или результатов не прошла; данные не менялись.');
    cms_write('content', $content);
    echo "Добавлено $count результатов. CMS сделала резервную копию прежних данных.\n";
    exit;
}
if ($manualDate !== '' || $manualFile !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $manualDate) || !is_file($manualFile)) {
        fwrite(STDERR, "Usage: php archive-protocol-import.php --date=YYYY-MM-DD --file=/path/to/protocol.txt [--apply]\n");
        exit(2);
    }
    $raw = file_get_contents($manualFile);
    if (!is_string($raw) || strlen($raw) > 250000) throw new RuntimeException('Файл протокола не прочитан или слишком велик.');
    $text = archive_plain_text($raw);
    if (archive_protocol_date($text) !== $manualDate) throw new RuntimeException('Дата внутри протокола не совпадает с карточкой архива.');
    $parts = archive_protocol_sections($text);
    if (!$parts['items']) throw new RuntimeException('В протоколе не найдено ни одного пункта.');
    echo "$manualDate: " . count($parts['items']) . " пунктов, " . count($parts['votes']) . " отдельных голосований, " . strlen($text) . " байт.\n";
    if (!$apply) { echo "Пробный запуск: изменений нет.\n"; exit; }
    $content = cms_content();
    if (!archive_apply_manual_protocol($content, $manualDate, $text)) {
        throw new RuntimeException('Протокол уже существует или карточка архива не найдена; данные не менялись.');
    }
    cms_write('content', $content);
    echo "Протокол сохранён. CMS сделала резервную копию прежнего содержимого.\n";
    exit;
}
$content = cms_content();
$updates = [];
$seen = [];
foreach (($content['archive']['items'] ?? []) as $item) {
    $date = (string)($item['event_date'] ?? '');
    $itemId = (string)($item['id'] ?? '');
    $docId = archive_google_doc_id($item);
    if ($docId === '') {
        echo "$date: нет ссылки на протокол; пропущено\n";
        continue;
    }
    if (!empty($item['protocol_text'])) {
        echo "$date: протокол уже на сайте; пропущено\n";
        continue;
    }
    $url = 'https://docs.google.com/document/d/' . rawurlencode($docId) . '/export?format=txt';
    $curl = curl_init($url);
    if ($curl === false) throw new RuntimeException('Не удалось открыть соединение с Google Docs.');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 18,
        CURLOPT_USERAGENT => 'PochtinormalnyeArchiveImport/1.0',
    ]);
    $raw = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $type = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    curl_close($curl);
    if (!is_string($raw) || $status !== 200 || !str_starts_with($type, 'text/plain') || strlen($raw) > 250000) {
        echo "$date: документ недоступен или не похож на текстовый протокол; пропущено\n";
        continue;
    }
    $text = archive_plain_text($raw);
    $sourceDate = archive_protocol_date($text);
    if ($sourceDate !== $date) {
        echo "$date: дата в документе " . ($sourceDate ?: 'не определена') . "; пропущено\n";
        continue;
    }
    $hash = hash('sha256', $text);
    if (isset($seen[$hash])) {
        echo "$date: тот же текст, что и у " . $seen[$hash] . "; пропущено\n";
        continue;
    }
    $seen[$hash] = $date;
    $updates[$itemId] = ['date' => $date, 'doc' => $docId, 'text' => $text];
    echo "$date: проверено " . mb_strlen($text) . " символов, " . count(archive_sections($text)['items']) . " пунктов" . ($apply ? "; к переносу\n" : "; пробный запуск\n");
}

if (!$apply) {
    echo "Пробный запуск: изменений нет. Для переноса используйте --apply.\n";
    exit;
}
if (!$updates) {
    echo "Подходящих новых протоколов нет; данные не менялись.\n";
    exit;
}

// Re-read after network requests so recent edits in the admin are not replaced.
$content = cms_content();
$updated = archive_apply_protocol_updates($content, $updates);
if ($updated) cms_write('content', $content);
echo "Перенесено протоколов: $updated. CMS сохранила резервную копию прежних данных.\n";
