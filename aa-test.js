(() => {
  const card = document.querySelector('.newcomers-card[data-page="aa-test"]');
  if (!card) return;

  const defaultQuestions = [
    'Бывало ли так, что вы решали не пить неделю или более, но вас хватало только на пару дней?',
    'Хотелось ли вам, чтобы окружающие перестали говорить вам о вашем пьянстве и о том, что вам следует делать?',
    'Пытались ли вы переключаться с одного вида выпивки на другой в надежде, что это поможет вам не напиться?',
    'Приходилось ли вам в течение последнего года выпивать утром, чтобы обрести способность начать новый день?',
    'Случалось ли вам завидовать людям, которые могут пить без неприятных последствий?',
    'Случались ли у вас в течение последнего года проблемы из-за выпивки?',
    'Возникали ли у вас из-за выпивки неприятности в семье?',
    'Случалось ли так, что выпивая в компании, вы старались перехватить «дополнительный» стаканчик, потому что вам не хватило?',
    'Утверждаете ли вы, что можете перестать пить в любой момент, как только захотите, хотя часто напиваетесь даже тогда, когда совсем не собираетесь этого делать?',
    'Приходилось ли вам прогуливать работу или занятия в связи с выпивками?',
    'Случались ли у вас провалы памяти?',
    'Появлялось ли у вас когда-либо ощущение, что если бы вы не пили, то ваша жизнь была бы лучше?'
  ];

  let config = {};
  try { config = JSON.parse(document.getElementById('aa-test-config')?.textContent || '{}'); }
  catch (_) { /* Use the built-in questions if configuration is unavailable. */ }
  const questions = Array.isArray(config.questions) && config.questions.length === 12 && config.questions.every((item) => typeof item === 'string' && item.trim())
    ? config.questions : defaultQuestions;
  const escapeHtml = (value) => String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');
  card.classList.add('newcomers-card--test');
  if (!card.querySelector('[data-aa-test-intro]')) {
    card.insertAdjacentHTML('beforeend', '<div class="aa-test__intro" data-aa-test-intro><p>Ответьте на двенадцать вопросов честно — только для себя. Это не диагноз, а повод внимательно посмотреть на то, как алкоголь влияет на жизнь.</p></div>');
  }
  card.insertAdjacentHTML('beforeend', `
    <section class="aa-test" data-aa-test aria-labelledby="aa-test-title">
      <div class="aa-test__progress" aria-live="polite"><div><strong data-aa-test-count></strong><span data-aa-test-caption></span></div><div class="aa-test__progress-track" role="progressbar" aria-label="Ход теста" aria-valuemin="1" aria-valuemax="12"><span data-aa-test-progress></span></div></div>
      <form class="aa-test__form" data-aa-test-form novalidate>
        <section class="aa-test__question" role="group" aria-labelledby="aa-test-title"><h3 class="aa-test__question-title" id="aa-test-title" data-aa-test-question></h3><div class="aa-test__answers"><button type="button" class="aa-test__answer" data-aa-test-answer="yes">Да</button><button type="button" class="aa-test__answer" data-aa-test-answer="no">Нет</button></div></section>
        <p class="aa-test__hint" data-aa-test-hint>Сначала выберите ответ.</p>
        <div class="aa-test__controls"><button type="button" class="aa-test__control aa-test__control--quiet" data-aa-test-prev>← Назад</button><button type="button" class="aa-test__control" data-aa-test-next disabled>Далее →</button></div>
      </form>
      <section class="aa-test__result" data-aa-test-result hidden aria-live="polite"></section>
    </section>`);

  const root = card.querySelector('[data-aa-test]');
  const form = root.querySelector('[data-aa-test-form]');
  const question = root.querySelector('[data-aa-test-question]');
  const count = root.querySelector('[data-aa-test-count]');
  const caption = root.querySelector('[data-aa-test-caption]');
  const progress = root.querySelector('[data-aa-test-progress]');
  const progressTrack = root.querySelector('.aa-test__progress-track');
  const hint = root.querySelector('[data-aa-test-hint]');
  const previous = root.querySelector('[data-aa-test-prev]');
  const next = root.querySelector('[data-aa-test-next]');
  const answers = [...root.querySelectorAll('[data-aa-test-answer]')];
  const result = root.querySelector('[data-aa-test-result]');
  const values = Array(questions.length).fill(null);
  let index = 0;

  const render = () => {
    question.textContent = questions[index];
    count.textContent = `Вопрос ${index + 1} из ${questions.length}`;
    caption.textContent = `Ответов «Да»: ${values.filter((value) => value === 'yes').length}`;
    const percent = ((index + 1) / questions.length) * 100;
    progress.style.width = `${percent}%`;
    progressTrack.setAttribute('aria-valuenow', String(index + 1));
    answers.forEach((answer) => {
      const selected = answer.dataset.aaTestAnswer === values[index];
      answer.classList.toggle('is-selected', selected);
      answer.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
    const hasAnswer = values[index] !== null;
    next.disabled = !hasAnswer;
    next.textContent = index === questions.length - 1 ? 'Показать итог' : 'Далее →';
    previous.hidden = index === 0;
    hint.textContent = hasAnswer ? 'Ответ можно изменить до завершения теста.' : 'Сначала выберите ответ.';
  };

  const showResult = () => {
    const yes = values.filter((value) => value === 'yes').length;
    form.hidden = true;
    result.hidden = false;
    result.classList.toggle('aa-test__result--attention', yes >= 4);
    result.innerHTML = `
      <p class="aa-test__result-count">Ваш результат: <strong>${yes} ${yes === 1 ? 'ответ' : yes >= 2 && yes <= 4 ? 'ответа' : 'ответов'} «Да» из ${questions.length}</strong></p>
      <h3>${escapeHtml(yes >= 4 ? config.test_attention_heading || 'Стоит отнестись к этому внимательно' : config.test_other_heading || 'Тест завершён')}</h3>
      <p>${escapeHtml(yes >= 4 ? config.test_attention_text || 'Четыре или более ответов «Да» могут быть поводом обсудить, как алкоголь влияет на вашу жизнь. Только вы сами можете решить, относите ли себя к алкоголикам.' : config.test_other_text || 'Этот результат не ставит диагноз. Если употребление алкоголя вызывает тревогу или вопросы, можно прийти на собрание АА, послушать опыт других и задать вопросы.')}</p>
      <div class="aa-test__result-actions"><a class="aa-test__control" href="/schedule.html">Посмотреть расписание</a><button type="button" class="aa-test__control aa-test__control--quiet" data-aa-test-restart>Пройти ещё раз</button></div>`;
    result.querySelector('[data-aa-test-restart]').addEventListener('click', () => {
      values.fill(null);
      index = 0;
      result.hidden = true;
      form.hidden = false;
      render();
      root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  };

  answers.forEach((answer) => answer.addEventListener('click', () => {
    values[index] = answer.dataset.aaTestAnswer;
    render();
  }));
  previous.addEventListener('click', () => { if (index > 0) { index -= 1; render(); } });
  next.addEventListener('click', () => {
    if (values[index] === null) return;
    if (index === questions.length - 1) showResult();
    else { index += 1; render(); }
  });
  render();
})();
