<?php
declare(strict_types=1);
require __DIR__ . '/cms.php';

$paths = [
    '',
    'newcomers.html',
    'schedule.html',
    'library.html',
    'announcements.html',
    'speakers.html',
    'service.html',
    'tradition.html',
    'archive.html',
];

foreach (cms_content()['newcomers']['pages'] ?? [] as $page) {
    if (($page['slug'] ?? '') === 'aa-test' && !empty($page['published'])) {
        $paths[] = 'p/test-na-alkogolizm';
        break;
    }
}

foreach (cms_content()['pages']['custom'] ?? [] as $page) {
    $slug = (string) ($page['slug'] ?? '');
    if (!empty($page['published']) && preg_match('/^[a-z0-9-]+$/', $slug)) {
        $paths[] = 'p/' . $slug;
    }
}

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
foreach (array_unique($paths) as $path) {
    $url = 'https://pochtinormalnye.ru/' . $path;
    echo '  <url><loc>', htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8'), '</loc></url>', "\n";
}
echo '</urlset>', "\n";
