self.addEventListener("install", () => {
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(self.clients.claim());
});

// минимально: просто присутствуем, ничего не кэшируем
self.addEventListener("fetch", () => {});
