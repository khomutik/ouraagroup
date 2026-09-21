import { describe, expect, it } from "vitest";
import { maxTestHelpers } from "../src/index";

describe("MAX webhook guards", () => {
  it("accepts only a direct dialogue with the bot", () => {
    expect(maxTestHelpers.isDirectMaxMessage({
      message: { sender: { user_id: 42 }, recipient: { chat_id: 777, chat_type: "dialog", user_id: 900 }, body: { text: "Привет" } },
    })).toBe(true);
    expect(maxTestHelpers.isDirectMaxMessage({
      message: { sender: { user_id: 42 }, recipient: { chat_id: 777, chat_type: "chat" }, body: { text: "Сообщение группы" } },
    })).toBe(false);
  });

  it("forwards MAX messages under an anonymous code only", () => {
    expect(maxTestHelpers.maxTelegramText("AB12", "Нужна помощь", true)).toBe(
      "Новое анонимное обращение из MAX\n\nMAX · Аноним AB12\n\nНужна помощь",
    );
  });

  it("derives a stable idempotency key from the MAX message id", () => {
    expect(maxTestHelpers.maxMessageIdentifier({ message: { body: { mid: "abc-123" } } })).toBe("max:abc-123");
  });

  it("recognizes a deleted or closed Telegram topic", () => {
    expect(maxTestHelpers.isMissingTelegramTopicResponse(400, "Bad Request: message thread not found")).toBe(true);
    expect(maxTestHelpers.isMissingTelegramTopicResponse(400, "Bad Request: TOPIC_CLOSED")).toBe(true);
    expect(maxTestHelpers.isMissingTelegramTopicResponse(400, "Bad Request: TOPIC_ID_INVALID")).toBe(true);
    expect(maxTestHelpers.isMissingTelegramTopicResponse(429, "Too Many Requests")).toBe(false);
  });

  it("never treats General or a mismatched Telegram topic as the destination", () => {
    expect(maxTestHelpers.isDedicatedTelegramThreadId(1)).toBe(false);
    expect(maxTestHelpers.isDedicatedTelegramThreadId(85)).toBe(true);
    expect(maxTestHelpers.telegramMessageMatchesThread(85, 85)).toBe(true);
    expect(maxTestHelpers.telegramMessageMatchesThread(85, 1)).toBe(false);
    expect(maxTestHelpers.telegramMessageMatchesThread(85, undefined)).toBe(false);
  });
});
