<?php
declare(strict_types=1);
require __DIR__ . '/cms.php';
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

$user = cms_nafanya_session_user();
if (!$user) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":false,"error":"unauthorized"}';
    exit;
}

header('X-PN-Nafanya-Role: ' . (!empty($user['admin']) ? 'admin' : 'staff'));
http_response_code(204);
