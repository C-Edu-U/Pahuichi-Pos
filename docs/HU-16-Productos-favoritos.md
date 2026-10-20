# SCRUM-27 · HU-16 Productos favoritos

## Alcance y procedencia

La funcionalidad se reutiliza de la base de productos y del POS habilitado para comprobar HU-10. Durante la revisión de HU-16 no se crearon ni modificaron archivos de aplicación. Este documento registra la validación de la historia y permite localizar el código existente; su publicación no representa una nueva implementación de favoritos.

El POS funcional se entregó en `feacture/HU-10-Leyendas-de-la-Ley-453`. Si esa rama todavía no está integrada en develop, la comprobación completa del POS requiere integrar primero ese soporte mediante la revisión del equipo. La publicación de este documento no copia ni fusiona dicha rama. Las fotos de HU-15 son complementarias; no son necesarias para marcar favoritos.

## Código que interviene

| Archivo | Responsabilidad |
| --- | --- |
| `app/Views/catalogo/productos.php` | Botón Favorito de cada producto, atributo aria-pressed y filtro de favoritos. |
| `public/assets/js/productos.js` | Envía el cambio al servidor y actualiza botón, tabla y filtros. |
| `app/rutas.php` | POST /catalogo/productos/{id}/favorito, protegido con permisos de administrador y CSRF. |
| `app/Controllers/ProductoController.php` | favorito() delega en marcar(): recupera el producto, cambia el estado, registra bitácora y responde JSON o redirección. |
| `app/Models/Producto.php` | marcar() persiste productos.favorito; listar() entrega la marca y la categoría. |
| `app/Controllers/PosController.php` | Entrega al POS el catálogo activo y sus marcas; el soporte funcional está en la entrega HU-10. |
| `app/Views/pos/index.php` | Botón Favoritos seleccionado inicialmente y contenedor de las tarjetas. |
| `public/assets/js/pos.js` | categoria = 'favoritos' al iniciar; pintarGrilla() filtra por favorito o por categoría y permite agregar el producto al pedido. Entregado con el soporte POS de HU-10. |
| `config/permisos.php` | catalogo.editar reservado al administrador. |
| `database/migraciones/001_base_sprint1.sql` | Define el campo productos.favorito ya existente. |
| `tests/Integracion/ProductosTest.php` | Pruebas existentes de respuesta JSON al marcar favorito y restricción al cajero. |

## Escenario 1: favoritos en la primera pantalla

- Dado que el administrador marcó ocho productos activos como favoritos.
- Cuando el cajero abre el punto de venta con caja abierta, sin elegir categoría.
- Entonces Favoritos está seleccionado y aparecen los ocho productos.
- Al recargar con F5 se mantienen las marcas y la vista inicial.

## Escenario 2: quitar favorito

- Dado que el administrador quita la marca de uno de los ocho productos, manteniéndolo activo.
- Cuando el cajero vuelve a abrir o recarga el punto de venta.
- Entonces se muestran los siete favoritos restantes.
- El producto desmarcado permanece en su categoría y se puede agregar al pedido.

Ocho es el dato de preparación de la prueba. Esta historia no impone mínimo ni máximo de ocho favoritos.

## Resultado y evidencias

El responsable reportó que sus pruebas manuales fueron satisfactorias el 17 de octubre de 2026. Las capturas deben adjuntarse a SCRUM-27 en Jira; este documento no incorpora imágenes ni certifica una revisión de otro integrante.

En la comprobación previa del entorno de desarrollo con Chromium se verificaron ambos escenarios, persistencia con F5 y presentación a 1440 y 360 píxeles, sin errores JavaScript. La vista móvil simulada no sustituye la evidencia de un teléfono físico ni la revisión de un compañero.

Evidencias para Jira:

1. Catálogo con ocho favoritos.
2. POS inicial del cajero con los ocho productos.
3. Catálogo tras quitar una marca.
4. POS inicial con siete y el producto ausente de Favoritos.
5. Producto desmarcado visible en su categoría y agregado al pedido.
6. Evidencias de celular y revisión por otro integrante según la definición de hecho.

## Entrega de esta rama

Únicamente `docs/HU-16-Productos-favoritos.md`. El código de aplicación citado se reutiliza y conserva su procedencia. No hay nueva tabla, migración, controlador ni servicio de favoritos en esta entrega.
