<?php
declare(strict_types=1);
require __DIR__ . '/cms.php';
cms_start_session();

$target = '/nafanya-zoom-control/app';
$error = '';
$locked = false;

if (cms_nafanya_session_user()) {
    header('Location: ' . $target, true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ($locked) {
        $error = 'Слишком много попыток. Подождите 10 минут и попробуйте снова.';
    } elseif (!hash_equals(cms_csrf(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        $error = 'Форма устарела. Обновите страницу и попробуйте ещё раз.';
    } else {
        $username = cms_lower(cms_text($_POST['username'] ?? '', 80));
        $password = (string)($_POST['password'] ?? '');
        $clientIp = cms_nafanya_login_client_ip();
        try {
            if (!cms_nafanya_login_rate_limit($username, $clientIp, true)) {
                $locked = true;
                $error = 'Слишком много попыток. Подождите 10 минут и попробуйте снова.';
            } else {
                $matched = null;
                foreach (cms_users() as $candidate) {
                    if (!empty($candidate['active']) && hash_equals(cms_lower((string)($candidate['username'] ?? '')), $username) && password_verify($password, (string)($candidate['password_hash'] ?? ''))) {
                        $matched = $candidate;
                        break;
                    }
                }
                if ($matched && !empty($matched['nafanya_access'])) {
                    cms_nafanya_login_rate_limit_success($username, $clientIp);
                    $token = cms_nafanya_create_session((string)$matched['id']);
                    setcookie('pn_nafanya_staff', $token, [
                        'expires'=>time() + 315360000,
                        'path'=>'/nafanya-zoom-control',
                        'secure'=>true,
                        'httponly'=>true,
                        'samesite'=>'Strict',
                    ]);
                    session_regenerate_id(true);
                    header('Location: ' . $target, true, 303);
                    exit;
                }
                $error = 'Не удалось войти. Проверьте логин и пароль; доступ к пульту должен быть включён администратором.';
            }
        } catch (Throwable $exception) {
            http_response_code(503);
            $locked = true;
            $error = 'Вход временно недоступен. Попробуйте позже.';
        }
    }
}

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Вход в пульт Нафани</title>
  <link rel="stylesheet" href="/admin/admin.css?v=28">
</head>
<body class="admin-login">
  <main class="login-card">
    <h1>Пульт Нафани</h1>
    <p>Войдите под своей учётной записью админки сайта. Доступ к пульту должен быть разрешён администратором.</p>
    <?php if ($error !== ''): ?><p class="notice notice--error"><?=h($error)?></p><?php endif; ?>
    <?php if ($locked): ?><p class="notice notice--error">Слишком много попыток. Подождите 10 минут и попробуйте снова.</p><?php else: ?>
      <form method="post" action="/nafanya-login" autocomplete="on">
        <input type="hidden" name="csrf" value="<?=h(cms_csrf())?>">
        <label class="field"><span>Логин</span><input name="username" autocomplete="username" required></label>
        <label class="field"><span>Пароль</span><input type="password" name="password" autocomplete="current-password" required></label>
        <button class="button" type="submit">Войти в пульт</button>
      </form>
    <?php endif; ?>
    <p><a href="/admin/">Открыть админку</a></p>
  </main>
</body>
</html>
