# Pahuichi POS

Punto de venta web para un local de comida típica cruceña. Proyecto de Ingeniería de Software II. El alcance, la arquitectura y el backlog están en [HANDOFF.md](HANDOFF.md).

## Estado actual: demo visual completa (solo frontend)

Todas las pantallas del producto final, en **HTML, CSS y JavaScript**, sin PHP ni base de datos. Los datos son de ejemplo y las reglas de negocio se simulan en el navegador para mostrar cada estado. El marcado se reutiliza después en las vistas PHP (`app/Views`).

| Pantalla | Archivo | Historias |
|---|---|---|
| Portada pública | `public/index.html` | HU-10 |
| Iniciar sesión (error, bloqueo 15 min, salida) | `public/login.html` | HU-05 |
| Escritorio por rol | `public/panel/escritorio.html` | HU-02 |
| Punto de venta: grilla, pedido, cliente, cobro en efectivo y con QR | `public/panel/pos.html` | HU-09, 12, 15, 16, 17 |
| Caja: apertura, arqueo por denominación, cierre, factura resumen 99003 | `public/panel/caja.html` | HU-22, 27 |
| Ventas y facturas: filtros, reimpresión, anulación con plazo | `public/panel/ventas.html` | HU-17, 25 |
| Factura en rollo de 80 mm y media hoja | `public/panel/factura.html` | HU-24 |
| Clientes y casos especiales del SIN | `public/panel/clientes.html` | HU-16 |
| Productos, alta y edición, categorías, «agotado hoy» | `public/panel/productos.html`, `producto-form.html`, `categorias.html` | HU-07, 28 |
| Pagos QR: vigencia de 24 h, subir, retirar, historial | `public/panel/qr.html` | HU-13, 14 |
| Reportes: tablero del analista y Libro de Ventas, exportar CSV | `public/panel/reportes.html` | HU-19, 23, 26 |
| Usuarios: alta, roles, activar, desbloquear, resetear clave | `public/panel/usuarios.html` | HU-11 |
| Configuración: datos fiscales, facturación, QR, leyendas, catálogos SIN | `public/panel/config.html` | HU-18 |
| Bitácora | `public/panel/bitacora.html` | HU-20 |
| Acceso denegado | `public/panel/403.html` | HU-06 |

### Cómo verlo

Abre `public/index.html` en el navegador, o sirve la carpeta: `npx serve public`.

- **Login:** `cajero`, `analista` o `admin` con cualquier clave. Cada rol entra a su pantalla: el cajero al punto de venta, el analista a reportes y el administrador al escritorio. Otro usuario cuenta como intento fallido; al quinto, la cuenta se bloquea 15 minutos.
- **Cambiar de rol:** el selector «Prototipo: ver como» de la barra superior, o `?rol=cajero` en la URL. El menú y las acciones se filtran según la matriz de la sección 4 del handoff. Si un rol entra por URL a un módulo ajeno, va a `403.html`.
- **Recorrido sugerido de la demo:**
  1. Vende en el POS con efectivo y con el producto de prueba QR.
  2. Imprime la factura.
  3. Anula una venta desde Ventas.
  4. Cierra la caja y prueba vender sin turno.
  5. En Pagos QR, usa «Simular vencimiento» y vuelve al POS.
- Los estados de la demo (caja cerrada, QR vencido, rol) se guardan en `localStorage`. Para volver al inicio, borra los datos del sitio.

Todo lo de seguridad (permisos, CSRF, bloqueo, validación de subidas, SHA-256 del QR) se **simula** en el cliente. En PHP lo decide el servidor.

### Sistema de diseño

`public/assets/css/`:

- `tokens.css`: color, series de datos, tipografía, espaciado (múltiplos de 8), radios, sombras y movimiento.
- `base.css`: reinicio, tipografía, foco visible, cifras tabulares.
- `components.css`: botón, campo, archivo, panel, tabla (con modo fichas en móvil), insignia, alerta, pestañas, control segmentado, modal, avisos flotantes y estado vacío.
- `layout.css`: riel lateral (completo en escritorio, íconos en tableta, cajón en móvil) y barra superior.
- `pages.css`: escritorio, productos, formularios, login y portada. `modulos.css`: caja, ventas, clientes, QR, reportes, usuarios, configuración y bitácora. `pos.css` y `factura.css`: punto de venta y factura. `print.css`: impresión.

Más detalle en [DESIGN.md](DESIGN.md). Íconos: [Tabler Icons](https://tabler.io/icons) (MIT), en un sprite local. Tipografía de marca: Archivo (SIL OFL), servida desde el propio sitio. Los códigos QR de ejemplo se generaron con `qrcode` y no corresponden a ninguna cuenta real.
