# HU-19 — Historial de cambios de precio

Como administrador, consultar quién cambió el precio de cada producto y cuándo (ERS RF-17).

## Uso

Catálogo → Editar producto → Cambios de precio. Guardar un precio diferente registra el anterior, el nuevo, la fecha y el usuario autenticado en la bitácora. El historial presenta diez cambios por página, del más reciente al más antiguo; Anterior/Siguiente permiten consultar todos. El precio se expresa en Bs. Solo el administrador puede editar y consultar esta pantalla.

Se conserva el diseño del formulario y se adapta la tabla a celular. Guardar el mismo importe, aunque cambie su formato (38/38,00), o enviar un precio inválido no genera un cambio de precio.

## Integridad

El producto se bloquea con SELECT FOR UPDATE y se lee su precio vigente. Precio y registros de auditoría se escriben en la misma transacción: si falla la auditoría, se deshace la edición. Así una edición simultánea no registra como anterior un precio desactualizado.

Las ventas ya cobradas conservan detalle_venta.precio_unitario, subtotales, pagos y snapshot de factura. Los tickets y PDF de HU-56 usan ese snapshot. Una venta posterior toma el precio nuevo; no se reescriben ventas antiguas.

Se reutiliza producto.precio en bitacora. No hay tablas, dependencias ni migraciones nuevas. No se reconstruyen cambios anteriores que nunca fueron registrados. El historial filtra también entidad=productos para evitar mezclar otros registros con el mismo identificador.

## Verificación

- Escenario 1: de 38 a 40 Bs, registro con ambos precios, usuario y fecha; visible al reabrir el producto.
- Escenario 2: venta real a 38, cambio a 40, detalle/pago/snapshot anterior idénticos y PDF accesible; nueva venta a 40.
- Mismo precio y validación inválida sin registros adicionales.
- Fallo deliberado de auditoría: precio y registros se revierten juntos.
- Doce cambios consultables en dos páginas; página fuera de rango limitada a la última válida.
- Cajero sin acceso a edición/historial; administrador autorizado.

88 pruebas PHP y 705 comprobaciones aprobadas, incluyendo productos, cobros, tablero y HU-56. Navegador: edición, tabla, paginación y ancho de 360 px. QA cruzado por otro integrante y evidencias de Jira quedan a cargo del equipo.

Base de entrega: develop 803395d con HU-56 ccbaa63 ya integrada localmente. Este cambio no reemplaza HU-56 ni publica develop en GitHub.
