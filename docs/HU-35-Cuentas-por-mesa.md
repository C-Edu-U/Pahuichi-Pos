# HU-35 — Cuentas por mesa (pedido desde el celular del mesero)

Como Mesero, quiero tomar el pedido desde mi celular y enviarlo a caja, para que el cajero solo tenga que cobrar (RF-35).

## Cómo funciona

- **El mesero toma pedidos por mesa** en `/mesero` (pensada para el celular): elige la mesa (botones 1 a 12 u otra, de 1 a 99), toca los platos, agrega notas para cocina y pulsa «Enviar a caja».
- **El primer pedido abre la cuenta de la mesa**; los siguientes se suman a la misma cuenta. Hay una sola cuenta abierta por mesa.
- **Cada pedido llega a caja**: en el POS, el botón «Mesas» muestra las mesas abiertas con sus pedidos y avisa cuando llega uno nuevo. Caja abre la comanda de cada pedido en PDF para cocina, con las copias que diga el perfil de cocina (HU-12).
- **Las promociones valen a la hora de cada pedido**: una mesa puede tener un pedido dentro del horario de una promoción y otro fuera.
- **Cerrar y cobrar**: el mesero (o caja, si el cliente va al mostrador) cierra la cuenta y cobra en efectivo. Sale una sola factura con todos los pedidos, más la copia de caja; las comandas no se repiten. QR y mixto llegan con HU-39.
- **La venta queda en la caja abierta**: el arqueo espera ese efectivo y el mesero lo entrega en caja. Sin caja abierta no se toman pedidos ni se cobran cuentas.
- **El descuento manual con PIN** solo lo aplica caja al cobrar la cuenta.
- **Cancelar un pedido**: el mesero cancela los que envió; caja, cualquiera. Siempre con motivo y en la bitácora. Si una cuenta se queda sin pedidos, se cancela y la mesa queda libre.
- **Sin duplicados**: un pedido o un cobro repetido por un reintento lleva el mismo `uuid` y se registra una sola vez.
- El pedido hecho en caja se sigue cobrando en el momento, como antes.

## Archivos

| Archivo | Qué hace |
|---|---|
| `database/migraciones/067_hu35_cuentas_mesa.sql` | `cuentas` (una abierta por mesa), `cuenta_rondas` (cada pedido, con su `uuid`) y `ronda_lineas` (cada línea con el precio y las promociones de su hora). |
| `app/Services/CuentaService.php` | Enviar un pedido, cancelarlo, cerrar y cobrar la cuenta. |
| `app/Services/VentaService.php` | `armarLineas()` (precios y promociones) y `emitir()` (venta, pago y factura), compartidos por el cobro de caja y el de las cuentas. |
| `app/Controllers/CuentaController.php` | Pantalla del mesero, lista de cuentas (JSON), comanda de cada pedido y factura de la cuenta en PDF. |
| `app/Views/mesero/index.php`, `public/assets/js/mesero.js` | Pantalla del mesero. |
| `public/assets/js/cuentas.js`, `app/Views/cuentas/_dialogos.php` | Ficha de la mesa, cobro y cancelación, compartidos por el mesero y el POS. |
| `app/Views/pos/_datos.php`, `_catalogo.php`, `_mesas.php` | Catálogo y mesa del pedido, compartidos por el POS y el mesero. |

## Pruebas

`tests/Integracion/CuentasMesaTest.php`: el pedido de la mesa 3 llega a caja pendiente de cobro (escenario 1); los pedidos se suman y caja imprime su comanda; promociones a la hora de cada pedido; el mesero cierra y cobra en la caja abierta; caja cobra con descuento; sin duplicados; cancelaciones; sin caja abierta no hay pedidos; el mesero no cobra en caja ni ve reportes (escenario 2).

## Pendiente

- **Modo sin conexión del mesero** (fase B): la cola de HU-36 aplicada a los pedidos y al cobro de la cuenta.
- **QR y mixto** al cobrar la cuenta: con HU-39.
