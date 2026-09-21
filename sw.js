const CACHE_NAME = "aa-pn-v163";
// Dynamic CMS pages are deliberately excluded: visitors must receive a fresh
// version after publication, rather than a copy saved by the service worker.
const APP_SHELL = [
  "./",
  "./index.html",
  "./newcomers.html",
  "./tradition.html",
  "./robots.txt",
  "./sitemap.xml",
  "./styles.css?v=102",
  "./site-nav.js?v=17",
  "./pwa-install.js?v=10",
  "./site-chat.js?v=6",
  "./manifest.webmanifest",
  "./favicon.ico",
  "./icons/favicon-16.png",
  "./icons/favicon-32.png",
  "./icons/favicon-48.png",
  "./assets/pn-text-tight.png?v=1",
  "./assets/pn-text-mobile-tight.png?v=1",
  "./assets/header-strokes-left-v2.png?v=1",
  "./assets/header-strokes-right-v2.png?v=1",
  "./assets/social-zoom-v2.png",
  "./assets/social-telegram-v2.png",
  "./assets/social-max-v2.png",
  "./assets/emoji/aa_info.png?v=4",
  "./assets/emoji/aa_ballot.png",
  "./assets/emoji/aa_book_blue.png",
  "./assets/emoji/aa_heart_hands.png",
  "./icons/icon-192-v3.png",
  "./icons/icon-512-v3.png",
  "./icons/apple-touch-icon-v3.png"
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(APP_SHELL))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys()
      .then((names) => Promise.all(names.filter((name) => name !== CACHE_NAME).map((name) => caches.delete(name))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  if (event.request.method !== "GET") return;

  if (event.request.mode === "navigate" || event.request.destination === "document") {
    event.respondWith(
      fetch(event.request).catch(() => caches.match("./index.html"))
    );
    return;
  }

  event.respondWith(
    caches.match(event.request).then((cached) => cached || fetch(event.request))
  );
});










