const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '..', 'admin', 'index.php'), 'utf8');
assert.match(source, /\$section === '' && \$tab === 'home' && !cms_can\('pages'\)/);
assert.match(source, /\$section === '' && \$tab === 'structure' && !cms_can\('pages'\)/);
assert.match(source, /if \(\$section !== '' && !cms_can\(\$section\)\)/);
console.log('Admin page-read permission guards present.');
