# HU-17 · Marcar producto como agotado

## Alcance de la entrega

Esta revisión reutiliza el marcado de productos existente en el catálogo y el POS funcional entregado como soporte de HU-10. No se crearon ni modificaron archivos de aplicación durante la revisión de HU-17. Esta rama agrega el registro de los criterios y la ubicación del código; no representa una nueva implementación del marcado.

Si `feacture/HU-10-Leyendas-de-la-Ley-453` aún no está integrada en develop, el recorrido completo de venta requiere integrar ese soporte mediante la revisión del equipo. Este documento no copia ni fusiona HU-10, HU-15 ni HU-16.

## Archivos que intervienen

| Archivo | Responsabilidad |
| --- | --- |
| `app/Views/catalogo/productos.php` | Botón Agotado con aria-pressed y estado Agotado/Disponible por producto. El mismo botón permite marcar y reponer. |
| `public/assets/js/productos.js` | Envía el cambio de un toque y actualiza la fila y el aviso. |
| `app/rutas.php` | POST /catalogo/productos/{id}/agotado con autenticación, CSRF y permiso catalogo.agotado. |
| `config/permisos.php` | Permite marcar agotado al personal autorizado de caja. |
| `app/Controllers/ProductoController.php` | agotado() y marcar() alternan la marca, guardan y registran la acción. |
| `app/Models/Producto.php` | marcar() actualiza únicamente la columna agotado; no cambia activo. listar() entrega ambos estados. |
| `app/Controllers/PosController.php` | Entrega el catálogo activo al POS; conserva los agotados para presentarlos bloqueados. |
| `app/Views/pos/index.php` | Contenedor del catálogo y los productos del POS. |
| `public/assets/js/pos.js` | pintarGrilla() deshabilita la tarjeta y agrega la etiqueta Agotado. agregar() también comprueba la marca. Entregado con el soporte POS de HU-10. |
| `app/Services/VentaService.php` | Vuelve a consultar el producto al cobrar y rechaza agotados aunque se intente enviar una venta desde una pantalla desactualizada. Entregado con HU-10. |
| `database/migraciones/001_base_sprint1.sql` | Campos activo y agotado existentes. No se requiere otra migración. |
| `tests/Integracion/ProductosTest.php` | Prueba existente: el cajero marca y repone, conservando activo = 1. |
| `tests/Integracion/FacturaLeyendaTest.php` | Prueba del soporte de HU-10 que rechaza el cobro de agotados, inactivos y productos de categoría inactiva. |

## Escenario 1: agotar

Dado un producto activo y disponible, cuando el cajero pulsa Agotado en Catálogo > Productos, entonces cambia su estado con un toque. Al abrir o recargar el POS sigue visible, con etiqueta Agotado y tarjeta deshabilitada. No se puede agregar al carrito.

## Escenario 2: reponer

Dado un producto agotado, cuando el cajero pulsa nuevamente el mismo botón Agotado en el catálogo, entonces pasa a Disponible. Al abrir o recargar el POS vuelve a poder agregarse al pedido.

No existe un botón separado Reponer en esta versión. La acción se realiza en el catálogo; la tarjeta bloqueada del POS no cambia la disponibilidad.

## Escenario 3: sin desactivar

Dado un producto agotado, cuando se revisa el catálogo y el formulario de edición como administrador, entonces el producto sigue activo. La marca persiste tras F5 y no elimina el producto ni cambia su categoría.

## Evidencias y definición de hecho

Adjuntar a la historia Jira: estado Agotado en catálogo, tarjeta bloqueada en POS, reposición a Disponible, producto agregado al pedido y casilla Activo conservada. Repetir los recorridos en escritorio y teléfono físico y registrar la revisión de otro integrante.

Este documento describe el comportamiento revisado en el código y el procedimiento de QA. No incorpora capturas ni certifica por sí solo las pruebas en un dispositivo físico o la revisión de otro integrante.

## Publicación y reemplazo de rama anterior

La nueva rama publica únicamente `docs/HU-17-Marcar-producto-como-agotado.md`. La implementación conserva su procedencia en el catálogo base y en el soporte POS de HU-10.

La rama anterior `feature/HU-17-productos-agotados` se reemplaza por esta entrega de validación. El script conserva una rama de respaldo local antes de borrarla del remoto y condiciona el borrado al SHA observado, para no eliminar cambios concurrentes. No se fusiona ni modifica develop.
