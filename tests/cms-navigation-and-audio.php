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
check(str_contains(cms_newcomer_seo_defaults('pn-meetings')['description'], 'Zoom'), 'Описание собраний группы должно объяснять способ входа.');
check(cms_newcomer_seo_defaults('unknown')['title'] === '', 'Неизвестным страницам нельзя подставлять чужой SEO-заголовок.');
check(cms_newcomer_actions([['url'=>'#aa','label'=>'АА']], ['aa'=>true])[0]['url'] === '/newcomers/aa/', 'Кнопка должна вести на отдельную статью.');
check(cms_newcomer_actions([['url'=>'newcomers.html#aa','label'=>'АА']], ['aa'=>true])[0]['url'] === '/newcomers/aa/', 'Старая кнопка с хешем должна вести на отдельную статью.');
check(in_array('https://example.org/groups', array_column($items, 'url'), true), 'Кнопки страниц для новичков должны попадать в меню.');
check(!in_array('nafanya-zoom-control', array_column($items, 'id'), true), 'Служебный пульт Нафани не должен отображаться в публичном меню сайта.');
$unsafeMenu = $content;
$unsafeMenu['navigation']['items'] = [
    ['id'=>'archive','title'=>'Архив решений','url'=>'archive.html','parent'=>''],
    ['id'=>'nafanya-zoom-control','title'=>'Старый пункт','url'=>'/nafanya-zoom-control/app?token=do-not-publish','parent'=>''],
];
$safeMenu = cms_navigation_payload($unsafeMenu)['items'];
check(!in_array('/nafanya-zoom-control/app', array_column($safeMenu, 'url'), true), 'Старую кнопку Нафани нужно скрыть и из сохранённого меню.');

$newcomerBody = cms_newcomer_body('<p>Read <a href="library.html">Library</a> <a href="schedule.html#top">Schedule</a> <a href="#aa">AA</a> <a href="#local">Local</a> <a href="/archive.html">Archive</a> <a href="https://example.org/library.html">External</a> <a href="javascript:alert(1)">Unsafe</a></p>', ['aa'=>true]);
check(str_contains($newcomerBody, 'href="/library.html"'), 'Legacy library links must resolve from the site root on nested newcomer pages.');
check(str_contains($newcomerBody, 'href="/schedule.html#top"'), 'Root HTML links must preserve fragments.');
check(str_contains($newcomerBody, 'href="/newcomers/aa/"'), 'Published newcomer sections must retain their canonical links.');
check(str_contains($newcomerBody, 'href="#local"'), 'Unrelated same-page fragments must remain unchanged.');
check(str_contains($newcomerBody, 'href="/archive.html"'), 'Already rooted paths must not gain a second slash.');
check(str_contains($newcomerBody, 'href="https://example.org/library.html"'), 'External links must not be rewritten.');
check(!str_contains($newcomerBody, 'javascript:'), 'Link normalization must retain HTML sanitization.');
check(str_contains(cms_newcomer_body("<a href='newcomers.html#aa'>AA</a>", ['aa'=>true]), 'href="/newcomers/aa/"'), 'Single-quoted legacy links must be normalized after sanitization.');

$preview = cms_drive_audio_preview_url('https://drive.google.com/file/d/ABC_123/view?resourcekey=0-KEY');
check($preview === 'https://drive.google.com/file/d/ABC_123/preview?resourcekey=0-KEY', 'Ссылка на плеер Google Диска составлена неверно.');
check(cms_drive_audio_preview_url('https://example.com/file/d/ABC_123/view') === '', 'Посторонний сайт не должен попадать в плеер.');
check(cms_drive_audio_preview_url('https://drive.google.com/drive/folders/ABC_123') === '', 'Нужна ссылка на файл, а не на папку.');
check(cms_speaker_preview_url(['audio_url'=>'https://drive.google.com/file/d/ORIGINAL/view','audio_stream_url'=>'https://drive.google.com/file/d/OPTIMIZED/view']) === 'https://drive.google.com/file/d/OPTIMIZED/preview', 'Плеер должен использовать облегчённую запись.');
check(cms_speaker_preview_url(['audio_url'=>'https://drive.google.com/file/d/ORIGINAL/view','audio_stream_url'=>'https://example.org/invalid']) === 'https://drive.google.com/file/d/ORIGINAL/preview', 'При недоступной ссылке на копию плеер должен использовать оригинал.');
check(cms_speaker_download_url(['audio_url'=>'https://drive.google.com/file/d/ORIGINAL/view?resourcekey=0-KEY']) === 'https://drive.google.com/uc?export=download&id=ORIGINAL&resourcekey=0-KEY', 'Кнопка скачивания должна вести к оригиналу.');
check(cms_speaker_download_url(['audio_url'=>'https://example.org/file']) === '', 'Скачивание не должно принимать посторонний адрес.');
check(cms_speaker_button([], 'player')['color'] === 'blue', 'Кнопка плеера должна быть синей.');
check(cms_speaker_button([], 'download')['type'] === 'outline', 'Кнопка скачивания должна быть прозрачной.');
check(in_array('rs_audio', CMS_SECTIONS, true), 'Аудиозаписи РС должны быть отдельным разделом CMS.');
check(in_array('scenarios', CMS_SECTIONS, true), 'Сценарии собраний должны быть отдельным разделом CMS.');
check(count(cms_default_content()['scenarios']['items']) === 5, 'В отдельном разделе должны быть ровно пять исходных сценариев.');
check(array_column(cms_default_content()['scenarios']['items'], 'label') === ['Сценарий (чтение книги)','Сценарий (обычное собрание)','Сценарий (для новичков)','Сценарий (спикерская)','Сценарий (свободные темы)'], 'Подписи пяти сценариев должны соответствовать расписанию.');
check(cms_default_content()['admin_portal']['order'] === ['services','scenarios','nafanya','edit_site','edit_admin','rs_audio'], 'Порядок кабинета должен ставить редактирование админки сразу под редактированием сайта.');
check(array_column(cms_rs_audio_newest_first([
    ['id'=>'old','event_date'=>'2026-06-27'],
    ['id'=>'new','event_date'=>'2026-10-03'],
    ['id'=>'middle','event_date'=>'2026-08-29'],
]), 'event_date') === ['2026-10-03','2026-08-29','2026-06-27'], 'Аудиозаписи РС должны сортироваться от новых к старым.');
check(cms_drive_audio_preview_url('https://drive.google.com/file/d/RS_AUDIO_123/view?usp=sharing') === 'https://drive.google.com/file/d/RS_AUDIO_123/preview', 'Для архивной записи нужен встроенный предпросмотр Google Диска.');

$speakers = cms_speakers_newest_first([
    ['id'=>'older','event_date'=>'2025-01-01','order'=>0],
    ['id'=>'newer-second','event_date'=>'2026-10-03','order'=>2],
    ['id'=>'newer-first','event_date'=>'2026-10-03','order'=>1],
]);
check(array_column($speakers, 'id') === ['newer-first','newer-second','older'], 'Спикерские должны идти от новых к старым.');

echo "CMS navigation and Drive audio checks passed.\n";
