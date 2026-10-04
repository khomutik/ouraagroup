<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/archive-lib.php';

$apply = in_array('--apply', $argv, true);
$fixture = __DIR__ . '/imports/treasurer-reports-2026.json';
$source = json_decode((string)file_get_contents($fixture), true, 512, JSON_THROW_ON_ERROR);
$reports = $source['reports'] ?? [];
if (!is_array($reports) || count($reports) !== 4) throw new RuntimeException('Ожидалось ровно четыре отчёта казначея.');
$sourceUrl = cms_safe_url($source['source'] ?? '');
if (!str_starts_with($sourceUrl, 'https://docs.google.com/spreadsheets/')) throw new RuntimeException('Некорректная ссылка на исходную таблицу.');

function treasurer_table(string $heading, string $unit, array $account): string {
    $labels = ['opening'=>'Остаток на начало', 'income'=>'Поступления', 'expense'=>'Расходы', 'closing'=>'Остаток на конец'];
    $html = '<p><strong>' . h($heading) . '</strong></p><table><tbody>';
    foreach ($labels as $key => $label) {
        $value = (string)($account[$key] ?? '');
        if (!preg_match('/^\d[\d ]*,\d{2}$/u', $value)) throw new RuntimeException('Некорректная сумма: ' . $heading . ' / ' . $label);
        $html .= '<tr><th>' . h($label) . '</th><td>' . h($value) . ' ' . h($unit) . '</td></tr>';
    }
    return $html . '</tbody></table>';
}

function treasurer_html(array $report, string $sourceUrl): string {
    $html = '<p><strong>За ' . h((string)$report['month']) . '</strong>. Казначей: ' . h(rtrim((string)$report['treasurer'], '.')) . '.</p>';
    $html .= treasurer_table('Сбер', '₽', $report['sber']);
    $html .= treasurer_table('PayPal', '€', $report['paypal']);
    foreach (['reserve_rub'=>'₽', 'reserve_eur'=>'€'] as $key => $unit) {
        $value = (string)($report[$key] ?? '');
        if (!preg_match('/^\d[\d ]*,\d{2}$/u', $value)) throw new RuntimeException('Некорректная сумма резерва.');
        $html .= '<p>Резерв группы: ' . h($value) . ' ' . h($unit) . '</p>';
    }
    if (!empty($report['note'])) $html .= '<p>' . h((string)$report['note']) . '</p>';
    $html .= '<p><a href="' . h($sourceUrl) . '">Исходная таблица казначея</a></p>';
    return cms_sanitize_html($html);
}

$content = cms_content();
$found = [];
if (!isset($content['archive']['items']) || !is_array($content['archive']['items'])) throw new RuntimeException('В CMS нет архива.');
foreach ($content['archive']['items'] as &$item) {
    $date = (string)($item['event_date'] ?? '');
    if (!isset($reports[$date])) continue;
    if (isset($found[$date])) throw new RuntimeException('Несколько карточек архива с датой ' . $date);
    $found[$date] = true;
    $html = treasurer_html($reports[$date], $sourceUrl);
    $previousGenerated = str_replace('Владимир Э.</p>', 'Владимир Э..</p>', $html);
    if (!empty($item['treasurer_report']) && !in_array((string)$item['treasurer_report'], [$html, $previousGenerated], true)) {
        throw new RuntimeException('Отчёт ' . $date . ' уже изменён в админке; ручные правки не перезаписывались.');
    }
    $item['treasurer_report'] = $html;
    echo $date . ': отчёт за ' . $reports[$date]['month'] . " проверен\n";
}
unset($item);
if (count($found) !== count($reports)) throw new RuntimeException('Не найдены все четыре карточки архива; изменений нет.');
$prepared = 0;
foreach ($content['archive']['items'] as $item) {
    if (isset($reports[(string)($item['event_date'] ?? '')]) && !empty($item['treasurer_report'])) $prepared++;
}
if ($prepared !== 4) throw new RuntimeException('Не все отчёты подготовлены к записи; изменений нет.');
if (!$apply) { echo "Пробный запуск: изменений нет.\n"; exit; }
cms_write('content', $content);
$saved = cms_read('content', []);
$verified = 0;
foreach (($saved['archive']['items'] ?? []) as $item) {
    if (isset($reports[(string)($item['event_date'] ?? '')]) && !empty($item['treasurer_report'])) $verified++;
}
if ($verified !== 4) throw new RuntimeException('Отчёты не прошли проверку после записи.');
echo "Отчёты сохранены. CMS сделала резервную копию прежних данных.\n";
