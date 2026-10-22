# HU-55 · Comanda de cocina

## Funcionalidad reutilizada

La comanda ya existía sobre HU-32. Muestra número de pedido grande, mesa o PARA LLEVAR, fecha/hora, ítems, cantidades y notas sin precios. La plantilla compartida filtra únicamente las líneas cuyo snapshot contiene cocina=true, valor que se obtiene de productos.va_a_cocina al cobrar.

Con el acuerdo del proyecto de no utilizar impresora física, la salida se visualiza/descarga en PDF mediante el perfil Cocina de HU-12. No se agrega un servicio de impresión ni una pantalla de pedidos en vivo.

## Corrección de las notas en el POS

Se corrigió la superposición del resumen de una nota sobre su propio campo de edición. El resumen y el editor ahora están dentro de un contenedor propio, sin compartir celda de cuadrícula. El editor usa un área de texto de varias líneas, etiqueta visible y ayuda con el límite de 60 caracteres. Mientras el editor está abierto, el resumen permanece oculto. Al cerrar el editor voluntariamente mediante el icono, el resumen aparece una sola vez.

El estado abierto/cerrado se conserva por producto cuando el pedido se vuelve a dibujar al agregar ítems o cambiar cantidades. Hacer clic fuera no cierra el campo. Durante la escritura se preserva el texto, incluidos espacios; el servidor conserva la normalización de la nota al confirmar el cobro. Quitar una línea, cancelar el pedido o finalizar el cobro elimina el estado de edición correspondiente.

Este estado visual vive en el navegador. Las notas confirmadas siguen guardándose en los detalles de venta y el snapshot de la factura, como antes. No se modifican tablas ni migraciones.

## Archivos de esta entrega

| Archivo | Cambio |
| --- | --- |
| `public/assets/js/pos.js` | Conserva el estado de edición de las notas y evita mostrar simultáneamente campo y resumen. |
| `public/assets/css/pos.css` | Separa el bloque de notas y permite texto largo sin superposición. |
| `tests/Integracion/ComandaCocinaTest.php` | Seis pruebas de contenido, filtros, pedido para llevar, PDF y snapshot. |
| `docs/HU-55-Comanda-de-cocina.md` | Guía de escenarios, evidencias y mapa de código. |

## Archivos existentes que explican la HU

| Archivo | Responsabilidad ya implementada |
| --- | --- |
| `app/Views/facturas/_ticket.php` | Bloque comanda: número, mesa/llevar, hora, cantidades, notas y filtro de cocina. No muestra precios. |
| `app/Services/VentaService.php` | Valida notas (hasta 60 caracteres) y guarda nota/va_a_cocina en el snapshot. |
| `app/Views/facturas/pdf.php` | Presentación del PDF: pedido grande, mesa destacada y notas. |
| `app/Services/TicketsPdf.php` | Aplica el perfil Cocina y genera el PDF real. |
| `app/Views/catalogo/producto-form.php` | Casilla Va a cocina del producto. |

## QA manual para Jira

1. Administrador: plato con Va a cocina activado, soda con la casilla desactivada; perfil Cocina incluye Comanda.
2. Cajero: Mesa 5, dos unidades del plato, nota sin ají.
3. Durante la escritura debe verse una sola nota. Hacer clic fuera, agregar una soda y cambiar cantidades: el campo permanece abierto y conserva el contenido. Añadir y sin arroz.
4. Cerrar el editor voluntariamente con el icono: queda solo el resumen. Abrirlo: queda solo el campo. Cancelar/quitar una línea y volver a agregarla: no debe recuperar una nota eliminada.
5. Confirmar cobro. Comanda muestra pedido grande, MESA 5, hora, cantidad y SIN AJÍ Y SIN ARROZ, sin precios. Capturar pedido y PDF de cocina.
6. Con plato y soda en la venta: solo el plato aparece en comanda. Capturar factura y comanda como evidencia del filtro.
7. Repetir para llevar y verificar PARA LLEVAR. Consultar los documentos después de F5: conservan los datos confirmados.
8. Probar edición y descarga desde teléfono. La comprobación automatizada cubrió un ancho simulado de 360 px; falta la prueba del equipo en un teléfono real.

## Comprobaciones realizadas

58 pruebas de HU-55/HU-32/HU-12/HU-58: 298 aserciones satisfactorias. Navegador: nota sin duplicado, edición después de agregar productos/cambiar cantidades/clic fuera y clic derecho seguido de reentrada con un clic, texto largo, cierre explícito, escritorio y 360 px; cobro real y PDF con la nota completa. Se repitió también el flujo de cliente registrado, S/N y cliente nuevo de HU-32. PHP 8.3.6/MariaDB 10.11.7 en el entorno de comprobación; el Docker del proyecto conserva PHP 8.2/MariaDB 11.4.

```powershell
docker compose -p pahuichi exec -T app php vendor/bin/phpunit --filter ComandaCocinaTest
```

Las pruebas usan exclusivamente la base terminada en _pruebas. El instalador copia estos cuatro archivos sobre HU-32 en una rama HU-55 y respalda los archivos existentes. No toca puertos, base de datos ni develop; no hace commit ni push.
