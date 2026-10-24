# HU-56 — Vista compacta y modo de reimpresión

## Revisión acordada después de la demostración

- Ventas y facturas conserva la acción **Ver tickets**. También es el destino al terminar un cobro.
- Abre directamente Comanda, Copia de caja y Factura, con las acciones en una barra superior. Se elimina el formulario grande de entrega y consulta.
- **Ver tickets** y **Reimpresión** son dos modos de la misma pantalla. La barra incluye Ver tickets, Reimpresión, un menú PDF de tickets y Anular venta. El menú sustituye los botones separados de PDF. Esta revisión no implementa la HU-44 de anulación.
- Ver tickets muestra los documentos sin marca. Pulsar Reimpresión muestra los tres documentos con REIMPRESIÓN; los enlaces PDF también incluyen la marca en ese modo.
- Ambos modos respetan los perfiles del administrador. Comanda usa el ancho de Cocina; Copia y Factura usan el de Caja. Ejemplo: Cocina 76 mm y Caja 58 mm. El ancho se muestra como texto junto al título de cada ticket; no hay botones para cambiarlo en esta pantalla.
- Los PDF respetan los tipos incluidos, columnas y número de copias configurados por destino. La selección explícita permite recuperar cualquiera de los tres documentos, igual que la recuperación individual de HU-56; los tipos a incluir se eligen en el menú.
- Cajero y administrador tienen acceso sin activar una opción adicional. Cada rol conserva el alcance de ventas existente: el cajero consulta sus ventas y el administrador todas. El cajero no puede cambiar los perfiles de impresión.
- Se retira de Configuración la opción de permiso para cajeros y su ruta de guardado. El valor antiguo en la base queda sin efecto; no se borra historial.
- Se conservan los datos guardados de la venta: número, CUF, productos, precios, promociones, notas y leyenda. No se genera otra venta, pago, factura ni correlativo.

## Bitácora

Abrir el modo Reimpresión regenera tres vistas y registra tres acciones factura.reimpresion, con usuario, fecha/hora, factura y tipo. El detalle distingue medio=vista. Cada PDF reimpreso registra los tipos realmente incluidos, con medio=pdf. Volver a abrir o recargar el modo genera nuevos registros; no duplica la venta. Consultar un PDF normal usa factura.consulta_original, sin contarlo como reimpresión.

## Compatibilidad

Los enlaces /originales y el POST /acceso-original de la revisión anterior redirigen a Ver tickets. El POST /original conserva CSRF y genera el PDF normal del perfil indicado. El POST /reimpresion mantiene la recuperación individual marcada y auditada para compatibilidad.

La tabla entregas_tickets y su migración 065 se conservan como historial de la revisión anterior; el nuevo recorrido no depende de tokens ni de cierres de entrega. No se necesita nueva migración ni instalación de dependencias. No se aplica la corrección pendiente de alineación del PDF.

## QA manual

1. Instalar sobre la HU-56 del commit e3c9572 y recargar con Ctrl + F5.
2. Entrar como administrador a Configuración → Tickets en PDF: la opción de permiso de cajeros ya no aparece. Guardar Caja a 58 mm y Cocina a 76 mm.
3. En Ventas y facturas pulsar Ver tickets: aparecen los tres documentos y los botones arriba, sin el formulario grande.
4. Comprobar 58 mm en Copia y Factura y 76 mm en Comanda; esos indicadores no son selectores.
5. Ver y descargar Caja: conserva Copia y Factura si ambas están incluidas en su perfil. Cocina conserva Comanda por defecto. Sin marca en el modo Ver tickets.
6. Pulsar Reimpresión: aparecen los mismos tres documentos marcados. Ver y descargar los PDF de ambos perfiles y Factura en PDF; todos deben estar marcados.
7. Volver a Ver tickets: los documentos aparecen sin marca. Los datos, número de venta y CUF son iguales en ambos modos.
8. Revisar la bitácora y distinguir vista de PDF en el detalle de cada reimpresión.
9. Repetir como cajero en una venta propia: no necesita habilitación adicional, y no puede editar Configuración.
10. Probar también en teléfono. Los botones se adaptan al ancho de pantalla; los tickets se distribuyen verticalmente cuando falta espacio.

## Validación

86 pruebas y 745 comprobaciones de la HU y funciones relacionadas. Se verificó en navegador con sesiones separadas de cajero y administrador, a 1600, 1366 y 360 px. Se descargaron diez PDF (cinco selecciones por dos modos), y se comprobó mediante extracción de texto y tamaño de página que Caja usa 58 mm, Cocina 76 mm y solo el modo Reimpresión incorpora la marca. Los tres tickets son idénticos entre modos salvo la visibilidad de la marca.

El entorno de prueba conserva la base disponible y el ticket combinado con regalos y promociones compartido por el usuario. La suite completa de promociones del develop remoto no fue ejecutada aquí.

## Entrega y GitHub

Este paquete actualiza la HU-56 ya instalada, respalda los archivos y no modifica la base de datos. Incluye un parche incremental para actualizar después la misma rama de GitHub. El instalador no hace commit ni push. No modifica los criterios de Jira ni acredita autorización fiscal del SIN.

## Menú de PDF

Pulsar **PDF de tickets** abre una lista compacta sin desplazar los documentos. Elegir:

- Solo comanda.
- Solo copia de caja.
- Solo factura.
- Copia de caja y factura.
- Los tres tickets.

En **Qué deseas hacer**, seleccionar **Ver PDF** o **Descargar PDF**, y pulsar **Generar PDF**. Cada selección produce un único archivo. El menú conserva las opciones elegidas mientras la página permanece abierta. Se cierra al pulsar fuera o Escape; el foco vuelve al botón al usar Escape.

En modo Ver tickets, el PDF no lleva marca. En modo Reimpresión, todos los documentos incluidos llevan la marca y se auditan los tipos seleccionados, con usuario, fecha, factura, destino y copias de cada perfil. La bitácora distingue la generación en pantalla de la generación del PDF.

Cada documento usa ancho, columnas y copias de su destino: Comanda corresponde a Cocina, y Copia/Factura a Caja. Seleccionar los tres reúne páginas de 58 y 76 mm cuando así están configurados los destinos, sin escalar el contenido ni imponer un ancho único. Se conserva el diseño actual del PDF, incluida la alineación pendiente.

### QA del menú

1. Abrir Ver tickets y pulsar PDF de tickets.
2. Comprobar las cinco opciones y las dos acciones.
3. Generar cada opción con Ver y Descargar: comparar los documentos incluidos.
4. Probar Los tres tickets con Cocina 76 mm y Caja 58 mm: debe ser un PDF de tres páginas si cada perfil tiene una copia.
5. Repetir en modo Reimpresión: deben estar marcadas todas las páginas seleccionadas.
6. Revisar la bitácora: el detalle de PDF registra solo los tipos seleccionados y sus destinos/copias correctos.
7. Probar en teléfono y cerrar con Escape o pulsando fuera.

El archivo tests/Integracion/SeleccionTicketsTest.php cubre las cinco selecciones en ambos modos, los anchos mixtos, copias por destino, descarga, auditoría, conservación de datos y rechazo de parámetros manipulados. No se agregan dependencias ni migraciones. Este paquete se instala después de Vista Compacta y conserva el respaldo de archivos; incluye un parche acumulado desde e3c9572 para la posterior actualización de la misma rama.

## Altura individual de cada página

Se corrigió el espacio vacío que aparecía al final de los documentos cortos. Antes, todas las páginas adoptaban el largo de la factura más extensa. Ahora se mide cada artículo con su ancho y tipografía configurados y se ajusta el límite físico de su página al contenido, conservando el margen de seguridad. Esto se aplica a documentos individuales, combinaciones, copias adicionales y PDF de Caja/Cocina de los enlaces anteriores, en modo normal y reimpresión.

El ajuste conserva la escala, el texto, los importes, el QR y sus enlaces. La altura de una comanda o copia combinada coincide con la que tiene al generarse individualmente. No modifica el diseño interno ni incorpora la alineación pendiente del PDF.

Para verificar, generar un PDF NUEVO de Los tres tickets. Comanda, Copia y Factura deben tener largos distintos según su contenido; el papel debe terminar poco después del último elemento. Comparar con Solo comanda y Solo copia. Repetir con reimpresión y con copias adicionales. Los archivos descargados antes de la actualización no se modifican.

Validación: 86 pruebas, 745 comprobaciones y las diez descargas reales de cinco selecciones por dos modos. Se extrajo el texto y se verificaron dimensiones, margen inferior y enlaces de cada página; los PDF combinados conservan exactamente el contenido de sus documentos individuales. La tabla y la vista HTML no cambian. No hay migraciones ni dependencias nuevas.
