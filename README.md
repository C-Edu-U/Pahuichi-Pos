# Pahuichi POS

Punto de venta web para un restaurante de comida típica cruceña. Está pensado para la hora pico: una venta de tres ítems cobrada en efectivo en 15 segundos o menos, y una venta favorita con cobro exacto en 4 toques o menos.

## Estado actual: demo visual

Todas las pantallas del producto, en **HTML, CSS y JavaScript propios** y sin backend. Los datos son de ejemplo y las reglas de negocio se simulan en el navegador para mostrar cada estado. El CSS y el JavaScript son los definitivos; el marcado pasa después a las vistas PHP.

Etapa siguiente: PHP 8.2 con MVC propio, MariaDB y PWA, todo con `docker compose up` y HTTPS en la red local.

## Cómo verla

Abre `public/login.html` en el navegador, o sirve la carpeta para probar también la PWA: `npx serve public`.

| Rol | Cómo entrar | Pantalla de entrada |
|---|---|---|
| Cajero | PIN **4821** en el teclado de la caja | Punto de venta |
| Administrador | Usuario `admin`, cualquier contraseña | Tablero |
| Mesero | Usuario `luis`, cualquier contraseña | Tomar pedido |
| Cocina | Usuario `teresa` | No entra al panel: recibe la comanda impresa |

- **Cambiar de rol:** el selector «Demo: ver como» de la barra superior, o `?rol=cajero` en la URL. Si un rol abre a mano la URL de un módulo ajeno, ve «acceso denegado» y ningún dato.
- **PIN y contraseña de demostración:** la autorización del administrador (anular, descuento manual) usa el PIN `1234`.
- **Reiniciar la demo:** los estados (caja, QR, ventas en espera, ventas cobradas) se guardan en el navegador. Para empezar de cero, borra los datos del sitio.

### Recorrido sugerido

1. Entra como cajero con el PIN 4821.
2. En el POS, toca Majadito, Picante y Mocochinchi, y luego «Efectivo exacto» (F8). La venta queda cobrada y el combo se aplica solo.
3. Cobra el «Producto de prueba QR» con QR y un número de comprobante. Repite el comprobante `BNB-55120` para ver el rechazo.
4. Cambia a administrador y confirma o rechaza los pagos en Conciliación QR.
5. En Ventas y facturas abre los tickets de una venta y verifica la factura con su QR.
6. Cierra la caja: el conteo es ciego y el resumen muestra la diferencia.
7. En QR del día usa «Simular medianoche» y vuelve al POS.

## Pantallas

| Pantalla | Archivo | Historias |
|---|---|---|
| Inicio de sesión con PIN o contraseña | `public/login.html` | HU-02 |
| Tablero del día (administrador) y del turno (cajero) | `public/panel/tablero.html` | HU-64 |
| Punto de venta | `public/panel/pos.html` | HU-21, 28 a 34, 37, 39, 40, 42, 46 a 51 |
| Caja: apertura, movimientos, cierre ciego y arqueo | `public/panel/caja.html` | HU-20 a 27 |
| Ventas y facturas, anulación con PIN | `public/panel/ventas.html` | HU-43, 44 |
| Tickets: comanda, copia de caja y factura | `public/panel/tickets.html` | HU-54 a 59, 62 |
| Verificación de facturas (pública) | `public/verificar.html` | HU-60 |
| Conciliación QR | `public/panel/conciliacion.html` | HU-41, 43 |
| QR del día | `public/panel/qr.html` | HU-38 |
| Clientes | `public/panel/clientes.html` | HU-32, 51 |
| Catálogo: productos, formulario y categorías | `public/panel/productos.html`, `producto-form.html`, `categorias.html` | HU-13 a 19 |
| Promociones y simulador | `public/panel/promociones.html`, `promocion-form.html` | HU-46 a 52 |
| Reportes y Libro de Ventas | `public/panel/reportes.html` | HU-63 |
| Usuarios | `public/panel/usuarios.html` | HU-03 |
| Configuración: negocio, fiscal, leyendas, turnos, impresoras, respaldos | `public/panel/config.html` | HU-07 a 12 |
| Cola de impresión | `public/panel/impresion.html` | HU-53 |
| Bitácora | `public/panel/bitacora.html` | HU-05 |
| Pedidos del mesero | `public/panel/mesero.html` | HU-35 |
| Pantalla del cliente | `public/cliente.html` | HU-45 |

## Estructura

```
public/
  login.html, verificar.html, cliente.html, manifest.json, sw.js
  panel/              páginas del panel (generadas, ver herramientas/panel)
  assets/css/         tokens, base, components, layout, pages, modulos, pos, tickets, print
  assets/js/          app.js (permisos, menú, utilidades), una por pantalla, datos.js,
                      promociones-motor.js, numero-letras.js, vendor/qrcode.js
  assets/img/         sprite de íconos, ícono de la app, QR de cobro de ejemplo
herramientas/
  panel/              marco común + fragmento por pantalla (LEEME.md)
  iconos.mjs          arma el sprite de íconos
```

Atajos del punto de venta: **F2** buscar, **F4** cobrar, **F8** efectivo exacto, **Esc** cancelar.

Más detalle del diseño en [DESIGN.md](DESIGN.md).

## Licencias de terceros

- Íconos: [Tabler Icons](https://tabler.io/icons), MIT.
- Tipografía de marca: Archivo, SIL Open Font License.
- Generador de QR: [qrcode-generator](https://github.com/kazuhikoarase/qrcode-generator) de Kazuhiko Arase, MIT.
