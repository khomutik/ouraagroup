(async () => {
  const shell = document.querySelector(".page-shell");
  if (!shell) return;

  const escapeHtml = (value) => String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#39;');
  const siteUrl = (value) => /^(?:https?:|tel:|mailto:|#|\/)/i.test(String(value || '')) ? String(value || '') : `/${value}`;

  // The live CMS payload is embedded in each page, so the menu does not depend
  // on a second network request. Keep only basic links as an emergency fallback.
  let navItems = [
    {title:'Главная страница',href:'index.html'},
    {title:'Новичкам',href:'newcomers.html'},
    {title:'Расписание собраний',href:'schedule.html'},
    {title:'Объявления',href:'announcements.html'},
    {title:'Библиотека',href:'library.html'},
    {title:'Спикерские',href:'speakers.html'},
    {title:'Служения',href:'service.html'},
    {title:'7-я традиция',href:'tradition.html'},
    {title:'Архив решений',href:'archive.html'}
  ];
  let socialLinks = [];
  try {
    const embedded = document.getElementById('site-navigation-data');
    const config = embedded
      ? JSON.parse(embedded.textContent)
      : await fetch('/cms-navigation.json', {cache:'no-store'}).then((response) => response.ok ? response.json() : null);
    if (config) {
      const hiddenMenuLabels = new Set(["Другая литература", "График служений", "Отчет казначея", "Отчёт казначея"]);
      const rows = (Array.isArray(config.items) ? config.items : [])
        .filter((row) => !hiddenMenuLabels.has(String(row.title || "")))
        .map((row) => ({...row,url:siteUrl(row.url),icon:row.icon ? siteUrl(row.icon) : ''}));
      const roots = rows.filter((row) => !row.parent);
      navItems = roots.map((row) => {
        const children = rows.filter((child) => child.parent === row.id || child.parent === row.title).map((child) => ({href:child.url,label:child.title,external:!!child.external}));
        const base = {title:row.title,href:row.url,external:!!row.external};
        if (children.length) return {...base,openOn:String(row.url).split('/').pop().split('#')[0],links:[...children,{href:row.url,label:`Открыть раздел «${row.title}»`,external:!!row.external}]};
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

    const isOpen = item.openOn === currentPage || item.links.some((link) => isActive(link.href));
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
