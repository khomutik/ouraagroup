(() => {
  const shell = document.querySelector(".page-shell");
  if (!shell) return;

  const serviceSheetUrl = "https://docs.google.com/spreadsheets/d/1VAWzdnevTgTmfyx83wfSig9BW6PfdKK1wZorpW0bIvU/edit?usp=sharing";
  const treasurerReportUrl = "https://docs.google.com/spreadsheets/d/1uFKVQ6Orlz2GMTyIWsB4ux7eDRKejFTQ120p6JowZSE/edit?usp=sharing";
  const speakersUrl = "https://drive.google.com/drive/folders/1x-bKBZzLpj1uTAnJpWqVFq3JBjXw-su-?usp=sharing";
  const archiveUrl = "https://docs.google.com/document/d/14c8l7aYBO2R3Gz-PgVV3y0CCMXS4gBflpFp4pQsn9v8/edit?usp=sharing";

  const navItems = [
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
        { href: "newcomers.html#pn-meetings", label: "Как проходят собрания на группе «Почти нормальные»?" },
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
        { href: "library.html", label: "Библиотека - книги и брошюры АА" },
        { href: "https://drive.google.com/drive/folders/1vKX6abhQRFOKIhHaqXmYcUWgjVcCpmHu?usp=sharing", label: "Другая литература", external: true }
      ]
    },
    { title: "Спикерские", href: speakersUrl, external: true },
    {
      title: "Служения",
      href: "service.html",
      openOn: "service.html",
      links: [
        { href: "service.html", label: "Служения - список служений группы" },
        { href: serviceSheetUrl, label: "График служений", external: true }
      ]
    },
    {
      title: "7-я традиция",
      href: "tradition.html",
      openOn: "tradition.html",
      links: [
        { href: "tradition.html", label: "7-я традиция - реквизиты" },
        { href: treasurerReportUrl, label: "Отчет казначея", external: true }
      ]
    },
    { title: "Архив решений", href: archiveUrl, external: true }
  ];

  const currentPage = location.pathname.split("/").pop() || "index.html";
  const activeHref = `${currentPage}${location.hash}`;

  const isActive = (href) => {
    if (href === activeHref) return true;
    if (!href.includes("#") && href === currentPage) return true;
    return false;
  };

  const linkHtml = (item, className = "site-nav__link") => {
    const active = !item.external && isActive(item.href) ? " is-active" : "";
    const current = active ? ' aria-current="page"' : "";
    const target = item.external ? ' target="_blank" rel="noopener"' : "";
    return `<a class="${className}${active}" href="${item.href}"${current}${target}>${item.label || item.title}</a>`;
  };

  const navHtml = navItems.map((item) => {
    if (!item.links) {
      return linkHtml(item, "site-nav__main-link");
    }

    const isOpen = item.openOn === currentPage;
    const open = isOpen ? " open" : "";
    const active = !item.external && item.href === currentPage ? " is-active" : "";

    return `
      <details class="site-nav__group"${open}>
        <summary class="site-nav__summary${active}">${item.title}</summary>
        <div class="site-nav__links">
          ${item.links.map((link) => linkHtml(link)).join("")}
        </div>
      </details>
    `;
  }).join("");

  shell.insertAdjacentHTML("beforeend", `
    <aside class="site-side-nav" aria-label="Меню сайта">
      <p class="site-side-nav__title">Меню</p>
      <nav class="site-nav">${navHtml}</nav>
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
        <nav class="site-nav">${navHtml}</nav>
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
