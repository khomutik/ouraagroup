const assert = require('node:assert/strict');
const {prepare, search} = require('../site-search.js');

async function main() {
  const response = await fetch('https://pochtinormalnye.ru/site-search.json?v=5', {cache: 'no-store'});
  assert.equal(response.status, 200, 'Public search index must be available');
  const {documents} = await response.json();
  const prepared = prepare(documents);
  const dates = [
    ['2026-03-09', '9 марта'], ['2026-03-28', '28 марта'],
    ['2026-04-05', '5 апреля'], ['2026-04-10', '10 апреля'],
    ['2026-04-19', '19 апреля'], ['2026-04-26', '26 апреля'],
    ['2026-05-03', '3 мая'], ['2026-05-24', '24 мая'],
    ['2026-05-31', '31 мая'], ['2026-06-27', '27 июня'],
    ['2026-07-25', '25 июля'], ['2026-08-29', '29 августа'],
    ['2026-10-03', '3 октября'],
  ];
  const cases = dates.map(([date, ru]) => [`повестка РС ${ru}`, 'Повестка РС', date]);
  cases.push(...dates.filter(([date]) => date !== '2026-10-03')
    .map(([date, ru]) => [`протокол РС ${ru}`, 'Протокол РС', date]));
  cases.push(
    ['название группы', 'Повестка РС', '2026-03-09'],
    ['правила чата', 'Повестка РС', '2026-03-28'],
    ['обязанности ведущего', 'Повестка РС', '2026-04-05'],
    ['расписание и темы собраний', 'Повестка РС', '2026-04-10'],
    ['формат поиска наставника', 'Повестка РС', '2026-04-19'],
    ['мы готовы открывать чат', 'Протокол РС', '2026-04-26'],
    ['куратор по обучению', 'Повестка РС', '2026-05-03'],
    ['голосование по кандидатам', 'Повестка РС', '2026-05-24'],
    ['сценарий обычного собрания', 'Повестка РС', '2026-05-31'],
    ['сценарии собраний', 'Повестка РС', '2026-06-27'],
    ['сценарий для спикерских собраний', 'Протокол РС', '2026-07-25'],
    ['оплатить хостинг для сайта на год', 'Протокол РС', '2026-08-29'],
    ['виртуальные медали', 'Повестка РС', '2026-10-03'],
    ['участие группы в 5 традиции', 'Протокол РС', '2026-08-29'],
    ['разделение зоны рассылки МАХ и ТГ', 'Протокол РС', '2026-08-29'],
    ['снизить ценз трезвости для рассыльного', 'Протокол РС', '2026-08-29'],
    ['спам фильтр анонсы', 'Протокол РС', '2026-04-10'],
    ['форс мажор техведа', 'Протокол РС', '2026-04-10'],
    ['молитва тур 2', 'Протокол РС', '2026-04-10'],
    ['название группы тур 1', 'Голосование группы', '2026-03-05'],
    ['в каком приложении проводить собрание голосом', 'Голосование группы', '2026-06-14'],
    ['Zoom расход август', 'Отчёт казначея', '2026-08-29'],
    ['расход зум август', 'Отчёт казначея', '2026-08-29'],
    ['отчёт казначея октябрь', 'Отчёт казначея', '2026-10-03'],
  );
  const failures = [];
  for (const [query, category, date] of cases) {
    const top = search(prepared, query).slice(0, 3);
    if (!top.some(({document}) => document.category === category && document.date === date)) {
      failures.push(`${query} -> ${top.map(({document}) => `${document.category || ''} ${document.date || ''} ${document.title}`).join(' | ')}`);
    }
  }
  assert.deepEqual(failures, [], `${failures.length}/${cases.length} search scenarios failed:\n${failures.join('\n')}`);
  assert.equal(documents.filter((item) => item.url.startsWith('/archive.html#vote-')).length >= 100, true, 'Individual votes must be indexed');
  console.log(`${cases.length} live archive search scenarios passed; ${documents.filter((item) => item.url.startsWith('/archive.html#vote-')).length} vote results indexed.`);
}

main().catch((error) => { console.error(error); process.exitCode = 1; });
