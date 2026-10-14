<?php
declare(strict_types=1);
require __DIR__ . '/../cms.php';

$cases = [
    ["Онлайн группа\nАнонимных алкоголиков\n«Почти нормальные»", 'Онлайн группа<br>Анонимных алкоголиков<br>«Почти нормальные»'],
    ['Онлайн группа\\nАнонимных алкоголиков\\n«Почти нормальные»', 'Онлайн группа<br>Анонимных алкоголиков<br>«Почти нормальные»'],
    ["Онлайн группаАнонимных\nАлкоголиков«Почти нормальные»", 'Онлайн группа Анонимных<br>Алкоголиков «Почти нормальные»'],
    ["<script>alert(1)</script>\nАнонимных алкоголиков", '&lt;script&gt;alert(1)&lt;/script&gt;<br>Анонимных алкоголиков'],
];
foreach ($cases as [$input, $expected]) {
    if (cms_home_heading_html($input) !== $expected) {
        fwrite(STDERR, "Homepage heading normalization failed for: {$input}\n");
        exit(1);
    }
}
echo "Homepage heading line-break, glued-word and escaping checks passed.\n";
