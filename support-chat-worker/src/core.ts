const encoder = new TextEncoder();

export const DEFAULT_MAX_MESSAGE_LENGTH = 2000;

export type TelegramTextEntity = {
  type: string;
  offset: number;
  length: number;
  custom_emoji_id?: string;
};

export type PublicCustomEmojiEntity = {
  type: "custom_emoji";
  offset: number;
  length: number;
  customEmojiId: string;
};

export function normalizeMessageText(value: unknown): string {
  if (typeof value !== "string") return "";
  return value.replace(/\r\n?/g, "\n").trim();
}

export function unicodeLength(value: string): number {
  return Array.from(value).length;
}

export function validateMessageText(value: unknown, maximum = DEFAULT_MAX_MESSAGE_LENGTH): string {
  const text = normalizeMessageText(value);
  if (!text) throw new Error("message_empty");
  if (unicodeLength(text) > maximum) throw new Error("message_too_long");
  return text;
}

export function normalizeTelegramCustomEmojiEntities(
  rawText: string,
  entities: TelegramTextEntity[] | undefined,
): PublicCustomEmojiEntity[] {
  if (!Array.isArray(entities) || !rawText) return [];
  const leadingLength = rawText.length - rawText.trimStart().length;
  const normalizedLength = rawText.trim().length;
  const contentEnd = leadingLength + normalizedLength;

  return entities
    .filter((entity) =>
      entity.type === "custom_emoji" &&
      Number.isInteger(entity.offset) &&
      Number.isInteger(entity.length) &&
      entity.length > 0 &&
      entity.offset >= leadingLength &&
      entity.offset + entity.length <= contentEnd &&
      /^\d{5,30}$/.test(String(entity.custom_emoji_id || "")),
    )
    .map((entity) => ({
      type: "custom_emoji" as const,
      offset: entity.offset - leadingLength,
      length: entity.length,
      customEmojiId: String(entity.custom_emoji_id),
    }))
    .sort((left, right) => left.offset - right.offset);
}

export function sanitizePagePath(value: unknown): string {
  if (typeof value !== "string") return "/";
  const trimmed = value.trim();
  if (!trimmed.startsWith("/") || trimmed.startsWith("//")) return "/";
  return trimmed.slice(0, 240).replace(/[\u0000-\u001f\u007f]/g, "");
}

export function parseAllowedOrigins(value: string | undefined): Set<string> {
  return new Set(
    String(value || "")
      .split(",")
      .map((origin) => origin.trim())
      .filter(Boolean),
  );
}

export function isAllowedOrigin(origin: string | null, configured: string | undefined): boolean {
  if (!origin) return false;
  return parseAllowedOrigins(configured).has(origin);
}

function normalizeIpAddress(value: string | null | undefined): string | null {
  const address = String(value || "").trim();
  return /^[0-9a-f:.]{3,45}$/i.test(address) ? address : null;
}

export function rateLimitAddress(
  directAddress: string | null,
  proxyAddress: string | null,
  trustedProxyIp: string | undefined,
): string {
  const direct = normalizeIpAddress(directAddress);
  const trusted = normalizeIpAddress(trustedProxyIp);
  if (direct && trusted && direct === trusted) {
    const forwarded = normalizeIpAddress(proxyAddress);
    if (forwarded) return forwarded;
  }
  return direct || "local-development";
}

export function visitorCode(sessionId: string): string {
  return sessionId.replace(/-/g, "").slice(0, 4).toUpperCase();
}

export function isValidClientMessageId(value: unknown): value is string {
  return typeof value === "string" && /^[A-Za-z0-9_-]{8,80}$/.test(value);
}

export function bytesToHex(bytes: ArrayBuffer): string {
  return Array.from(new Uint8Array(bytes), (byte) => byte.toString(16).padStart(2, "0")).join("");
}

export async function sha256Hex(value: string): Promise<string> {
  return bytesToHex(await crypto.subtle.digest("SHA-256", encoder.encode(value)));
}

export async function hmacSha256Hex(secret: string, value: string): Promise<string> {
  const key = await crypto.subtle.importKey(
    "raw",
    encoder.encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  return bytesToHex(await crypto.subtle.sign("HMAC", key, encoder.encode(value)));
}

function bytesToBase64Url(bytes: Uint8Array): string {
  let binary = "";
  for (const byte of bytes) binary += String.fromCharCode(byte);
  return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
}

function base64UrlToBytes(value: string): Uint8Array<ArrayBuffer> {
  if (!/^[A-Za-z0-9_-]+$/.test(value)) throw new Error("invalid_encrypted_value");
  const padded = value.replace(/-/g, "+").replace(/_/g, "/") + "===".slice((value.length + 3) % 4);
  const binary = atob(padded);
  const bytes = new Uint8Array(new ArrayBuffer(binary.length));
  for (let index = 0; index < binary.length; index += 1) bytes[index] = binary.charCodeAt(index);
  return bytes;
}

async function secretEncryptionKey(secret: string): Promise<CryptoKey> {
  if (secret.length < 32) throw new Error("invalid_encryption_secret");
  const keyBytes = await crypto.subtle.digest(
    "SHA-256",
    encoder.encode("pn-support-telegram-chat-v1\u0000" + secret),
  );
  return crypto.subtle.importKey("raw", keyBytes, { name: "AES-GCM" }, false, ["encrypt", "decrypt"]);
}

export async function encryptSecretString(secret: string, value: string): Promise<string> {
  const iv = new Uint8Array(12);
  crypto.getRandomValues(iv);
  const encrypted = await crypto.subtle.encrypt(
    { name: "AES-GCM", iv },
    await secretEncryptionKey(secret),
    encoder.encode(value),
  );
  return "v1." + bytesToBase64Url(iv) + "." + bytesToBase64Url(new Uint8Array(encrypted));
}

export async function decryptSecretString(secret: string, payload: string): Promise<string> {
  const parts = payload.split(".");
  if (parts.length !== 3 || parts[0] !== "v1") throw new Error("invalid_encrypted_value");
  const iv = base64UrlToBytes(parts[1]);
  const encrypted = base64UrlToBytes(parts[2]);
  if (iv.byteLength !== 12 || encrypted.byteLength < 17) throw new Error("invalid_encrypted_value");
  const decrypted = await crypto.subtle.decrypt(
    { name: "AES-GCM", iv },
    await secretEncryptionKey(secret),
    encrypted,
  );
  return new TextDecoder().decode(decrypted);
}

export function constantTimeEqual(left: string, right: string): boolean {
  const leftBytes = encoder.encode(left);
  const rightBytes = encoder.encode(right);
  if (leftBytes.byteLength !== rightBytes.byteLength) return false;
  let mismatch = 0;
  for (let index = 0; index < leftBytes.length; index += 1) {
    mismatch |= leftBytes[index] ^ rightBytes[index];
  }
  return mismatch === 0;
}

export function randomToken(byteLength = 32): string {
  const bytes = new Uint8Array(byteLength);
  crypto.getRandomValues(bytes);
  let binary = "";
  for (const byte of bytes) binary += String.fromCharCode(byte);
  return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
}
