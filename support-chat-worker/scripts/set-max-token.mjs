import { randomBytes } from "node:crypto";
import { spawn } from "node:child_process";
import { createServer } from "node:http";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const projectDirectory = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const wranglerEntry = resolve(projectDirectory, "node_modules", "wrangler", "bin", "wrangler.js");
const nonce = randomBytes(24).toString("hex");
function page(title, body) {
  return `<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${title}</title><style>body{display:grid;place-items:center;min-height:100vh;margin:0;padding:18px;box-sizing:border-box;color:#111b45;background:#111b45;font:16px system-ui,sans-serif}.card{width:min(520px,100%);box-sizing:border-box;padding:26px;border-radius:18px;background:#fff8ea;box-shadow:0 20px 60px #0006}h1{margin:0 0 12px;font-size:25px}p{line-height:1.45}label{display:grid;gap:7px;font-weight:700}input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #8c96bd;border-radius:10px;font:16px monospace}button{margin-top:14px;padding:11px 18px;border:0;border-radius:999px;color:#fff;background:#df5b0b;font:inherit;font-weight:800;cursor:pointer}.note{color:#59617a;font-size:13px}</style></head><body><main class="card">${body}</main></body></html>`;
}

function send(response, status, html) {
  response.writeHead(status, {"Content-Type":"text/html; charset=utf-8","Cache-Control":"no-store","Content-Security-Policy":"default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'","X-Content-Type-Options":"nosniff"});
  response.end(html);
}

function run(command, args, input = "") {
  return new Promise((resolvePromise, rejectPromise) => {
    const child = spawn(command, args, {cwd:projectDirectory, shell:false, stdio:["pipe","pipe","pipe"], windowsHide:true});
    let output = "";
    child.stdout.on("data", (chunk) => output += String(chunk).slice(0, 4000));
    child.stderr.on("data", (chunk) => output += String(chunk).slice(0, 4000));
    child.on("error", rejectPromise);
    child.on("close", (code) => code === 0 ? resolvePromise(output) : rejectPromise(new Error(`${command} завершился с кодом ${code}: ${output.slice(-900)}`)));
    child.stdin.end(input);
  });
}

async function configure(token) {
  const secret = randomBytes(32).toString("base64url");
  await run(process.execPath, [wranglerEntry, "secret", "put", "MAX_BOT_TOKEN"], token + "\n");
  await run(process.execPath, [wranglerEntry, "secret", "put", "MAX_WEBHOOK_SECRET"], secret + "\n");
  await run(process.execPath, [wranglerEntry, "deploy"]);
  const response = await fetch("https://pn-support-chat.pochtinormalnye.workers.dev/max/register", {
    method: "POST",
    headers: {"X-Max-Bot-Api-Secret":secret},
  });
  if (!response.ok) throw new Error("Cloudflare не смог зарегистрировать webhook MAX. Проверьте, что бот прошёл модерацию, а токен скопирован целиком.");
  const healthResponse = await fetch("https://pn-support-chat.pochtinormalnye.workers.dev/health", {cache:"no-store"});
  const health = await healthResponse.json();
  if (!health.maxConfigured) throw new Error("Cloudflare не видит сохранённый токен MAX.");
  return health;
}

const server = createServer((request, response) => {
  const url = new URL(request.url || "/", "http://127.0.0.1");
  if (request.method === "GET" && url.pathname === `/${nonce}`) {
    send(response, 200, page("Подключение MAX-бота", `<h1>Подключить MAX-бота</h1><p>Вставьте токен уже созданного и одобренного MAX-бота. Он уйдёт прямо в защищённые секреты Cloudflare и не запишется в файл.</p><form method="post" action="/save/${nonce}"><label>Токен MAX<input name="token" type="password" required autocomplete="off" spellcheck="false" autofocus></label><button type="submit">Сохранить и подключить</button></form><p class="note">Страница работает только на этом компьютере и закроется после настройки.</p>`));
    return;
  }
  if (request.method === "POST" && url.pathname === `/save/${nonce}`) {
    let body = "";
    request.on("data", (chunk) => { body += String(chunk); if (Buffer.byteLength(body) > 4096) request.destroy(); });
    request.on("end", async () => {
      let token = new URLSearchParams(body).get("token")?.trim() || "";
      if (!/^\S{20,512}$/.test(token)) { send(response,400,page("Неверный токен", "<h1>Токен не похож на токен MAX</h1><p>Вернитесь назад и скопируйте строку целиком.</p>")); return; }
      try {
        await configure(token); token = "";
        send(response,200,page("MAX-бот подключён", "<h1>Готово ✓</h1><p>Токен сохранён в Cloudflare, webhook зарегистрирован. Можно закрыть вкладку.</p>"));
      } catch (error) {
        token = "";
        const message = error instanceof Error ? error.message.replace(/[<>]/g, "") : "Неизвестная ошибка";
        send(response,500,page("Ошибка настройки", `<h1>Не получилось</h1><p>${message}</p><p>Токен нигде не сохранён в открытом виде.</p>`));
      }
    });
    return;
  }
  send(response,404,page("Страница не найдена", "<h1>Страница больше не действует</h1>"));
});

server.listen(0, "127.0.0.1", () => {
  const address = server.address();
  if (!address || typeof address === "string") throw new Error("Не удалось открыть локальную страницу.");
  console.log(`SETUP_URL=http://127.0.0.1:${address.port}/${nonce}`);
});
