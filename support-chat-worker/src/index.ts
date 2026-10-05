import {
  constantTimeEqual,
  decryptSecretString,
  encryptSecretString,
  hmacSha256Hex,
  isAllowedOrigin,
  isValidClientMessageId,
  normalizeTelegramCustomEmojiEntities,
  type PublicCustomEmojiEntity,
  randomToken,
  rateLimitAddress,
  sanitizePagePath,
  sha256Hex,
  type TelegramTextEntity,
  validateMessageText,
  visitorCode,
} from "./core";

type RuntimeEnv = Env & {
  TELEGRAM_BOT_TOKEN?: string;
  TELEGRAM_WEBHOOK_SECRET?: string;
  MAX_BOT_TOKEN?: string;
  MAX_WEBHOOK_SECRET?: string;
  MAX_PROXY_SECRET?: string;
  RATE_LIMIT_SECRET?: string;
  TELEGRAM_CHAT_ENCRYPTION_KEY?: string;
};

const MAX_WEBHOOK_URL = "https://pochtinormalnye.ru/support-chat-api/max/webhook";
const MAX_API_PROXY_URL = "https://pochtinormalnye.ru/support-chat-max-api-";

type SessionRow = {
  id: string;
  token_hash: string;
  visitor_code: string;
  page_path: string;
  source: "site" | "max";
  max_user_id: string | null;
  telegram_user_hash: string | null;
  telegram_chat_id_encrypted: string | null;
  telegram_thread_id: number | null;
  status: "open" | "closed";
  created_at: number;
  last_activity_at: number;
  closed_at: number | null;
};

type MessageRow = {
  id: number;
  session_id: string;
  direction: "visitor" | "operator" | "system";
  text: string;
  client_message_id: string | null;
  telegram_message_id: number | null;
  entities_json: string | null;
  delivery_status: "pending" | "delivered" | "failed";
  created_at: number;
};

type TelegramApiResponse<T> = {
  ok: boolean;
  result?: T;
  error_code?: number;
  description?: string;
};

type TelegramMessage = {
  message_id: number;
  message_thread_id?: number;
  text?: string;
  entities?: TelegramTextEntity[];
  chat: { id: number; type: string; title?: string; is_forum?: boolean };
  from?: { id: number; is_bot?: boolean };
};

type TelegramSticker = {
  file_id: string;
  thumbnail?: { file_id: string };
};

type TelegramFile = {
  file_path?: string;
};

type TelegramUpdate = {
  update_id?: number;
  message?: TelegramMessage;
  callback_query?: {
    id: string;
    data?: string;
    from: { id: number; is_bot?: boolean };
    message?: TelegramMessage;
  };
};

type MaxUser = {
  user_id?: string | number;
  is_bot?: boolean;
};

type MaxMessage = {
  sender?: MaxUser;
  recipient?: {
    chat_id?: string | number;
    chat_type?: "chat" | "channel" | "dialog" | string;
    user_id?: string | number | null;
  };
  body?: { mid?: string | number; text?: string | null } | null;
};

type MaxUpdate = {
  update_type?: string;
  timestamp?: number;
  chat_id?: string | number;
  user?: MaxUser;
  message?: MaxMessage;
};

class ApiError extends Error {
  constructor(
    readonly status: number,
    readonly code: string,
    message: string,
    readonly retryable = false,
  ) {
    super(message);
  }
}

class TelegramApiError extends ApiError {
  constructor(
    readonly telegramErrorCode: number | undefined,
    readonly telegramDescription: string,
  ) {
    super(502, "telegram_unavailable", "Telegram временно недоступен.", true);
  }
}

class TelegramTopicMismatchError extends ApiError {
  constructor() {
    super(502, "telegram_topic_mismatch", "Telegram не подтвердил отдельную тему обращения.", true);
  }
}

const JSON_HEADERS = { "Content-Type": "application/json; charset=utf-8" };
const RETENTION_FALLBACK_DAYS = 30;
const MAX_BODY_BYTES = 16 * 1024;
const MAX_TELEGRAM_BODY_BYTES = 256 * 1024;

function nowSeconds(): number {
  return Math.floor(Date.now() / 1000);
}

function positiveInteger(value: string | undefined, fallback: number): number {
  const parsed = Number.parseInt(String(value || ""), 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

function json(payload: unknown, status = 200, extraHeaders: HeadersInit = {}): Response {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { ...JSON_HEADERS, "Cache-Control": "no-store", ...extraHeaders },
  });
}

function corsHeaders(origin: string): Record<string, string> {
  return {
    "Access-Control-Allow-Origin": origin,
    "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
    "Access-Control-Allow-Headers": "Authorization, Content-Type",
    "Access-Control-Max-Age": "86400",
    Vary: "Origin",
  };
}

function isLoopbackHost(hostname: string): boolean {
  return hostname === "localhost" || hostname === "127.0.0.1" || hostname === "[::1]";
}

function requestOriginAllowed(request: Request, env: RuntimeEnv): boolean {
  const origin = request.headers.get("Origin");
  if (isAllowedOrigin(origin, env.ALLOWED_ORIGINS)) return true;
  // Браузер не обязан присылать Origin у безопасного GET-запроса с того же сайта.
  // Такой запрос всё равно требует токен диалога и не может ничего отправить.
  if (!origin) return request.method === "GET";
  try {
    return isLoopbackHost(new URL(request.url).hostname) && isLoopbackHost(new URL(origin).hostname);
  } catch {
    return false;
  }
}

async function readJsonBody<T>(request: Request, maximumBytes: number): Promise<T> {
  const announcedLength = Number.parseInt(request.headers.get("Content-Length") || "0", 10);
  if (announcedLength > maximumBytes) {
    throw new ApiError(413, "request_too_large", "Запрос слишком большой.");
  }
  if (!request.body) throw new ApiError(400, "invalid_json", "Пустой запрос.");

  const reader = request.body.getReader();
  const chunks: Uint8Array[] = [];
  let total = 0;
  while (true) {
    const part = await reader.read();
    if (part.done) break;
    total += part.value.byteLength;
    if (total > maximumBytes) {
      await reader.cancel();
      throw new ApiError(413, "request_too_large", "Запрос слишком большой.");
    }
    chunks.push(part.value);
  }

  const body = new Uint8Array(total);
  let offset = 0;
  for (const chunk of chunks) {
    body.set(chunk, offset);
    offset += chunk.byteLength;
  }
  try {
    return JSON.parse(new TextDecoder().decode(body)) as T;
  } catch {
    throw new ApiError(400, "invalid_json", "Не удалось прочитать запрос.");
  }
}

function storedEntities(value: string | null): PublicCustomEmojiEntity[] {
  if (!value) return [];
  try {
    const parsed = JSON.parse(value) as unknown;
    if (!Array.isArray(parsed)) return [];
    return parsed.filter(
      (entity): entity is PublicCustomEmojiEntity =>
        entity?.type === "custom_emoji" &&
        Number.isInteger(entity.offset) &&
        Number.isInteger(entity.length) &&
        entity.offset >= 0 &&
        entity.length > 0 &&
        /^\d{5,30}$/.test(String(entity.customEmojiId || "")),
    );
  } catch {
    return [];
  }
}

function publicMessage(row: MessageRow) {
  return {
    id: row.id,
    direction: row.direction,
    text: row.text,
    createdAt: row.created_at,
    deliveryStatus: row.delivery_status,
    entities: storedEntities(row.entities_json),
  };
}

function requireTelegramConfiguration(env: RuntimeEnv): void {
  if (!env.TELEGRAM_BOT_TOKEN || !env.TELEGRAM_WEBHOOK_SECRET) {
    throw new ApiError(503, "telegram_not_configured", "Чат ещё настраивается. Попробуйте позже.", true);
  }
}

function requireMaxConfiguration(env: RuntimeEnv): void {
  if (!env.MAX_BOT_TOKEN || !env.MAX_WEBHOOK_SECRET || !env.MAX_PROXY_SECRET) {
    throw new ApiError(503, "max_not_configured", "MAX-бот ещё настраивается.", true);
  }
}

function requireTelegramPrivateConfiguration(env: RuntimeEnv): string {
  requireTelegramConfiguration(env);
  const secret = env.TELEGRAM_CHAT_ENCRYPTION_KEY || "";
  if (secret.length < 32) {
    throw new ApiError(503, "telegram_private_not_configured", "Личные сообщения боту ещё настраиваются.", true);
  }
  return secret;
}

type SessionRoute = "site" | "max" | "telegram";

function sessionRoute(session: SessionRow): SessionRoute {
  if (session.telegram_user_hash) return "telegram";
  return session.source;
}

function telegramPrivateChatId(message: TelegramMessage): string {
  if (message.chat.type !== "private" || message.from?.is_bot) return "";
  const chatId = String(message.chat.id);
  const senderId = String(message.from?.id || "");
  return /^\d{1,20}$/.test(chatId) && senderId === chatId ? chatId : "";
}

async function telegramUserHash(env: RuntimeEnv, chatId: string): Promise<string> {
  return hmacSha256Hex(requireTelegramPrivateConfiguration(env), "telegram-user:" + chatId);
}

function telegramMessageIdentifier(userHash: string, messageId: number): string {
  return "telegram:" + userHash.slice(0, 32) + ":" + messageId;
}

async function getSetting(env: RuntimeEnv, key: string): Promise<string | null> {
  const row = await env.DB.prepare("SELECT value FROM settings WHERE key = ?").bind(key).first<{ value: string }>();
  return row?.value ?? null;
}

async function setSetting(env: RuntimeEnv, key: string, value: string): Promise<void> {
  await env.DB.prepare(
    "INSERT INTO settings(key, value, updated_at) VALUES (?, ?, ?) " +
      "ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at",
  )
    .bind(key, value, nowSeconds())
    .run();
}

async function telegramApi<T>(env: RuntimeEnv, method: string, payload: unknown): Promise<T> {
  requireTelegramConfiguration(env);
  const response = await fetch("https://api.telegram.org/bot" + env.TELEGRAM_BOT_TOKEN + "/" + method, {
    method: "POST",
    headers: JSON_HEADERS,
    body: JSON.stringify(payload),
  });
  const result = (await response.json()) as TelegramApiResponse<T>;
  if (!response.ok || !result.ok || result.result === undefined) {
    throw new TelegramApiError(result.error_code, result.description || "");
  }
  return result.result;
}

function isMissingTelegramTopicResponse(errorCode: number | undefined, description: string): boolean {
  return errorCode === 400 &&
    /message thread not found|message thread is closed|topic[_ ]closed|topic[_ ]id[_ ]invalid/i.test(description);
}

function isMissingTelegramTopic(error: unknown): boolean {
  return error instanceof TelegramTopicMismatchError ||
    (error instanceof TelegramApiError &&
      isMissingTelegramTopicResponse(error.telegramErrorCode, error.telegramDescription));
}

function isDedicatedTelegramThreadId(value: unknown): value is number {
  // У General всегда id=1. Обращения должны жить только в отдельных темах.
  return typeof value === "number" && Number.isSafeInteger(value) && value > 1;
}

function telegramMessageMatchesThread(expectedThreadId: number, actualThreadId: unknown): boolean {
  return isDedicatedTelegramThreadId(expectedThreadId) && actualThreadId === expectedThreadId;
}

function isTelegramTopicUnchanged(error: unknown): boolean {
  return error instanceof TelegramApiError &&
    error.telegramErrorCode === 400 &&
    /topic[_ ]not[_ ]modified/i.test(error.telegramDescription);
}

async function maxApi<T>(env: RuntimeEnv, path: string, payload: unknown): Promise<T> {
  requireMaxConfiguration(env);
  const response = await fetch(MAX_API_PROXY_URL + (env.MAX_PROXY_SECRET || "") + path, {
    method: "POST",
    headers: {
      ...JSON_HEADERS,
      Authorization: env.MAX_BOT_TOKEN || "",
    },
    body: JSON.stringify(payload),
  });
  if (!response.ok) {
    throw new ApiError(502, "max_unavailable", "MAX временно недоступен.", true);
  }
  const result = (await response.json()) as T & { success?: unknown };
  if (result && typeof result === "object" && result.success === false) {
    throw new ApiError(502, "max_unavailable", "MAX не подтвердил операцию.", true);
  }
  return result;
}

async function sendMaxMessage(env: RuntimeEnv, userId: string, text: string): Promise<void> {
  await maxApi(env, "/messages?user_id=" + encodeURIComponent(userId), {
    text,
    notify: true,
  });
}

function telegramImageContentType(filePath: string, supplied: string | null): string {
  if (/\.webp$/i.test(filePath)) return "image/webp";
  if (/\.png$/i.test(filePath)) return "image/png";
  if (/\.jpe?g$/i.test(filePath)) return "image/jpeg";
  return supplied?.startsWith("image/") ? supplied : "application/octet-stream";
}

async function customEmojiImage(
  request: Request,
  env: RuntimeEnv,
  ctx: ExecutionContext,
  customEmojiId: string,
): Promise<Response> {
  if (!/^\d{5,30}$/.test(customEmojiId)) {
    throw new ApiError(404, "emoji_not_found", "Эмодзи не найден.");
  }
  requireTelegramConfiguration(env);
  const cacheUrl = new URL(request.url);
  cacheUrl.search = "";
  const cacheKey = new Request(cacheUrl.toString(), { method: "GET" });
  const cache = await caches.open("pn-support-chat-custom-emoji-v1");
  const cached = await cache.match(cacheKey);
  if (cached) return cached;
  await consumeCustomEmojiRateLimit(request, env);

  const stickers = await telegramApi<TelegramSticker[]>(env, "getCustomEmojiStickers", {
    custom_emoji_ids: [customEmojiId],
  });
  const sticker = stickers[0];
  if (!sticker) throw new ApiError(404, "emoji_not_found", "Эмодзи не найден.");

  // A thumbnail is always a browser-friendly still image, including for
  // animated and video custom emoji. Fall back to the original static file.
  const file = await telegramApi<TelegramFile>(env, "getFile", {
    file_id: sticker.thumbnail?.file_id || sticker.file_id,
  });
  if (!file.file_path) throw new ApiError(502, "emoji_unavailable", "Эмодзи временно недоступен.", true);
  const upstream = await fetch(
    "https://api.telegram.org/file/bot" + env.TELEGRAM_BOT_TOKEN + "/" + file.file_path,
  );
  if (!upstream.ok || !upstream.body) {
    throw new ApiError(502, "emoji_unavailable", "Эмодзи временно недоступен.", true);
  }

  const response = new Response(upstream.body, {
    status: 200,
    headers: {
      "Content-Type": telegramImageContentType(file.file_path, upstream.headers.get("Content-Type")),
      "Cache-Control": "public, max-age=86400, s-maxage=2592000, immutable",
      "X-Content-Type-Options": "nosniff",
    },
  });
  ctx.waitUntil(cache.put(cacheKey, response.clone()));
  return response;
}

async function operatorChatId(env: RuntimeEnv): Promise<string> {
  const chatId = await getSetting(env, "operator_chat_id");
  if (!chatId) {
    throw new ApiError(503, "operator_group_not_bound", "Чат ещё настраивается. Попробуйте позже.", true);
  }
  return chatId;
}

async function consumeSessionRateLimit(request: Request, env: RuntimeEnv): Promise<void> {
  if (!env.RATE_LIMIT_SECRET) {
    throw new ApiError(503, "rate_limit_not_configured", "Чат ещё настраивается. Попробуйте позже.", true);
  }
  const ip = rateLimitAddress(
    request.headers.get("CF-Connecting-IP"),
    request.headers.get("X-PN-Visitor-IP"),
    env.TRUSTED_PROXY_IP,
  );
  const now = nowSeconds();
  // Fixed 10-minute buckets cap the wait at ten minutes while still preventing
  // one address from creating a large number of Telegram topics at once.
  const windowSeconds = 10 * 60;
  const bucket = Math.floor(now / windowSeconds);
  const digest = await hmacSha256Hex(env.RATE_LIMIT_SECRET, ip + ":" + bucket);
  const key = "new-session:" + bucket + ":" + digest;
  const row = await env.DB.prepare(
    "INSERT INTO rate_limits(bucket_key, count, expires_at) VALUES (?, 1, ?) " +
      "ON CONFLICT(bucket_key) DO UPDATE SET count = count + 1 RETURNING count",
  )
    .bind(key, now + windowSeconds * 2)
    .first<{ count: number }>();
  if ((row?.count ?? 1) > 5) {
    throw new ApiError(429, "too_many_sessions", "Слишком много новых диалогов. Попробуйте снова не позднее чем через 10 минут.", true);
  }
}

async function consumeCustomEmojiRateLimit(request: Request, env: RuntimeEnv): Promise<void> {
  if (!env.RATE_LIMIT_SECRET) {
    throw new ApiError(503, "rate_limit_not_configured", "Эмодзи временно недоступен.", true);
  }
  const ip = rateLimitAddress(
    request.headers.get("CF-Connecting-IP"),
    request.headers.get("X-PN-Visitor-IP"),
    env.TRUSTED_PROXY_IP,
  );
  const now = nowSeconds();
  const windowSeconds = 10 * 60;
  const bucket = Math.floor(now / windowSeconds);
  const digest = await hmacSha256Hex(env.RATE_LIMIT_SECRET, "emoji:" + ip + ":" + bucket);
  const row = await env.DB.prepare(
    "INSERT INTO rate_limits(bucket_key, count, expires_at) VALUES (?, 1, ?) " +
      "ON CONFLICT(bucket_key) DO UPDATE SET count = count + 1 RETURNING count",
  )
    .bind("emoji:" + bucket + ":" + digest, now + windowSeconds * 2)
    .first<{ count: number }>();
  if ((row?.count ?? 1) > 60) {
    throw new ApiError(429, "emoji_rate_limited", "Слишком много запросов эмодзи. Попробуйте позже.", true);
  }
}

async function enforceMessageRateLimit(env: RuntimeEnv, sessionId: string): Promise<void> {
  const now = nowSeconds();
  const row = await env.DB.prepare(
    "SELECT " +
      "SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS recent_count, " +
      "COUNT(*) AS daily_count " +
      "FROM messages WHERE session_id = ? AND direction = 'visitor' AND created_at >= ?",
  )
    .bind(now - 300, sessionId, now - 86400)
    .first<{ recent_count: number | null; daily_count: number }>();
  if ((row?.recent_count ?? 0) >= 10) {
    throw new ApiError(429, "message_rate_limited", "Слишком много сообщений подряд. Немного подождите.");
  }
  if ((row?.daily_count ?? 0) >= 100) {
    throw new ApiError(429, "daily_message_limit", "Дневной лимит сообщений в этом диалоге исчерпан.");
  }
}

async function authenticateSession(request: Request, env: RuntimeEnv): Promise<SessionRow> {
  const authorization = request.headers.get("Authorization") || "";
  const match = authorization.match(/^Bearer ([A-Za-z0-9_-]{32,100})$/);
  if (!match) throw new ApiError(401, "invalid_session", "Диалог не найден.");
  const tokenHash = await sha256Hex(match[1]);
  const session = await env.DB.prepare("SELECT * FROM sessions WHERE token_hash = ?")
    .bind(tokenHash)
    .first<SessionRow>();
  if (!session) throw new ApiError(401, "invalid_session", "Диалог не найден.");
  return session;
}

async function createSession(request: Request, env: RuntimeEnv): Promise<Response> {
  requireTelegramConfiguration(env);
  await operatorChatId(env);
  await consumeSessionRateLimit(request, env);
  const body = await readJsonBody<{ pagePath?: unknown }>(request, MAX_BODY_BYTES);
  const token = randomToken();
  const tokenHash = await sha256Hex(token);
  const sessionId = crypto.randomUUID();
  const code = visitorCode(sessionId);
  const pagePath = sanitizePagePath(body.pagePath);
  const now = nowSeconds();
  await env.DB.prepare(
    "INSERT INTO sessions(id, token_hash, visitor_code, page_path, created_at, last_activity_at) " +
      "VALUES (?, ?, ?, ?, ?, ?)",
  )
    .bind(sessionId, tokenHash, code, pagePath, now, now)
    .run();
  return json({ ok: true, session: { token, visitorCode: code, status: "open" } }, 201);
}

function maxUserId(user: MaxUser | undefined): string {
  const value = user?.user_id;
  return value === undefined || value === null ? "" : String(value);
}

function maxMessageIdentifier(update: MaxUpdate): string {
  const value = update.message?.body?.mid;
  const raw = value === undefined || value === null ? String(update.timestamp || "") : String(value);
  return "max:" + (raw.replace(/[^A-Za-z0-9._:-]/g, "").slice(0, 180) || crypto.randomUUID());
}

function isDirectMaxMessage(update: MaxUpdate): boolean {
  const senderId = maxUserId(update.message?.sender);
  // В MAX chat_id — это идентификатор диалога, а не ID отправителя.
  // Надёжный признак личного сообщения — тип получателя "dialog".
  return Boolean(senderId && update.message?.recipient?.chat_type === "dialog" && !update.message?.sender?.is_bot);
}

async function findOrCreateMaxSession(env: RuntimeEnv, userId: string): Promise<SessionRow> {
  const existing = await env.DB.prepare("SELECT * FROM sessions WHERE source = 'max' AND max_user_id = ? AND status = 'open'")
    .bind(userId)
    .first<SessionRow>();
  if (existing) return existing;
  const sessionId = crypto.randomUUID();
  const now = nowSeconds();
  const tokenHash = await sha256Hex("max-session:" + userId);
  try {
    await env.DB.prepare(
      "INSERT INTO sessions(id, token_hash, visitor_code, page_path, source, max_user_id, created_at, last_activity_at) " +
        "VALUES (?, ?, ?, '/max', 'max', ?, ?, ?)",
    )
      .bind(sessionId, tokenHash, visitorCode(sessionId), userId, now, now)
      .run();
  } catch {
    // Одновременные повторы одного вебхука не должны заводить две темы.
  }
  const session = await env.DB.prepare("SELECT * FROM sessions WHERE source = 'max' AND max_user_id = ? AND status = 'open'")
    .bind(userId)
    .first<SessionRow>();
  if (!session) throw new ApiError(500, "max_session_store_failed", "Не удалось открыть диалог MAX.", true);
  return session;
}

async function findOpenTelegramSession(env: RuntimeEnv, userHash: string): Promise<SessionRow | null> {
  return env.DB.prepare(
    "SELECT * FROM sessions WHERE telegram_user_hash = ? AND status = 'open'",
  )
    .bind(userHash)
    .first<SessionRow>();
}

async function findOrCreateTelegramSession(env: RuntimeEnv, chatId: string): Promise<SessionRow> {
  const userHash = await telegramUserHash(env, chatId);
  const existing = await findOpenTelegramSession(env, userHash);
  if (existing) return existing;

  const now = nowSeconds();
  const recent = await env.DB.prepare(
    "SELECT COUNT(*) AS count FROM sessions WHERE telegram_user_hash = ? AND created_at >= ?",
  )
    .bind(userHash, now - 10 * 60)
    .first<{ count: number }>();
  if ((recent?.count ?? 0) >= 5) {
    throw new ApiError(429, "too_many_telegram_sessions", "Слишком много новых диалогов. Попробуйте снова не позднее чем через 10 минут.");
  }

  const sessionId = crypto.randomUUID();
  const tokenHash = await sha256Hex(randomToken());
  const encryptedChatId = await encryptSecretString(requireTelegramPrivateConfiguration(env), chatId);
  try {
    await env.DB.prepare(
      "INSERT INTO sessions(" +
        "id, token_hash, visitor_code, page_path, source, telegram_user_hash, telegram_chat_id_encrypted, created_at, last_activity_at" +
        ") VALUES (?, ?, ?, '/telegram', 'site', ?, ?, ?, ?)",
    )
      .bind(sessionId, tokenHash, visitorCode(sessionId), userHash, encryptedChatId, now, now)
      .run();
  } catch {
    // Повтор одного webhook может прийти одновременно с первым: открытая сессия должна остаться одна.
  }
  const session = await findOpenTelegramSession(env, userHash);
  if (!session) throw new ApiError(500, "telegram_session_store_failed", "Не удалось открыть диалог Telegram.", true);
  return session;
}

function telegramTopicName(session: SessionRow): string {
  const route = sessionRoute(session);
  return route === "max"
    ? "MAX · Аноним " + session.visitor_code
    : route === "telegram"
      ? "Telegram · Аноним " + session.visitor_code
      : "Сайт · Аноним " + session.visitor_code;
}

async function ensureTopic(env: RuntimeEnv, session: SessionRow): Promise<{ threadId: number; isNew: boolean }> {
  if (isDedicatedTelegramThreadId(session.telegram_thread_id)) {
    return { threadId: session.telegram_thread_id, isNew: false };
  }
  if (session.telegram_thread_id !== null) {
    await env.DB.prepare(
      "UPDATE sessions SET telegram_thread_id = NULL, topic_lock_id = NULL, topic_lock_until = NULL WHERE id = ?",
    )
      .bind(session.id)
      .run();
  }
  const chatId = await operatorChatId(env);
  const lockId = crypto.randomUUID();
  const now = nowSeconds();
  const claimed = await env.DB.prepare(
    "UPDATE sessions SET topic_lock_id = ?, topic_lock_until = ? " +
      "WHERE id = ? AND telegram_thread_id IS NULL " +
      "AND (topic_lock_until IS NULL OR topic_lock_until < ?) RETURNING id",
  )
    .bind(lockId, now + 20, session.id, now)
    .first<{ id: string }>();

  if (!claimed) {
    const fresh = await env.DB.prepare("SELECT telegram_thread_id FROM sessions WHERE id = ?")
      .bind(session.id)
      .first<{ telegram_thread_id: number | null }>();
    if (isDedicatedTelegramThreadId(fresh?.telegram_thread_id)) {
      return { threadId: fresh.telegram_thread_id, isNew: false };
    }
    throw new ApiError(409, "topic_busy", "Диалог создаётся. Повторите отправку.", true);
  }

  try {
    const topic = await telegramApi<{ message_thread_id: number }>(env, "createForumTopic", {
      chat_id: chatId,
      name: telegramTopicName(session),
      icon_color: 7322096,
    });
    if (!isDedicatedTelegramThreadId(topic.message_thread_id)) {
      throw new TelegramTopicMismatchError();
    }
    await env.DB.prepare(
      "UPDATE sessions SET telegram_thread_id = ?, topic_lock_id = NULL, topic_lock_until = NULL " +
        "WHERE id = ? AND topic_lock_id = ?",
    )
      .bind(topic.message_thread_id, session.id, lockId)
      .run();
    return { threadId: topic.message_thread_id, isNew: true };
  } catch (error) {
    await env.DB.prepare(
      "UPDATE sessions SET topic_lock_id = NULL, topic_lock_until = NULL WHERE id = ? AND topic_lock_id = ?",
    )
      .bind(session.id, lockId)
      .run();
    throw error;
  }
}

async function verifyExistingTelegramTopic(
  env: RuntimeEnv,
  chatId: string,
  session: SessionRow,
  threadId: number,
): Promise<void> {
  if (!isDedicatedTelegramThreadId(threadId)) throw new TelegramTopicMismatchError();
  try {
    // В Bot API нет отдельного метода «получить тему». Безвредное редактирование
    // с тем же названием служит проверкой: удалённая тема даст ошибку до отправки.
    await telegramApi<boolean>(env, "editForumTopic", {
      chat_id: chatId,
      message_thread_id: threadId,
      name: telegramTopicName(session),
    });
  } catch (error) {
    if (isTelegramTopicUnchanged(error)) return;
    throw error;
  }
}

async function deleteTelegramMessageQuietly(env: RuntimeEnv, chatId: string, messageId: number): Promise<void> {
  try {
    await telegramApi<boolean>(env, "deleteMessage", { chat_id: chatId, message_id: messageId });
  } catch (error) {
    console.error(JSON.stringify({
      event: "telegram_misdirected_message_delete_failed",
      error: error instanceof Error ? error.name : "unknown",
    }));
  }
}

function visitorTelegramText(session: SessionRow, text: string, firstMessage: boolean): string {
  const sender = "Сайт · Аноним " + session.visitor_code;
  if (!firstMessage) return sender + ":\n\n" + text;
  return (
    "Новый анонимный вопрос с сайта\n\n" +
    sender +
    "\nСтраница: " +
    session.page_path +
    "\n\n" +
    text
  );
}

async function findClientMessage(
  env: RuntimeEnv,
  sessionId: string,
  clientMessageId: string,
): Promise<MessageRow | null> {
  return env.DB.prepare("SELECT * FROM messages WHERE session_id = ? AND client_message_id = ?")
    .bind(sessionId, clientMessageId)
    .first<MessageRow>();
}

async function isFirstVisitorDelivery(env: RuntimeEnv, sessionId: string, messageId: number): Promise<boolean> {
  const earlier = await env.DB.prepare(
    "SELECT id FROM messages WHERE session_id = ? AND direction = 'visitor' " +
      "AND delivery_status = 'delivered' AND id <> ? LIMIT 1",
  )
    .bind(sessionId, messageId)
    .first<{ id: number }>();
  return !earlier;
}

function maxTelegramText(visitorCode: string, text: string, firstMessage: boolean): string {
  const sender = "MAX · Аноним " + visitorCode;
  if (!firstMessage) return sender + ":\n\n" + text;
  return "Новое анонимное обращение из MAX\n\n" + sender + "\n\n" + text;
}

function privateTelegramText(visitorCode: string, text: string, firstMessage: boolean): string {
  const sender = "Telegram · Аноним " + visitorCode;
  if (!firstMessage) return sender + ":\n\n" + text;
  return "Новое анонимное обращение из Telegram\n\n" + sender + "\n\n" + text;
}

function telegramVisitorText(session: SessionRow, text: string, introductory: boolean): string {
  const route = sessionRoute(session);
  if (route === "max") return maxTelegramText(session.visitor_code, text, introductory);
  if (route === "telegram") return privateTelegramText(session.visitor_code, text, introductory);
  return visitorTelegramText(session, text, introductory);
}

async function sendVisitorMessageToTelegram(
  env: RuntimeEnv,
  session: SessionRow,
  messageId: number,
  text: string,
): Promise<number> {
  const chatId = await operatorChatId(env);

  const sendToTopic = async (topic: { threadId: number; isNew: boolean }): Promise<number> => {
    if (!topic.isNew) {
      await verifyExistingTelegramTopic(env, chatId, session, topic.threadId);
    }
    const introductory = topic.isNew || await isFirstVisitorDelivery(env, session.id, messageId);
    const sent = await telegramApi<TelegramMessage>(env, "sendMessage", {
      chat_id: chatId,
      message_thread_id: topic.threadId,
      text: telegramVisitorText(session, text, introductory),
      reply_markup: introductory
        ? { inline_keyboard: [[{ text: "Закрыть диалог", callback_data: "close:" + session.id }]] }
        : undefined,
    });
    if (!telegramMessageMatchesThread(topic.threadId, sent.message_thread_id)) {
      console.error(JSON.stringify({
        event: "telegram_topic_mismatch",
        expectedThreadId: topic.threadId,
        actualThreadId: sent.message_thread_id ?? null,
      }));
      await deleteTelegramMessageQuietly(env, chatId, sent.message_id);
      throw new TelegramTopicMismatchError();
    }
    return sent.message_id;
  };

  const topic = await ensureTopic(env, session);
  try {
    return await sendToTopic(topic);
  } catch (error) {
    if (!isMissingTelegramTopic(error)) throw error;

    await env.DB.prepare(
      "UPDATE sessions SET telegram_thread_id = NULL, topic_lock_id = NULL, topic_lock_until = NULL " +
        "WHERE id = ? AND telegram_thread_id = ?",
    )
      .bind(session.id, topic.threadId)
      .run();

    const replacement = await ensureTopic(env, { ...session, telegram_thread_id: null });
    console.log(JSON.stringify({ event: "telegram_topic_recreated", source: sessionRoute(session) }));
    return sendToTopic(replacement);
  }
}

async function sendMaxVisitorMessage(update: MaxUpdate, env: RuntimeEnv): Promise<void> {
  if (!isDirectMaxMessage(update)) return;
  const user = update.message?.sender;
  const userId = maxUserId(user);
  const rawText = typeof update.message?.body?.text === "string" ? update.message.body.text : "";
  const text = rawText.trim();
  const session = await findOrCreateMaxSession(env, userId);

  if (/^\/close(?:\s|$)/i.test(text)) {
    await closeFromMax(session, env, true);
    return;
  }

  const messageKey = maxMessageIdentifier(update);
  const existing = await findClientMessage(env, session.id, messageKey);
  if (existing?.delivery_status === "delivered") return;
  if (!existing) await enforceMessageRateLimit(env, session.id);

  const maximum = positiveInteger(env.MAX_MESSAGE_LENGTH, 2000);
  let messageText: string;
  try {
    messageText = validateMessageText(text, maximum);
  } catch (error) {
    const code = error instanceof Error ? error.message : "invalid_message";
    if (code === "message_too_long") {
      await sendMaxMessage(env, userId, "Сообщение длиннее " + maximum + " символов. Разделите его на несколько.");
      return;
    }
    await sendMaxMessage(env, userId, "Пока я могу передать только текстовое сообщение.");
    return;
  }

  let message = existing;
  if (!message) {
    try {
      message = await env.DB.prepare(
        "INSERT INTO messages(session_id, direction, text, client_message_id, delivery_status, created_at) " +
          "VALUES (?, 'visitor', ?, ?, 'pending', ?) RETURNING *",
      )
        .bind(session.id, messageText, messageKey, nowSeconds())
        .first<MessageRow>();
    } catch {
      message = await findClientMessage(env, session.id, messageKey);
    }
  }
  if (!message) throw new ApiError(500, "max_message_store_failed", "Не удалось сохранить сообщение MAX.", true);

  try {
    const telegramMessageId = await sendVisitorMessageToTelegram(env, session, message.id, messageText);
    await env.DB.batch([
      env.DB.prepare("UPDATE messages SET delivery_status = 'delivered', telegram_message_id = ? WHERE id = ?")
        .bind(telegramMessageId, message.id),
      env.DB.prepare("UPDATE sessions SET last_activity_at = ? WHERE id = ?").bind(nowSeconds(), session.id),
    ]);
  } catch (error) {
    await env.DB.prepare("UPDATE messages SET delivery_status = 'failed' WHERE id = ?").bind(message.id).run();
    throw error;
  }
}

const TELEGRAM_PRIVATE_WELCOME =
  "Здравствуйте! Здесь можно задать вопрос, касающийся проблем с алкоголем, работы нашей группы АА или Сообщества Анонимных Алкоголиков в целом. " +
  "Вам ответит трезвый алкоголик — служащий нашей группы. Чат анонимный: служащие не увидят ваше имя, username или профиль, только случайный номер.\n\n" +
  "Напишите вопрос обычным текстом. Чтобы завершить текущий диалог, отправьте /close.";

function telegramApiEntities(entities: PublicCustomEmojiEntity[]): Array<Record<string, string | number>> | undefined {
  if (!entities.length) return undefined;
  return entities.map((entity) => ({
    type: "custom_emoji",
    offset: entity.offset,
    length: entity.length,
    custom_emoji_id: entity.customEmojiId,
  }));
}

async function sendTelegramPrivateText(
  env: RuntimeEnv,
  chatId: string,
  text: string,
  entities: PublicCustomEmojiEntity[] = [],
): Promise<void> {
  await telegramApi(env, "sendMessage", {
    chat_id: chatId,
    text,
    entities: telegramApiEntities(entities),
  });
}

async function telegramSessionChatId(env: RuntimeEnv, session: SessionRow): Promise<string> {
  if (!session.telegram_chat_id_encrypted) {
    throw new ApiError(500, "telegram_session_missing_chat", "Не найден получатель Telegram.", true);
  }
  const chatId = await decryptSecretString(
    requireTelegramPrivateConfiguration(env),
    session.telegram_chat_id_encrypted,
  );
  if (!/^\d{1,20}$/.test(chatId)) {
    throw new ApiError(500, "telegram_session_invalid_chat", "Не найден получатель Telegram.", true);
  }
  return chatId;
}

async function deliverOperatorMessageToTelegram(
  env: RuntimeEnv,
  session: SessionRow,
  text: string,
  entities: PublicCustomEmojiEntity[],
): Promise<void> {
  const chatId = await telegramSessionChatId(env, session);
  try {
    await sendTelegramPrivateText(env, chatId, text, entities);
  } catch (error) {
    // Некоторые custom emoji недоступны конкретному боту. Сам ответ важнее украшения:
    // в таком случае Telegram всё равно получит текст с обычным emoji-символом.
    if (entities.length && error instanceof TelegramApiError && error.telegramErrorCode === 400) {
      await sendTelegramPrivateText(env, chatId, text);
      return;
    }
    throw error;
  }
}

function isTelegramRecipientUnavailable(error: unknown): boolean {
  return error instanceof TelegramApiError &&
    (error.telegramErrorCode === 403 ||
      (error.telegramErrorCode === 400 && /chat not found|user is deactivated/i.test(error.telegramDescription)));
}

async function handleTelegramPrivateMessage(message: TelegramMessage, env: RuntimeEnv): Promise<void> {
  const chatId = telegramPrivateChatId(message);
  if (!chatId) return;
  const rawText = typeof message.text === "string" ? message.text : "";
  const text = rawText.trim();

  if (/^\/(?:start|help)(?:@\w+)?(?:\s|$)/i.test(text)) {
    await sendTelegramPrivateText(env, chatId, TELEGRAM_PRIVATE_WELCOME);
    return;
  }

  const userHash = await telegramUserHash(env, chatId);
  if (/^\/close(?:@\w+)?(?:\s|$)/i.test(text)) {
    const session = await findOpenTelegramSession(env, userHash);
    if (!session) {
      await sendTelegramPrivateText(env, chatId, "Сейчас открытого диалога нет. Просто напишите вопрос — я создам новый.");
      return;
    }
    await closeFromPrivateTelegram(session, env, true);
    return;
  }

  const maximum = positiveInteger(env.MAX_MESSAGE_LENGTH, 2000);
  let messageText: string;
  try {
    messageText = validateMessageText(text, maximum);
  } catch (error) {
    const code = error instanceof Error ? error.message : "invalid_message";
    await sendTelegramPrivateText(
      env,
      chatId,
      code === "message_too_long"
        ? "Сообщение длиннее " + maximum + " символов. Разделите его на несколько."
        : "Пока я могу передать только текстовое сообщение.",
    );
    return;
  }

  const messageKey = telegramMessageIdentifier(userHash, message.message_id);
  const duplicate = await env.DB.prepare("SELECT id FROM messages WHERE client_message_id = ? LIMIT 1")
    .bind(messageKey)
    .first<{ id: number }>();
  if (duplicate) return;

  let session: SessionRow;
  try {
    session = await findOrCreateTelegramSession(env, chatId);
  } catch (error) {
    if (error instanceof ApiError && error.status === 429) {
      await sendTelegramPrivateText(env, chatId, error.message);
      return;
    }
    throw error;
  }
  try {
    await enforceMessageRateLimit(env, session.id);
  } catch (error) {
    if (error instanceof ApiError && error.status === 429) {
      await sendTelegramPrivateText(env, chatId, error.message);
      return;
    }
    throw error;
  }
  const now = nowSeconds();
  let visitorMessage: MessageRow | null = null;
  try {
    visitorMessage = await env.DB.prepare(
      "INSERT INTO messages(session_id, direction, text, client_message_id, delivery_status, created_at) " +
        "VALUES (?, 'visitor', ?, ?, 'pending', ?) RETURNING *",
    )
      .bind(session.id, messageText, messageKey, now)
      .first<MessageRow>();
  } catch {
    visitorMessage = await env.DB.prepare("SELECT * FROM messages WHERE client_message_id = ?")
      .bind(messageKey)
      .first<MessageRow>();
  }
  if (!visitorMessage) {
    throw new ApiError(500, "telegram_message_store_failed", "Не удалось сохранить сообщение Telegram.", true);
  }
  if (visitorMessage.delivery_status === "delivered") return;

  const firstMessage = await isFirstVisitorDelivery(env, session.id, visitorMessage.id);
  try {
    const groupMessageId = await sendVisitorMessageToTelegram(env, session, visitorMessage.id, messageText);
    await env.DB.batch([
      env.DB.prepare("UPDATE messages SET delivery_status = 'delivered', telegram_message_id = ? WHERE id = ?")
        .bind(groupMessageId, visitorMessage.id),
      env.DB.prepare("UPDATE sessions SET last_activity_at = ? WHERE id = ?").bind(now, session.id),
    ]);
    if (firstMessage) {
      try {
        await sendTelegramPrivateText(env, chatId, "Сообщение передано служащим группы. Ответ придёт сюда.");
      } catch (error) {
        console.error(JSON.stringify({ event: "telegram_private_ack_failed", error: error instanceof Error ? error.name : "unknown" }));
      }
    }
  } catch (error) {
    await env.DB.prepare("UPDATE messages SET delivery_status = 'failed' WHERE id = ?")
      .bind(visitorMessage.id)
      .run();
    throw error;
  }
}

async function sendVisitorMessage(request: Request, env: RuntimeEnv): Promise<Response> {
  requireTelegramConfiguration(env);
  const session = await authenticateSession(request, env);
  if (session.status !== "open") throw new ApiError(409, "chat_closed", "Этот диалог уже закрыт.");
  const body = await readJsonBody<{ text?: unknown; clientMessageId?: unknown }>(request, MAX_BODY_BYTES);
  if (!isValidClientMessageId(body.clientMessageId)) {
    throw new ApiError(400, "invalid_message_id", "Не удалось определить сообщение.");
  }

  const existing = await findClientMessage(env, session.id, body.clientMessageId);
  if (existing?.delivery_status === "delivered") {
    return json({ ok: true, message: publicMessage(existing) });
  }

  let text = existing?.text;
  if (!text) {
    const maximum = positiveInteger(env.MAX_MESSAGE_LENGTH, 2000);
    try {
      text = validateMessageText(body.text, maximum);
    } catch (error) {
      const code = error instanceof Error ? error.message : "invalid_message";
      if (code === "message_too_long") {
        throw new ApiError(400, code, "Сообщение длиннее " + maximum + " символов.");
      }
      throw new ApiError(400, "message_empty", "Напишите сообщение перед отправкой.");
    }
  }

  if (!existing) await enforceMessageRateLimit(env, session.id);
  const now = nowSeconds();
  let message = existing;
  if (!message) {
    try {
      message = await env.DB.prepare(
        "INSERT INTO messages(session_id, direction, text, client_message_id, delivery_status, created_at) " +
          "VALUES (?, 'visitor', ?, ?, 'pending', ?) RETURNING *",
      )
        .bind(session.id, text, body.clientMessageId, now)
        .first<MessageRow>();
    } catch {
      message = await findClientMessage(env, session.id, body.clientMessageId);
    }
  }
  if (!message) throw new ApiError(500, "message_store_failed", "Не удалось сохранить сообщение.", true);

  try {
    const telegramMessageId = await sendVisitorMessageToTelegram(env, session, message.id, text);
    await env.DB.batch([
      env.DB.prepare(
        "UPDATE messages SET delivery_status = 'delivered', telegram_message_id = ? WHERE id = ?",
      ).bind(telegramMessageId, message.id),
      env.DB.prepare("UPDATE sessions SET last_activity_at = ? WHERE id = ?").bind(now, session.id),
    ]);
    message.delivery_status = "delivered";
    message.telegram_message_id = telegramMessageId;
    return json({ ok: true, message: publicMessage(message) });
  } catch (error) {
    await env.DB.prepare("UPDATE messages SET delivery_status = 'failed' WHERE id = ?")
      .bind(message.id)
      .run();
    throw error;
  }
}

async function listMessages(request: Request, env: RuntimeEnv): Promise<Response> {
  const session = await authenticateSession(request, env);
  const url = new URL(request.url);
  const rawAfter = Number.parseInt(url.searchParams.get("after") || "0", 10);
  const after = Number.isFinite(rawAfter) && rawAfter > 0 ? rawAfter : 0;
  const result = await env.DB.prepare(
    "SELECT * FROM messages WHERE session_id = ? AND id > ? AND delivery_status = 'delivered' " +
      "ORDER BY id ASC LIMIT 100",
  )
    .bind(session.id, after)
    .all<MessageRow>();
  return json({
    ok: true,
    status: session.status,
    messages: result.results.map(publicMessage),
    serverTime: nowSeconds(),
  });
}

async function closeTelegramTopic(env: RuntimeEnv, chatId: string, threadId: number): Promise<void> {
  try {
    await telegramApi<boolean>(env, "closeForumTopic", { chat_id: chatId, message_thread_id: threadId });
  } catch (error) {
    console.error(JSON.stringify({ event: "telegram_topic_close_failed", error: error instanceof Error ? error.name : "unknown" }));
  }
}

async function notifyVisitorClosed(env: RuntimeEnv, session: SessionRow): Promise<void> {
  if (!session.telegram_thread_id) return;
  const chatId = await operatorChatId(env);
  try {
    const route = sessionRoute(session);
    await telegramApi<{ message_id: number }>(env, "sendMessage", {
      chat_id: chatId,
      message_thread_id: session.telegram_thread_id,
      text: route === "max"
        ? "MAX · аноним завершил диалог."
        : route === "telegram"
          ? "Telegram · аноним завершил диалог."
          : "Сайт · аноним завершил диалог.",
    });
  } catch (error) {
    console.error(JSON.stringify({ event: "telegram_close_notice_failed", error: error instanceof Error ? error.name : "unknown" }));
  }
  await closeTelegramTopic(env, chatId, session.telegram_thread_id);
}

async function closeFromWebsite(request: Request, env: RuntimeEnv, ctx: ExecutionContext): Promise<Response> {
  const session = await authenticateSession(request, env);
  if (session.status === "closed") return json({ ok: true, status: "closed" });
  const now = nowSeconds();
  await env.DB.batch([
    env.DB.prepare(
      "UPDATE sessions SET status = 'closed', closed_at = ?, last_activity_at = ? WHERE id = ?",
    ).bind(now, now, session.id),
    env.DB.prepare(
      "INSERT INTO messages(session_id, direction, text, delivery_status, created_at) " +
        "VALUES (?, 'system', 'Диалог завершён.', 'delivered', ?)",
    ).bind(session.id, now),
  ]);
  ctx.waitUntil(notifyVisitorClosed(env, session));
  return json({ ok: true, status: "closed" });
}

async function closeFromMax(session: SessionRow, env: RuntimeEnv, notifyMax: boolean): Promise<void> {
  if (session.status === "closed") return;
  const now = nowSeconds();
  await env.DB.batch([
    env.DB.prepare("UPDATE sessions SET status = 'closed', closed_at = ?, last_activity_at = ? WHERE id = ?")
      .bind(now, now, session.id),
    env.DB.prepare(
      "INSERT INTO messages(session_id, direction, text, delivery_status, created_at) " +
        "VALUES (?, 'system', 'Диалог MAX завершён.', 'delivered', ?)",
    ).bind(session.id, now),
  ]);
  await notifyVisitorClosed(env, session);
  if (notifyMax && session.max_user_id) {
    await sendMaxMessage(env, session.max_user_id, "Диалог завершён. Если понадобится снова написать группе, просто отправьте новое сообщение.");
  }
}

async function closeFromPrivateTelegram(
  session: SessionRow,
  env: RuntimeEnv,
  notifyTelegramUser: boolean,
): Promise<void> {
  if (session.status === "closed") return;
  const now = nowSeconds();
  await env.DB.batch([
    env.DB.prepare("UPDATE sessions SET status = 'closed', closed_at = ?, last_activity_at = ? WHERE id = ?")
      .bind(now, now, session.id),
    env.DB.prepare(
      "INSERT INTO messages(session_id, direction, text, delivery_status, created_at) " +
        "VALUES (?, 'system', 'Диалог Telegram завершён.', 'delivered', ?)",
    ).bind(session.id, now),
  ]);
  await notifyVisitorClosed(env, session);
  if (notifyTelegramUser) {
    await sendTelegramPrivateText(
      env,
      await telegramSessionChatId(env, session),
      "Диалог завершён. Если понадобится снова написать группе, просто отправьте новое сообщение.",
    );
  }
}

async function bindOperatorGroup(message: TelegramMessage, env: RuntimeEnv): Promise<void> {
  if (String(message.from?.id || "") !== String(env.OWNER_TELEGRAM_ID || "")) return;
  if (message.chat.type !== "supergroup" || !message.chat.is_forum) {
    await telegramApi(env, "sendMessage", {
      chat_id: message.chat.id,
      message_thread_id: message.message_thread_id,
      text: "Сначала включите в этой закрытой группе режим «Темы», затем повторите /bind.",
    });
    return;
  }
  await setSetting(env, "operator_chat_id", String(message.chat.id));
  await setSetting(env, "operator_chat_title", message.chat.title || "ПН — обращения с сайта");
  await telegramApi(env, "sendMessage", {
    chat_id: message.chat.id,
    message_thread_id: message.message_thread_id,
    text: "Готово. Эта группа привязана к чату сайта. Новые обращения появятся в отдельных темах.",
  });
}

async function closeFromTelegram(session: SessionRow, env: RuntimeEnv): Promise<void> {
  if (session.status === "closed") return;
  const route = sessionRoute(session);
  if (route === "max" && session.max_user_id) {
    // Сначала доставляем человеку уведомление: при временной ошибке Telegram повторит webhook,
    // а тема ещё останется открытой.
    await sendMaxMessage(env, session.max_user_id, "Диалог завершён служащим группы. Если понадобится помощь снова, напишите боту.");
  } else if (route === "telegram") {
    await sendTelegramPrivateText(
      env,
      await telegramSessionChatId(env, session),
      "Диалог завершён служащим группы. Если понадобится помощь снова, напишите боту.",
    );
  }
  const now = nowSeconds();
  await env.DB.batch([
    env.DB.prepare(
      "UPDATE sessions SET status = 'closed', closed_at = ?, last_activity_at = ? WHERE id = ?",
    ).bind(now, now, session.id),
    env.DB.prepare(
      "INSERT INTO messages(session_id, direction, text, delivery_status, created_at) " +
        "VALUES (?, 'system', 'Служащий группы завершил диалог.', 'delivered', ?)",
    ).bind(session.id, now),
  ]);
}

async function handleTelegramCallback(update: TelegramUpdate, env: RuntimeEnv): Promise<void> {
  const callback = update.callback_query;
  if (!callback?.message || !callback.data) return;
  const match = callback.data.match(/^close:([0-9a-f-]{36})$/i);
  if (!match) return;
  const boundChatId = await getSetting(env, "operator_chat_id");
  if (!boundChatId || String(callback.message.chat.id) !== boundChatId) return;
  const session = await env.DB.prepare(
    "SELECT * FROM sessions WHERE id = ? AND telegram_thread_id = ?",
  )
    .bind(match[1], callback.message.message_thread_id || 0)
    .first<SessionRow>();
  if (!session) return;
  await closeFromTelegram(session, env);
  await telegramApi(env, "answerCallbackQuery", {
    callback_query_id: callback.id,
    text: session.status === "closed" ? "Диалог уже закрыт" : "Диалог закрыт",
  });
  if (session.telegram_thread_id) {
    await closeTelegramTopic(env, boundChatId, session.telegram_thread_id);
  }
}

async function handleTelegramMessage(message: TelegramMessage, env: RuntimeEnv): Promise<void> {
  if (message.from?.is_bot) return;
  if (message.chat.type === "private") {
    await handleTelegramPrivateMessage(message, env);
    return;
  }
  const rawText = typeof message.text === "string" ? message.text : "";
  const text = rawText.trim();
  if (/^\/bind(?:@\w+)?(?:\s|$)/i.test(text)) {
    await bindOperatorGroup(message, env);
    return;
  }

  const boundChatId = await getSetting(env, "operator_chat_id");
  if (!boundChatId || String(message.chat.id) !== boundChatId || !message.message_thread_id) return;
  const session = await env.DB.prepare("SELECT * FROM sessions WHERE telegram_thread_id = ?")
    .bind(message.message_thread_id)
    .first<SessionRow>();
  if (!session) return;

  if (/^\/close(?:@\w+)?(?:\s|$)/i.test(text)) {
    await closeFromTelegram(session, env);
    await telegramApi(env, "sendMessage", {
      chat_id: boundChatId,
      message_thread_id: message.message_thread_id,
      text: "Диалог закрыт.",
    });
    await closeTelegramTopic(env, boundChatId, message.message_thread_id);
    return;
  }

  if (session.status !== "open") {
    await telegramApi(env, "sendMessage", {
      chat_id: boundChatId,
      message_thread_id: message.message_thread_id,
      text: "Этот диалог уже закрыт.",
    });
    return;
  }

  if (!text) {
    await telegramApi(env, "sendMessage", {
      chat_id: boundChatId,
      message_thread_id: message.message_thread_id,
      text: "Пока посетителю можно отправить только текстовое сообщение.",
    });
    return;
  }

  const maximum = positiveInteger(env.MAX_MESSAGE_LENGTH, 2000);
  let operatorText: string;
  try {
    operatorText = validateMessageText(text, maximum);
  } catch {
    await telegramApi(env, "sendMessage", {
      chat_id: boundChatId,
      message_thread_id: message.message_thread_id,
      text: "Ответ слишком длинный. Разделите его на несколько сообщений.",
    });
    return;
  }

  const entities = normalizeTelegramCustomEmojiEntities(rawText, message.entities);
  const entitiesJson = entities.length ? JSON.stringify(entities) : null;
  const route = sessionRoute(session);

  const now = nowSeconds();
  let operatorMessage = await env.DB.prepare(
    "SELECT * FROM messages WHERE telegram_message_id = ?",
  )
    .bind(message.message_id)
    .first<MessageRow>();
  if (!operatorMessage) {
    operatorMessage = await env.DB.prepare(
      "INSERT OR IGNORE INTO messages(session_id, direction, text, telegram_message_id, entities_json, delivery_status, created_at) " +
        "VALUES (?, 'operator', ?, ?, ?, ?, ?) RETURNING *",
    )
      .bind(session.id, operatorText, message.message_id, entitiesJson, route === "site" ? "delivered" : "pending", now)
      .first<MessageRow>();
    if (!operatorMessage) {
      operatorMessage = await env.DB.prepare("SELECT * FROM messages WHERE telegram_message_id = ?")
        .bind(message.message_id)
        .first<MessageRow>();
    }
  }
  if (!operatorMessage) return;

  if (route === "max" && operatorMessage.delivery_status !== "delivered") {
    if (!session.max_user_id) throw new ApiError(500, "max_session_missing_user", "Не найден получатель MAX.", true);
    try {
      await sendMaxMessage(env, session.max_user_id, operatorMessage.text);
      await env.DB.prepare("UPDATE messages SET delivery_status = 'delivered' WHERE id = ?")
        .bind(operatorMessage.id)
        .run();
    } catch (error) {
      await env.DB.prepare("UPDATE messages SET delivery_status = 'failed' WHERE id = ?")
        .bind(operatorMessage.id)
        .run();
      throw error;
    }
  } else if (route === "telegram" && operatorMessage.delivery_status !== "delivered") {
    try {
      await deliverOperatorMessageToTelegram(env, session, operatorMessage.text, entities);
      await env.DB.prepare("UPDATE messages SET delivery_status = 'delivered' WHERE id = ?")
        .bind(operatorMessage.id)
        .run();
    } catch (error) {
      await env.DB.prepare("UPDATE messages SET delivery_status = 'failed' WHERE id = ?")
        .bind(operatorMessage.id)
        .run();
      if (isTelegramRecipientUnavailable(error)) {
        await env.DB.prepare(
          "UPDATE sessions SET status = 'closed', closed_at = ?, last_activity_at = ? WHERE id = ?",
        )
          .bind(now, now, session.id)
          .run();
        await telegramApi(env, "sendMessage", {
          chat_id: boundChatId,
          message_thread_id: message.message_thread_id,
          text: "Ответ не доставлен: пользователь остановил или заблокировал бота. Диалог закрыт.",
        });
        await closeTelegramTopic(env, boundChatId, message.message_thread_id);
        return;
      }
      throw error;
    }
  }
  await env.DB.prepare("UPDATE sessions SET last_activity_at = ? WHERE id = ?")
    .bind(now, session.id)
    .run();
}

async function handleTelegramWebhook(request: Request, env: RuntimeEnv): Promise<Response> {
  requireTelegramConfiguration(env);
  const supplied = request.headers.get("X-Telegram-Bot-Api-Secret-Token") || "";
  if (!constantTimeEqual(supplied, env.TELEGRAM_WEBHOOK_SECRET || "")) {
    throw new ApiError(401, "invalid_telegram_secret", "Unauthorized");
  }
  const update = await readJsonBody<TelegramUpdate>(request, MAX_TELEGRAM_BODY_BYTES);
  if (update.callback_query) await handleTelegramCallback(update, env);
  if (update.message) await handleTelegramMessage(update.message, env);
  return json({ ok: true });
}

async function handleMaxWebhook(request: Request, env: RuntimeEnv): Promise<Response> {
  requireMaxConfiguration(env);
  const supplied = request.headers.get("X-Max-Bot-Api-Secret") || "";
  if (!constantTimeEqual(supplied, env.MAX_WEBHOOK_SECRET || "")) {
    console.log(JSON.stringify({ event: "max_webhook_rejected", reason: "secret_mismatch" }));
    throw new ApiError(401, "invalid_max_secret", "Unauthorized");
  }
  const update = await readJsonBody<MaxUpdate>(request, MAX_TELEGRAM_BODY_BYTES);
  console.log(JSON.stringify({
    event: "max_webhook_received",
    updateType: update.update_type || "unknown",
    directMessage: update.update_type === "message_created" ? isDirectMaxMessage(update) : undefined,
  }));
  if (update.update_type === "bot_started") {
    const userId = maxUserId(update.user);
    if (userId && String(update.chat_id || "") === userId) {
      await sendMaxMessage(env, userId, "Здравствуйте! Напишите сообщение — я передам его группе. Чтобы завершить диалог, отправьте /close.");
    }
  } else if (update.update_type === "message_created") {
    await sendMaxVisitorMessage(update, env);
  } else if (update.update_type === "bot_stopped" || update.update_type === "dialog_removed") {
    const userId = maxUserId(update.user);
    if (userId) {
      const session = await env.DB.prepare("SELECT * FROM sessions WHERE source = 'max' AND max_user_id = ? AND status = 'open'")
        .bind(userId)
        .first<SessionRow>();
      if (session) await closeFromMax(session, env, false);
    }
  }
  return json({ ok: true });
}

async function registerMaxWebhook(request: Request, env: RuntimeEnv): Promise<Response> {
  requireMaxConfiguration(env);
  const supplied = request.headers.get("X-Max-Bot-Api-Secret") || "";
  if (!constantTimeEqual(supplied, env.MAX_WEBHOOK_SECRET || "")) {
    throw new ApiError(401, "invalid_max_secret", "Unauthorized");
  }
  await maxApi<unknown>(env, "/subscriptions", {
    url: MAX_WEBHOOK_URL,
    update_types: ["bot_started", "bot_stopped", "dialog_removed", "message_created"],
    secret: env.MAX_WEBHOOK_SECRET,
  });
  return json({ ok: true, webhook: MAX_WEBHOOK_URL });
}

async function registerTelegramWebhook(request: Request, env: RuntimeEnv): Promise<Response> {
  requireTelegramConfiguration(env);
  const supplied = request.headers.get("Authorization") || "";
  if (!constantTimeEqual(supplied, "Bearer " + env.TELEGRAM_BOT_TOKEN)) {
    throw new ApiError(401, "invalid_telegram_registration_token", "Unauthorized");
  }
  const origin = new URL(request.url).origin;
  const registeredAt = Number.parseInt((await getSetting(env, "webhook_registered_at")) || "0", 10);
  if (registeredAt > nowSeconds() - 600 && (await getSetting(env, "webhook_origin")) === origin) {
    return json({ ok: true, webhook: origin + "/telegram/webhook", unchanged: true });
  }
  const registered = await telegramApi<boolean>(env, "setWebhook", {
    url: origin + "/telegram/webhook",
    secret_token: env.TELEGRAM_WEBHOOK_SECRET,
    allowed_updates: ["message", "callback_query"],
    drop_pending_updates: false,
  });
  await telegramApi<boolean>(env, "setMyCommands", {
    scope: { type: "all_private_chats" },
    commands: [
      { command: "start", description: "Начать анонимный разговор" },
      { command: "close", description: "Завершить текущий диалог" },
    ],
  });
  await setSetting(env, "webhook_origin", origin);
  await setSetting(env, "webhook_registered_at", String(nowSeconds()));
  return json({ ok: registered, webhook: origin + "/telegram/webhook" });
}

async function health(env: RuntimeEnv): Promise<Response> {
  await env.DB.prepare("SELECT 1").first();
  return json({
    ok: true,
    version: "1.3.0",
    telegramConfigured: Boolean(env.TELEGRAM_BOT_TOKEN && env.TELEGRAM_WEBHOOK_SECRET),
    telegramPrivateConfigured: Boolean(env.TELEGRAM_CHAT_ENCRYPTION_KEY && env.TELEGRAM_CHAT_ENCRYPTION_KEY.length >= 32),
    maxConfigured: Boolean(env.MAX_BOT_TOKEN && env.MAX_WEBHOOK_SECRET && env.MAX_PROXY_SECRET),
    operatorGroupBound: Boolean(await getSetting(env, "operator_chat_id")),
    retentionDays: positiveInteger(env.RETENTION_DAYS, RETENTION_FALLBACK_DAYS),
  });
}

async function cleanupExpired(env: RuntimeEnv): Promise<void> {
  const now = nowSeconds();
  const cutoff = now - positiveInteger(env.RETENTION_DAYS, RETENTION_FALLBACK_DAYS) * 86400;
  const expired = await env.DB.prepare(
    "SELECT id, telegram_thread_id FROM sessions WHERE last_activity_at < ? ORDER BY last_activity_at ASC LIMIT 40",
  )
    .bind(cutoff)
    .all<{ id: string; telegram_thread_id: number | null }>();
  const chatId = await getSetting(env, "operator_chat_id");
  let topicsClosed = 0;
  if (chatId) {
    for (const session of expired.results) {
      if (session.telegram_thread_id) {
        try {
          await closeTelegramTopic(env, chatId, session.telegram_thread_id);
          topicsClosed++;
        } catch {
          // A Telegram outage must not keep expired private messages in D1.
        }
      }
    }
  }
  // Close at most 40 Telegram topics per run, but remove every expired D1
  // session (and its messages through ON DELETE CASCADE) regardless of backlog.
  const deleted = await env.DB.prepare("DELETE FROM sessions WHERE last_activity_at < ?")
    .bind(cutoff)
    .run();
  await env.DB.prepare("DELETE FROM rate_limits WHERE expires_at < ?").bind(now).run();
  console.log(JSON.stringify({
    event: "support_chat_cleanup",
    sessionsDeleted: deleted.meta.changes,
    topicsClosed,
  }));
}

async function route(request: Request, env: RuntimeEnv, ctx: ExecutionContext): Promise<Response> {
  const url = new URL(request.url);
  if (request.method === "GET" && url.pathname === "/health") return health(env);
  if (request.method === "POST" && url.pathname === "/telegram/register") {
    return registerTelegramWebhook(request, env);
  }
  if (request.method === "POST" && url.pathname === "/telegram/webhook") {
    return handleTelegramWebhook(request, env);
  }
  if (request.method === "POST" && url.pathname === "/max/webhook") {
    return handleMaxWebhook(request, env);
  }
  if (request.method === "POST" && url.pathname === "/max/register") {
    return registerMaxWebhook(request, env);
  }

  if (!url.pathname.startsWith("/api/chat/")) {
    throw new ApiError(404, "not_found", "Not found");
  }
  const origin = request.headers.get("Origin") || "";
  if (request.method === "OPTIONS") {
    if (!requestOriginAllowed(request, env)) throw new ApiError(403, "origin_forbidden", "Origin forbidden");
    return new Response(null, { status: 204, headers: corsHeaders(origin) });
  }
  if (!requestOriginAllowed(request, env)) {
    throw new ApiError(403, "origin_forbidden", "Origin forbidden");
  }

  let response: Response;
  const customEmojiMatch = url.pathname.match(/^\/api\/chat\/custom-emoji\/(\d{5,30})$/);
  if (request.method === "GET" && customEmojiMatch) {
    response = await customEmojiImage(request, env, ctx, customEmojiMatch[1]);
  } else if (request.method === "POST" && url.pathname === "/api/chat/session") {
    response = await createSession(request, env);
  } else if (request.method === "POST" && url.pathname === "/api/chat/messages") {
    response = await sendVisitorMessage(request, env);
  } else if (request.method === "GET" && url.pathname === "/api/chat/messages") {
    response = await listMessages(request, env);
  } else if (request.method === "POST" && url.pathname === "/api/chat/close") {
    response = await closeFromWebsite(request, env, ctx);
  } else {
    throw new ApiError(404, "not_found", "Not found");
  }
  const headers = new Headers(response.headers);
  if (origin) {
    for (const [key, value] of Object.entries(corsHeaders(origin))) headers.set(key, value);
  }
  return new Response(response.body, { status: response.status, headers });
}

// Небольшой публичный шов только для unit-тестов формата MAX-вебхука.
export const maxTestHelpers = {
  maxMessageIdentifier,
  isDirectMaxMessage,
  isMissingTelegramTopicResponse,
  isDedicatedTelegramThreadId,
  telegramMessageMatchesThread,
  maxTelegramText,
};

export const telegramTestHelpers = {
  telegramPrivateChatId,
  telegramMessageIdentifier,
  privateTelegramText,
};

export default {
  async fetch(request, env, ctx) {
    try {
      return await route(request, env, ctx);
    } catch (error) {
      if (error instanceof ApiError) {
        const origin = request.headers.get("Origin") || "";
        const headers = origin && requestOriginAllowed(request, env) ? corsHeaders(origin) : {};
        return json(
          { ok: false, error: { code: error.code, message: error.message, retryable: error.retryable } },
          error.status,
          headers,
        );
      }
      console.error(
        JSON.stringify({
          event: "support_chat_unhandled_error",
          method: request.method,
          path: new URL(request.url).pathname,
          error: error instanceof Error ? error.name : "unknown",
        }),
      );
      return json({ ok: false, error: { code: "internal_error", message: "Временная ошибка сервера.", retryable: true } }, 500);
    }
  },

  async scheduled(_controller, env, ctx) {
    ctx.waitUntil(cleanupExpired(env));
  },
} satisfies ExportedHandler<RuntimeEnv>;
