const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '..', 'admin', 'index.php'), 'utf8');
const home = fs.readFileSync(path.join(__dirname, '..', 'site-page.php'), 'utf8');
assert.match(source, /\$section === '' && \$tab === 'home' && !cms_can\('pages'\)/);
assert.match(source, /\$section === '' && \$tab === 'structure' && !cms_can\('pages'\)/);
assert.match(source, /if \(\$section !== '' && !cms_can\(\$section\)\)/);
assert.match(source, /\$section === '' && \$tab === 'admin_settings' && !cms_is_admin\(\)/);
assert.match(source, /if \(in_array\(\$section, \['scenarios','rs_audio'\], true\) && !cms_is_admin\(\)\)/);
assert.doesNotMatch(source, /\$section === '' && \$tab === 'service_schedule' && !cms_can\('services'\)/,
  'Все вошедшие пользователи должны видеть график служений.');
assert.doesNotMatch(source, /\$section === '' && \$tab === 'scenarios' && !cms_can\('scenarios'\)/,
  'Все вошедшие пользователи должны видеть сценарии собраний.');
assert.match(home, /class="social-link social-link--nafanya" href="\/admin\/" aria-label="Вход в админку"/);
assert.doesNotMatch(home, /class="social-link social-link--nafanya" href="\/nafanya-login"/);
assert.doesNotMatch(source, /\$section === '' && \$tab === 'rs_audio' && !cms_is_admin\(\) && !array_filter\(CMS_SECTIONS, 'cms_can'\)/,
  'Все вошедшие пользователи должны видеть аудиозаписи РС.');
assert.doesNotMatch(source, /admin_home_navigation_fields\s*\(/, 'Главная больше не вызывает удалённую функцию.');
assert.doesNotMatch(source, /Ссылка на MP3-файл в Google Диске \(необязательно\)/);
assert.doesNotMatch(fs.readFileSync(path.join(__dirname, '..', 'cms.php'), 'utf8'), /'admin_link'=>\['Главная','Ссылка на админку'/);
assert.match(source, /'services' => true, 'scenarios' => true, 'nafanya' => true/,
  'Служащие должны видеть рабочие разделы и кнопку Нафани в кабинете.');
assert.ok(source.includes("'edit_admin' => cms_is_admin(), 'rs_audio' => true"),
  'Редактор админки остаётся только администраторам, аудио доступно всем служащим.');
assert.match(source, /if \(in_array\(\$section, \['scenarios','rs_audio'\], true\) && !cms_is_admin\(\)\)/,
  'Права на редактирование сценариев и аудио остаются только у администратора.');
console.log('Admin cabinet read access and editing restrictions verified.');
