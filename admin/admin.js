(async () => {
  document.documentElement.classList.add('admin-js');
  let emojis = [
    {emoji:'😀',file:'0_0.png'}, {emoji:'😃',file:'0_1.png'}, {emoji:'😄',file:'0_2.png'},
    {emoji:'😁',file:'0_3.png'}, {emoji:'😂',file:'0_7.png'}, {emoji:'😊',file:'0_11.png'},
    {emoji:'😍',file:'0_17.png'}, {emoji:'🥰',file:'0_18.png'}, {emoji:'👍',file:'0_160.png'},
    {emoji:'🙏',file:'0_365.png'}, {emoji:'❤',file:'6_1.png'}, {emoji:'✅',file:'6_108.png'}
  ];
  let groupEmojis = ['aa_ballot.png','aa_book_blue.png','aa_book_green.png','aa_book_orange.png','aa_book_red.png','aa_books.png','aa_calendar.png','aa_check.png','aa_double_exclamation.png','aa_free.png','aa_heart_hands.png','aa_message.png','aa_microphone.png','aa_mini_nafanya.png','aa_new.png','aa_orange_heart.png','aa_party.png','aa_people.png','aa_play.png','aa_point_right.png','aa_point_up.png','aa_question_exclamation.png','aa_speaking.png','aa_thanks.png','aa_thumbs_up.png','aa_video.png','aa_writing.png'].map((file) => ({file,title:file}));
  try {
    const response = await fetch('/assets/group-emoji/index.json', {cache:'no-store'});
    const catalog = response.ok ? await response.json() : [];
    if (Array.isArray(catalog) && catalog.length) groupEmojis = catalog;
  } catch (_) { /* The compact built-in set remains available offline. */ }
  try {
    const response = await fetch('/assets/telegram-emoji/index.json?v=2', {cache:'force-cache'});
    const catalog = response.ok ? await response.json() : [];
    if (Array.isArray(catalog) && catalog.length) emojis = catalog;
  } catch (_) { /* A compact Telegram-image set remains available offline. */ }

  const serviceIcons = [
    {id:'telegram', label:'Telegram', image:'/assets/social-telegram-v2.png'},
    {id:'zoom', label:'Zoom', image:'/assets/social-zoom-v2.png'},
    {id:'max', label:'MAX', image:'/assets/social-max-v2.png'},
    {id:'gmail', label:'Gmail', image:'/assets/service-gmail.svg'},
    {id:'whatsapp', label:'WhatsApp', image:'/assets/service-whatsapp.svg'},
    {id:'vk', label:'ВКонтакте', image:'/assets/service-vk.svg'},
    {id:'youtube', label:'YouTube', image:'/assets/service-youtube.svg'},
    {id:'rutube', label:'RuTube', image:'/assets/service-rutube.svg'},
    {id:'paypal', label:'PayPal', image:'/assets/service-paypal.svg'},
    {id:'sber', label:'Сбер', image:'/assets/service-sber.svg'},
    {id:'sbp', label:'СБП', image:'/assets/service-sbp.svg'}
  ];

  const optimizeImage = async (file) => {
    if (!file?.type?.startsWith('image/') || file.type === 'image/gif') return file;
    try {
      const bitmap = await createImageBitmap(file);
      const largest = Math.max(bitmap.width, bitmap.height);
      if (largest <= 1800 && file.size <= 600 * 1024) { bitmap.close(); return file; }
      const scale = Math.min(1, 1800 / largest);
      const canvas = document.createElement('canvas');
      canvas.width = Math.round(bitmap.width * scale); canvas.height = Math.round(bitmap.height * scale);
      canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height); bitmap.close();
      const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', .86));
      if (!blob || blob.size >= file.size) return file;
      return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.webp', {type:'image/webp', lastModified:Date.now()});
    } catch (_) { return file; }
  };

  const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;');

  const makeButton = (label, title, className = 'editor-tool') => {
    const control = document.createElement('button');
    control.type = 'button';
    control.className = className;
    control.textContent = label;
    control.title = title;
    control.setAttribute('aria-label', title);
    return control;
  };

  const makeRich = (textarea) => {
    if (textarea.dataset.richReady === 'true') return;
    textarea.dataset.richReady = 'true';
    const wrapper = document.createElement('div');
    wrapper.className = 'rich-editor-wrap';
    const toolbar = document.createElement('div');
    toolbar.className = 'editor-toolbar';
    toolbar.setAttribute('role', 'toolbar');
    toolbar.setAttribute('aria-label', 'Форматирование текста');

    const editor = document.createElement('div');
    editor.className = 'rich-editor';
    editor.contentEditable = 'true';
    editor.setAttribute('role', 'textbox');
    editor.setAttribute('aria-multiline', 'true');
    editor.setAttribute('aria-label', textarea.closest('.field')?.querySelector('span')?.textContent || 'Текст');
    editor.dataset.placeholder = 'Введите текст…';
    editor.innerHTML = textarea.value;

    let savedRange = null;
    const saveRange = () => {
      const selection = window.getSelection();
      if (!selection?.rangeCount) return;
      const range = selection.getRangeAt(0);
      if (editor.contains(range.commonAncestorContainer)) savedRange = range.cloneRange();
    };
    const restoreRange = () => {
      editor.focus();
      if (!savedRange) return;
      const selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(savedRange);
    };
    const run = (command, value = null) => {
      restoreRange();
      document.execCommand(command, false, value);
      saveRange();
      editor.dispatchEvent(new Event('input', {bubbles: true}));
    };
    const selectionText = (fallback) => {
      restoreRange();
      return window.getSelection()?.toString() || fallback;
    };
    const insertHtml = (html) => run('insertHTML', html);
    const insertWrapped = (tag, className, fallback) => {
      const text = escapeHtml(selectionText(fallback));
      const classAttribute = className ? ` class="${className}"` : '';
      insertHtml(`<${tag}${classAttribute}>${text}</${tag}>`);
    };
    const validUrl = (value) => {
      const url = String(value || '').trim();
      if (!url) return '';
      if (!/^https?:\/\//i.test(url) && !url.startsWith('/') && !url.startsWith('#')) {
        alert('Ссылка должна начинаться с https://');
        return '';
      }
      return url;
    };
    const chooseOption = (question, options, fallback = 1) => {
      const answer = prompt(`${question}\n${options.map((item, index) => `${index + 1} — ${item.label}`).join('\n')}`, String(fallback));
      if (answer === null) return null;
      const index = Number.parseInt(answer, 10) - 1;
      return options[index] || options[fallback - 1] || options[0];
    };
    const closeMenus = (except = null) => {
      toolbar.querySelectorAll('.editor-menu[open]').forEach((menu) => {
        if (menu !== except) menu.open = false;
      });
    };

    const addTool = (label, title, action) => {
      const control = makeButton(label, title);
      control.addEventListener('mousedown', (event) => event.preventDefault());
      control.addEventListener('click', () => {
        closeMenus();
        action();
      });
      toolbar.append(control);
      return control;
    };

    const addMenu = (label, title, items, extraClass = '') => {
      const menu = document.createElement('details');
      menu.className = `editor-menu ${extraClass}`.trim();
      const toggle = document.createElement('summary');
      toggle.textContent = label;
      toggle.title = title;
      toggle.setAttribute('aria-label', title);
      const panel = document.createElement('div');
      panel.className = 'editor-menu-panel';
      panel.setAttribute('role', 'menu');
      items.forEach(({label: itemLabel, title: itemTitle = itemLabel, action, className = '', image = ''}) => {
        const item = makeButton(itemLabel, itemTitle, `editor-menu-item ${className}`.trim());
        if (image) { item.textContent = ''; const img = document.createElement('img'); img.src = image; img.alt = ''; item.append(img); }
        item.setAttribute('role', 'menuitem');
        item.addEventListener('mousedown', (event) => event.preventDefault());
        item.addEventListener('click', () => {
          action();
          menu.open = false;
        });
        panel.append(item);
      });
      menu.addEventListener('toggle', () => { if (menu.open) closeMenus(menu); });
      menu.append(toggle, panel);
      toolbar.append(menu);
      return menu;
    };

    addTool('↶', 'Отменить', () => run('undo'));
    addTool('↷', 'Повторить', () => run('redo'));

    addMenu('Aa', 'Стиль абзаца', [
      {label:'Обычный текст', action:() => run('formatBlock', 'p')},
      {label:'Заголовок', action:() => run('formatBlock', 'h2')},
      {label:'Подзаголовок', action:() => run('formatBlock', 'h3')},
      {label:'Цитата', action:() => run('formatBlock', 'blockquote')},
      {label:'Выносная цитата', action:() => insertWrapped('blockquote', 'pull-quote', 'Текст цитаты')},
      {label:'Код', action:() => insertHtml(`<pre><code>${escapeHtml(selectionText('Код'))}</code></pre>`)},
      {label:'Мелкий шрифт', action:() => insertWrapped('small', '', 'Мелкий текст')},
      {label:'Разделитель', action:() => run('insertHorizontalRule')}
    ]);

    addMenu('Ж', 'Оформление символов', [
      {label:'Жирный', action:() => run('bold'), className:'menu-bold'},
      {label:'Курсив', action:() => run('italic'), className:'menu-italic'},
      {label:'Подчёркнутый', action:() => run('underline'), className:'menu-underline'},
      {label:'Зачёркнутый', action:() => run('strikeThrough'), className:'menu-strike'},
      {label:'Скрытый текст', action:() => insertWrapped('span', 'spoiler', 'Скрытый текст')},
      {label:'Подстрочный', action:() => run('subscript')},
      {label:'Надстрочный', action:() => run('superscript')},
      {label:'Выделенный', action:() => insertWrapped('mark', '', 'Выделенный текст')},
      {label:'Очистить формат', action:() => run('removeFormat')}
    ]);

    addMenu('Цвет', 'Цвет текста', [
      {label:'Темно-синий', className:'menu-color-blue', action:() => insertWrapped('span', 'text-color-blue', 'Текст')},
      {label:'Бежевый', className:'menu-color-beige', action:() => insertWrapped('span', 'text-color-beige', 'Текст')},
      {label:'Оранжевый', className:'menu-color-orange', action:() => insertWrapped('span', 'text-color-orange', 'Текст')}
    ]);

    addMenu('☷', 'Списки и блоки', [
      {label:'Нумерованный список', action:() => run('insertOrderedList')},
      {label:'Маркированный список', action:() => run('insertUnorderedList')},
      {label:'Чек-лист', action:() => insertHtml('<ul class="checklist"><li>Первый пункт</li><li>Второй пункт</li></ul>')},
      {label:'Сворачиваемый блок', action:() => insertHtml('<details><summary>Нажмите, чтобы раскрыть</summary><p>Скрытый текст</p></details>')}
    ]);

    const tableAction = () => {
      const rowsInput = prompt('Количество строк вместе с заголовком (2–30)', '11');
      if (rowsInput === null) return;
      const parsedRows = Number.parseInt(rowsInput, 10);
      if (!Number.isFinite(parsedRows)) { alert('Введите количество строк цифрами.'); return; }
      const rows = Math.min(30, Math.max(2, parsedRows));
      const columnsInput = prompt('Количество столбцов (2–6)', '3');
      if (columnsInput === null) return;
      const parsedColumns = Number.parseInt(columnsInput, 10);
      if (!Number.isFinite(parsedColumns)) { alert('Введите количество столбцов цифрами.'); return; }
      const columns = Math.min(6, Math.max(2, parsedColumns));
      const head = Array.from({length: columns}, (_, i) => `<th>Заголовок ${i + 1}</th>`).join('');
      const body = Array.from({length: rows - 1}, () => `<tr>${Array.from({length: columns}, () => '<td>Ячейка</td>').join('')}</tr>`).join('');
      insertHtml(`<table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table><p><br></p>`);
    };
    addTool('▦', 'Добавить таблицу', tableAction);

    addMenu('≡', 'Выравнивание', [
      {label:'По левому краю', action:() => run('justifyLeft')},
      {label:'По центру', action:() => run('justifyCenter')},
      {label:'По правому краю', action:() => run('justifyRight')},
      {label:'По ширине', action:() => run('justifyFull')}
    ]);

    const textLinkAction = () => {
      const url = validUrl(prompt('Вставьте ссылку, начинающуюся с https://'));
      if (!url) return;
      const selected = selectionText('');
      if (selected) run('createLink', url);
      else {
        const label = prompt('Текст ссылки', 'Открыть ссылку');
        if (label) insertHtml(`<a href="${escapeHtml(url)}">${escapeHtml(label)}</a>`);
      }
    };
    const buttonAction = () => {
      const url = validUrl(prompt('Вставьте ссылку для кнопки (https://, / или #)', 'https://'));
      if (!url) return;
      const label = prompt('Текст на кнопке', 'Читать');
      if (!label) return;
      const type = chooseOption('Тип кнопки', [
        {label:'Основная (оранжевая)', className:'content-button--primary'},
        {label:'Спокойная (светлая)', className:'content-button--soft'},
        {label:'Контурная', className:'content-button--outline'},
        {label:'Ссылка-текст', className:'content-button--link'}
      ]);
      if (!type) return;
      const color = chooseOption('Цвет кнопки', [
        {label:'Оранжевый', className:'content-button--orange'},
        {label:'Темно-синий', className:'content-button--blue'},
        {label:'Бежевый', className:'content-button--beige'}
      ]);
      if (!color) return;
      const shape = chooseOption('Форма кнопки', [
        {label:'Скругленная', className:''},
        {label:'Пилюля', className:'content-button--pill'},
        {label:'Прямоугольная', className:'content-button--square'}
      ]);
      if (!shape) return;
      const align = chooseOption('Расположение', [
        {label:'Слева', className:'content-button--align-left'},
        {label:'По центру', className:'content-button--align-center'},
        {label:'Справа', className:'content-button--align-right'},
        {label:'На всю ширину', className:'content-button--align-full'}
      ]);
      if (!align) return;
      const classes = ['content-button', type.className, color.className, shape.className, align.className].filter(Boolean).join(' ');
      insertHtml(`<a class="${classes}" href="${escapeHtml(url)}">${escapeHtml(label)}</a>`);
    };
    addMenu('🔗', 'Ссылки', [
      {label:'Текстовая ссылка', action:textLinkAction},
      {label:'Кнопка — настроить', action:buttonAction},
      {label:'Убрать ссылку', action:() => run('unlink')}
    ]);

    if (textarea.dataset.inlineImages === 'true') {
      const uploadInput = document.createElement('input');
      uploadInput.type = 'file';
      uploadInput.name = `${textarea.name}_inline_images[]`;
      uploadInput.accept = 'image/jpeg,image/png,image/webp';
      uploadInput.multiple = true;
      uploadInput.hidden = true;
      const picker = document.createElement('input');
      picker.type = 'file';
      picker.accept = uploadInput.accept;
      picker.hidden = true;
      picker.dataset.skipOptimize = 'true';
      uploadInput.dataset.skipOptimize = 'true';
      const transfer = new DataTransfer();
      picker.addEventListener('change', async () => {
        let file = picker.files?.[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
          alert('Картинка слишком большая. Максимальный размер — 5 МБ.');
          picker.value = '';
          return;
        }
        file = await optimizeImage(file);
        transfer.items.add(file);
        uploadInput.files = transfer.files;
        const index = transfer.files.length - 1;
        const alt = prompt('Коротко опишите картинку для незрячих посетителей', file.name.replace(/\.[^.]+$/, '')) || '';
        const caption = prompt('Подпись под картинкой (можно оставить пустой)', '') || '';
        const preview = URL.createObjectURL(file);
        const captionHtml = caption ? `<figcaption>${escapeHtml(caption)}</figcaption>` : '';
        insertHtml(`<figure><img src="${preview}" data-upload-index="${index}" alt="${escapeHtml(alt)}">${captionHtml}</figure><p><br></p>`);
        picker.value = '';
      });
      wrapper.append(uploadInput, picker);
      addTool('🖼', 'Добавить изображение в текст', () => picker.click());
    }

    addTool('Σ', 'Добавить формулу', () => {
      const formula = prompt('Введите формулу обычными символами, например: a² + b² = c²');
      if (formula) insertHtml(`<span class="formula">${escapeHtml(formula)}</span>`);
    });

    const emojiMenu = document.createElement('details');
    emojiMenu.className = 'editor-menu editor-menu--emoji';
    const emojiSummary = document.createElement('summary'); emojiSummary.innerHTML = '<img src="/assets/telegram-emoji/0_11.png" alt="">'; emojiSummary.title = 'Эмодзи Telegram — только стандартный жёлтый цвет, без профессий'; emojiSummary.setAttribute('aria-label', emojiSummary.title);
    const emojiPanel = document.createElement('div'); emojiPanel.className = 'editor-menu-panel'; emojiPanel.setAttribute('role','menu');
    let emojiOffset = 0;
    const addEmojiChunk = () => {
      emojis.slice(emojiOffset, emojiOffset + 200).forEach(({emoji,file}) => {
        const source = `/assets/telegram-emoji/${encodeURIComponent(file)}`;
        const item = makeButton('', `Вставить ${emoji}`, 'editor-menu-item emoji-option emoji-option--image'); item.setAttribute('role','menuitem');
        const picture = document.createElement('img'); picture.src = source; picture.alt = emoji; picture.loading = 'lazy'; item.append(picture);
        item.addEventListener('mousedown', (event) => event.preventDefault()); item.addEventListener('click', () => { insertHtml(`<img class="inline-emoji" src="${source}" alt="${escapeHtml(emoji)}">`); emojiMenu.open = false; }); emojiPanel.append(item);
      });
      emojiOffset += 200;
    };
    emojiMenu.addEventListener('toggle', () => { if (emojiMenu.open) { closeMenus(emojiMenu); if (!emojiOffset) addEmojiChunk(); } });
    emojiPanel.addEventListener('scroll', () => { if (emojiPanel.scrollTop + emojiPanel.clientHeight >= emojiPanel.scrollHeight - 100 && emojiOffset < emojis.length) addEmojiChunk(); });
    emojiMenu.append(emojiSummary, emojiPanel); toolbar.append(emojiMenu);
    const groupEmojiItems = groupEmojis.map(({file,title}) => ({
      label: '', image: `/assets/group-emoji/${file.split('/').map(encodeURIComponent).join('/')}`,
      title: `Вставить групповой эмодзи ${String(title || file).replace(/\.png$/i, '')}`,
      className: 'emoji-option emoji-option--image',
      action: () => insertHtml(`<img class="inline-emoji" src="/assets/group-emoji/${file.split('/').map(encodeURIComponent).join('/')}" alt="">`)
    }));
    addMenu('ПН', 'Наши групповые эмодзи', groupEmojiItems, 'editor-menu--emoji editor-menu--group-emoji');
    addMenu('Соц', 'Значки сервисов', serviceIcons.map((service) => ({
      label: '', image: service.image, title: `Вставить значок ${service.label}`,
      className: 'emoji-option emoji-option--image',
      action: () => insertHtml(`<img class="inline-emoji" src="${service.image}" alt="${escapeHtml(service.label)}">`)
    })), 'editor-menu--emoji editor-menu--service-emoji');

    ['keyup','mouseup','input','focus'].forEach((eventName) => editor.addEventListener(eventName, () => {
      saveRange();
      editor.classList.remove('rich-editor--error');
    }));
    editor.addEventListener('keydown', (event) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        textLinkAction();
      }
      if ((event.ctrlKey || event.metaKey) && event.shiftKey && event.key.toLowerCase() === 't') {
        event.preventDefault();
        tableAction();
      }
    });

    wrapper.prepend(toolbar, editor);
    textarea.before(wrapper);
    textarea.form?.addEventListener('submit', (event) => {
      textarea.value = editor.innerHTML;
      const isEmpty = !editor.textContent.trim() && !editor.querySelector('img,table,hr');
      if (textarea.dataset.required === 'true' && isEmpty) {
        event.preventDefault();
        editor.classList.add('rich-editor--error');
        editor.focus();
        alert('Введите текст события.');
      }
    });
  };

  document.querySelectorAll('.js-rich').forEach(makeRich);

  // Обычные поля (названия, подписи, комментарии) не являются rich-редакторами,
  // но им тоже нужен нормальный, одинаковый выбор эмодзи.
  const plainEmojiPicker = document.createElement('div');
  plainEmojiPicker.className = 'plain-emoji-picker';
  plainEmojiPicker.hidden = true;
  plainEmojiPicker.innerHTML = '<button type="button" class="plain-emoji-picker__toggle" aria-label="Выбрать эмодзи" title="Выбрать эмодзи"><img src="/assets/telegram-emoji/0_11.png" alt=""></button><section class="plain-emoji-picker__panel" hidden aria-label="Выбор эмодзи"><div class="plain-emoji-picker__tabs"><button type="button" data-plain-emoji-tab="telegram">Telegram</button><button type="button" data-plain-emoji-tab="group">ПН</button><button type="button" data-plain-emoji-tab="services">Сервисы</button></div><div class="plain-emoji-picker__caption"></div><div class="plain-emoji-picker__items"></div><button type="button" class="plain-emoji-picker__more" hidden>Показать ещё</button></section>';
  document.body.append(plainEmojiPicker);
  const plainEmojiToggle = plainEmojiPicker.querySelector('.plain-emoji-picker__toggle');
  const plainEmojiPanel = plainEmojiPicker.querySelector('.plain-emoji-picker__panel');
  const plainEmojiItems = plainEmojiPicker.querySelector('.plain-emoji-picker__items');
  const plainEmojiCaption = plainEmojiPicker.querySelector('.plain-emoji-picker__caption');
  const plainEmojiMore = plainEmojiPicker.querySelector('.plain-emoji-picker__more');
  let plainEmojiTarget = null;
  let plainEmojiTab = 'telegram';
  let plainEmojiOffset = 0;

  const isPlainEmojiField = (element) => {
    if (!(element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement)) return false;
    if (element.disabled || element.readOnly || element.classList.contains('js-rich')) return false;
    if (element instanceof HTMLTextAreaElement) return true;
    return ['','text','search'].includes((element.type || '').toLowerCase());
  };
  const movePlainEmojiPicker = () => {
    if (!plainEmojiTarget || !document.contains(plainEmojiTarget)) return;
    const box = plainEmojiTarget.getBoundingClientRect();
    plainEmojiPicker.hidden = false;
    plainEmojiPicker.style.top = `${Math.max(8, Math.min(window.innerHeight - 42, box.top + 7))}px`;
    plainEmojiPicker.style.left = `${Math.max(8, Math.min(window.innerWidth - 42, box.right - 41))}px`;
  };
  const closePlainEmojiPicker = () => {
    plainEmojiPanel.hidden = true;
    plainEmojiPicker.hidden = true;
    plainEmojiTarget = null;
  };
  const insertPlainEmoji = (value) => {
    if (!plainEmojiTarget) return;
    const target = plainEmojiTarget;
    const start = Number.isInteger(target.selectionStart) ? target.selectionStart : target.value.length;
    const end = Number.isInteger(target.selectionEnd) ? target.selectionEnd : start;
    target.setRangeText(value, start, end, 'end');
    target.dispatchEvent(new Event('input', {bubbles:true}));
    target.dispatchEvent(new Event('change', {bubbles:true}));
    target.focus();
    movePlainEmojiPicker();
  };
  const plainEmojiButton = (title, image, value, text = '') => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'plain-emoji-picker__item';
    button.title = title;
    button.setAttribute('aria-label', title);
    if (image) { const picture = document.createElement('img'); picture.src = image; picture.alt = ''; picture.loading = 'lazy'; button.append(picture); }
    else button.textContent = text;
    button.addEventListener('mousedown', (event) => event.preventDefault());
    button.addEventListener('click', () => insertPlainEmoji(value));
    return button;
  };
  const renderPlainEmoji = (append = false) => {
    // Только Telegram подгружается порциями. Для вкладок ПН и «Сервисы»
    // всегда очищаем контейнер: иначе один клик мог размножить значки.
    const mayAppend = append && plainEmojiTab === 'telegram';
    if (!mayAppend) { plainEmojiOffset = 0; plainEmojiItems.replaceChildren(); }
    plainEmojiPicker.querySelectorAll('[data-plain-emoji-tab]').forEach((button) => button.classList.toggle('is-active', button.dataset.plainEmojiTab === plainEmojiTab));
    if (plainEmojiTab === 'telegram') {
      plainEmojiCaption.textContent = 'Стандартные эмодзи Telegram: жёлтый цвет кожи, без профессий.';
      emojis.slice(plainEmojiOffset, plainEmojiOffset + 160).forEach(({emoji,file}) => {
        plainEmojiItems.append(plainEmojiButton(`Вставить ${emoji}`, `/assets/telegram-emoji/${encodeURIComponent(file)}`, emoji));
      });
      plainEmojiOffset += 160;
      plainEmojiMore.hidden = plainEmojiOffset >= emojis.length;
    } else if (plainEmojiTab === 'group') {
      plainEmojiCaption.textContent = 'Групповые эмодзи ПН.';
      groupEmojis.forEach(({file,title}) => {
        const source = `/assets/group-emoji/${file.split('/').map(encodeURIComponent).join('/')}`;
        plainEmojiItems.append(plainEmojiButton(`Вставить групповой эмодзи ${title || file}`, source, `[[icon:group:${encodeURIComponent(file)}]]`));
      });
      plainEmojiMore.hidden = true;
    } else {
      plainEmojiCaption.textContent = 'Значки сервисов: они сохранятся как настоящие иконки на сайте.';
      serviceIcons.forEach((service) => plainEmojiItems.append(plainEmojiButton(`Вставить ${service.label}`, service.image, `[[icon:${service.id}]]`)));
      plainEmojiMore.hidden = true;
    }
  };
  plainEmojiToggle.addEventListener('mousedown', (event) => event.preventDefault());
  plainEmojiToggle.addEventListener('click', (event) => {
    event.stopPropagation();
    if (!plainEmojiTarget) return;
    plainEmojiPanel.hidden = !plainEmojiPanel.hidden;
    if (!plainEmojiPanel.hidden) renderPlainEmoji();
  });
  plainEmojiPicker.addEventListener('click', (event) => {
    const tab = event.target.closest('[data-plain-emoji-tab]');
    if (tab) { event.preventDefault(); event.stopPropagation(); plainEmojiTab = tab.dataset.plainEmojiTab; renderPlainEmoji(); return; }
    if (event.target.closest('.plain-emoji-picker__more')) renderPlainEmoji(true);
  });
  document.addEventListener('focusin', (event) => {
    if (!isPlainEmojiField(event.target)) return;
    plainEmojiTarget = event.target;
    movePlainEmojiPicker();
  });
  window.addEventListener('resize', movePlainEmojiPicker);
  window.addEventListener('scroll', movePlainEmojiPicker, true);
  document.addEventListener('pointerdown', (event) => {
    if (!plainEmojiTarget) return;
    if (plainEmojiPicker.contains(event.target) || event.target === plainEmojiTarget) return;
    closePlainEmojiPicker();
  });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closePlainEmojiPicker(); });

  document.addEventListener('click', (event) => {
    document.querySelectorAll('.editor-menu[open]').forEach((menu) => {
      if (!menu.contains(event.target)) menu.open = false;
    });
    const move = event.target.closest('[data-move]');
    if (move) {
      const row = move.closest('.repeat-row,.button-editor');
      const sibling = move.dataset.move === 'up' ? row?.previousElementSibling : row?.nextElementSibling;
      if (row && sibling && sibling.parentElement === row.parentElement) {
        markDirty(move.closest('form'));
        if (move.dataset.move === 'up') row.parentElement.insertBefore(row, sibling);
        else row.parentElement.insertBefore(sibling, row);
      }
      return;
    }
    const remove = event.target.closest('.remove-row');
    if (remove) {
      markDirty(remove.closest('form'));
      remove.closest('.repeat-row,.button-editor,.card-editor,.service-editor,.holder-row')?.remove();
      renumberServices();
      renumberNavigation();
      return;
    }
    const add = event.target.closest('[data-add-row]');
    if (add) {
      markDirty(add.closest('form'));
      const box = add.closest('[data-repeat]');
      const template = box?.querySelector(':scope > template');
      const items = box?.querySelector(':scope > [data-repeat-items]');
      if (template && items) {
        items.insertAdjacentHTML('beforeend', template.innerHTML);
        items.querySelectorAll('.js-rich').forEach((textarea) => { if (textarea.dataset.richReady !== 'true') makeRich(textarea); });
        items.querySelectorAll('.button-editor').forEach(updateButtonPreview);
        renumberServices();
        renumberNavigation();
      }
      return;
    }
    const holder = event.target.closest('[data-add-holder]');
    if (holder) {
      markDirty(holder.closest('form'));
      const service = holder.closest('[data-service]');
      const index = [...document.querySelectorAll('[data-service]')].indexOf(service);
      const list = service.querySelector('[data-holders]');
      list.insertAdjacentHTML('beforeend', `<div class="holder-row"><input name="holder_name[${index}][]" placeholder="Имя"><input type="date" name="holder_rotation[${index}][]"><button type="button" class="remove-row">Убрать</button></div>`);
    }
  });

  function renumberServices() {
    document.querySelectorAll('[data-service]').forEach((service, index) => {
      service.querySelectorAll('input[type=checkbox]').forEach((input) => { input.name = `service_open[${index}]`; });
      service.querySelectorAll('[data-holders] .holder-row').forEach((row) => {
        const [name, date] = row.querySelectorAll('input');
        if (name) name.name = `holder_name[${index}][]`;
        if (date) date.name = `holder_rotation[${index}][]`;
      });
      service.querySelectorAll('input[name^="service_action_"]').forEach((input) => {
        input.name = input.name.replace(/^(service_action_(?:label|url|type|color|shape|align))\[\d+\]\[\]$/, `$1[${index}][]`);
      });
    });
  }

  function renumberNavigation() {
    document.querySelectorAll('[data-nav-item]').forEach((item, index) => {
      const checkbox = item.querySelector('input[type=checkbox]');
      if (checkbox) checkbox.name = `nav_external[${index}]`;
    });
  }

  document.addEventListener('change', async (event) => {
    const input = event.target.closest?.('input[type="file"][accept*="image"]:not([data-skip-optimize])');
    if (!input?.files?.length) return;
    const optimized = await Promise.all([...input.files].map(optimizeImage));
    const transfer = new DataTransfer(); optimized.forEach((file) => transfer.items.add(file)); input.files = transfer.files;
  });
  function updateButtonPreview(editor) {
    const previews = editor.querySelectorAll('.button-preview');
    if (!previews.length) return;
    const value = (part, fallback) => editor.querySelector(`[data-button-option="${part}"]`)?.value || fallback;
    const label = editor.querySelector('[data-action-label], input[name$="_label[]"]')?.value.trim();
    const text = label || 'Пример кнопки';
    previews.forEach((preview) => {
      const isSummary = preview.classList.contains('button-preview--summary');
      preview.className = `button-preview${isSummary ? ' button-preview--summary' : ''} button-preview--${value('type','primary')} button-preview--${value('color','orange')} button-preview--${value('shape','rounded')} button-preview--${value('align','left')}`;
      preview.textContent = text;
    });
    const summaryLabel = editor.querySelector('.button-editor__summary-label');
    if (summaryLabel) summaryLabel.textContent = text;
  }
  document.addEventListener('click', (event) => {
    const buttonEditorToggle = event.target.closest?.('[data-button-editor-toggle]');
    if (buttonEditorToggle) {
      const editor = buttonEditorToggle.closest('.button-editor');
      const open = !editor.classList.contains('is-mobile-open');
      editor.classList.toggle('is-mobile-open', open);
      buttonEditorToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      return;
    }
    const choice = event.target.closest?.('.button-choice');
    if (!choice) return;
    const option = choice.closest('.button-option');
    const input = option?.querySelector('[data-button-option]');
    if (!input) return;
    input.value = choice.dataset.buttonValue || '';
    option.querySelectorAll('.button-choice').forEach((button) => {
      const selected = button === choice;
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
    updateButtonPreview(choice.closest('.button-editor'));
    markDirty(choice.closest('form'));
  });
  document.addEventListener('input', (event) => { const editor = event.target.closest?.('.button-editor'); if (editor) updateButtonPreview(editor); });
  document.addEventListener('change', (event) => { const editor = event.target.closest?.('.button-editor'); if (editor) updateButtonPreview(editor); });
  document.addEventListener('change', (event) => {
    const destination = event.target.closest?.('[data-action-destination]');
    if (!destination?.value) return;
    const editor = destination.closest('.button-editor');
    const url = editor?.querySelector('[data-action-url]');
    if (url) {
      url.value = destination.value;
      updateButtonPreview(editor);
    }
  });
  document.querySelectorAll('.button-editor').forEach(updateButtonPreview);
  renumberServices();
  renumberNavigation();

  // Проводник структуры: сворачивается только выбранная ветка, как в файловом менеджере.
  document.addEventListener('click', (event) => {
    const toggle = event.target.closest?.('[data-tree-toggle]');
    if (!toggle) return;
    const item = toggle.closest('[data-tree-item]');
    const children = item?.querySelector(':scope > [data-tree-children]');
    if (!item || !children) return;
    const expanded = toggle.getAttribute('aria-expanded') !== 'false';
    item.classList.toggle('is-collapsed', expanded);
    toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    toggle.setAttribute('aria-label', expanded ? 'Развернуть ветку' : 'Свернуть ветку');
  });

  // На телефоне админка — рабочий экран, а не уменьшенная фотография десктопа.
  const smallScreen = window.matchMedia('(max-width: 760px)');
  const adminNav = document.getElementById('admin-nav');
  const adminMenuToggles = document.querySelectorAll('[data-admin-menu-toggle]');
  const setAdminMenu = (open) => {
    if (!adminNav || !smallScreen.matches) return;
    adminNav.classList.toggle('is-open', open);
    document.body.classList.toggle('admin-menu-open', open);
    adminMenuToggles.forEach((button) => button.setAttribute('aria-expanded', open ? 'true' : 'false'));
  };
  document.addEventListener('click', (event) => {
    if (event.target.closest?.('[data-admin-menu-toggle]')) { setAdminMenu(true); return; }
    if (event.target.closest?.('[data-admin-menu-close]')) { setAdminMenu(false); return; }
    if (smallScreen.matches && event.target.closest?.('#admin-nav a')) setAdminMenu(false);
  });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setAdminMenu(false); });
  smallScreen.addEventListener?.('change', () => setAdminMenu(false));

  // Keep each page's headings and permanent labels next to its editor.
  const pageTexts = document.querySelector('.admin-page-texts');
  const pageHeading = document.querySelector('.admin-main h1');
  if (pageTexts && pageHeading) (document.querySelector('.admin-page-links') || pageHeading.closest('.section-heading') || pageHeading).after(pageTexts);

  // Одна заметная кнопка сохранения снизу, когда пальцем уже далеко от верха формы.
  const editableForms = [...document.querySelectorAll('form.admin-form')];
  let dirtyForm = null;
  const mobileSaveBar = document.createElement('div');
  mobileSaveBar.className = 'admin-mobile-save';
  mobileSaveBar.hidden = true;
  mobileSaveBar.innerHTML = '<span>Есть несохранённые изменения</span><button type="button" class="button">Сохранить</button>';
  document.body.append(mobileSaveBar);
  const refreshMobileSave = () => {
    const visible = Boolean(dirtyForm && smallScreen.matches);
    mobileSaveBar.hidden = !visible;
    document.body.classList.toggle('admin-has-pending-save', visible);
  };
  const markDirty = (form) => {
    if (!form?.classList.contains('admin-form')) return;
    dirtyForm = form;
    form.dataset.dirty = 'true';
    refreshMobileSave();
  };
  document.addEventListener('input', (event) => markDirty(event.target.closest?.('form')));
  document.addEventListener('change', (event) => markDirty(event.target.closest?.('form')));
  editableForms.forEach((form) => form.addEventListener('submit', () => {
    form.dataset.dirty = 'false';
    if (dirtyForm === form) dirtyForm = null;
    refreshMobileSave();
  }));
  mobileSaveBar.querySelector('button')?.addEventListener('click', () => dirtyForm?.requestSubmit());
  document.addEventListener('click', (event) => {
    const formControl = event.target.closest?.('[data-move],[data-add-row],.remove-row,[data-add-holder]');
    if (formControl) markDirty(formControl.closest('form'));
    const link = event.target.closest?.('a[href]');
    if (!link || !dirtyForm || !smallScreen.matches || link.target === '_blank') return;
    if (!confirm('Изменения ещё не сохранены. Перейти и потерять их?')) event.preventDefault();
  });
  window.addEventListener('beforeunload', (event) => {
    if (!dirtyForm || !smallScreen.matches) return;
    event.preventDefault();
    event.returnValue = '';
  });
  smallScreen.addEventListener?.('change', refreshMobileSave);

  const backups = document.querySelector('.backups-panel');
  if (backups?.parentElement) backups.parentElement.append(backups);
})();
