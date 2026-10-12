const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const css = fs.readFileSync(path.join(__dirname, '..', 'admin', 'admin.css'), 'utf8');

assert.match(css, /@media\(min-width:761px\)[\s\S]*?\.admin-layout:not\(:has\(\.admin-portal\)\)\{display:grid;grid-template-columns:minmax\(230px,300px\) minmax\(0,1fr\)/,
  'Desktop cabinet navigation and workspace must sit side by side.');
assert.match(css, /@media\(max-width:760px\)\{\.admin-layout:not\(:has\(\.admin-portal\)\)\{display:block\}\}/,
  'The cabinet must switch back to the mobile menu layout on narrow screens.');
assert.match(css, /\.rs-audio-card::before\{[^}]*width:6px;[^}]*background:#df5b0b/,
  'Audio cards should use the same warm left accent as public speaker cards.');
assert.match(css, /\.rs-audio-card iframe\{[^}]*height:220px/,
  'The embedded player should use the same comfortable height as speaker-card players.');
assert.match(css, /@media\(min-width:761px\)\{[\s\S]*?\.admin-layout:not\(:has\(\.admin-portal\)\)>\.admin-nav\{color:#fbf7ea/,
  'Desktop navigation labels must contrast against the navy page background.');
assert.match(css, /\.admin-layout:not\(:has\(\.admin-portal\)\) \.admin-nav__explorer \.site-explorer__cycle\{color:#fbf7ea\}/,
  'The page-tree labels must remain readable without hover.');
assert.match(css, /\.admin-layout:not\(:has\(\.admin-portal\)\)>\.admin-nav>a\.is-active,[\s\S]*?color:#111a40/,
  'The active/hovered navigation row must switch to dark text on its cream surface.');

console.log('Admin desktop/mobile layout and speaker-style audio-card checks passed.');
