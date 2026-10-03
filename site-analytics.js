(() => {
  "use strict";

  // The AA group's public pages are sensitive. Never load a third-party tag
  // until the visitor has explicitly opted in.
  if (location.hostname !== "pochtinormalnye.ru") return;

  const counterId = 113365596;
  const storageKey = "pn-analytics-consent-v1";
  const disableKey = `disableYaCounter${counterId}`;
  let choice = null;

  try {
    choice = localStorage.getItem(storageKey);
  } catch (_) {
    // A private-browsing setting may block storage; ask again next visit.
  }

  window[disableKey] = choice !== "yes";

  function startCounter() {
    if (document.getElementById("pn-metrika-tag")) return;
    window[disableKey] = false;
    window.ym = window.ym || function () {
      (window.ym.a = window.ym.a || []).push(arguments);
    };
    window.ym.l = Date.now();

    const script = document.createElement("script");
    script.id = "pn-metrika-tag";
    script.async = true;
    script.src = `https://mc.yandex.ru/metrika/tag.js?id=${counterId}`;
    document.head.appendChild(script);

    window.ym(counterId, "init", {
      defer: true,
      webvisor: false,
      clickmap: false,
      trackLinks: false,
      accurateTrackBounce: false,
      sendTitle: false,
      trackHash: false
    });
    // Never send URL query parameters or fragments (a shared link may contain
    // private information); only count the public page path.
    window.ym(counterId, "hit", location.origin + location.pathname);
  }

  function saveChoice(value) {
    choice = value;
    try {
      localStorage.setItem(storageKey, value);
    } catch (_) {
      // The current choice still applies to this page.
    }
    window[disableKey] = value !== "yes";
    banner.hidden = true;
    if (value === "yes") startCounter();
  }

  const banner = document.createElement("section");
  banner.className = "pn-analytics-consent";
  banner.setAttribute("aria-label", "Статистика посещений");
  banner.innerHTML = `
    <p>Поможете понять, как находят наш сайт? С вашего согласия мы считаем посещения через Яндекс.Метрику. Запись экрана, кликов, чата и имён отключена. Без согласия сайт работает так же.</p>
    <div class="pn-analytics-consent__actions">
      <button type="button" data-consent="yes">Разрешить статистику</button>
      <button type="button" data-consent="no">Нет, спасибо</button>
    </div>`;
  banner.querySelector('[data-consent="yes"]').addEventListener("click", () => saveChoice("yes"));
  banner.querySelector('[data-consent="no"]').addEventListener("click", () => saveChoice("no"));

  const settings = document.createElement("button");
  settings.type = "button";
  settings.className = "pn-analytics-settings";
  settings.textContent = "Настройки статистики";
  settings.addEventListener("click", () => {
    banner.hidden = false;
    banner.querySelector("button").focus();
  });

  function mount() {
    document.body.appendChild(banner);
    const main = document.querySelector("main");
    (main || document.body).appendChild(settings);
    banner.hidden = choice === "yes" || choice === "no";
    if (choice === "yes") startCounter();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", mount, { once: true });
  } else {
    mount();
  }
})();
