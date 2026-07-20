/**
 * Service worker : ne met en cache que la coquille applicative (HTML/CSS/JS)
 * pour un chargement hors ligne instantané (R5). Les appels /api/* ne sont
 * JAMAIS mis en cache ici — les données vivent dans IndexedDB (db.js), gérée
 * explicitement par l'application (cohérence de synchronisation).
 */
const CACHE_NAME = 'radio-cr-shell-v1';
const SHELL_FILES = [
  '/app/',
  '/app/index.html',
  '/app/css/app.css',
  '/app/js/app.js',
  '/app/js/api.js',
  '/app/js/db.js',
  '/app/js/semantic.js',
  '/app/manifest.webmanifest',
  '/app/icons/icon-192.png',
  '/app/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_FILES)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  if (url.pathname.startsWith('/api/')) {
    return; // laissé au réseau, jamais mis en cache (données patient).
  }

  if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        const clone = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
        return response;
      })
      .catch(() => caches.match(event.request).then((cached) => cached || caches.match('/app/index.html')))
  );
});
