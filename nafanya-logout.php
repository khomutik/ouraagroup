<?php
declare(strict_types=1);
require __DIR__ . '/cms.php';
$token = (string)($_COOKIE['pn_nafanya_staff'] ?? '');
if ($token !== '') cms_nafanya_revoke_session($token);
setcookie('pn_nafanya_staff', '', [
    'expires'=>time() - 3600,
    'path'=>'/nafanya-zoom-control',
    'secure'=>true,
    'httponly'=>true,
    'samesite'=>'Strict',
]);
header('Cache-Control: no-store, private');
header('Location: /?nafanya_logged_out=1', true, 303);
