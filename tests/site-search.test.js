const assert = require('node:assert/strict');
const {normalize, prepare, search, excerpt} = require('../site-search.js');

const documents = prepare([
  {title:'Тест на алкоголизм',keywords:'подходит ли тебе аа',text:'Ответьте на 12 вопросов.',url:'/p/test-na-alkogolizm'},
  {title:'Новичкам: с чего начать',keywords:'бросить пить перестать пить',text:'Можно прийти на собрание и просто послушать.',url:'/newcomers.html'},
  {title:'Расписание собраний',keywords:'когда онлайн собрание',text:'Встречи проходят в 21:30 по Москве.',url:'/schedule.html'},
  {title:'Спикерские',keywords:'записи аудио',text:'Истории участников.',url:'/speakers.html'},
]);

assert.equal(normalize('Ёжик И ЁЛКА'), 'ежик и елка');
assert.equal(search(documents, 'тест на алкоголизм')[0].document.url, '/p/test-na-alkogolizm');
assert.equal(search(documents, 'как бросить пить')[0].document.url, '/newcomers.html');
assert.equal(search(documents, 'собрания')[0].document.url, '/schedule.html');
assert.equal(search(documents, 'запись аудио')[0].document.url, '/speakers.html');
assert.equal(search(documents, 'несуществующее слово').length, 0);
assert.match(excerpt('Первая часть. Нужная информация о собрании в конце текста.', 'собрание'), /собрании/);

const realCases = prepare([
  {title:'4 шаг',keywords:'спикерская выступление запись аудио',text:'Владимир Ринго Хельсинки',url:'/speakers.html#four'},
  {title:'Первый, второй и третий шаги',keywords:'спикерская выступление запись аудио',text:'История участника',url:'/speakers.html#first'},
  {title:'Рабочее собрание',keywords:'решение группы протокол',text:'Пункт 4 повестки. ' + 'Другие вопросы. '.repeat(80) + 'Обсудили шаг.',url:'/archive.html#unrelated'},
  {title:'Собрание: Вторник',keywords:'21:30',text:'Тема собрания',url:'/schedule.html#tuesday'},
  {title:'Расписание онлайн-собраний АА',keywords:'когда собрание zoom дни время темы',text:'21:30 по Москве',url:'/schedule.html'},
]);
assert.deepEqual(search(realCases, '4 шаг').map((row) => row.document.url), ['/speakers.html#four']);
assert.equal(search(realCases, 'Первый шаг')[0].document.url, '/speakers.html#first');
assert.deepEqual(search(realCases, 'вторник').map((row) => row.document.url), ['/schedule.html#tuesday']);

const naturalCases = prepare([
  {title:'Группа АА',keywords:'zoom зум войти ссылка чат телеграм',text:'',url:'/'},
  {title:'Расписание онлайн-собраний АА',keywords:'сегодня во сколько собрание',text:'21:30 по Москве',url:'/schedule.html'},
  {title:'Тест «Подходит ли тебе АА?»',keywords:'подходит ли мне аа тест',text:'',url:'/p/test-na-alkogolizm'},
  {title:'Двенадцать Шагов',keywords:'12 шагов',text:'',url:'/newcomers.html#twelve-steps'},
  {title:'12 шагов спонсора',keywords:'',text:'',url:'/newcomers.html#sponsor-steps'},
  {title:'7-я традиция и пожертвования',keywords:'седьмая традиция',text:'',url:'/tradition.html'},
  {category:'Спикерские',title:'4 шаг',keywords:'спикерская запись аудио',text:'',url:'/speakers.html#four'},
  {category:'Повестка РС',title:'Повестка РС 29.08.2026',keywords:'повестка рс',text:'',date:'2026-08-29',url:'/archive.html#agenda'},
  {category:'Повестка РС',title:'Оплатить хостинг сайта',keywords:'повестка рс',text:'Обсудить и проголосовать',date:'2026-08-29',url:'/archive.html#agenda-point'},
  {category:'Протокол РС',title:'Оплатить хостинг сайта',keywords:'протокол рс голосование',text:'Голосование: за 7, против 0. Решение принято.',date:'2026-08-29',url:'/archive.html#protocol-point'},
  {category:'Отчёт казначея',title:'Отчёт казначея · 29.08.2026',keywords:'отчёт казначей приход расход остаток',text:'За август 2026. Расход PayPal: Zoom — 19,27 €.',date:'2026-08-29',url:'/archive.html#treasurer-august'},
  {category:'Отчёт казначея',title:'Отчёт казначея · 03.10.2026',keywords:'отчёт казначей приход расход остаток',text:'За сентябрь 2026. Поступления и расходы.',date:'2026-10-03',url:'/archive.html#treasurer-september'},
]);
for (const [query, url] of [
  ['войти в зум', '/'], ['во сколько собрание', '/schedule.html'],
  ['подходит ли мне АА', '/p/test-na-alkogolizm'],
  ['12 шагов', '/newcomers.html#twelve-steps'],
  ['7-я традиция', '/tradition.html'],
  ['седьмая традиция', '/tradition.html'],
  ['спикерская 4 шаг', '/speakers.html#four'],
  ['повестка РС 29 августа', '/archive.html#agenda'],
  ['протокол РС оплата хостинга', '/archive.html#protocol-point'],
  ['Zoom расход август', '/archive.html#treasurer-august'],
  ['расход зум август', '/archive.html#treasurer-august'],
  ['отчёт казначея октябрь', '/archive.html#treasurer-september'],
]) {
  assert.equal(search(naturalCases, query)[0]?.document.url, url, query);
}

console.log('Site search ranking checks passed.');
