<?php
declare(strict_types=1);

require dirname(__DIR__) . '/site-search-lib.php';

function archive_expect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$agendaHtml = '<details><summary>Повестка РС 29.08.2026</summary><summary><br>0. Утвердить повестку.<br><br>1. Оплатить хостинг сайта.<br>Обсудить и проголосовать.<br><br>2. Служения группы.</summary></details>';
$agenda = archive_sections(archive_plain_text($agendaHtml, true));
archive_expect(count($agenda['items']) === 3, 'Все пункты повестки должны быть отдельными.');
archive_expect($agenda['items'][1]['heading'] === 'Оплатить хостинг сайта.', 'Тема повестки потеряна.');
archive_expect(archive_protocol_date("Протокол РС 05.04.2026\n1. Решение принято.") === '2026-04-05', 'Дата протокола определена неверно.');
archive_expect(archive_protocol_date("Протокол РС 05.04.2026\n1. Решение принято.") !== '2026-04-10', 'Чужой протокол нельзя привязать к другой дате.');
archive_expect(str_contains(archive_document_html("Протокол РС 29.08.2026\n\n1. Оплатить хостинг.\nПринято единогласно.", 'protocol', 'record-1'), 'protocol-record-1-point-0'), 'Глубокая ссылка на решение отсутствует.');
archive_expect(!str_contains(archive_document_html("1. <script>alert(1)</script>", 'agenda', 'record-1'), '<script>'), 'Текст документа нужно экранировать.');
$protocolWithVotes = "Протокол РС 29.08.2026\n\n1. Оплатить хостинг.\nОбсудили.\n\n29.08.2026\nИсточник: Telegram\nВопрос: Оплатить хостинг на год\nВарианты:\n— За — 10 голосов\n— Против — 0 голосов\nИтог: решение принято.\n\n29.08.2026\nИсточник: Telegram\nВопрос: Открыть чат\nВарианты:\n— За — 8 голосов\n— Против — 2 голоса\nИтог: решение принято.";
$split = archive_protocol_sections($protocolWithVotes);
archive_expect(count($split['items']) === 1 && count($split['votes']) === 2, 'Итоги голосований не должны прилипать к последнему пункту протокола.');
archive_expect(!str_contains($split['items'][0]['text'], 'Открыть чат'), 'Текст голосования попал в пункт протокола.');
archive_expect($split['votes'][0]['heading'] === 'Оплатить хостинг на год', 'Тема голосования потеряна.');
archive_expect(str_contains(archive_document_html($protocolWithVotes, 'protocol', 'record-1'), 'vote-record-1-point-0'), 'Глубокая ссылка на голосование отсутствует.');
$manualText = (string)file_get_contents(dirname(__DIR__) . '/imports/protocol-2026-04-10.txt');
archive_expect(archive_protocol_date($manualText) === '2026-04-10', 'Дата присланного протокола не распознана.');
archive_expect(count(archive_protocol_sections($manualText)['items']) === 6, 'Пункты присланного протокола должны отображаться отдельно.');
archive_expect(str_contains($manualText, 'Итог: решение не принято'), 'Указанный итог голосования о молитве потерян.');

$content = cms_default_content();
$content['archive']['items'] = [[
    'id' => 'record-1',
    'title' => 'Рабочее собрание',
    'event_date' => '2026-08-29',
    'body' => $agendaHtml,
    'protocol_text' => "Протокол РС 29.08.2026\n\n1. Оплатить хостинг сайта.\nГолосование: за 7, против 0. Решение принято.",
    'links' => [['label' => 'Оригинал', 'url' => 'https://docs.google.com/document/d/ValidDoc123/edit']],
]];
$docs = site_search_documents($content, '2026-10-04');
$point = array_values(array_filter($docs, static fn($doc): bool => $doc['url'] === '/archive.html#agenda-record-1-point-1'));
$decision = array_values(array_filter($docs, static fn($doc): bool => $doc['url'] === '/archive.html#protocol-record-1-point-0'));
archive_expect(count($point) === 1 && $point[0]['category'] === 'Повестка РС', 'Пункт повестки должен искаться отдельно.');
archive_expect(count($decision) === 1 && $decision[0]['category'] === 'Протокол РС', 'Пункт протокола должен искаться отдельно.');
archive_expect(str_contains($decision[0]['text'], 'за 7, против 0'), 'Итог голосования потерян при индексации.');
$content['archive']['items'][0]['protocol_text'] = $protocolWithVotes;
$voteDocs = site_search_documents($content, '2026-10-04');
$vote = array_values(array_filter($voteDocs, static fn($doc): bool => $doc['url'] === '/archive.html#vote-record-1-point-0'));
archive_expect(count($vote) === 1 && str_contains($vote[0]['text'], '10 голосов'), 'Результат отдельного голосования не ищется.');
archive_expect(archive_google_doc_id($content['archive']['items'][0]) === 'ValidDoc123', 'Ссылка на оригинал не распознана.');
$fresh = ['archive' => ['items' => [['id'=>'record-1','event_date'=>'2026-08-29','links'=>$content['archive']['items'][0]['links']]]]];
$updated = archive_apply_protocol_updates($fresh, ['record-1'=>['date'=>'2026-08-29','doc'=>'ValidDoc123','text'=>'Результат голосования: принято.']]);
archive_expect($updated === 1 && $fresh['archive']['items'][0]['protocol_text'] === 'Результат голосования: принято.', 'Протокол не записан в настоящий массив CMS.');
$manual = ['archive'=>['items'=>[['id'=>'april','event_date'=>'2026-04-10','links'=>[['url'=>'https://docs.google.com/document/d/12EgF3ar4Cw4bOykH3u2DZXJaiiB3ommgMjTIHM100l4/edit']]]]]];
archive_expect(archive_apply_manual_protocol($manual, '2026-04-10', $manualText), 'Присланный протокол не записан в CMS.');
archive_expect(!archive_apply_manual_protocol($manual, '2026-04-10', $manualText), 'Нельзя незаметно перезаписывать уже опубликованный протокол.');
archive_expect(!archive_public_links($manual['archive']['items'][0]), 'Неверная ссылка на документ за 5 апреля не должна отображаться под 10 апреля.');
$verifiedVotes = (string)file_get_contents(dirname(__DIR__) . '/imports/votes-2026-04-10.txt');
archive_expect(count(archive_protocol_sections($verifiedVotes)['votes']) === 12, 'Из первоисточника должны импортироваться все 12 результатов 10–11 апреля.');
$pdfSource = 'https://drive.google.com/file/d/1_RfSvqhbNPAGMUT7rczYha2povbuJ2zz/view?usp=drive_link';
archive_expect(archive_append_verified_votes($manual, '2026-04-10', $verifiedVotes, $pdfSource) === 12, 'Результаты голосований не добавлены к протоколу.');
archive_expect(archive_append_verified_votes($manual, '2026-04-10', $verifiedVotes, $pdfSource) === 0, 'Повторный импорт не должен дублировать голосования.');
archive_expect(count(archive_protocol_sections($manual['archive']['items'][0]['protocol_text'])['votes']) === 12, 'Числа голосов или итоги потеряны при объединении протокола.');
archive_expect(count(archive_public_links($manual['archive']['items'][0])) === 1, 'Публичной должна остаться только проверенная ссылка на PDF.');
$standalone = ['archive'=>['items'=>[]]];
archive_expect(archive_add_standalone_vote($standalone, '2026-03-05', 'Голосование группы', (string)file_get_contents(dirname(__DIR__) . '/imports/vote-2026-03-05.txt'), $pdfSource), 'Первое голосование о названии потеряно.');
archive_expect(archive_add_standalone_vote($standalone, '2026-06-14', 'Внеочередное РС', (string)file_get_contents(dirname(__DIR__) . '/imports/vote-2026-06-14.txt'), $pdfSource), 'Внеочередное голосование о Zoom потеряно.');
archive_expect(!archive_add_standalone_vote($standalone, '2026-06-14', 'Дубликат', (string)file_get_contents(dirname(__DIR__) . '/imports/vote-2026-06-14.txt'), $pdfSource), 'Нельзя дублировать отдельное голосование.');
archive_expect(($standalone['archive']['items'][0]['event_date'] ?? '') === '2026-06-14', 'Карточки должны идти от новых к старым.');
archive_expect(str_contains(archive_document_html($standalone['archive']['items'][0]['protocol_text'], 'protocol', 'vote-test'), 'Итоги голосования'), 'Отдельное голосование нельзя называть протоколом РС.');

echo "Archive parsing, vote preservation and deep-link checks passed.\n";
