(() => {
  "use strict";

  const stopWords = new Set(["а", "в", "во", "для", "и", "или", "как", "на", "не", "по", "с", "со", "у", "что", "это"]);
  const normalize = (value) => String(value ?? "").normalize("NFKC").toLocaleLowerCase("ru").replaceAll("ё", "е");
  const aliases = new Map([["зум", "zoom"], ["двенадцать", "12"], ["двенадцати", "12"], ["седьмая", "7"], ["седьмой", "7"], ["седьмую", "7"], ["веда", "ведущего"]]);
  const words = (value) => (normalize(value).replace(/(\d+)[-–](?:й|я|е|го|ый|ой)(?!\p{L})/gu, "$1").match(/[\p{L}\p{N}]+/gu) || [])
    .map((word) => aliases.get(word) || word);
  const queryWords = (query) => words(query).filter((word) => !stopWords.has(word)).slice(0, 8);
  const matchesWord = (word, queryWord) => {
    if (word === queryWord) return true;
    if (queryWord.length < 3 || /^\d+$/u.test(queryWord)) return false;
    const stem = queryWord.slice(0, queryWord.length > 8 ? 7 : queryWord.length > 5 ? 5 : queryWord.length);
    return word.startsWith(stem);
  };
  const closestSpan = (sourceWords, tokens) => {
    if (!sourceWords.length) return Infinity;
    const counts = new Array(tokens.length).fill(0);
    let found = 0;
    let left = 0;
    let best = Infinity;
    for (let right = 0; right < sourceWords.length; right++) {
      tokens.forEach((token, index) => {
        if (matchesWord(sourceWords[right], token) && counts[index]++ === 0) found++;
      });
      while (found === tokens.length) {
        best = Math.min(best, right - left + 1);
        tokens.forEach((token, index) => {
          if (matchesWord(sourceWords[left], token) && --counts[index] === 0) found--;
        });
        left++;
      }
    }
    return best;
  };
  const prepare = (documents) => documents.map((document, index) => ({
    document,
    index,
    title: words(document.title).join(" "),
    keywords: words(document.keywords).join(" "),
    text: words(document.text).join(" "),
    titleWords: words(document.title),
    keywordWords: words(document.keywords),
    textWords: words(document.text),
    dateWords: (() => {
      const date = String(document.date || "").split("-");
      const months = ["", "января", "февраля", "марта", "апреля", "мая", "июня", "июля", "августа", "сентября", "октября", "ноября", "декабря"];
      return date.length === 3 ? [String(Number(date[2])), months[Number(date[1])] || "", date[1], date[0]] : [];
    })(),
    combinedWords: [...words(document.title), ...words(document.keywords), ...words(document.text)],
  }));
  const search = (prepared, query) => {
    const originalTokens = queryWords(query);
    const intents = [
      {prefix: "протокол", category: "Протокол РС"},
      {prefix: "повестк", category: "Повестка РС"},
      {prefix: "спикерск", category: "Спикерские"},
    ];
    const intent = originalTokens.length > 1 ? intents.find((item) => originalTokens.some((token) => token.startsWith(item.prefix))) : null;
    if (!originalTokens.length) return [];
    const ranked = [];
    for (const row of prepared) {
      const tokens = intent && row.document.category === intent.category
        ? originalTokens.filter((token) => !token.startsWith(intent.prefix) && token !== "рс")
        : originalTokens;
      if (!tokens.length) continue;
      const phrase = tokens.join(" ");
      const titleSpan = closestSpan(row.titleWords, tokens);
      const keywordSpan = closestSpan(row.keywordWords, tokens);
      const textSpan = closestSpan(row.textWords, tokens);
      const dateSpan = closestSpan(row.dateWords, tokens);
      const combinedSpan = closestSpan(row.combinedWords, tokens);
      const limit = tokens.some((token) => /^\d+$/u.test(token)) ? (tokens.length <= 2 ? 2 : 8) : 8;
      let score = 0;
      if (titleSpan <= limit) score = 60 - titleSpan + Math.max(0, 20 - row.titleWords.length) + 8 * tokens.filter((token) => row.titleWords.includes(token)).length;
      else if (keywordSpan <= limit) score = 32 - keywordSpan;
      else if (dateSpan <= limit) score = 30 - dateSpan;
      else if (!tokens.some((token) => /^\d+$/u.test(token))
        && tokens.every((token) => [...row.titleWords, ...row.keywordWords].some((word) => matchesWord(word, token)))
        && tokens.some((token) => row.titleWords.some((word) => matchesWord(word, token)))) score = 25;
      else if (textSpan <= limit) score = 14 - textSpan;
      else if (combinedSpan <= limit) score = 8 - combinedSpan;
      else if (row.document.category === "Отчёт казначея"
        && tokens.every((token) => [...row.titleWords, ...row.dateWords, ...row.textWords]
          .some((word) => matchesWord(word, token)))) score = 16;
      else continue;
      if (intent && row.document.category === intent.category) score += 20;
      if (phrase.length > 2 && row.title === phrase) score += 80;
      else if (phrase.length > 2 && row.title.includes(phrase)) score += 45;
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
  const status = make("p", "site-search__status");
  status.setAttribute("role", "status");
  status.hidden = true;
  const results = make("div", "site-search__results");
  panel.append(form, status, results);
  root.append(panel, launcher);
  document.body.append(root);

  let prepared = null;
  let pending = null;
  let currentResults = [];
  const loadIndex = () => {
    if (prepared || pending) return pending;
    pending = fetch("/site-search.json?v=5", {credentials: "omit"})
      .then((response) => { if (!response.ok) throw new Error("Search unavailable"); return response.json(); })
      .then((payload) => {
        if (!Array.isArray(payload.documents)) throw new Error("Invalid search index");
        prepared = prepare(payload.documents.filter((item) => typeof item?.url === "string" && item.url.startsWith("/") && !item.url.startsWith("//")));
        render();
      })
      .catch(() => {
        pending = null;
        if (input.value.trim()) {
          status.hidden = false;
          status.textContent = "Поиск пока недоступен.";
        }
      });
    return pending;
  };

  function render() {
    results.replaceChildren();
    currentResults = [];
    const query = input.value.trim();
    status.hidden = true;
    if (query.length < 2) return;
    if (!prepared) { status.hidden = false; status.textContent = "Ищу…"; return; }
    currentResults = search(prepared, query);
    if (!currentResults.length) { status.hidden = false; status.textContent = "Ничего не найдено."; }
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
