<?php
declare(strict_types=1);
require dirname(__DIR__) . '/cms.php';

function expect(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$token = str_repeat('a', 64);
$users = [
    ['id'=>'volunteer-1','username'=>'volunteer','active'=>true,'nafanya_access'=>true,'admin'=>false],
    ['id'=>'disabled-1','username'=>'disabled','active'=>false,'nafanya_access'=>true],
    ['id'=>'no-pult-1','username'=>'no-pult','active'=>true,'nafanya_access'=>false],
];
$sessions = [cms_nafanya_token_hash($token)=>['user_id'=>'volunteer-1','created_at'=>time()]];
expect((cms_nafanya_session_user_from($users, $sessions, $token)['username'] ?? '') === 'volunteer', 'Активный пользователь с разрешённой галочкой должен проходить.');
expect(cms_nafanya_session_user_from($users, $sessions, str_repeat('b', 64)) === null, 'Неизвестная сессия должна быть закрыта.');
expect(cms_nafanya_session_user_from($users, $sessions, 'not-a-token') === null, 'Токен неверного формата должен быть закрыт.');
$disabledSessions = [cms_nafanya_token_hash($token)=>['user_id'=>'disabled-1','created_at'=>time()]];
expect(cms_nafanya_session_user_from($users, $disabledSessions, $token) === null, 'Неактивная учётная запись должна быть закрыта.');
$uncheckedSessions = [cms_nafanya_token_hash($token)=>['user_id'=>'no-pult-1','created_at'=>time()]];
expect(cms_nafanya_session_user_from($users, $uncheckedSessions, $token) === null, 'Снятая галочка доступа должна закрывать пульт.');

$limitDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pn-nafanya-limit-' . bin2hex(random_bytes(8));
$testIp = '203.0.113.17';
$baseTime = 1800000000;
for ($attempt = 0; $attempt < 5; $attempt++) {
    expect(cms_nafanya_login_rate_limit('volunteer', $testIp, true, $baseTime + $attempt, $limitDir), 'Каждая из первых пяти попыток должна учитываться, несмотря на новые сессии.');
}
expect(!cms_nafanya_login_rate_limit('volunteer', $testIp, true, $baseTime + 5, $limitDir), 'Шестая попытка для того же имени/IP должна блокироваться между сессиями.');
expect(cms_nafanya_login_rate_limit('another-user', $testIp, true, $baseTime + 6, $limitDir), 'Лимит одной пары не должен блокировать другую учётную запись.');
expect(cms_nafanya_login_rate_limit('volunteer', '203.0.113.18', true, $baseTime + 7, $limitDir), 'Лимит одной пары не должен блокировать тот же аккаунт с другого IP.');
cms_nafanya_login_rate_limit_success('volunteer', $testIp, $limitDir);
expect(cms_nafanya_login_rate_limit('volunteer', $testIp, true, $baseTime + 8, $limitDir), 'Успешный вход должен сбрасывать лимит этой пары.');
expect(cms_nafanya_login_rate_limit('volunteer', $testIp, true, $baseTime + 608, $limitDir), 'После десятиминутного окна попытки должны снова разрешаться.');
$ipLimit = '203.0.113.19';
for ($attempt = 0; $attempt < 30; $attempt++) {
    expect(cms_nafanya_login_rate_limit('user-' . $attempt, $ipLimit, true, $baseTime + $attempt, $limitDir), 'Первые 30 попыток с одного IP должны укладываться в общий IP-лимит.');
}
expect(!cms_nafanya_login_rate_limit('user-extra', $ipLimit, true, $baseTime + 31, $limitDir), '31-я попытка с одного IP должна блокироваться даже при смене имени.');
foreach (['nafanya-login-attempts.json','nafanya-login-attempts.lock'] as $file) @unlink($limitDir . DIRECTORY_SEPARATOR . $file);
@rmdir($limitDir);

$sessionDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pn-nafanya-sessions-' . bin2hex(random_bytes(8));
$persistentToken = cms_nafanya_create_session('volunteer-1', $sessionDir);
$sessionPath = $sessionDir . DIRECTORY_SEPARATOR . 'nafanya_sessions.json';
$persisted = json_decode((string)file_get_contents($sessionPath), true);
expect(is_array($persisted) && isset($persisted[cms_nafanya_token_hash($persistentToken)]), 'Созданная сессия должна сохраниться в постоянном хранилище.');
expect((cms_nafanya_session_user_from($users, $persisted, $persistentToken)['username'] ?? '') === 'volunteer', 'Сохранённая сессия должна авторизовать разрешённого пользователя.');
cms_nafanya_revoke_session($persistentToken, $sessionDir);
$persisted = json_decode((string)file_get_contents($sessionPath), true);
expect(cms_nafanya_session_user_from($users, $persisted, $persistentToken) === null, 'После выхода токен должен отсутствовать в постоянном хранилище и перестать авторизовывать пользователя.');
$secondToken = cms_nafanya_create_session('volunteer-1', $sessionDir);
cms_nafanya_revoke_user_sessions('volunteer-1', $sessionDir);
$persisted = json_decode((string)file_get_contents($sessionPath), true);
expect(cms_nafanya_session_user_from($users, $persisted, $secondToken) === null, 'Отключение доступа пользователя должно отзывать все его сессии.');
foreach (['nafanya_sessions.json','nafanya-sessions.lock'] as $file) @unlink($sessionDir . DIRECTORY_SEPARATOR . $file);
@rmdir($sessionDir);

$agentSource = (string)file_get_contents(dirname(__DIR__) . '/nafanya-control-agent/main.mjs');
$caddySource = (string)file_get_contents(dirname(__DIR__) . '/deploy/Caddyfile.vdsina-1063655');
expect(str_contains($agentSource, 'href="/nafanya-zoom-control/logout"'), 'Ссылка выхода должна оставаться внутри пути staff-cookie.');
expect(str_contains($agentSource, 'Path=/nafanya-zoom-control; HttpOnly; Secure; SameSite=Strict'), 'Обновление cookie агентом должно сохранять тот же узкий Path.');
expect(substr_count($caddySource, 'rewrite * /nafanya-logout') >= 2, 'Оба домена Caddy должны направлять logout-путь в PHP CMS.');
expect(str_contains((string)file_get_contents(dirname(__DIR__) . '/nafanya-logout.php'), "'path'=>'/nafanya-zoom-control'"), 'Выход должен удалить cookie с тем же узким Path.');
$cmsSource = (string)file_get_contents(dirname(__DIR__) . '/cms.php');
expect(str_contains($cmsSource, 'nafanya-sessions.lock') && str_contains($cmsSource, 'flock($lock, LOCK_EX)'), 'Изменения сессий должны сериализоваться общей блокировкой.');
echo "Nafanya staff authorization checks passed.\n";
