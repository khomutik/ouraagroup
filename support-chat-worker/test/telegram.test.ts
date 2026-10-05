import { describe, expect, it } from "vitest";
import worker, { telegramTestHelpers } from "../src/index";

describe("Telegram private chat guards", () => {
  it("rejects unauthenticated webhook registration before calling Telegram", async () => {
    const request = new Request("https://pn-support-chat.pochtinormalnye.workers.dev/telegram/register", {
      method: "POST",
    });
    const env = {
      TELEGRAM_BOT_TOKEN: "test-token",
      TELEGRAM_WEBHOOK_SECRET: "test-webhook-secret",
    };
    const response = await worker.fetch(request as any, env as any, {} as any);
    expect(response.status).toBe(401);
    expect((await response.json() as { error: { code: string } }).error.code).toBe("invalid_telegram_registration_token");
  });

  it("deletes all expired D1 sessions even when topic closure is capped", async () => {
    const queries: string[] = [];
    const db = {
      prepare(sql: string) {
        queries.push(sql);
        return {
          bind() { return this; },
          async all() { return { results: [] }; },
          async first() { return null; },
          async run() { return { meta: { changes: 55 } }; },
        };
      },
    };
    const work: Promise<unknown>[] = [];
    worker.scheduled({} as any, { DB: db, RETENTION_DAYS: "30" } as any, {
      waitUntil(task: Promise<unknown>) { work.push(task); },
    } as any);
    await Promise.all(work);
    expect(queries).toContain("DELETE FROM sessions WHERE last_activity_at < ?");
    expect(queries).toContain("DELETE FROM rate_limits WHERE expires_at < ?");
  });

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
