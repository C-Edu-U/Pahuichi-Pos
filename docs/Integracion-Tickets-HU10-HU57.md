# Recuperación del flujo de tickets existente

Esta entrega recupera el flujo de tickets de la entrega anterior de HU-10 y lo integra con la numeración transaccional de HU-57. No desarrolla HU-58, HU-12 ni HU-32.

## Procedencia y alcance

El ZIP de rama recibido el 20/10/2026, `Pahuichi-Pos-feacture-HU-10-Leyendas-de-la-Ley-453.zip`, commit `a0a33a34c2d7b7b6213f4669821569818cfadca1`, contiene el catálogo de leyendas pero no los controladores de venta/factura del paquete anterior. Se reutilizan las vistas, QR e impresión de `HU10_Pahuichi_POS_Factura.zip`, conservando el catálogo/carrito de develop y el servicio/contador de HU-57. El ZIP no contiene historial suficiente para atribuir esos cambios a una persona.

## Comportamiento recuperado

- Efectivo exacto a la izquierda (F8), Cobrar a la derecha (F4).
- Tras confirmar el cobro, se vacía el carrito y aparece el aviso «Pedido cobrado» con «Ver tickets», sin abandonar el POS.
- «Ver tickets» abre comanda de cocina, copia de caja y factura, todas con los datos guardados de esa venta.
- Notas por producto: botón de nota, hasta 60 caracteres; se guardan en el detalle y aparecen en la comanda. Solo los productos configurados para cocina entran en ella.
- Factura con la leyenda fija y una leyenda del catálogo activo guardada al emitir. Reabrir/recargar/imprimir conserva la misma leyenda.
- QR con enlace a la verificación local de la factura por NIT, CUF, número y monto. No conecta con el SIN. Para leerlo desde otro dispositivo, la dirección del proyecto debe ser accesible desde ese dispositivo; localhost identifica a cada equipo.
- «Factura en PDF» abre el diálogo de impresión del navegador para elegir «Guardar como PDF». «Reimprimir los tres» registra la solicitud y abre el mismo diálogo con los tres documentos.
- Botones de ancho 76/58 mm recuperados de la vista previa anterior. No crean una configuración persistente de impresoras.
- En espera, Retomar y Descuento se muestran deshabilitados, como en el paquete anterior. Vaciar mantiene el diálogo de confirmación actual.
- Cliente S/N · NIT 0, sin edición ni búsqueda. Si una factura histórica ya guarda un literal, se puede mostrar; no se añade el conversor de HU-58 ni se generan nuevos literales.

## Archivos para explicar

| Archivo | Responsabilidad |
|---|---|
| app/Views/pos/index.php | Posición de botones, aviso Ver tickets, controles anteriores y cliente fijo. |
| public/assets/js/pos.js | Envía el cobro, muestra su resultado sin redirección, vacía el carrito, notas y atajos. Conserva el UUID para evitar duplicar reintentos. |
| app/Controllers/FacturaController.php | Carga los tickets guardados, verifica permisos, registra la solicitud de reimpresión y atiende la verificación del QR. |
| app/Models/Factura.php | Recupera facturas; añade la búsqueda del QR. El contador transaccional de HU-57 se conserva. |
| app/Views/facturas/tickets.php | Comanda, copia y factura con sus datos persistidos. |
| app/Views/facturas/index.php | Enlace «Ver tickets» desde el historial. |
| app/Views/facturas/verificar.php | Pantalla de verificación local del QR. |
| public/assets/js/qrcode.js | Generador QR existente, de Kazuhiko Arase, licencia MIT conservada. |
| public/assets/js/tickets.js | Genera el QR, alterna ancho y abre window.print(). No descarga mediante una librería PDF. |
| public/assets/css/tickets-hu10.css | Reglas de impresión: solo factura o tres documentos, sin menú del panel. |
| app/rutas.php | Ruta de reimpresión protegida y verificación local por datos del QR. |

No requiere una migración nueva. Conserva las ventas anteriores, el CUF de 28 caracteres, el número y la leyenda de cada factura. El PDF usa esos mismos datos y no vuelve a emitir.

## QA de esta actualización

Se verificó el cobro exacto y por modal, el aviso sin redirección, el orden de los botones, los tres tickets, las notas de cocina, la existencia y destino del QR, el modo de impresión de factura y de tres documentos, el registro de reimpresión y el flujo móvil. Se generaron PDFs de comprobación y se revisó su texto. También pasó el script específico de HU-57 para comprobar que se conserva su comportamiento transaccional. No se repitió la suite completa de 164 pruebas.

Prueba manual: agrega un plato, escribe una nota, cobra, abre Ver tickets y comprueba los tres. Pulsa Factura en PDF y elige Guardar como PDF; abre el archivo. Luego prueba Reimprimir los tres. Vuelve al POS y repite con Cobrar. F5 sobre los tickets debe conservar número, CUF y leyenda.

## Pendientes ya identificados

Esta actualización recupera los tickets. No cambia la configuración de sucursales, el texto incorrecto de «Próxima factura» ni conecta todavía el bloqueo por CUFD vencido. Esos puntos ya detectados deben tratarse por separado; no se presentan como corregidos.
