<?php
declare(strict_types=1);

require dirname(__DIR__) . '/cms.php';

function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}

$content = cms_default_content();
$content['newcomers']['pages'][] = ['slug'=>'aa-test','title'=>'Тест','published'=>true];
$content['newcomers']['pages'][] = ['slug'=>'extra','title'=>'Дополнительная страница','published'=>true,'actions'=>[['label'=>'Найти группу в России','url'=>'https://example.org/groups']]];
$content['navigation']['items'][] = ['id'=>'old-link','title'=>'Старая страница','url'=>'newcomers.html#deleted','parent'=>'Новичкам'];
$payload = cms_navigation_payload($content);
$items = $payload['items'];
$root = array_search('newcomers', array_column($items, 'id'), true);
check($root !== false, 'Нет раздела «Новичкам».');
check(($items[$root + 1]['url'] ?? '') === '/p/test-na-alkogolizm', 'Тест должен идти сразу после раздела «Новичкам».');
check(!in_array('newcomers.html#deleted', array_column($items, 'url'), true), 'Старая ссылка не удалена из меню.');
check(in_array(cms_newcomer_path('aa'), array_column($items, 'url'), true), 'У статьи новичков должен быть собственный адрес.');
check(cms_newcomer_path('menu') === '/newcomers.html', 'Главный раздел должен сохранить адрес.');
check(cms_newcomer_path('aa-test') === '/p/test-na-alkogolizm', 'Тест должен сохранить адрес.');
check(cms_newcomer_actions([['url'=>'#aa','label'=>'АА']], ['aa'=>true])[0]['url'] === '/newcomers/aa/', 'Кнопка должна вести на отдельную статью.');
check(cms_newcomer_actions([['url'=>'newcomers.html#aa','label'=>'АА']], ['aa'=>true])[0]['url'] === '/newcomers/aa/', 'Старая кнопка с хешем должна вести на отдельную статью.');
check(in_array('https://example.org/groups', array_column($items, 'url'), true), 'Кнопки страниц для новичков должны попадать в меню.');

$preview = cms_drive_audio_preview_url('https://drive.google.com/file/d/ABC_123/view?resourcekey=0-KEY');
check($preview === 'https://drive.google.com/file/d/ABC_123/preview?resourcekey=0-KEY', 'Ссылка на плеер Google Диска составлена неверно.');
check(cms_drive_audio_preview_url('https://example.com/file/d/ABC_123/view') === '', 'Посторонний сайт не должен попадать в плеер.');
check(cms_drive_audio_preview_url('https://drive.google.com/drive/folders/ABC_123') === '', 'Нужна ссылка на файл, а не на папку.');
check(cms_speaker_preview_url(['audio_url'=>'https://drive.google.com/file/d/ORIGINAL/view','audio_stream_url'=>'https://drive.google.com/file/d/OPTIMIZED/view']) === 'https://drive.google.com/file/d/OPTIMIZED/preview', 'Плеер должен использовать облегчённую запись.');
check(cms_speaker_preview_url(['audio_url'=>'https://drive.google.com/file/d/ORIGINAL/view','audio_stream_url'=>'https://example.org/invalid']) === 'https://drive.google.com/file/d/ORIGINAL/preview', 'При недоступной ссылке на копию плеер должен использовать оригинал.');

$speakers = cms_speakers_newest_first([
    ['id'=>'older','event_date'=>'2025-01-01','order'=>0],
    ['id'=>'newer-second','event_date'=>'2026-10-03','order'=>2],
    ['id'=>'newer-first','event_date'=>'2026-10-03','order'=>1],
]);
check(array_column($speakers, 'id') === ['newer-first','newer-second','older'], 'Спикерские должны идти от новых к старым.');

echo "CMS navigation and Drive audio checks passed.\n";
