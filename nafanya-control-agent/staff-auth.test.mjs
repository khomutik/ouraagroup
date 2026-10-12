import test from "node:test";
import assert from "node:assert/strict";
import { verifyStaffSession, workerPathRequiresAdmin } from "./staff-auth.mjs";
import { buildControlHtml, hideWorkerAdminPanel } from "./html.mjs";

const token = "a".repeat(64);
const response = (status, role = "") => ({ status, headers: { get: (name) => name.toLowerCase() === "x-pn-nafanya-role" ? role : null } });

test("rejects a request without a staff cookie without contacting CMS", async () => {
  let called = false;
  const result = await verifyStaffSession({ headers: {} }, { authUrl: "https://example.test/check", fetchImpl: async () => { called = true; return response(204, "staff"); } });
  assert.equal(result, null);
  assert.equal(called, false);
});

test("accepts the role returned by CMS and forwards only the session cookie", async () => {
  let sentCookie = "";
  const result = await verifyStaffSession({ headers: { cookie: `other=x; pn_nafanya_staff=${token}` } }, {
    authUrl: "https://example.test/check",
    fetchImpl: async (_url, options) => { sentCookie = options.headers.cookie; return response(204, "staff"); }
  });
  assert.equal(result, "staff");
  assert.equal(sentCookie, `pn_nafanya_staff=${token}`);
});

test("rejects a revoked CMS session", async () => {
  const result = await verifyStaffSession({ headers: { cookie: `pn_nafanya_staff=${token}` } }, { authUrl: "https://example.test/check", fetchImpl: async () => response(401) });
  assert.equal(result, null);
});

test("fails closed when the role header is absent", async () => {
  const result = await verifyStaffSession({ headers: { cookie: `pn_nafanya_staff=${token}` } }, { authUrl: "https://example.test/check", fetchImpl: async () => response(204) });
  assert.equal(result, null);
});

test("staff see the control panel without the privileged admin tools", () => {
  const staffHtml = buildControlHtml({ staff: true });
  const adminHtml = buildControlHtml();
  assert.match(staffHtml, /id="adminPanel" hidden/u);
  assert.doesNotMatch(adminHtml, /id="adminPanel" hidden/u);
});

test("the embedded Zoom admin and book-base section is hidden for staff and Zoom app users", () => {
  const workerHtml = '<details class="admin"><summary>Админ / книжная база</summary></details>';
  assert.match(hideWorkerAdminPanel(workerHtml, "staff"), /<details class="admin" hidden>/u);
  assert.match(hideWorkerAdminPanel(workerHtml, "zoom_app"), /<details class="admin" hidden>/u);
  assert.equal(hideWorkerAdminPanel(workerHtml, "admin"), workerHtml);
});

test("book-base endpoints require the CMS Administrator role", () => {
  assert.equal(workerPathRequiresAdmin("/zoom-only/library/status"), true);
  assert.equal(workerPathRequiresAdmin("/zoom-only/library/import"), true);
  assert.equal(workerPathRequiresAdmin("/zoom-only/app/action"), false);
});
