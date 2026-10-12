import http from "node:http";
import { DockerOps } from "./docker-ops.mjs";
import { LocalProcessOps } from "./local-ops.mjs";
import { ZoomControlService } from "./service.mjs";
import { buildControlHtml, hideWorkerAdminPanel } from "./html.mjs";
import { buildZoomAppSessionCookie, decryptZoomAppContext, issueZoomAppSession, verifyZoomAppSession } from "./zoom-app-auth.mjs";
import { verifyStaffSession, workerPathRequiresAdmin } from "./staff-auth.mjs";

function env(name, fallback = "") { return String(process.env[name] || fallback).trim(); }
const config = {
  host: env("ZOOM_CONTROL_HOST", "127.0.0.1"),
  port: Number(env("ZOOM_CONTROL_PORT", "3098")),
  zoomAppCookieName: "nafanya_zoom_app",
  zoomAppClientSecret: env("ZOOM_APP_CLIENT_SECRET"),
  zoomAppSessionSecret: env("ZOOM_APP_SESSION_SECRET") || env("ZOOM_APP_CLIENT_SECRET"),
  projectDir: env("ZOOM_CONTROL_PROJECT_DIR", "/workspace"),
  envPath: env("ZOOM_CONTROL_ENV_PATH", "/workspace/.env"),
  profileDir: env("ZOOM_CONTROL_PROFILE_DIR", "/sender-profile"),
  diagnosticsDir: env("ZOOM_CONTROL_DIAGNOSTICS_DIR", "/workspace/diagnostics"),
  workerBaseUrl: env("WORKER_BASE_URL").replace(/\/+$/u, ""),
  zoomOnlySecret: env("ZOOM_ONLY_SECRET") || env("ZOOM_BRIDGE_SECRET"),
  panelToken: env("ZOOM_PANEL_TOKEN") || env("ZOOM_V2_PANEL_TOKEN"),
  healthUrl: env("ZOOM_CONTROL_HEALTH_URL", "http://host.docker.internal:3097/health")
};
if (!config.workerBaseUrl || !config.zoomOnlySecret || !config.panelToken) throw new Error("Missing control-agent configuration");

const ops = env("ZOOM_CONTROL_LOCAL_MODE", "false").toLowerCase() === "true" ? new LocalProcessOps(config) : new DockerOps(config);
const service = new ZoomControlService(ops);
const json = (res, status, body) => { res.writeHead(status, { "content-type": "application/json; charset=utf-8", "cache-control": "no-store" }); res.end(JSON.stringify(body)); };
const audit = (event, details = {}) => console.log(JSON.stringify({ event, at: new Date().toISOString(), ...details }));
const cookies = (request) => Object.fromEntries(String(request.headers.cookie || "").split(";").map((item) => item.trim().split("=")).filter(([key]) => key));
const zoomAppSession = (request) => verifyZoomAppSession(cookies(request)[config.zoomAppCookieName], config.zoomAppSessionSecret);
const zoomAppAuthorized = (request) => Boolean(zoomAppSession(request));
const accessLevel = async (request) => {
  if (zoomAppAuthorized(request)) return "zoom_app";
  return await verifyStaffSession(request, { authUrl: "https://pochtinormalnye.ru/nafanya-auth-check" });
};

const zoomAppHtmlHeaders = (sessionCookie = "") => ({
  "content-type": "text/html; charset=utf-8",
  "cache-control": "no-store",
  "content-security-policy": "default-src 'self'; object-src 'none'; base-uri 'none'; script-src 'self' 'unsafe-inline' https://appssdk.zoom.us; style-src 'self' 'unsafe-inline'; connect-src 'self'; img-src 'self' data:; frame-src 'self'",
  "cross-origin-resource-policy": "cross-origin",
  "referrer-policy": "no-referrer",
  "strict-transport-security": "max-age=31536000; includeSubDomains",
  "x-content-type-options": "nosniff",
  ...(sessionCookie ? { "set-cookie": sessionCookie } : {})
});
const zoomAppLockedHtml = "<!doctype html><html lang=\"ru\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>Nafanya Zoom</title></head><body><p>\u041e\u0442\u043a\u0440\u043e\u0439\u0442\u0435 \u043f\u0440\u0438\u043b\u043e\u0436\u0435\u043d\u0438\u0435 \u0432\u043d\u0443\u0442\u0440\u0438 \u043a\u043e\u043d\u0444\u0435\u0440\u0435\u043d\u0446\u0438\u0438 Zoom.</p></body></html>";
const workerPanelOffHtml = "<!doctype html><html lang=\"ru\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><style>body{margin:0;padding:18px;font:700 15px Arial,sans-serif;color:#4f5365;background:#fbf7ea}p{margin:0}</style><title>Nafanya Zoom</title></head><body><p>\u0412\u043a\u043b\u044e\u0447\u0438\u0442\u0435 \u041d\u0430\u0444\u0430\u043d\u044e, \u0447\u0442\u043e\u0431\u044b \u0437\u0430\u0433\u0440\u0443\u0437\u0438\u0442\u044c \u043f\u0443\u043b\u044c\u0442 \u0442\u0435\u0445\u0432\u0435\u0434\u0430.</p></body></html>";

async function proxyWorkerOnlyWhileSenderRuns(request, res, pathname, search = "", access = "admin") {
  const current = await service.status();
  if (!current.running) {
    if (pathname === "/zoom-only/app") {
      res.writeHead(200, { "content-type": "text/html; charset=utf-8", "cache-control": "no-store" });
      return res.end(workerPanelOffHtml);
    }
    return json(res, 409, { ok: false, error: "nafanya_off" });
  }
  return proxyWorker(request, res, pathname, search, access);
}

async function readRequestBody(request, maxBytes = 32768) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    let size = 0;
    request.on("data", (chunk) => {
      size += chunk.length;
      if (size > maxBytes) {
        reject(new Error("request_too_large"));
        request.destroy();
        return;
      }
      chunks.push(chunk);
    });
    request.on("end", () => resolve(Buffer.concat(chunks)));
    request.on("error", reject);
  });
}

async function proxyWorker(request, res, pathname, search = "", access = "admin", suppliedBody) {
  const body = suppliedBody === undefined && request.method === "POST" ? await readRequestBody(request) : suppliedBody;
  const query = new URLSearchParams(search);
  query.set("controlRequest", String(Date.now()));
  const workerPath = `${pathname}?${query}`;
  const response = await fetch(`${config.workerBaseUrl}${workerPath}`, {
    method: request.method,
    headers: { "x-nafanya-zoom-panel-token": config.panelToken, "content-type": request.headers["content-type"] || "application/json", "user-agent": "Nafanya-Zoom-Control/1.0" },
    body
  });
  const contentType = response.headers.get("content-type") || "application/json";
  res.writeHead(response.status, { "content-type": contentType, "cache-control": "no-store" });
  if (pathname === "/zoom-only/app" && contentType.includes("text/html")) {
    let html = (await response.text())
      .replaceAll('"/zoom-only/app/action"', '"./zoom-only/app/action"')
      .replaceAll('"/zoom-only/status"', '"./zoom-only/status"')
      .replaceAll('"/zoom-only/library/status"', '"./zoom-only/library/status"')
      .replaceAll('"/zoom-only/library/import"', '"./zoom-only/library/import"');
    html = hideWorkerAdminPanel(html, access);
    return res.end(html);
  }
  res.end(Buffer.from(await response.arrayBuffer()));
}

async function proxyTimer(request, res, access) {
  if (request.method !== "POST") return json(res, 405, { ok: false, error: "method_not_allowed" });
  const body = await readRequestBody(request);
  let payload;
  try {
    payload = JSON.parse(body.toString("utf8"));
  } catch {
    return json(res, 400, { ok: false, error: "invalid_json" });
  }
  const allowedTimerActions = new Set(["sync", "configure", "start", "pause", "extend", "reset"]);
  if (payload?.action !== "zoom_timer_action" || !allowedTimerActions.has(String(payload.timerAction || "sync"))) {
    return json(res, 400, { ok: false, error: "timer_action_required" });
  }
  return proxyWorker(request, res, "/zoom-only/app/action", "", access, body);
}

const server = http.createServer(async (request, res) => {
  try {
    const url = new URL(request.url, "http://control.local");
    if (url.pathname === "/health") return json(res, 200, { ok: true });
    if (url.pathname === "/app" && url.searchParams.has("token")) {
      res.writeHead(303, { location: "/nafanya-login?return=%2Fnafanya-zoom-control%2Fapp", "cache-control": "no-store" });
      return res.end();
    }
    if (request.method === "GET" && url.pathname === "/zoom-app") {
      if (!config.zoomAppClientSecret || !config.zoomAppSessionSecret) return json(res, 503, { ok: false, error: "zoom_app_not_configured" });
      try {
        const sessionContext = decryptZoomAppContext(request.headers["x-zoom-app-context"], config.zoomAppClientSecret);
        const session = issueZoomAppSession(sessionContext, config.zoomAppSessionSecret);
        res.writeHead(200, zoomAppHtmlHeaders(buildZoomAppSessionCookie(config.zoomAppCookieName, session)));
        return res.end(buildControlHtml({ zoomApp: true }));
      } catch {
        if (!zoomAppAuthorized(request)) {
          res.writeHead(200, zoomAppHtmlHeaders());
          return res.end(zoomAppLockedHtml);
        }
        res.writeHead(200, zoomAppHtmlHeaders());
        return res.end(buildControlHtml({ zoomApp: true }));
      }
    }
    const access = await accessLevel(request);
    const staffToken = cookies(request).pn_nafanya_staff || "";
    if ((access === "admin" || access === "staff") && /^[a-f0-9]{64}$/u.test(staffToken)) {
      res.setHeader("set-cookie", `pn_nafanya_staff=${staffToken}; Max-Age=315360000; Path=/nafanya-zoom-control; HttpOnly; Secure; SameSite=Strict`);
    }
    if (!access) {
      if (request.method === "GET" && url.pathname === "/app") {
        res.writeHead(303, { location: "/nafanya-login?return=%2Fnafanya-zoom-control%2Fapp", "cache-control": "no-store" });
        return res.end();
      }
      return json(res, 401, { ok: false, error: "unauthorized", loginUrl: "/nafanya-login?return=%2Fnafanya-zoom-control%2Fapp" });
    }
    if (request.method === "GET" && url.pathname === "/auth/check") {
      if (access !== "admin") return json(res, 403, { ok: false, error: "admin_required" });
      res.writeHead(204, { "cache-control": "no-store" }); return res.end();
    }
    if (request.method === "GET" && url.pathname === "/app") {
      let html = buildControlHtml({ staff: access === "staff" });
      if (access !== "zoom_app") {
        html = html.replace("</head>", '<style>.staff-logout{position:fixed;z-index:50;top:7px;right:8px;min-height:28px;padding:4px 9px;border:1px solid #9e2d2d;border-radius:7px;background:#fff;color:#9e2d2d;font:700 12px Arial,sans-serif;text-decoration:none;box-shadow:0 2px 5px #0002}.staff-logout:hover{background:#fff4f0}</style></head>')
          .replace("<body>", '<body><a class="staff-logout" href="/nafanya-zoom-control/logout">Выйти</a>');
      }
      res.writeHead(200, { "content-type": "text/html; charset=utf-8", "cache-control": "no-store" }); return res.end(html);
    }
    if (url.pathname === "/zoom-only/timer") return proxyTimer(request, res, access);
    if (request.method === "GET" && url.pathname === "/worker-panel") return proxyWorkerOnlyWhileSenderRuns(request, res, "/zoom-only/app", url.search, access);
    if (["/zoom-only/status", "/zoom-only/app/action", "/zoom-only/library/status", "/zoom-only/library/import"].includes(url.pathname)) {
      if (workerPathRequiresAdmin(url.pathname) && access !== "admin") return json(res, 403, { ok: false, error: "admin_required" });
      if (request.method === "POST" && url.pathname === "/zoom-only/library/import" && access !== "admin") return json(res, 403, { ok: false, error: "admin_required" });
      return proxyWorkerOnlyWhileSenderRuns(request, res, url.pathname, url.search, access);
    }
    if (request.method === "GET" && url.pathname === "/api/status") return json(res, 200, await service.status());
    if (request.method === "POST" && url.pathname === "/api/start") { const result = service.requestStart(); audit("zoom_sender_start_requested", { accepted: result.accepted }); return json(res, 202, result); }
    if (request.method === "POST" && url.pathname === "/api/stop") { const result = await service.stop(); audit("zoom_sender_stop_requested", { ok: result.ok }); return json(res, result.status, result); }
    if (request.method === "POST" && url.pathname === "/api/auth-setup") {
      if (access !== "admin") return json(res, 403, { ok: false, error: "admin_required" });
      return json(res, 202, service.requestAuthSetup());
    }
    if (request.method === "POST" && url.pathname === "/api/auth-setup/stop") {
      if (access !== "admin") return json(res, 403, { ok: false, error: "admin_required" });
      const result = await service.stopAuthSetup(); return json(res, result.status, result);
    }
    return json(res, 404, { ok: false, error: "not_found" });
  } catch (error) {
    console.error(JSON.stringify({ event: "zoom_control_request_failed", path: String(request.url || "").split("?")[0], error: String(error?.message || error || "unknown").slice(0, 300) }));
    if (error?.message === "staff_auth_unavailable") return json(res, 503, { ok: false, error: "authorization_service_unavailable" });
    return json(res, 500, { ok: false, error: "control_agent_error" });
  }
});
server.listen(config.port, config.host, () => console.log(`Nafanya Zoom control listening on ${config.host}:${config.port}`));
