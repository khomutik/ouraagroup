import { describe, expect, it } from "vitest";
import { telegramTestHelpers } from "../src/index";

describe("Telegram private chat guards", () => {
  it("accepts only a private message sent by that Telegram user", () => {
    expect(telegramTestHelpers.telegramPrivateChatId({
      message_id: 1,
      chat: { id: 42, type: "private" },
      from: { id: 42 },
    })).toBe("42");
    expect(telegramTestHelpers.telegramPrivateChatId({
      message_id: 1,
      chat: { id: -100123, type: "supergroup" },
      from: { id: 42 },
    })).toBe("");
    expect(telegramTestHelpers.telegramPrivateChatId({
      message_id: 1,
      chat: { id: 42, type: "private" },
      from: { id: 7 },
    })).toBe("");
  });

  it("shows only an anonymous code to the operator group", () => {
    expect(telegramTestHelpers.privateTelegramText("A7F3", "Нужна помощь", true)).toBe(
      "Новое анонимное обращение из Telegram\n\nTelegram · Аноним A7F3\n\nНужна помощь",
    );
  });

  it("builds an idempotency key without the Telegram chat id", () => {
    const key = telegramTestHelpers.telegramMessageIdentifier("abcdef0123456789abcdef0123456789abcdef", 77);
    expect(key).toBe("telegram:abcdef0123456789abcdef0123456789:77");
    expect(key).not.toContain("9988776655");
  });
});
