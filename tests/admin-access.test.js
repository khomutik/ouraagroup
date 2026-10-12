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
assert.match(source, /\$section === '' && \$tab === 'service_schedule' && !cms_can\('services'\)/);
assert.match(source, /\$section === '' && \$tab === 'scenarios' && !cms_can\('scenarios'\)/);
assert.match(home, /class="social-link social-link--nafanya" href="\/admin\/" aria-label="Вход в админку"/);
assert.doesNotMatch(home, /class="social-link social-link--nafanya" href="\/nafanya-login"/);
console.log('Admin page-read permission guards present.');
