const COOKIE_NAME = "pn_nafanya_staff";

export function workerPathRequiresAdmin(pathname) {
  return String(pathname || "").startsWith("/zoom-only/library/");
}

function cookieValue(header, name) {
  for (const item of String(header || "").split(";")) {
    const part = item.trim();
    const separator = part.indexOf("=");
    if (separator >= 0 && part.slice(0, separator) === name) return part.slice(separator + 1);
  }
  return "";
}

export async function verifyStaffSession(request, { authUrl, fetchImpl = fetch, timeoutMs = 3000 } = {}) {
  const token = cookieValue(request.headers.cookie, COOKIE_NAME);
  if (!/^[a-f0-9]{64}$/u.test(token)) return null;
  const response = await fetchImpl(authUrl, {
    method: "GET",
    headers: { cookie: `${COOKIE_NAME}=${token}` },
    redirect: "manual",
    signal: AbortSignal.timeout(timeoutMs)
  });
  if (response.status === 401 || response.status === 403) return null;
  if (response.status !== 204) throw new Error("staff_auth_unavailable");
  const role = response.headers.get("x-pn-nafanya-role");
  return role === "admin" ? "admin" : role === "staff" ? "staff" : null;
}
