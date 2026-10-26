> Actualización: el flujo anterior de tres tickets, QR y Guardar como PDF fue recuperado. Consulta `Integracion-Tickets-HU10-HU57.md` para el alcance actual y los archivos. El documento siguiente describe la primera entrega de HU-57.

# HU-57 — Numeración correlativa y CUF de 28 caracteres

## Qué se hizo

Se habilitó el cobro mínimo en efectivo para emitir facturas persistentes y comprobar HU-57. El POS conserva el catálogo, favoritos, búsqueda, fotos y carrito existentes. El selector de mesa/llevar, el modal de efectivo y la factura usan el diseño de demo/. No se agregaron las funciones de HU-58 (literal), HU-32 (gestión/búsqueda de clientes), HU-12 (configuración/PDF), QR de pago ni descuentos.

El cliente impreso es S/N, NIT 0, fijo hasta implementar HU-32. Las dos leyendas se muestran: texto fijo y una leyenda activa del catálogo existente de HU-10; la elegida queda guardada en la factura.

## Archivos nuevos y cómo explicarlos

| Archivo | Parte que debes mostrar | Función |
|---|---|---|
| `app/Controllers/FacturaController.php` | `index()` y `mostrar()` | Consulta las facturas guardadas; un cajero solo puede ver las de su caja y el administrador puede ver todas. |
| `app/Models/Factura.php` | `siguientes()` | Obtiene/bloquea el contador de sucursal y punto con `FOR UPDATE`, incrementa exactamente una vez y exige una transacción abierta. |
| `app/Models/Factura.php` | `crear()` y `buscar()` | Guarda y recupera número, CUF, leyenda y snapshot (copia de los datos al emitir). |
| `app/Models/Venta.php` | `cajaParaCobrar()`, `productosParaCobrar()`, `crear()`, `detalle()`, `pago()` | Consultas de caja, productos, venta, detalle y efectivo. |
| `app/Services/VentaService.php` | `cobrar()` y `DB::transaccion(...)` | Valida caja, productos, cantidades, precio y efectivo; emite todo junto o deshace todo. |
| `app/Services/VentaService.php` | `Venta::porUuid($uuid)` | Si llega otra vez la misma solicitud, devuelve la factura anterior sin volver a cobrar. Rechaza reutilizar ese UUID con otro contenido o usuario. |
| `app/Services/Cuf.php` | `generar()` | Hash SHA-256 de datos de emisión, toma 28 posiciones y convierte a mayúsculas. La base impide CUF repetidos. |
| `app/Views/facturas/tickets.php` | Campos `factura` y `cuf` | Muestra la factura guardada, con la estructura y clases del ticket de la demo. No calcula números al abrir la pantalla. |
| `app/Views/facturas/index.php` | Listado y enlace de factura | Permite volver a abrir hasta las 100 facturas más recientes autorizadas. Es consulta básica para QA, no el módulo completo de ventas/anulación. |
| `database/migraciones/057_hu57_correlativos.sql` | `correlativos`, `facturas` e índices `UNIQUE` | Tablas de venta/pago/factura y unicidad de número por serie, CUF global y una factura por venta. Conserva el esquema anterior de HU-10 si ya está instalado. |

Este documento también es nuevo.

## Archivos existentes modificados

- `app/Controllers/PosController.php`: agrega `cobrar()`; conserva la consulta y filtros de la cuadrícula actual.
- `app/Views/pos/index.php`: selector de mesa/llevar, efectivo exacto y modal de cobro tomado de la demo.
- `public/assets/js/pos.js`: habilita los botones al cargar productos, calcula el cambio mostrado y envía el pedido al servidor. Guarda temporalmente el UUID de un cobro pendiente para poder recuperar su respuesta tras un corte de red; las ventas confirmadas se guardan en MariaDB, no en ese almacenamiento temporal.
- `app/rutas.php`: POST `/pos/cobrar`, GET `/ventas` y GET `/facturas/{id}`, protegidos por sesión/permisos y CSRF global en POST.
- `config/modulos.php`: permite entrar a la consulta básica de Ventas y facturas.
- `tests/Integracion/CarritoPosTest.php`: reemplaza la expectativa antigua de «cobro pendiente» por la presencia del nuevo flujo.
- `tests/Integracion/CuadriculaPosTest.php`: lee el atributo de productos sin depender del orden de otros atributos HTML.

No se modifican los archivos de estilos ni demo/. No se copia el paquete anterior completo sobre el develop actual.

## Cómo se evitan los saltos

El número de factura no es el `AUTO_INCREMENT` de la venta. Un ID interno puede tener saltos después de un rollback; el número impreso se obtiene del contador transaccional propio.

Dentro de la transacción se bloquea la caja, se comprueba el UUID, se validan los productos/precios y se bloquean los parámetros fiscales para que no cambien durante la emisión. Después se incrementa el contador de la serie y se insertan venta, líneas, pago, factura y bitácora. Si falla alguna inserción, `DB::transaccion()` revierte también el contador.

El número inicial solo sirve para una serie nueva. Una serie ya usada continúa desde su último número, aunque se cambie el parámetro inicial. No se implementa eliminar facturas ni reiniciar contadores. Volver a ver una factura no consume un número.

## CUF académico

Se sigue el formato de la HU: 28 caracteres hexadecimales mayúsculos, único y persistente. La entrada del hash incluye NIT, instante, sucursal, punto, número y control CUFD; usa un arreglo JSON para no concatenar campos ambiguamente. El índice único de `facturas.cuf` rechaza una colisión y la transacción revierte todo.

El proyecto trabaja con parámetros fiscales simulados. Esto adapta el hash de la demo; no es una implementación oficial del algoritmo del SIN ni una integración con su servicio. El ERS completo no vino en el ZIP y queda pendiente contrastarlo si define reglas adicionales al formato mostrado en Jira.

## Validación de esta entrega

- Suite del proyecto: **164 pruebas, 922 aserciones, todas correctas**, ajustando las dos expectativas afectadas por habilitar cobro.
- Script de QA de esa entrega (retirado del repositorio al cerrar el proyecto): aprobados concurrencia, rollback, reintento, consulta MVC, conservación de snapshot, migración desde HU-10, CSRF, permisos, series separadas, efectivo insuficiente y formato CUF.
- Navegador: flujo de efectivo en escritorio 1440 px y móvil 390 px; factura sin desbordamiento horizontal; cero errores JavaScript.
- Red: se simuló que el servidor confirma el cobro pero se pierde la respuesta; después de F5, reintentar recuperó la misma factura sin duplicarla.
- Sintaxis PHP y JavaScript revisada.

Entorno de validación disponible: PHP 8.3.6 y MariaDB 10.11.14; navegador Chromium. El proyecto entregado mantiene su Docker original (PHP 8.2 / MariaDB 11.4). Queda la validación final en el Docker de Jefferson y el QA de otro compañero, tal como pide Jira. El instalador PowerShell se revisó, pero no se ejecutó en una PC Windows desde este entorno.

## Rama de entrega

`feacture/HU-57-Numeracion-correlativa-y-CUF-de-28-caracteres`

Base: develop. Commit sugerido: `feat(HU-57): numeracion correlativa y CUF con cobro persistente`.

No incluir futuros cambios de HU-58, HU-32 ni HU-12 en esta rama/entrega. No se ejecutó ninguna publicación en GitHub.
