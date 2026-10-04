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

console.log('Site search ranking checks passed.');
