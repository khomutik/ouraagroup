import { randomBytes } from "node:crypto";
import { spawn } from "node:child_process";
import { createServer } from "node:http";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const projectDirectory = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const wranglerEntry = resolve(projectDirectory, "node_modules", "wrangler", "bin", "wrangler.js");
const nonce = randomBytes(24).toString("hex");
const maximumBodyBytes = 4096;

function page(title, body) {
  return `<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${title}</title><style>body{display:grid;place-items:center;min-height:100vh;margin:0;padding:18px;box-sizing:border-box;color:#111b45;background:#111b45;font:16px system-ui,sans-serif}.card{width:min(520px,100%);box-sizing:border-box;padding:26px;border-radius:18px;background:#fff8ea;box-shadow:0 20px 60px #0006}h1{margin:0 0 12px;font-size:25px}p{line-height:1.45}label{display:grid;gap:7px;font-weight:700}input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #8c96bd;border-radius:10px;font:16px monospace}button{margin-top:14px;padding:11px 18px;border:0;border-radius:999px;color:#fff;background:#df5b0b;font:inherit;font-weight:800;cursor:pointer}.note{color:#59617a;font-size:13px}</style></head><body><main class="card">${body}</main></body></html>`;
}

function send(response, status, html) {
  response.writeHead(status, {
    "Content-Type": "text/html; charset=utf-8",
    "Cache-Control": "no-store",
    "Content-Security-Policy": "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
    "X-Content-Type-Options": "nosniff",
  });
  response.end(html);
}

function run(command, args, input = "") {
  return new Promise((resolvePromise, rejectPromise) => {
    const child = spawn(command, args, {
      cwd: projectDirectory,
      shell: false,
      stdio: ["pipe", "pipe", "pipe"],
      windowsHide: true,
    });
    let output = "";
    child.stdout.on("data", (chunk) => (output += String(chunk).slice(0, 6000)));
    child.stderr.on("data", (chunk) => (output += String(chunk).slice(0, 6000)));
    child.on("error", rejectPromise);
    child.on("close", (code) => {
      if (code === 0) resolvePromise(output);
      else rejectPromise(new Error(`${command} завершился с кодом ${code}: ${output.slice(-1200)}`));
    });
    child.stdin.end(input);
  });
}

async function configure(token) {
  await run(process.execPath, [wranglerEntry, "secret", "put", "TELEGRAM_BOT_TOKEN"], token + "\n");
  await run(process.execPath, [wranglerEntry, "deploy"]);
  const registerResponse = await fetch("https://pn-support-chat.pochtinormalnye.workers.dev/telegram/register", {
    method: "POST",
  });
  if (!registerResponse.ok) throw new Error("Telegram не принял регистрацию webhook.");
  const healthResponse = await fetch("https://pn-support-chat.pochtinormalnye.workers.dev/health", {
    cache: "no-store",
  });
  const health = await healthResponse.json();
  if (!health.telegramConfigured) throw new Error("Cloudflare не видит сохранённый токен.");
  return health;
}

const server = createServer((request, response) => {
  const url = new URL(request.url || "/", "http://127.0.0.1");
  if (request.method === "GET" && url.pathname === `/${nonce}`) {
    send(
      response,
      200,
      page(
        "Настройка Telegram-бота",
        `<h1>Подключить Telegram-бота</h1><p>Вставьте <strong>новый</strong> токен от BotFather. Он уйдёт напрямую в защищённые секреты Cloudflare и не будет записан в файл.</p><form method="post" action="/save/${nonce}"><label>Токен бота<input name="token" type="password" required autocomplete="off" spellcheck="false" autofocus></label><button type="submit">Сохранить и подключить</button></form><p class="note">Страница работает только на вашем компьютере и закроется после настройки.</p>`,
      ),
    );
    return;
  }

  if (request.method === "POST" && url.pathname === `/save/${nonce}`) {
    let body = "";
    request.on("data", (chunk) => {
      body += String(chunk);
      if (Buffer.byteLength(body) > maximumBodyBytes) request.destroy();
    });
    request.on("end", async () => {
      let token = new URLSearchParams(body).get("token")?.trim() || "";
      if (!/^\d{6,14}:[A-Za-z0-9_-]{30,100}$/.test(token)) {
        send(response, 400, page("Неверный токен", `<h1>Токен не похож на токен Telegram</h1><p>Вернитесь назад и скопируйте всю строку после сообщения BotFather.</p>`));
        return;
      }
      try {
        await configure(token);
        token = "";
        send(response, 200, page("Бот подключён", `<h1>Готово ✓</h1><p>Токен сохранён в Cloudflare, Worker обновлён, webhook Telegram зарегистрирован.</p><p>Можно закрыть эту вкладку и написать Жорику: <strong>«форма показала Готово»</strong>.</p>`));
        setTimeout(() => server.close(), 60_000).unref();
      } catch (error) {
        token = "";
        const message = error instanceof Error ? error.message.replace(/[<>]/g, "") : "Неизвестная ошибка";
        send(response, 500, page("Ошибка настройки", `<h1>Не получилось</h1><p>${message}</p><p>Токен нигде не сохранён в открытом виде. Сообщите Жорику текст этой ошибки.</p>`));
      }
    });
    return;
  }

  send(response, 404, page("Страница не найдена", "<h1>Страница больше не действует</h1>"));
});

server.listen(0, "127.0.0.1", () => {
  const address = server.address();
  if (!address || typeof address === "string") throw new Error("Не удалось открыть локальную страницу.");
  console.log(`SETUP_URL=http://127.0.0.1:${address.port}/${nonce}`);
});
