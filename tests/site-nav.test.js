const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const config = {
  items: [
    {id:'home',title:'Главная страница',url:'index.html',parent:''},
    {id:'newcomers',title:'Новичкам',url:'newcomers.html',parent:''},
    {id:'test',title:'Тест на алкоголизм',url:'/p/test-na-alkogolizm',parent:'Новичкам'},
    {id:'aa',title:'Кто такие АА',url:'newcomers.html#aa',parent:'Новичкам'},
    {id:'speakers',title:'Спикерские',url:'speakers.html',parent:''}
  ],
  socials: []
};
let html = '';
const shell = {
  insertAdjacentHTML(_where, markup) { html = markup; },
  querySelector() { return {hidden:true}; },
  addEventListener() {}
};
const document = {
  querySelector: (selector) => selector === '.page-shell' ? shell : null,
  getElementById: () => ({textContent: JSON.stringify(config)}),
  addEventListener() {}
};

(async () => {
  await vm.runInNewContext(fs.readFileSync(path.join(__dirname, '..', 'site-nav.js'), 'utf8'), {
    document,
    location: {pathname:'/speakers.html',origin:'https://pochtinormalnye.ru',hash:''},
    URL,
    window: {},
    history: {length:1},
    addEventListener() {}
  });
  const group = html.match(/<summary[^>]*>Новичкам<\/summary>\s*<div class="site-nav__links">(.*?)<\/div>/s);
  assert.ok(group, 'Раздел «Новичкам» должен присутствовать.');
  const urls = [...group[1].matchAll(/href="([^"]+)"/g)].map((match) => match[1]);
  assert.equal(urls[0], '/p/test-na-alkogolizm', 'Тест должен быть первым после «Новичкам».');
  assert.equal(urls.at(-1), '/newcomers.html', 'Ссылка на страницу раздела должна остаться доступной.');
  assert.ok(html.includes('Спикерские'));
  console.log('Site navigation rendering checks passed.');
})().catch((error) => { console.error(error); process.exitCode = 1; });
