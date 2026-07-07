(() => {
  const shell = document.querySelector(".page-shell");
  if (!shell) return;

  const serviceSheetUrl = "https://docs.google.com/spreadsheets/d/1VAWzdnevTgTmfyx83wfSig9BW6PfdKK1wZorpW0bIvU/edit?usp=sharing";
  const treasurerReportUrl = "https://docs.google.com/spreadsheets/d/1uFKVQ6Orlz2GMTyIWsB4ux7eDRKejFTQ120p6JowZSE/edit?usp=sharing";
  const speakersUrl = "https://drive.google.com/drive/folders/1x-bKBZzLpj1uTAnJpWqVFq3JBjXw-su-?usp=sharing";
  const archiveUrl = "https://docs.google.com/document/d/14c8l7aYBO2R3Gz-PgVV3y0CCMXS4gBflpFp4pQsn9v8/edit?usp=sharing";

  const groups = [
    {
      title: "Разделы",
      open: true,
      links: [
        { href: "newcomers.html", label: "Новичкам" },
        { href: "schedule.html", label: "Расписание" },
        { href: "announcements.html", label: "Объявления" },
        { href: "library.html", label: "Библиотека" },
        { href: speakersUrl, label: "Спикерские", external: true },
        { href: "service.html", label: "Служения" },
        { href: "tradition.html", label: "7-я традиция" },
        { href: archiveUrl, label: "Архив решений", external: true }
      ]
    },
    {
      title: "Новичкам",
      openOn: "newcomers.html",
      links: [
        { href: "newcomers.html#aa", label: "Кто такие АА?" },
        { href: "newcomers.html#twelve-steps", label: "12 шагов АА" },
        { href: "newcomers.html#program-help", label: "Всем ли помогает АА?" },
        { href: "newcomers.html#aa-community", label: "Общение с АА" },
        { href: "newcomers.html#sponsor", label: "Кто такой спонсор?" },
        { href: "newcomers.html#sponsor-steps", label: "12 шагов спонсора" },
        { href: "newcomers.html#alcoholism", label: "Немного об алкоголизме" },
        { href: "newcomers.html#alcoholism-learned", label: "Что мы узнали?" },
        { href: "newcomers.html#alcoholism-disease", label: "Алкоголизм - болезнь" },
        { href: "newcomers.html#meetings", label: "Собрания АА" },
        { href: "newcomers.html#meeting-process", label: "Что происходит?" },
        { href: "newcomers.html#pn-meetings", label: "Наши собрания" },
        { href: "newcomers.html#recommendations", label: "Рекомендации" },
        { href: "newcomers.html#today-only", label: "Только сегодня" }
      ]
    },
    {
      title: "Служения",
      open: true,
      openOn: "service.html",
      links: [
        { href: "service.html", label: "Все служения" },
        { href: serviceSheetUrl, label: "График служений", external: true }
      ]
    },
    {
      title: "7-я традиция",
      open: true,
      openOn: "tradition.html",
      links: [
        { href: "tradition.html", label: "Реквизиты" },
        { href: treasurerReportUrl, label: "Отчет казначея", external: true }
      ]
    }
  ];

  const currentPage = location.pathname.split("/").pop() || "index.html";
  const activeHref = `${currentPage}${location.hash}`;

  const isActive = (href) => {
    if (href === activeHref) return true;
    if (!href.includes("#") && href === currentPage) return true;
    return false;
  };

  const linkHtml = (item) => {
    const active = !item.external && isActive(item.href) ? " is-active" : "";
    const current = active ? ' aria-current="page"' : "";
    const target = item.external ? ' target="_blank" rel="noopener"' : "";
    return `<a class="site-nav__link${active}" href="${item.href}"${current}${target}>${item.label}</a>`;
  };

  const groupHtml = groups.map((group) => {
    const isOpen = group.open || group.openOn === currentPage;
    const open = isOpen ? " open" : "";
    return `
      <details class="site-nav__group"${open}>
        <summary class="site-nav__summary">${group.title}</summary>
        <div class="site-nav__links">
          ${group.links.map(linkHtml).join("")}
        </div>
      </details>
    `;
  }).join("");

  shell.insertAdjacentHTML("beforeend", `
    <aside class="site-side-nav" aria-label="Меню сайта">
      <p class="site-side-nav__title">Меню</p>
      <nav class="site-nav">${groupHtml}</nav>
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
        <nav class="site-nav">${groupHtml}</nav>
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

    if (event.target.closest(".mobile-menu-panel .site-nav__link")) {
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
