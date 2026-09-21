<?php
declare(strict_types=1);
require __DIR__ . '/cms.php';
cms_start_session();

$config = cms_config();
$users = cms_users();
$error = '';
if ($users) { http_response_code(404); exit('Настройка уже завершена.'); }
if (!is_file(__DIR__ . '/cms-config.php') || strlen((string)$config['setup_token']) < 32 || str_contains((string)$config['setup_token'], 'PUT-A-LONG')) {
    http_response_code(503);
    exit('Сначала создайте cms-config.php по образцу cms-config.example.php и задайте секрет установки.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        cms_verify_csrf();
        if (!hash_equals((string)$config['setup_token'], (string)($_POST['setup_token'] ?? ''))) throw new RuntimeException('Неверный секрет установки.');
        $username = cms_text($_POST['username'] ?? '', 80); $password = (string)($_POST['password'] ?? '');
        if (!preg_match('/^[\p{L}\p{N}._-]{3,80}$/u', $username)) throw new RuntimeException('Логин: от 3 символов, буквы, цифры, точка, дефис или подчёркивание.');
        if (cms_length($password) < 12) throw new RuntimeException('Пароль должен быть не короче 12 символов.');
        cms_write('users', [['id'=>cms_id(),'username'=>$username,'password_hash'=>password_hash($password, PASSWORD_DEFAULT),'active'=>true,'admin'=>true,'permissions'=>CMS_SECTIONS]]);
        cms_log('Первичная настройка');
        header('Location: /admin/'); exit;
    } catch (Throwable $exception) { $error = $exception->getMessage(); }
}
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Первичная настройка CMS</title><link rel="stylesheet" href="admin/admin.css?v=4"></head><body class="admin-login"><main class="login-card"><h1>Первичная настройка</h1><p>Создайте первый аккаунт администратора. После этого эта страница отключится сама.</p><?php if($error):?><p class="notice notice--error"><?=h($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=h(cms_csrf())?>"><label class="field"><span>Секрет установки из cms-config.php</span><input type="password" name="setup_token" required></label><label class="field"><span>Логин администратора</span><input name="username" autocomplete="username" required></label><label class="field"><span>Пароль (не короче 12 символов)</span><input type="password" name="password" autocomplete="new-password" required></label><button class="button">Создать администратора</button></form></main></body></html>
