<?php
declare(strict_types=1);

require dirname(__DIR__) . '/site-search-lib.php';

function expect_search(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$content = cms_default_content();
$content['newcomers']['pages'][] = ['slug'=>'secret-draft','title'=>'СЕКРЕТ_НОВИЧКИ','body'=>'Не публиковать','published'=>false];
$content['pages']['custom'][] = ['slug'=>'private','title'=>'СЕКРЕТ_СТРАНИЦА','body'=>'Не публиковать','published'=>false];
$content['pages']['custom'][] = ['slug'=>'public-page','title'=>'Полезная страница','body'=>'Открытый текст','published'=>true];
$content['pages']['custom'][array_key_last($content['pages']['custom'])]['description'] = 'СЕКРЕТ_SEO_СТРАНИЦА';
$content['site_texts']['schedule_description'] = 'СЕКРЕТ_SEO_РАСПИСАНИЕ';
$content['site_texts']['announcements_description'] = 'СЕКРЕТ_SEO_ОБЪЯВЛЕНИЯ';
$content['site_texts']['archive_description'] = 'СЕКРЕТ_SEO_АРХИВ';
$content['announcements'][] = ['id'=>'expired','title'=>'СЕКРЕТ_УСТАРЕЛО','body'=>'Не показывать','hide_after'=>'2020-01-01'];
$content['speakers']['items'][] = ['id'=>'record-1','title'=>'Первый шаг','speaker'=>'Мария','event_date'=>'2026-10-03'];
$documents = site_search_documents($content, '2026-10-04');
$urls = array_column($documents, 'url');
$dump = json_encode($documents, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

expect_search(in_array('/newcomers.html', $urls, true), 'Главная страница новичков пропала из поиска.');
expect_search(in_array('/p/public-page', $urls, true), 'Опубликованная страница пропала из поиска.');
expect_search(in_array('/speakers.html#speaker-record-1', $urls, true), 'Поиск должен вести к конкретной записи.');
expect_search(in_array('/p/test-na-alkogolizm', $urls, true) === in_array('aa-test', array_column($content['newcomers']['pages'], 'slug'), true), 'Тест включается только при публикации.');
expect_search(!str_contains($dump, 'СЕКРЕТ_'), 'Черновики и устаревшие объявления не должны попасть в поиск.');
expect_search(!str_contains($dump, '+7 981'), 'Платёжные реквизиты не должны попадать в поиск.');
expect_search(site_search_text('<p>Текст<br><strong>после</strong> &amp; ещё</p>') === 'Текст после & ещё', 'Поисковый текст должен быть чистым и читаемым.');

echo "Site search document checks passed.\n";
