(() => {
  const shell = document.querySelector(".page-shell");
  if (!shell) return;

  const items = [
    { href: "newcomers.html", label: "Новичкам" },
    { href: "schedule.html", label: "Расписание" },
    { href: "announcements.html", label: "Объявления" },
    { href: "library.html", label: "Библиотека" },
    { href: "https://drive.google.com/drive/folders/1x-bKBZzLpj1uTAnJpWqVFq3JBjXw-su-?usp=sharing", label: "Спикерские", external: true },
    { href: "service.html", label: "Служения" },
    { href: "tradition.html", label: "7-я традиция" },
    { href: "https://docs.google.com/document/d/14c8l7aYBO2R3Gz-PgVV3y0CCMXS4gBflpFp4pQsn9v8/edit?usp=sharing", label: "Архив решений", external: true }
  ];

  const currentPage = location.pathname.split("/").pop() || "index.html";
  const linkHtml = items.map((item) => {
    const active = !item.external && item.href === currentPage ? " is-active" : "";
    const current = active ? ' aria-current="page"' : "";
    const target = item.external ? ' target="_blank" rel="noopener"' : "";
    return `<a class="site-nav__link${active}" href="${item.href}"${current}${target}>${item.label}</a>`;
  }).join("");

  shell.insertAdjacentHTML("beforeend", `
    <aside class="site-side-nav" aria-label="Меню сайта">
      <p class="site-side-nav__title">Меню</p>
      <nav class="site-nav">${linkHtml}</nav>
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
      <a class="mobile-bottom-nav__item" href="index.html">
        <span aria-hidden="true">⌂</span>
        <span>Дом</span>
      </a>
    </nav>

    <div class="mobile-menu-panel" data-site-menu-panel hidden>
      <div class="mobile-menu-panel__sheet" role="dialog" aria-modal="true" aria-label="Меню сайта">
        <button class="mobile-menu-panel__close" type="button" data-site-menu-close>Закрыть</button>
        <nav class="site-nav">${linkHtml}</nav>
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

    if (event.target.closest("[data-site-back]")) {
      if (window.handleNewcomersBack?.()) return;
      if (history.length > 1) {
        history.back();
      } else {
        location.href = "index.html";
      }
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !menuPanel.hidden) {
      closeMenu();
    }
  });
})();
