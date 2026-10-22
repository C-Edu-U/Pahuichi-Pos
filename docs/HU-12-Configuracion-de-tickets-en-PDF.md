# HU-12 · Configuración de tickets en PDF

## Comportamiento

El administrador configura los perfiles Caja y Cocina desde Configuración → Tickets en PDF. Cada perfil guarda en MariaDB el ancho (58 o 76 mm), las columnas (24 a 48), las copias (1 a 3) y los tipos incluidos (comanda, copia de caja, factura). Es obligatorio seleccionar al menos un tipo.

Después de cobrar, Ver tickets mantiene las tres vistas del pedido. Los botones Ver PDF de caja/cocina muestran un PDF real y Descargar PDF de caja/cocina descargan el archivo. Factura en PDF genera solamente la factura con el perfil de caja. El navegador puede decidir mostrar o descargar un PDF según su soporte y preferencias; la opción Descargar solicita siempre un archivo adjunto.

El servidor utiliza Dompdf para generar el PDF y BaconQrCode para incluir el QR sin depender de JavaScript. No existe una llamada a imprimir en este flujo, no se buscan impresoras y no se envía ningún trabajo de impresión. Las opciones que ofrezca el visor PDF instalado en el dispositivo pertenecen al visor.

El PDF usa el ancho físico indicado. Las columnas determinan el tamaño de la letra monoespaciada dentro del ancho útil (ancho menos 6 mm de márgenes). Cada ticket/copia empieza en una hoja; la altura se calcula a partir del contenido. Un pedido excepcionalmente largo puede continuar en otra hoja del mismo ancho.

Los datos de la venta provienen de `facturas.snapshot_json`: productos, precios, fecha, CUF, número, leyendas y monto literal se conservan. Cambiar un perfil afecta el siguiente PDF generado, incluso de una venta anterior, pero no modifica esa venta ni su snapshot. No se guarda un PDF duplicado en la base de datos: se genera bajo demanda con los datos persistidos.

## Archivos para explicar al docente

| Archivo | Responsabilidad |
| --- | --- |
| `app/Models/ConfiguracionTickets.php` | Lee y actualiza los perfiles guardados en la base. |
| `app/Services/TicketsPdf.php` | Valida opciones, guarda con auditoría y genera el PDF según el perfil vigente. |
| `app/Controllers/ConfigController.php` | Recibe el formulario del administrador. |
| `app/Controllers/FacturaController.php` | Comprueba permisos y entrega bytes PDF con `application/pdf` e `inline` o `attachment`. |
| `app/Views/config/_tickets.php` | Formularios Caja y Cocina. |
| `app/Views/config/index.php` | Integra esos formularios al diseño existente. |
| `app/Views/facturas/_ticket.php` | Contenido compartido: comanda, copia y factura desde el snapshot. |
| `app/Views/facturas/pdf.php` | Plantilla interna y estilo del PDF; no se entrega como página HTML. |
| `app/Views/facturas/tickets.php` | Vista previa y botones de ver/descargar por perfil. |
| `public/assets/js/tickets.js` | QR de la vista previa, selector visual de ancho y acceso a factura PDF. |
| `app/rutas.php` | Rutas de configuración y PDF con autenticación/permisos. |
| `config/bitacora.php` | Nombre de la acción de configuración de perfiles. |
| `database/migraciones/012_hu12_tickets_pdf.sql` | Tabla, restricciones y perfiles iniciales; se conserva la migración anterior. |
| `composer.json`, `composer.lock` | Dependencias PDF/QR y sus versiones reproducibles. |
| `tests/Integracion/TicketsPdfTest.php` | Configuración, PDF real, ancho, copias, descarga y permisos. |

La actualización retira `public/assets/css/tickets-pdf.css` y `public/assets/js/tickets-pdf.js`, exclusivos del flujo anterior de impresión. La nueva plantilla comparte el contenido del ticket y tiene el estilo necesario para Dompdf. No se modifican las migraciones ya aplicadas ni se vacían tablas.

## QA manual

1. Administrador: configurar Caja en 76 mm, 42 columnas, 1 copia, factura y copia de caja. Guardar, F5 y comprobar persistencia.
2. Configurar Cocina en 58 mm, 32 columnas, 2 copias y solamente comanda.
3. Cajero: abrir caja, agregar un plato que vaya a cocina y cobrar. Entrar a Ver tickets.
4. Ver PDF de caja: dos hojas (copia y factura), ancho 76 mm. Descargar, abrir el archivo guardado y comprobar productos, importes, literal, CUF, ambas leyendas y QR.
5. Ver PDF de cocina: dos comandas de 58 mm; no contiene factura ni copia de caja. Revisar notas de cocina.
6. Cambiar las copias de Caja a 3. El siguiente PDF de caja tendrá seis hojas. La venta conserva número, CUF, productos y total.
7. Cambiar columnas y generar otro PDF: cambia el ajuste de la letra, manteniendo el ancho físico seleccionado.
8. Probar vista y descarga en computadora y teléfono. Ninguna acción debe abrir automáticamente el diálogo de impresión.
9. Rechazar ancho 80, columnas 0/49, copias 0/4 y ningún tipo seleccionado. El perfil anterior permanece.
10. El cajero no puede configurar perfiles. Otro cajero no puede consultar una venta que no le corresponde.

La medida física se revisa en las propiedades de página del PDF; el zoom del visor no equivale al ancho del documento.

## Comandos

```powershell
docker compose -p pahuichi exec -T app php vendor/bin/phpunit --filter 'TicketsPdfTest|NumeroALetrasTest|MontoLiteralTest'
```

Las pruebas usan exclusivamente la base terminada en `_pruebas`. El instalador respalda los archivos y la base de trabajo antes de reemplazar la HU-12 anterior. No hace commits ni sube cambios.

## Verificación de esta entrega

- 36 pruebas de HU-12/HU-58: 167 aserciones satisfactorias.
- Descarga real por HTTP con `application/pdf`, disposición `inline`/`attachment`, configuración persistente y botones en escritorio y móvil simulado.
- Archivos PDF inspeccionados: Caja 58 mm, dos tipos y dos copias (cuatro hojas); Cocina 76 mm (una comanda). Anchos físicos 58,000 y 76,000 mm; texto dentro de la página, literal, ambas leyendas y QR presentes.
- Composer validado y auditoría de dependencias de producción sin avisos conocidos al preparar la entrega.
- Entorno de verificación: PHP 8.3.6/MariaDB 10.11.7; el despliegue del proyecto conserva PHP 8.2/MariaDB 11.4 de Docker. Queda realizar el QA manual en tu Docker y tu teléfono real.
