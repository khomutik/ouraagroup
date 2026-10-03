(async () => {
  const shell = document.querySelector(".page-shell");
  if (!shell) return;

  const speakersUrl = "https://drive.google.com/drive/folders/1x-bKBZzLpj1uTAnJpWqVFq3JBjXw-su-?usp=sharing";
  const archiveUrl = "archive.html";
  const zoomUrl = "https://us06web.zoom.us/j/5487249245?pwd=UE3buqca6pTDt8kGPJDW9pRoaC7gkt.1";
  const telegramUrl = "https://telegram.me/+mta_CKQY2c05ODRi";
  const maxUrl = "https://max.ru/join/GV-P-08zFtVs6pX-xR5Z8x80MMNPzhjJ1w6JVEYGn9M";
  const escapeHtml = (value) => String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#39;');
  const siteUrl = (value) => /^(?:https?:|tel:|mailto:|#|\/)/i.test(String(value || '')) ? String(value || '') : `/${value}`;

  let navItems = [
    { title: "Главная страница", href: "index.html" },
    {
      title: "Новичкам",
      href: "newcomers.html",
      openOn: "newcomers.html",
      links: [
        { href: "newcomers.html", label: "Новичкам - главное меню раздела" },
        { href: "newcomers.html#aa", label: "Кто такие Анонимные Алкоголики?" },
        { href: "newcomers.html#twelve-steps", label: "Программа «Двенадцать Шагов» АА" },
        { href: "newcomers.html#program-help", label: "Всем ли помогает Программа АА?" },
        { href: "newcomers.html#aa-community", label: "Зачем мне общение с анонимными алкоголиками?" },
        { href: "newcomers.html#sponsor", label: "Кто такой спонсор в АА?" },
        { href: "newcomers.html#sponsor-steps", label: "12 шагов спонсора" },
        { href: "newcomers.html#alcoholism", label: "Немного об алкоголизме" },
        { href: "newcomers.html#alcoholism-learned", label: "Что мы узнали об алкоголизме?" },
        { href: "newcomers.html#alcoholism-disease", label: "Алкоголизм - это болезнь" },
        { href: "newcomers.html#meetings", label: "Собрания АА" },
        { href: "newcomers.html#meeting-process", label: "Что происходит на собраниях Анонимных Алкоголиков?" },
        { href: "newcomers.html#pn-meetings", label: "Как проходят собрания в группе «Почти нормальные»?" },
        { href: "newcomers.html#recommendations", label: "Практические рекомендации" },
        { href: "newcomers.html#today-only", label: "Принцип «Только сегодня»" }
      ]
    },
    { title: "Расписание собраний", href: "schedule.html" },
    { title: "Объявления", href: "announcements.html" },
    {
      title: "Библиотека",
      href: "library.html",
      openOn: "library.html",
      links: [
        { href: "library.html", label: "Библиотека - книги и брошюры АА" }
      ]
    },
    { title: "Спикерские", href: "speakers.html" },
    {
      title: "Служения",
      href: "service.html",
      openOn: "service.html",
      links: [
        { href: "service.html", label: "Служения - список служений группы" }
      ]
    },
    {
      title: "7-я традиция",
      href: "tradition.html",
      openOn: "tradition.html",
      links: [
        { href: "tradition.html", label: "7-я традиция - реквизиты" }
      ]
    },
    { title: "Архив", href: archiveUrl }
  ];

  let socialLinks = [
    {title:'Zoom', url:zoomUrl, icon:'/assets/social-zoom-v2.png'},
    {title:'Telegram', url:telegramUrl, icon:'/assets/social-telegram-v2.png'},
    {title:'MAX', url:maxUrl, icon:'/assets/social-max-v2.png'}
  ];
  try {
    const response = await fetch('/cms-navigation.json', {cache:'no-store'});
    if (response.ok) {
      const config = await response.json();
      const hiddenMenuLabels = new Set(["Другая литература", "График служений", "Отчет казначея", "Отчёт казначея"]);
      const rows = (Array.isArray(config.items) ? config.items : [])
        .filter((row) => !hiddenMenuLabels.has(String(row.title || "")))
        .map((row) => ({...row,url:siteUrl(row.url),icon:row.icon ? siteUrl(row.icon) : ''}));
      const roots = rows.filter((row) => !row.parent);
      navItems = roots.map((row) => {
        const children = rows.filter((child) => child.parent === row.id || child.parent === row.title).map((child) => ({href:child.url,label:child.title,external:!!child.external}));
        const base = {title:row.title,href:row.url,external:!!row.external};
        if (children.length) return {...base,openOn:String(row.url).split('/').pop().split('#')[0],links:[{href:row.url,label:row.title,external:!!row.external},...children]};
        return base;
      });
      if (Array.isArray(config.socials) && config.socials.length) socialLinks = config.socials.map((row) => ({...row,url:siteUrl(row.url),icon:row.icon ? siteUrl(row.icon) : ''}));
    }
  } catch (_) { /* Keep the built-in menu when the CMS is temporarily unavailable. */ }

  const currentPage = location.pathname.split("/").pop() || "index.html";
  const currentPath = location.pathname === "/" ? "/index.html" : location.pathname;

  const isActive = (href) => {
    const target = new URL(siteUrl(href), location.origin);
    const targetPath = target.pathname === "/" ? "/index.html" : target.pathname;
    return target.origin === location.origin && targetPath === currentPath && (!target.hash || target.hash === location.hash);
  };

  const linkHtml = (item, className = "site-nav__link") => {
    const href = siteUrl(item.href);
    const active = !item.external && isActive(href) ? " is-active" : "";
    const current = active ? ' aria-current="page"' : "";
    const target = item.external ? ' target="_blank" rel="noopener"' : "";
    return `<a class="${className}${active}" href="${escapeHtml(href)}"${current}${target}>${escapeHtml(item.label || item.title)}</a>`;
  };

  const navHtml = navItems.map((item) => {
    const divider = String(item.href || '').replace(/^\//, '').split('#')[0] === 'service.html'
      ? '<span class="site-nav__divider" aria-hidden="true"></span>'
      : '';
    if (!item.links) {
      return divider + linkHtml(item, "site-nav__main-link");
    }

    const isOpen = item.openOn === currentPage;
    const open = isOpen ? " open" : "";
    const active = !item.external && isActive(item.href) ? " is-active" : "";

    return divider + `
      <details class="site-nav__group"${open}>
        <summary class="site-nav__summary${active}">${escapeHtml(item.title)}</summary>
        <div class="site-nav__links">
          ${item.links.map((link) => linkHtml(link)).join("")}
        </div>
      </details>
    `;
  }).join("");

 const socialHtml = `<div class="site-nav-socials" aria-label="Соцсети и быстрые ссылки">${socialLinks.map((item) => `<a class="site-nav-socials__link" href="${escapeHtml(item.url)}" target="_blank" rel="noopener">${item.icon ? `<img src="${escapeHtml(item.icon)}" alt="" />` : ''}<span>${escapeHtml(item.title)}</span></a>`).join('')}</div>`;
  const adminLinkHtml = `<a class="site-nav__admin-link" href="/admin/" aria-label="Вход в админку">Вход в админку</a>`;

  shell.insertAdjacentHTML("beforeend", `
    <aside class="site-side-nav" aria-label="Меню сайта">
     <p class="site-side-nav__title">Меню</p>
     <nav class="site-nav">${navHtml}</nav>
      ${adminLinkHtml}
     ${socialHtml}
    </aside>

    <nav class="mobile-bottom-nav" aria-label="Навигация">
      <button class="mobile-bottom-nav__item" type="button" data-site-back>
        <span aria-hidden="true">←</span>
        <span>Назад</span>
      </button>
      <button class="mobile-bottom-nav__item" type="button" data-site-menu>
        <span aria-hidden="true">☰</span>
        <span>Меню</span>
      </button>
      <a class="mobile-bottom-nav__item" href="/">
        <span aria-hidden="true">⌂</span>
        <span>Дом</span>
      </a>
    </nav>

    <div class="mobile-menu-panel" data-site-menu-panel hidden>
      <div class="mobile-menu-panel__sheet" role="dialog" aria-modal="true" aria-label="Меню сайта">
       <button class="mobile-menu-panel__close" type="button" data-site-menu-close>Закрыть</button>
       <nav class="site-nav">${navHtml}</nav>
        ${adminLinkHtml}
       ${socialHtml}
      </div>
    </div>
  `);

  const menuPanel = shell.querySelector("[data-site-menu-panel]");
  const openMenu = () => {
    menuPanel.hidden = false;
    document.body.classList.add("has-mobile-menu");
  };
  const closeMenu = () => {
    menuPanel.hidden = true;
    document.body.classList.remove("has-mobile-menu");
  };

  shell.addEventListener("click", (event) => {
    if (event.target.closest("[data-site-menu]")) {
      openMenu();
      return;
    }

    if (event.target.closest("[data-site-menu-close]") || event.target === menuPanel) {
      closeMenu();
      return;
    }

    if (event.target.closest(".mobile-menu-panel .site-nav__link, .mobile-menu-panel .site-nav__main-link")) {
      closeMenu();
      return;
    }

    if (event.target.closest("[data-site-back]")) {
      if (window.handleNewcomersBack?.()) return;
      if (history.length > 1) {
        history.back();
      } else {
        location.href = "/";
      }
    }

  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !menuPanel.hidden) {
      closeMenu();
    }
  });
})();
