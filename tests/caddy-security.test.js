const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

for (const name of ['deploy/Caddyfile.vdsina-1063655', 'server/Caddyfile']) {
  const caddyfile = fs.readFileSync(path.join(__dirname, '..', name), 'utf8');
  const privateMatchers = caddyfile.match(/^\s*@private path .+$/gm) || [];
  assert.ok(privateMatchers.length > 0, `${name}: private matcher missing`);
  for (const matcher of privateMatchers) {
    assert.match(matcher, /(?:^|\s)\*\.php(?:\s|$)/, `${name}: PHP source must not be served statically`);
  }
  assert.doesNotMatch(
    caddyfile,
    /header_up X-PN-Visitor-IP \{http\.request\.header\.CF-Connecting-IP\}/,
    `${name}: visitor IP must not come from an unverified request header`,
  );
  assert.match(caddyfile, /header_up X-PN-Visitor-IP \{http\.request\.remote\.host\}/);
}

console.log('Caddy source-disclosure and visitor-IP checks passed.');
