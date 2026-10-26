# HU-36 — PWA y funcionamiento sin red

Como Cajero, quiero que el punto de venta siga funcionando si se corta la red, para no perder ventas en plena hora pico (RNF-08, RNF-11, RNF-13).

## Qué se hizo

- **El pedido sobrevive a recargar la página.** Las líneas, las notas, la mesa, el tipo de pedido y el cliente se guardan en el equipo (`localStorage`) en cada cambio y vuelven al abrir el POS. Son del cajero y de la caja: otro usuario no los ve.
- **Cola de ventas sin red.** Cada cobro entra a una cola con su identificador único (`uuid`) y la hora en que se vendió. Con red sale enseguida. Sin red queda guardado con un número provisional (S-1, S-2…) para anotar el pedido a cocina, y el cajero sigue vendiendo. Las ventas guardadas se envían solas, de a una y en orden, al volver la red, al abrir el POS y cada 30 segundos mientras queden.
- **Sin duplicados.** Una venta sale de la cola recién cuando el servidor la confirma. Si la respuesta se pierde, el reintento lleva el mismo `uuid` y el servidor devuelve la venta original (HU-57). Dos disparos a la vez comparten el mismo envío.
- **Promociones a la hora de la venta.** El servidor evalúa las promociones a la hora en que se vendió, si esa hora es creíble: dentro de la caja abierta, de las últimas 12 horas y no del futuro. Si no lo es, vale la hora de llegada. La venta y su factura llevan la hora en que llegan al servidor; la bitácora del cobro anota «Vendida sin red» con la hora real.
- **Carga desde la caché.** El service worker guarda una copia de `/pos` cada vez que se abre con red, los CSS, JS y fuentes, y las fotos de todo el catálogo. Sin red entrega esa copia, con la grilla y las fotos. Las demás páginas muestran «Sin conexión».

## Lo que no hace sin red

Sin factura no hay tickets: la comanda, la copia de caja y la factura los genera el servidor. Durante el corte, el pedido va a cocina anotado con su número provisional; los tickets se abren desde «Ventas sin enviar» cuando la venta llega.

Una venta que el servidor no acepta al llegar (por ejemplo, la caja se cerró mientras no había red) queda en «Ventas sin enviar» con el motivo. El cajero puede volverla al pedido para corregirla y cobrarla otra vez, o descartarla.

## Archivos

| Archivo | Qué hace |
|---|---|
| `public/assets/js/cola-ventas.js` | La cola: guarda, envía en orden, no duplica, conserva lo que no se pudo enviar. No toca la pantalla. |
| `public/assets/js/pos.js` | Guarda y restaura el pedido; cobra a través de la cola; dibuja «Ventas sin enviar»; usa la hora guardada si la página salió de la caché; pide guardar las fotos. |
| `public/sw.js` | Copia de `/pos` (red primero), fotos (caché primero), recursos (red primero) y la página «Sin conexión». Borra la copia de `/pos` al volver al login. |
| `public/sin-conexion.html` | Página para las pantallas que necesitan red. |
| `app/Services/VentaService.php` | `momentoDeVenta()`: la hora con que se evalúan las promociones. |
| `app/Middleware/VerificarCsrf.php` | Con la página vencida responde `recargar`, para que la cola espere en vez de rechazar la venta. |
| `public/assets/js/app.js` | «Sin conexión con el servidor» también en la copia guardada; sin red no se cierra la sesión por inactividad. |

## Pruebas automáticas

- `tests/Integracion/VentaSinRedTest.php`: la misma venta dos veces deja una venta, una factura y un pago (escenario 3); promociones a la hora de la venta; horas no creíbles; todo lo que carga `/pos` está en la caché del service worker.
- `tests/js/cola-ventas.test.mjs` (`node tests/js/cola-ventas.test.mjs`): la venta sobrevive a recargar, se envía una sola vez al reconectar, en orden, se detiene sin red o sin sesión, y no envía las de otro cajero.

## Cómo probarlo en el navegador

El service worker solo funciona con un certificado de confianza. En producción lo da el hosting. En esta PC, con el certificado autofirmado, activa `chrome://flags/#allow-insecure-localhost` en Chrome y reinícialo.

1. Entra como cajero, abre la caja y abre el POS. En DevTools → Application → Service workers debe figurar `sw.js` activo.
2. **Escenario 2:** con DevTools → Network → «Offline», recarga `/pos`. La grilla y las fotos aparecen y arriba se ve «Sin conexión con el servidor».
3. **Escenario 1:** agrega tres ítems y recarga (sigue «Offline»). El pedido sigue ahí. Cóbralo: queda «Pedido S-1 guardado sin conexión» y aparece en «Ventas sin enviar».
4. Quita «Offline». En unos segundos el pedido S-1 pasa a «Enviada» con su factura y sus tickets. En Ventas y facturas hay una sola venta.
