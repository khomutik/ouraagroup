(() => {
  "use strict";

  const script = document.currentScript;
  const configuredApiBase = String(script?.dataset.api || "").replace(/\/$/, "");
  // Old cached pages may still name workers.dev. Keep them usable in networks
  // where that domain is unavailable by routing through the website instead.
  const apiBase = /\.workers\.dev$/i.test(configuredApiBase) ? "/support-chat-api" : configuredApiBase;
  if (!apiBase || document.querySelector("[data-site-chat]")) return;

  const STORAGE_KEY = "pn-support-chat-session-v1";
  const MAX_LENGTH = 2000;
  let session = readSession();
  let lastMessageId = 0;
  let pollTimer = 0;
  let pollStartedAt = 0;
  let lastActivityAt = Date.now();
  let requestInFlight = false;
  const renderedIds = new Set();
  const renderedMessageText = new Map();
  const adminEmojiFiles = new Map();
  const graphemeSegmenter = typeof Intl.Segmenter === "function" ? new Intl.Segmenter("ru", { granularity: "grapheme" }) : null;
  const serviceIconFiles = {
    telegram: ["/assets/social-telegram-v2.png", "Telegram"],
    zoom: ["/assets/social-zoom-v2.png", "Zoom"],
    max: ["/assets/social-max-v2.png", "MAX"],
    gmail: ["/assets/service-gmail.svg", "Gmail"],
    whatsapp: ["/assets/service-whatsapp.svg", "WhatsApp"],
    vk: ["/assets/service-vk.svg", "ВКонтакте"],
    youtube: ["/assets/service-youtube.svg", "YouTube"],
    rutube: ["/assets/service-rutube.svg", "RuTube"],
    paypal: ["/assets/service-paypal.svg", "PayPal"],
    sber: ["/assets/service-sber.svg", "Сбер"],
    sbp: ["/assets/service-sbp.svg", "СБП"],
  };
  const adminEmojiReady = fetch("/assets/telegram-emoji/index.json?v=2", { cache: "force-cache" })
    .then((response) => (response.ok ? response.json() : []))
    .then((catalog) => {
      if (Array.isArray(catalog)) {
        for (const item of catalog) {
          if (typeof item?.emoji === "string" && /^[A-Za-z0-9_.-]+\.png$/.test(String(item.file || ""))) {
            adminEmojiFiles.set(item.emoji, "/assets/telegram-emoji/" + encodeURIComponent(item.file));
          }
        }
      }
      for (const [element, payload] of renderedMessageText) renderMessageText(element, payload);
    })
    .catch(() => undefined);

  const make = (tag, className, text) => {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (typeof text === "string") element.textContent = text;
    return element;
  };

  const root = make("div", "site-chat");
  root.dataset.siteChat = "";
  if (document.querySelector(".app-shell")) root.classList.add("site-chat--home");

  const launcher = make("button", "site-chat__launcher");
  launcher.type = "button";
  launcher.setAttribute("aria-expanded", "false");
  launcher.setAttribute("aria-controls", "site-chat-panel");
  launcher.setAttribute("aria-label", "Открыть чат с группой");
  const launcherIcon = make("span", "site-chat__launcher-icon", "💬");
  launcherIcon.setAttribute("aria-hidden", "true");
  const launcherText = make("span", "site-chat__launcher-text", "Задать вопрос");
  launcher.append(launcherIcon, launcherText);

  const panel = make("section", "site-chat__panel");
  panel.id = "site-chat-panel";
  panel.hidden = true;
  panel.setAttribute("role", "dialog");
  panel.setAttribute("aria-modal", "false");
  panel.setAttribute("aria-labelledby", "site-chat-title");

  const header = make("header", "site-chat__header");
  const heading = make("div", "site-chat__heading");
  const title = make("strong", "site-chat__title", "Связь с группой");
  title.id = "site-chat-title";
  const subtitle = make("span", "site-chat__subtitle", "Анонимный чат открыт — можно писать");
  heading.append(title, subtitle);
  const headerActions = make("div", "site-chat__header-actions");
  const hideButton = make("button", "site-chat__hide", "−");
  hideButton.type = "button";
  hideButton.setAttribute("aria-label", "Свернуть чат");
  hideButton.title = "Свернуть окно — диалог останется открыт";
  headerActions.append(hideButton);
  header.append(heading, headerActions);

  const messages = make("div", "site-chat__messages");
  messages.setAttribute("role", "log");
  messages.setAttribute("aria-live", "polite");
  messages.setAttribute("aria-relevant", "additions text");

  const welcome = make(
    "div",
    "site-chat__welcome",
    "Здравствуйте! Здесь можно задать вопрос, касающийся проблем с алкоголем, работы нашей группы АА или Сообщества Анонимных Алкоголиков в целом. Вам ответит трезвый алкоголик - служащий нашей группы. Чат анонимный, представляться не обязательно.",
  );
  messages.append(welcome);

  const closedBox = make("div", "site-chat__closed");
  closedBox.hidden = true;
  const closedText = make("p", "", "Диалог закрыт. Если появился новый вопрос, начните новый чат.");
  const newChatButton = make("button", "site-chat__new", "Начать новый чат");
  newChatButton.type = "button";
  closedBox.append(closedText, newChatButton);

  const form = make("form", "site-chat__form");
  const textarea = make("textarea", "site-chat__input");
  textarea.name = "message";
  textarea.rows = 2;
  textarea.maxLength = MAX_LENGTH;
  textarea.placeholder = "Напишите сообщение…";
  textarea.setAttribute("aria-label", "Сообщение");
  const status = make("span", "site-chat__status", "");
  status.setAttribute("role", "status");
  status.hidden = true;
  const formFooter = make("div", "site-chat__form-footer");
  const sendButton = make("button", "site-chat__send", "Отправить");
  sendButton.type = "submit";
  formFooter.append(sendButton);
  form.append(textarea, status, formFooter);

  panel.append(header, messages, closedBox, form);
  root.append(launcher, panel);
  document.body.append(root);

  function readSession() {
    try {
      const value = JSON.parse(localStorage.getItem(STORAGE_KEY) || "null");
      return value && typeof value.token === "string" ? value : null;
    } catch {
      return null;
    }
  }

  function saveSession(value) {
    session = value;
    try {
      if (value) localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
      else localStorage.removeItem(STORAGE_KEY);
    } catch {
      // Чат продолжит работать до закрытия вкладки, даже если хранилище запрещено.
    }
  }

  function setStatus(text, isError = false) {
    status.textContent = text;
    status.hidden = !text;
    status.classList.toggle("site-chat__status--error", isError);
  }

  function scrollToLatest() {
    messages.scrollTop = messages.scrollHeight;
  }

  function appendInlineImage(container, source, alternative, fallback, useCors = false) {
    const image = make("img", "site-chat__custom-emoji");
    if (useCors) {
      image.crossOrigin = "anonymous";
      image.referrerPolicy = "no-referrer";
    }
    image.src = source;
    image.alt = alternative;
    image.loading = "lazy";
    image.decoding = "async";
    image.addEventListener(
      "error",
      () => image.replaceWith(document.createTextNode(fallback)),
      { once: true },
    );
    container.append(image);
    return image;
  }

  function appendCatalogEmojiText(container, value) {
    const parts = graphemeSegmenter
      ? Array.from(graphemeSegmenter.segment(value), (part) => part.segment)
      : Array.from(value);
    let plain = "";
    const flush = () => {
      if (plain) container.append(document.createTextNode(plain));
      plain = "";
    };
    for (const part of parts) {
      const source = adminEmojiFiles.get(part);
      if (!source) {
        plain += part;
        continue;
      }
      flush();
      appendInlineImage(container, source, part, part);
    }
    flush();
  }

  function iconToken(value) {
    let key;
    try {
      key = decodeURIComponent(value);
    } catch {
      return null;
    }
    if (serviceIconFiles[key]) return serviceIconFiles[key];
    const group = key.match(/^group:([A-Za-z0-9_.-]+\.png)$/);
    return group ? ["/assets/group-emoji/" + encodeURIComponent(group[1]), ""] : null;
  }

  function appendAdminEmojiText(container, value) {
    const tokenPattern = /\[\[icon:([^\]]+)\]\]/giu;
    let cursor = 0;
    for (const match of value.matchAll(tokenPattern)) {
      const start = match.index ?? 0;
      appendCatalogEmojiText(container, value.slice(cursor, start));
      const icon = iconToken(match[1]);
      if (icon) appendInlineImage(container, icon[0], icon[1], match[0]);
      else appendCatalogEmojiText(container, match[0]);
      cursor = start + match[0].length;
    }
    appendCatalogEmojiText(container, value.slice(cursor));
  }

  function appendLinkedTextSegment(container, text) {
    const urlPattern = /https?:\/\/[^\s<>"']+/giu;
    let cursor = 0;
    for (const match of text.matchAll(urlPattern)) {
      const start = match.index ?? 0;
      if (start > cursor) appendAdminEmojiText(container, text.slice(cursor, start));
      let href = match[0];
      while (/[),.!?;:]$/.test(href)) href = href.slice(0, -1);
      const trailing = match[0].slice(href.length);
      const link = make("a", "site-chat__link", href);
      link.href = href;
      link.target = "_blank";
      link.rel = "noopener noreferrer";
      container.append(link);
      if (trailing) appendAdminEmojiText(container, trailing);
      cursor = start + match[0].length;
    }
    if (cursor < text.length) appendAdminEmojiText(container, text.slice(cursor));
  }

  function appendLinkedText(container, value, entities = []) {
    const text = String(value || "");
    const customEmojis = Array.isArray(entities)
      ? entities
          .filter(
            (entity) =>
              entity?.type === "custom_emoji" &&
              Number.isInteger(entity.offset) &&
              Number.isInteger(entity.length) &&
              entity.offset >= 0 &&
              entity.length > 0 &&
              /^\d{5,30}$/.test(String(entity.customEmojiId || "")),
          )
          .sort((left, right) => left.offset - right.offset)
      : [];
    let cursor = 0;
    for (const entity of customEmojis) {
      if (entity.offset < cursor || entity.offset + entity.length > text.length) continue;
      appendLinkedTextSegment(container, text.slice(cursor, entity.offset));
      const fallback = text.slice(entity.offset, entity.offset + entity.length);
      appendInlineImage(
        container,
        apiBase + "/api/chat/custom-emoji/" + encodeURIComponent(entity.customEmojiId),
        fallback,
        fallback,
        true,
      );
      cursor = entity.offset + entity.length;
    }
    appendLinkedTextSegment(container, text.slice(cursor));
  }

  function renderMessageText(element, payload) {
    element.replaceChildren();
    appendLinkedText(element, payload.text, payload.entities);
  }

  function addMessage(data, options = {}) {
    if (data.id && renderedIds.has(data.id)) return null;
    if (data.id) {
      renderedIds.add(data.id);
      lastMessageId = Math.max(lastMessageId, Number(data.id) || 0);
    }
    const row = make("div", "site-chat__message site-chat__message--" + data.direction);
    const author =
      data.direction === "visitor" ? "Вы" : data.direction === "operator" ? "Служащий группы" : "Сообщение";
    const text = make("p", "site-chat__text");
    const textPayload = { text: data.text, entities: data.entities };
    renderedMessageText.set(text, textPayload);
    renderMessageText(text, textPayload);
    row.append(make("strong", "site-chat__author", author), text);
    if (options.pending) row.classList.add("site-chat__message--pending");
    if (options.failed) {
      row.classList.add("site-chat__message--failed");
      const retry = make("button", "site-chat__retry", "Повторить отправку");
      retry.type = "button";
      retry.addEventListener("click", () => sendPending(row, options.pendingMessage));
      row.append(retry);
    }
    messages.append(row);
    lastActivityAt = Date.now();
    scrollToLatest();
    return row;
  }

  function setClosed(closed) {
    closedBox.hidden = !closed;
    form.hidden = closed;
    subtitle.textContent = closed ? "Диалог закрыт" : "Анонимный чат открыт — можно писать";
    if (closed) stopPolling();
  }

  async function api(path, options = {}) {
    const headers = { Accept: "application/json", ...(options.headers || {}) };
    if (options.body) headers["Content-Type"] = "application/json";
    if (session?.token) headers.Authorization = "Bearer " + session.token;
    let response;
    try {
      response = await fetch(apiBase + path, { ...options, headers, cache: "no-store" });
    } catch {
      throw { message: "Нет связи с сервером. Проверьте интернет и повторите.", retryable: true };
    }
    let payload = null;
    try {
      payload = await response.json();
    } catch {
      // Ниже будет общее сообщение без технической тарабарщины.
    }
    if (!response.ok || !payload?.ok) {
      const error = payload?.error || {};
      throw {
        status: response.status,
        code: error.code,
        message: error.message || "Чат временно недоступен. Попробуйте позже.",
        retryable: Boolean(error.retryable || response.status >= 500),
      };
    }
    return payload;
  }

  async function ensureSession() {
    if (session?.token) return;
    const payload = await api("/api/chat/session", {
      method: "POST",
      body: JSON.stringify({ pagePath: location.pathname + location.search }),
    });
    saveSession(payload.session);
  }

  function makeClientMessageId() {
    if (crypto.randomUUID) return crypto.randomUUID();
    const bytes = new Uint8Array(18);
    crypto.getRandomValues(bytes);
    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, "0")).join("");
  }

  async function sendPending(row, pendingMessage) {
    if (!pendingMessage || row.dataset.sending === "true") return;
    row.dataset.sending = "true";
    row.classList.add("site-chat__message--pending");
    row.classList.remove("site-chat__message--failed");
    row.querySelector(".site-chat__retry")?.remove();
    sendButton.disabled = true;
    setStatus("Отправляем…");
    try {
      await ensureSession();
      const payload = await api("/api/chat/messages", {
        method: "POST",
        body: JSON.stringify(pendingMessage),
      });
      row.classList.remove("site-chat__message--pending");
      row.dataset.sending = "false";
      if (payload.message?.id) {
        renderedIds.add(payload.message.id);
        lastMessageId = Math.max(lastMessageId, Number(payload.message.id) || 0);
      }
      setStatus("Сообщение доставлено");
      schedulePoll(1200);
    } catch (error) {
      row.dataset.sending = "false";
      row.classList.remove("site-chat__message--pending");
      row.classList.add("site-chat__message--failed");
      const retry = make("button", "site-chat__retry", "Повторить отправку");
      retry.type = "button";
      retry.addEventListener("click", () => sendPending(row, pendingMessage));
      row.append(retry);
      setStatus(error?.message || "Не удалось отправить сообщение.", true);
      if (error?.status === 401 || error?.code === "invalid_session") saveSession(null);
      if (error?.code === "chat_closed") setClosed(true);
    } finally {
      sendButton.disabled = false;
    }
  }

  async function submitMessage() {
    const text = textarea.value.trim();
    if (!text) {
      setStatus("Сначала напишите сообщение.", true);
      textarea.focus();
      return;
    }
    const pendingMessage = { text, clientMessageId: makeClientMessageId() };
    textarea.value = "";
    const row = addMessage({ direction: "visitor", text }, { pending: true, pendingMessage });
    await sendPending(row, pendingMessage);
  }

  async function loadMessages() {
    if (!session?.token || requestInFlight || panel.hidden || document.visibilityState !== "visible") return;
    requestInFlight = true;
    try {
      const payload = await api("/api/chat/messages?after=" + lastMessageId);
      for (const message of payload.messages || []) addMessage(message);
      setClosed(payload.status === "closed");
      if (payload.status === "open") setStatus("");
    } catch (error) {
      if (error?.status === 401 || error?.code === "invalid_session") {
        saveSession(null);
        setClosed(false);
        addMessage({ direction: "system", text: "Предыдущий диалог уже удалён. Можно начать новый." });
      } else {
        setStatus(error?.message || "Не удалось проверить новые ответы.", true);
      }
    } finally {
      requestInFlight = false;
      schedulePoll();
    }
  }

  function pollingDelay() {
    const openFor = Date.now() - pollStartedAt;
    const idleFor = Date.now() - lastActivityAt;
    if (openFor < 60_000 || idleFor < 60_000) return 3000;
    if (openFor < 5 * 60_000 || idleFor < 5 * 60_000) return 10_000;
    return 30_000;
  }

  function schedulePoll(delay = pollingDelay()) {
    stopPolling();
    if (panel.hidden || document.visibilityState !== "visible" || !session?.token || form.hidden) return;
    pollTimer = window.setTimeout(loadMessages, delay);
  }

  function stopPolling() {
    if (pollTimer) window.clearTimeout(pollTimer);
    pollTimer = 0;
  }

  function openPanel() {
    window.dispatchEvent(new Event("pn:chat-open"));
    panel.hidden = false;
    launcher.setAttribute("aria-expanded", "true");
    launcher.setAttribute("aria-label", "Свернуть чат");
    pollStartedAt = Date.now();
    lastActivityAt = Date.now();
    if (session?.token) loadMessages();
    window.setTimeout(() => textarea.focus(), 0);
  }

  function hidePanel() {
    panel.hidden = true;
    launcher.setAttribute("aria-expanded", "false");
    launcher.setAttribute("aria-label", "Открыть чат с группой");
    stopPolling();
    launcher.focus();
  }

  launcher.addEventListener("click", () => (panel.hidden ? openPanel() : hidePanel()));
  window.addEventListener("pn:search-open", () => { if (!panel.hidden) hidePanel(); });
  hideButton.addEventListener("click", hidePanel);
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    void submitMessage();
  });
  textarea.addEventListener("keydown", (event) => {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();
      form.requestSubmit();
    }
  });
  textarea.addEventListener("input", () => {
    const remaining = MAX_LENGTH - Array.from(textarea.value).length;
    setStatus(remaining < 200 ? "Осталось символов: " + Math.max(0, remaining) : "");
  });
  newChatButton.addEventListener("click", () => {
    saveSession(null);
    lastMessageId = 0;
    renderedIds.clear();
    renderedMessageText.clear();
    messages.replaceChildren(welcome);
    setClosed(false);
    setStatus("");
    textarea.focus();
  });
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible" && !panel.hidden) loadMessages();
    else stopPolling();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !panel.hidden) hidePanel();
  });

  setClosed(false);
})();
