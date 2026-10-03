(() => {
  const DISMISSED_AT_KEY = "aa-pn-install-dismissed-at";
  const REMIND_AFTER_MS = 7 * 24 * 60 * 60 * 1000;
  const MANUAL_FALLBACK_DELAY_MS = 1500;
  let deferredInstallPrompt = null;
  let banner = null;

  const isAndroid = /Android/i.test(navigator.userAgent);
  const isYandexBrowser = /YaBrowser|YandexBrowser|YaSearchBrowser|YandexSearch/i.test(navigator.userAgent);
  const isEdgeBrowser = /EdgA|EdgiOS|Edg\//i.test(navigator.userAgent);
  const isStandalone = () =>
    window.matchMedia("(display-mode: standalone)").matches ||
    window.matchMedia("(display-mode: fullscreen)").matches ||
    window.navigator.standalone === true;

  const readDismissedAt = () => {
    try {
      return Number.parseInt(localStorage.getItem(DISMISSED_AT_KEY) || "0", 10) || 0;
    } catch {
      return 0;
    }
  };

  const rememberDismissal = () => {
    try {
      localStorage.setItem(DISMISSED_AT_KEY, String(Date.now()));
    } catch {
      // Installation still works when storage is unavailable.
    }
  };

  const hideBanner = () => {
    banner?.remove();
    banner = null;
  };

  const showBanner = () => {
    if (banner || isStandalone()) return;
    if (Date.now() - readDismissedAt() < REMIND_AFTER_MS) return;

    banner = document.createElement("section");
    banner.className = "pwa-install-banner";
    banner.setAttribute("aria-labelledby", "pwa-install-title");
    banner.setAttribute("aria-describedby", "pwa-install-description");
    banner.setAttribute("aria-live", "polite");
    banner.innerHTML = `
      <img class="pwa-install-banner__icon" src="/icons/icon-192-v3.png" alt="" width="56" height="56" />
      <div class="pwa-install-banner__content">
        <strong id="pwa-install-title" class="pwa-install-banner__title">Установить приложение?</strong>
        <span id="pwa-install-description" class="pwa-install-banner__description">Сайт будет открываться с главного экрана телефона.</span>
      </div>
      <div class="pwa-install-banner__actions">
        <button class="pwa-install-banner__button pwa-install-banner__button--primary" type="button" data-pwa-install>Установить</button>
        <button class="pwa-install-banner__button" type="button" data-pwa-later>Возможно, позже</button>
      </div>
    `;

    banner.querySelector("[data-pwa-later]").addEventListener("click", () => {
      rememberDismissal();
      hideBanner();
    });

    banner.querySelector("[data-pwa-install]").addEventListener("click", async () => {
      if (!deferredInstallPrompt) {
        const instructionsTitle = isYandexBrowser
          ? "Как добавить сайт в Яндекс Браузере"
          : isEdgeBrowser
            ? "Как установить сайт в Microsoft Edge"
            : "Как добавить сайт на главный экран";
        const instructions = isYandexBrowser
          ? `
              <li>Откройте меню браузера <strong>⋮</strong>.</li>
              <li>Нажмите <strong>«Добавить ярлык на рабочий стол»</strong>.</li>
              <li>В появившемся окне нажмите <strong>«Добавить»</strong>.</li>
            `
          : `
              <li>Откройте меню браузера <strong>⋮</strong>.</li>
              <li>Выберите <strong>«Установить приложение»</strong>, <strong>«Добавить на телефон»</strong> или <strong>«Добавить на главный экран»</strong>.</li>
              <li>Подтвердите добавление в появившемся окне.</li>
            `;
        banner.setAttribute("aria-labelledby", "pwa-install-instructions-title");
        banner.setAttribute("aria-describedby", "pwa-install-instructions-list");
        banner.innerHTML = `
          <div class="pwa-install-banner__instructions">
            <strong id="pwa-install-instructions-title" class="pwa-install-banner__title">${instructionsTitle}</strong>
            <ol id="pwa-install-instructions-list" class="pwa-install-banner__steps">
              ${instructions}
            </ol>
            <button class="pwa-install-banner__button pwa-install-banner__button--primary" type="button" data-pwa-understood>Понятно</button>
          </div>
        `;
        banner.querySelector("[data-pwa-understood]").addEventListener("click", () => {
          rememberDismissal();
          hideBanner();
        });
        return;
      }

      const promptEvent = deferredInstallPrompt;
      deferredInstallPrompt = null;
      hideBanner();
      await promptEvent.prompt();
      const choice = await promptEvent.userChoice;
      if (choice.outcome !== "accepted") rememberDismissal();
    });

    document.body.append(banner);
  };

  window.addEventListener("load", () => {
    if ("serviceWorker" in navigator) {
      navigator.serviceWorker.register("/sw.js?v=140").catch(() => {});
    }
    window.setTimeout(showBanner, MANUAL_FALLBACK_DELAY_MS);
  });

  if (!isAndroid || isStandalone()) return;

  window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    showBanner();
  });

  window.addEventListener("appinstalled", () => {
    deferredInstallPrompt = null;
    hideBanner();
    try {
      localStorage.removeItem(DISMISSED_AT_KEY);
    } catch {
      // Nothing else is required after installation.
    }
  });
})();
