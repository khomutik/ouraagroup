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
  assert.match(caddyfile, /header_up X-PN-Client-IP \{http\.request\.remote\.host\}/, `${name}: trusted client IP must be overwritten at Caddy ingress`);
  assert.match(caddyfile, /\/nafanya-login\s+\/nafanya-auth-check\s+\/nafanya-logout/, `${name}: Nafanya auth endpoints must reach PHP`);
  assert.match(caddyfile, /handle \/nafanya-zoom-control\/logout[\s\S]{0,180}rewrite \* \/nafanya-logout/, `${name}: cookie-scoped logout must reach PHP revocation before the Zoom proxy`);
}

const deployedCaddyfile = fs.readFileSync(path.join(__dirname, '..', 'deploy/Caddyfile.vdsina-1063655'), 'utf8');
assert.equal((deployedCaddyfile.match(/@speaker_audio path_regexp/g) || []).length, 2, 'Production and preview hosts must route speaker audio through PHP.');
assert.equal((deployedCaddyfile.match(/rewrite \* \/public\.php\?speaker_audio=\{re\.speaker_audio\.1\}/g) || []).length, 2, 'Speaker audio proxy must forward only the validated path ID.');

const nafanyaProxyBlocks = deployedCaddyfile.match(/reverse_proxy https:\/\/91\.84\.122\.244 \{[^}]*\}\s*\}/g) || [];
assert.equal(nafanyaProxyBlocks.length, 4, 'Zoom and MAX must share the verified upstream on both hosts.');
for (const proxy of nafanyaProxyBlocks) {
  assert.match(proxy, /header_up Host pochtinormalnye\.ru\b/);
  assert.match(proxy, /tls_server_name today\.khomutik\.ru\b/);
  assert.doesNotMatch(proxy, /tls_insecure_skip_verify/);
}

console.log('Caddy source-disclosure and visitor-IP checks passed.');
