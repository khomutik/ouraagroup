<?php
declare(strict_types=1);

define('PN_ADMIN_SCENARIOS', true);
$texts = require dirname(__DIR__) . '/admin/scenario-texts.php';
$expected = ['big-book','regular','newcomers','speaker','free-topics'];
if (array_keys($texts) !== $expected) throw new RuntimeException('Должны быть доступны тексты всех пяти сценариев.');
$pageCount = 0;
foreach ($texts as $slug=>$html) {
    if (!str_contains($html, 'scenario-reader-page')) throw new RuntimeException("В сценарии {$slug} отсутствуют страницы.");
    $pageCount += substr_count($html, 'scenario-reader-page');
}
if ($pageCount !== 33) throw new RuntimeException('Число страниц сценариев должно совпадать с исходными PDF.');
foreach (['host','actions','newcomers'] as $kind) {
    $hasKind = false;
    foreach ($texts as $html) if (str_contains($html, 'scenario-text-block--' . $kind)) $hasKind = true;
    if (!$hasKind) throw new RuntimeException("В цветовой разметке отсутствует тип блока {$kind}.");
}
echo "Five scenario readers and 33 pages verified.\n";
