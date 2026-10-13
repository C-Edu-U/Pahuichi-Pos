/* Service worker de Pahuichi POS: guarda los recursos de la interfaz para
   que el POS cargue rápido y siga abriendo sin red. Las ventas hechas sin
   conexión las guarda la página (IndexedDB) y se reenvían al volver. */
const VERSION = 'pahuichi-v2';
const RECURSOS = [
  'login.html',
  'manifest.json',
  'assets/css/tokens.css',
  'assets/css/base.css',
  'assets/css/components.css',
  'assets/css/layout.css',
  'assets/css/pages.css',
  'assets/css/modulos.css',
  'assets/css/pos.css',
  'assets/js/iconos.js',
  'assets/js/app.js',
  'assets/js/datos.js',
  'assets/js/promociones-motor.js',
  'assets/js/pos.js',
  'assets/img/icono.svg',
  'assets/img/qr-cobro-demo.svg',
  'assets/fonts/archivo-variable-latin.woff2',
  'panel/pos.html'
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

// Primero la red (datos frescos); si no hay conexión, lo guardado
self.addEventListener('fetch', (e) => {
  if (e.request.method !== 'GET') return;
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
