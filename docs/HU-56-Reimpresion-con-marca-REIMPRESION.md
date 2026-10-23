# HU-56 — Reimpresión y consulta de documentos emitidos

## Comportamiento

Después de cobrar, Ver tickets abre la entrega inicial. Caja y Cocina tienen botones Ver y Descargar que entregan el mismo PDF por perfil. Se respeta la configuración de ancho, columnas, tipos y copias. Entrar al historial o terminar la entrega cierra ese permiso inicial.

Desde el historial se recupera un ticket individual con REIMPRESIÓN y registro de usuario, fecha/hora, tipo y referencia de factura. La venta, pagos, número, CUF, leyenda e importes se conservan. Las URL PDF antiguas también producen reimpresiones auditadas.

El administrador ve **Consultar documentos emitidos** en Reimpresión. Abre Caja y Cocina del mismo pedido sin marca; la página se titula **Documentos emitidos de esta venta**. El acceso se registra como **Consulta de documentos emitidos**. No reabre el estado de entrega inicial ni crea una factura.

En **Configuración → Tickets en PDF → Consulta de documentos emitidos**, el administrador puede marcar **Permitir que los cajeros consulten los documentos emitidos** y pulsar **Guardar permiso**. Por defecto está desactivado. Los cajeros mantienen el alcance de sus ventas; esto no les permite consultar ventas de otros cajeros.

La autorización se comprueba al abrir la página y al solicitar cada PDF, incluso si estaba guardado en la sesión. Desactivar el permiso revoca los accesos de cajeros ya abiertos; el administrador conserva su acceso. Un archivo ya descargado en un dispositivo queda fuera del control del sistema.

No hay palabra secreta ni atajo de teclado para originales. Se exige sesión, permiso de acceso a la venta y CSRF. Las ventas anuladas no admiten esta consulta.

## Instalación

Ejecutar Instalar-HU56.ps1 con Docker activo y la carpeta montada correcta. Verifica el manifiesto, respalda archivos y base, aplica la migración idempotente 065 si falta y copia el código. Se utiliza la tabla existente configuracion para el permiso; no hay migración nueva respecto de Entrega Clara. No modifica puertos, dependencias ni alineación pendiente del PDF. No hace commit ni push.

## QA manual

1. Como administrador, abrir una venta cobrada desde Ventas y facturas. En Reimpresión, pulsar Consultar documentos emitidos; verificar el mismo pedido, número y total.
2. Ver y descargar Caja: deben incluir copia + factura si ambas están configuradas. Cocina conserva su perfil. No deben mostrar REIMPRESIÓN.
3. Volver a Reimpresiones y generar un ticket: debe mostrar REIMPRESIÓN.
4. Con el permiso desactivado, entrar como el cajero dueño de la venta: el botón no debe aparecer. Las reimpresiones siguen disponibles.
5. Como administrador, activar el permiso y guardarlo. Recargar la venta como cajero: debe aparecer Consultar documentos emitidos y abrir los mismos documentos.
6. Desactivar el permiso desde otra sesión de administrador. Intentar generar un original en la página que dejó abierta el cajero: debe rechazarse. Recargar Reimpresión: desaparece el botón.
7. El administrador debe seguir viendo el botón con el permiso desactivado.
8. Revisar bitácora: Consulta de documentos emitidos, Permiso de cajeros para consultar documentos emitidos y Reimpresión de tickets son acciones distintas.
9. Probar una compra nueva para comprobar la entrega inicial por perfiles.

## Archivos principales

- app/Controllers/FacturaController.php: acceso a venta, consulta de originales y respuestas PDF.
- app/Services/EntregaInicialService.php: permisos de sesión, configuración vigente y caché por perfil.
- app/Services/ReimpresionService.php: marca y auditoría de reimpresiones.
- app/Models/Configuracion.php y app/Controllers/ConfigController.php: persistencia y cambio autorizado del permiso.
- app/Views/config/_tickets.php y app/Views/facturas/tickets.php: opción de configuración y botón visible.
- app/rutas.php: rutas protegidas.
- config/bitacora.php: nombres legibles y filtros para las acciones.
- public/assets/js/tickets.js: QR y ancho visual; sin truco secreto.

## Validación

81 pruebas y 592 comprobaciones de entrega inicial, originales autorizados, revocación, CSRF, acceso a ventas, reimpresión, PDF y funciones relacionadas. Recorrido real en navegador con sesiones separadas de administrador y cajero; configuración persistente y pantalla de 360 px. Se extrajo el texto de los PDF descargados para confirmar originales sin marca y reimpresiones con marca. Pendiente QA en el Windows del usuario.

```powershell
docker compose -p pahuichi exec -T app php vendor/bin/phpunit --filter 'EntregaInicialTicketsTest|ReimpresionTicketsTest|TicketsPdfTest|MontoLiteralTest|ComandaCocinaTest|ClientesFacturaTest|PinAutorizacionTest'
```

Las pruebas reconstruyen exclusivamente la base dedicada de pruebas.

## Alcance académico y acuerdo de equipo

La consulta autorizada de documentos emitidos es una ampliación propuesta de HU-56. Está implementada para revisión; Jefferson la presentará a Eduardo en clase. La modificación de los criterios en Jira queda pendiente de ese acuerdo. No se ha solicitado ni obtenido autorización fiscal del SIN mediante este cambio.

La marca REIMPRESIÓN forma parte de los criterios académicos de esta HU. El cierre de la entrega inicial y los permisos de consulta son decisiones del proyecto, no requisitos tributarios acreditados. Para uso fiscal real se debe revisar la modalidad y especificaciones aplicables y completar la autorización e integración correspondientes.

Se conservan los identificadores internos de configuración y bitácora para no perder la continuidad con los registros existentes; sus nombres visibles son Consulta de documentos emitidos y Permiso de cajeros para consultar documentos emitidos.
