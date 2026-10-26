/* Service worker de Pahuichi POS (HU-36: PWA y funcionamiento sin red).
   - Recursos de la interfaz (CSS, JS, fuentes): primero la red; sin conexión, lo guardado.
   - Punto de venta (/pos) y pedidos del mesero (/mesero, HU-35): primero la red, y se
     guarda una copia; sin conexión se entrega esa copia, marcada con data-sin-red para que
     la pantalla use la hora guardada. La copia lleva el nombre del usuario: se borra al
     volver al login (cerrar sesión).
   - Fotos de productos: desde la caché al instante y se actualizan por detrás. El POS
     pide guardar las de todo el catálogo al abrirse.
   - Otras páginas sin conexión: la página «Sin conexión». Nunca se guardan: traen datos. */
const VERSION = 'pahuichi-v5';
const PAGINAS = 'pahuichi-paginas';
const FOTOS = 'pahuichi-fotos';
const RECURSOS = [
  '/manifest.json',
  '/sin-conexion.html',
  '/assets/css/tokens.css',
  '/assets/css/base.css',
  '/assets/css/components.css',
  '/assets/css/layout.css',
  '/assets/css/pages.css',
  '/assets/css/modulos.css',
  '/assets/css/pos.css',
  '/assets/css/mesero.css',
  '/assets/css/foto-producto.css',
  '/assets/js/app.js',
  '/assets/js/carrito.js',
  '/assets/js/motor-promociones.js',
  '/assets/js/cliente-pos.js',
  '/assets/js/cola-ventas.js',
  '/assets/js/cuentas.js',
  '/assets/js/mesero.js',
  '/assets/js/pos.js',
  '/assets/js/producto-fotos-pos.js',
  '/assets/img/icono.svg',
  '/assets/fonts/archivo-variable-latin.woff2'
];
const FOTO = /^\/catalogo\/productos\/\d+\/foto$/;

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(RECURSOS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  const vigentes = [VERSION, PAGINAS, FOTOS];
  e.waitUntil(
    caches.keys()
      .then((claves) => Promise.all(claves.filter((k) => !vigentes.includes(k)).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// La copia guardada del POS se marca: así la página sabe que su hora es vieja
async function copiaMarcada(respuesta) {
  const html = (await respuesta.text()).replace('<html', '<html data-sin-red');
  return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}

// Pantallas que funcionan sin red: se guarda la última copia de cada una
const SIN_RED = ['/pos', '/mesero'];

async function pantallaSinRed(pedido, ruta) {
  try {
    const r = await fetch(pedido);
    // Solo la pantalla real: no una redirección al login ni un error
    if (r.ok && !r.redirected && r.type === 'basic') {
      const copia = r.clone();
      caches.open(PAGINAS).then((c) => c.put(ruta, copia));
    }
    return r;
  } catch (err) {
    const guardada = await caches.match(ruta, { cacheName: PAGINAS });
    return guardada ? copiaMarcada(guardada) : caches.match('/sin-conexion.html');
  }
}

async function pagina(pedido, url) {
  try {
    const r = await fetch(pedido);
    // Volver al login es cerrar sesión (o cambiar de usuario): la copia del POS se borra
    if (url.pathname === '/login') caches.delete(PAGINAS);
    return r;
  } catch (err) {
    return (await caches.match('/sin-conexion.html')) || Response.error();
  }
}

async function foto(pedido) {
  const cache = await caches.open(FOTOS);
  const guardada = await cache.match(pedido);
  const fresca = fetch(pedido).then((r) => {
    if (r.ok && r.type === 'basic') cache.put(pedido, r.clone());
    return r;
  }).catch(() => null);
  return guardada || (await fresca) || Response.error();
}

async function recurso(pedido) {
  try {
    const r = await fetch(pedido);
    if (r.ok) {
      const copia = r.clone();
      caches.open(VERSION).then((c) => c.put(pedido, copia));
    }
    return r;
  } catch (err) {
    return (await caches.match(pedido)) || Response.error();
  }
}

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.origin !== self.location.origin) return;
  if (e.request.mode === 'navigate') {
    e.respondWith(SIN_RED.includes(url.pathname) ? pantallaSinRed(e.request, url.pathname) : pagina(e.request, url));
  } else if (FOTO.test(url.pathname)) {
    e.respondWith(foto(e.request));
  } else if (url.pathname.startsWith('/assets/') || url.pathname === '/manifest.json') {
    e.respondWith(recurso(e.request));
  }
});

// El POS manda las fotos del catálogo: se guardan las que falten (de a una, sin apuro)
self.addEventListener('message', (e) => {
  if (!e.data || e.data.tipo !== 'fotos' || !Array.isArray(e.data.urls)) return;
  e.waitUntil((async () => {
    const cache = await caches.open(FOTOS);
    for (const ruta of e.data.urls.slice(0, 500)) {
      const url = new URL(ruta, self.location.origin);
      if (url.origin !== self.location.origin || !FOTO.test(url.pathname) || await cache.match(url.href)) continue;
      try {
        const r = await fetch(url.href, { credentials: 'same-origin' });
        if (r.ok) await cache.put(url.href, r);
      } catch (err) { return; }   // sin red: se intenta en la próxima apertura
    }
  })());
});
