<?php
declare(strict_types=1);

require __DIR__ . '/site-search-lib.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=60');
header('X-Robots-Tag: noindex, nofollow');
echo json_encode(['documents' => site_search_documents(cms_content())], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
