(() => {
  "use strict";

  const stopWords = new Set(["а", "в", "во", "для", "и", "или", "как", "на", "не", "по", "с", "со", "у", "что", "это"]);
  const normalize = (value) => String(value ?? "").normalize("NFKC").toLocaleLowerCase("ru").replaceAll("ё", "е");
  const words = (value) => normalize(value).match(/[\p{L}\p{N}]+/gu) || [];
  const queryWords = (query) => words(query).filter((word) => !stopWords.has(word)).slice(0, 8);
  const matches = (sourceWords, queryWord) => {
    const stem = queryWord.length > 5 ? queryWord.slice(0, 5) : queryWord;
    return sourceWords.some((word) => word === queryWord || (queryWord.length > 4 && word.startsWith(stem)));
  };
  const prepare = (documents) => documents.map((document, index) => ({
    document,
    index,
    title: normalize(document.title),
    keywords: normalize(document.keywords),
    text: normalize(document.text),
    titleWords: words(document.title),
    keywordWords: words(document.keywords),
    textWords: words(document.text),
  }));
  const search = (prepared, query) => {
    const tokens = queryWords(query);
    if (!tokens.length) return [];
    const phrase = normalize(query).trim();
    const ranked = [];
    for (const row of prepared) {
      let score = 0;
      let foundAll = true;
      for (const token of tokens) {
        if (matches(row.titleWords, token)) score += 30;
        else if (matches(row.keywordWords, token)) score += 16;
        else if (matches(row.textWords, token)) score += 3;
        else { foundAll = false; break; }
      }
      if (!foundAll) continue;
      if (phrase.length > 2 && row.title.includes(phrase)) score += 45;
      else if (phrase.length > 2 && row.keywords.includes(phrase)) score += 18;
      else if (phrase.length > 2 && row.text.includes(phrase)) score += 6;
      ranked.push({...row, score});
    }
    ranked.sort((a, b) => b.score - a.score || a.index - b.index);
    return ranked;
  };
  const excerpt = (text, query) => {
    const source = String(text || "");
    if (!source) return "Открыть раздел сайта";
    const lowered = normalize(source);
    const token = queryWords(query).find((word) => lowered.includes(word)) || "";
    const position = token ? lowered.indexOf(token) : 0;
    const start = Math.max(0, position - 58);
    const end = Math.min(source.length, start + 185);
    return (start ? "…" : "") + source.slice(start, end).trim() + (end < source.length ? "…" : "");
  };

  if (typeof module !== "undefined" && module.exports) module.exports = {normalize, prepare, search, excerpt};
  if (typeof document === "undefined" || document.querySelector("[data-site-search]")) return;

  const make = (tag, className, text) => {
    const element = document.createElement(tag);
    element.className = className;
    if (text !== undefined) element.textContent = text;
    return element;
  };
  const root = make("div", "site-search");
  root.dataset.siteSearch = "";
  if (document.querySelector(".app-shell")) root.classList.add("site-search--home");
  const launcher = make("button", "site-search__launcher");
  launcher.type = "button";
  launcher.setAttribute("aria-label", "Поиск по сайту");
  launcher.setAttribute("aria-expanded", "false");
  launcher.setAttribute("aria-controls", "site-search-panel");
  launcher.append(make("span", "site-search__icon"));
  const panel = make("section", "site-search__panel");
  panel.id = "site-search-panel";
  panel.hidden = true;
  panel.setAttribute("aria-label", "Поиск по сайту");
  const form = make("form", "site-search__form");
  form.setAttribute("role", "search");
  const input = make("input", "site-search__input");
  input.type = "search";
  input.name = "q";
  input.placeholder = "Поиск по сайту…";
  input.autocomplete = "off";
  input.setAttribute("aria-label", "Что найти на сайте?");
  const close = make("button", "site-search__close", "×");
  close.type = "button";
  close.setAttribute("aria-label", "Закрыть поиск");
  form.append(input, close);
  const status = make("p", "site-search__status", "Напишите, что хотите найти");
  status.setAttribute("role", "status");
  const results = make("div", "site-search__results");
  const suggestions = make("div", "site-search__suggestions");
  for (const phrase of ["Новичкам", "Расписание", "Тест на алкоголизм", "Спикерские"]) {
    const suggestion = make("button", "site-search__suggestion", phrase);
    suggestion.type = "button";
    suggestion.addEventListener("click", () => { input.value = phrase; render(); input.focus(); });
    suggestions.append(suggestion);
  }
  panel.append(form, status, suggestions, results);
  root.append(panel, launcher);
  document.body.append(root);

  let prepared = null;
  let pending = null;
  let currentResults = [];
  const loadIndex = () => {
    if (prepared || pending) return pending;
    status.textContent = "Загружаю материалы…";
    pending = fetch("/site-search.json", {credentials: "omit"})
      .then((response) => { if (!response.ok) throw new Error("Search unavailable"); return response.json(); })
      .then((payload) => {
        if (!Array.isArray(payload.documents)) throw new Error("Invalid search index");
        prepared = prepare(payload.documents.filter((item) => typeof item?.url === "string" && item.url.startsWith("/") && !item.url.startsWith("//")));
        render();
      })
      .catch(() => {
        pending = null;
        status.textContent = "Поиск пока недоступен. Нажмите на лупу ещё раз, чтобы повторить.";
      });
    return pending;
  };

  function render() {
    results.replaceChildren();
    currentResults = [];
    const query = input.value.trim();
    suggestions.hidden = query.length > 0;
    if (!prepared) return;
    if (query.length < 2) {
      status.textContent = "Напишите хотя бы два символа";
      return;
    }
    currentResults = search(prepared, query);
    status.textContent = currentResults.length
      ? `Найдено: ${currentResults.length}${currentResults.length > 8 ? ". Показаны первые 8 — уточните запрос, если нужно." : ""}`
      : "Ничего не нашлось. Попробуйте другое слово — например, «собрание» или «тест».";
    for (const row of currentResults.slice(0, 8)) {
      const item = row.document;
      const link = make("a", "site-search__result");
      link.href = item.url;
      const meta = make("span", "site-search__result-meta", item.category + (item.date ? " · " + item.date.split("-").reverse().join(".") : ""));
      link.append(meta, make("strong", "site-search__result-title", item.title), make("span", "site-search__result-text", excerpt(item.text, query)));
      results.append(link);
    }
  }

  function hideSearch(restoreFocus = false) {
    panel.hidden = true;
    launcher.setAttribute("aria-expanded", "false");
    if (restoreFocus) launcher.focus();
  }
  function showSearch() {
    window.dispatchEvent(new Event("pn:search-open"));
    panel.hidden = false;
    launcher.setAttribute("aria-expanded", "true");
    input.focus();
    void loadIndex();
    if (prepared) render();
  }
  launcher.addEventListener("click", () => panel.hidden ? showSearch() : hideSearch(true));
  close.addEventListener("click", () => hideSearch(true));
  input.addEventListener("input", render);
  input.addEventListener("keydown", (event) => {
    if (event.key === "ArrowDown" && results.firstElementChild) { event.preventDefault(); results.firstElementChild.focus(); }
  });
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    if (currentResults[0]) location.assign(currentResults[0].document.url);
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !panel.hidden) hideSearch(true);
  });
  document.addEventListener("pointerdown", (event) => {
    if (!panel.hidden && !root.contains(event.target)) hideSearch();
  });
  window.addEventListener("pn:chat-open", () => { if (!panel.hidden) hideSearch(); });
})();
