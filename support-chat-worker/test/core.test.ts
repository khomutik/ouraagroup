import { describe, expect, it } from "vitest";
import {
  constantTimeEqual,
  decryptSecretString,
  encryptSecretString,
  isAllowedOrigin,
  isValidClientMessageId,
  normalizeTelegramCustomEmojiEntities,
  normalizeMessageText,
  rateLimitAddress,
  sanitizePagePath,
  unicodeLength,
  validateMessageText,
  visitorCode,
} from "../src/core";

describe("message validation", () => {
  it("normalizes line endings and trims only the outside", () => {
    expect(normalizeMessageText("  строка 1\r\nстрока 2  ")).toBe("строка 1\nстрока 2");
  });

  it("counts an emoji as one Unicode character", () => {
    expect(unicodeLength("а🙂б")).toBe(3);
  });

  it("rejects empty and overlong messages", () => {
    expect(() => validateMessageText("   ")).toThrow("message_empty");
    expect(() => validateMessageText("1234", 3)).toThrow("message_too_long");
  });

  it("keeps Telegram custom emoji positions after trimming", () => {
    expect(
      normalizeTelegramCustomEmojiEntities("  a😀b  ", [
        { type: "custom_emoji", offset: 3, length: 2, custom_emoji_id: "5373141891321699086" },
      ]),
    ).toEqual([
      { type: "custom_emoji", offset: 1, length: 2, customEmojiId: "5373141891321699086" },
    ]);
  });
});

describe("request safety", () => {
  it("allows only configured exact origins", () => {
    const configured = "https://pochtinormalnye.ru,https://www.pochtinormalnye.ru";
    expect(isAllowedOrigin("https://pochtinormalnye.ru", configured)).toBe(true);
    expect(isAllowedOrigin("https://evil.example", configured)).toBe(false);
  });

  it("keeps only a local page path", () => {
    expect(sanitizePagePath("/newcomers.html#help")).toBe("/newcomers.html#help");
    expect(sanitizePagePath("https://evil.example/")).toBe("/");
    expect(sanitizePagePath("//evil.example/")).toBe("/");
  });

  it("validates idempotency identifiers", () => {
    expect(isValidClientMessageId("b80b9bb0-a41b-4d8e-a6e1-a2a34bd72d88")).toBe(true);
    expect(isValidClientMessageId("oops!")).toBe(false);
  });

  it("derives a short anonymous visitor code", () => {
    expect(visitorCode("a7f31234-5678-9abc-def0-123456789abc")).toBe("A7F3");
  });

  it("compares secrets without an early character exit", () => {
    expect(constantTimeEqual("secret", "secret")).toBe(true);
    expect(constantTimeEqual("secret", "secrex")).toBe(false);
    expect(constantTimeEqual("short", "longer")).toBe(false);
  });

  it("accepts a visitor address only from the trusted website proxy", () => {
    expect(rateLimitAddress("89.124.123.138", "203.0.113.7", "89.124.123.138")).toBe("203.0.113.7");
    expect(rateLimitAddress("198.51.100.4", "203.0.113.7", "89.124.123.138")).toBe("198.51.100.4");
    expect(rateLimitAddress("89.124.123.138", "not-an-ip", "89.124.123.138")).toBe("89.124.123.138");
  });

  it("encrypts a Telegram chat address and rejects tampering", async () => {
    const secret = "a-long-unit-test-secret-that-is-not-used-in-production";
    const encrypted = await encryptSecretString(secret, "123456789");
    expect(encrypted).not.toContain("123456789");
    await expect(decryptSecretString(secret, encrypted)).resolves.toBe("123456789");
    await expect(decryptSecretString(secret, encrypted.slice(0, -1) + "A")).rejects.toThrow();
  });
});
