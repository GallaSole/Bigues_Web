const CACHE = 'bigues-resultats-v5';
const STATIC = ['./assets/style.css?v=5', './assets/app.js?v=5', './icons/icon-192.png', './icons/icon-512.png'];

self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(STATIC)).catch(() => {}));
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  if (event.request.method !== 'GET' || url.pathname.endsWith('.php')) return;
  event.respondWith(caches.match(event.request).then(hit => hit || fetch(event.request)));
});
