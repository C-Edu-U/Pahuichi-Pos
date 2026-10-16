/* Service worker de Pahuichi POS: guarda los recursos de la interfaz (CSS, JS,
   fuentes e imágenes) para que cargue rápido. Las páginas no se guardan: traen
   datos del usuario y siempre se piden al servidor. */
const VERSION = 'pahuichi-v3';
const RECURSOS = [
  '/manifest.json',
  '/assets/css/tokens.css',
  '/assets/css/base.css',
  '/assets/css/components.css',
  '/assets/css/layout.css',
  '/assets/css/pages.css',
  '/assets/css/modulos.css',
  '/assets/css/pos.css',
  '/assets/js/app.js',
  '/assets/img/icono.svg',
  '/assets/fonts/archivo-variable-latin.woff2'
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(RECURSOS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((claves) => Promise.all(claves.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// Recursos: primero la red (versión fresca); sin conexión, lo guardado
self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.origin !== self.location.origin || !url.pathname.startsWith('/assets/')) return;
  e.respondWith(
    fetch(e.request)
      .then((r) => {
        const copia = r.clone();
        caches.open(VERSION).then((c) => c.put(e.request, copia));
        return r;
      })
      .catch(() => caches.match(e.request))
  );
});
