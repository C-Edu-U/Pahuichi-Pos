# HU-32 · Cliente por NIT o CI

## Alcance y uso

Se implementan los tres escenarios de Jira sobre la versión publicada de HU-12. No se modifica develop ni se incluyen los ajustes pendientes de presentación/generación automática de HU-12.

En Punto de venta, Cambiar despliega el buscador con el aspecto de la demo. Al escribir el documento completo de un cliente registrado se completan su nombre y tipo. Un prefijo muestra hasta cinco sugerencias: se seleccionan con un toque o con flechas y Enter. Si el documento cambia, se limpia el nombre anterior; una respuesta de búsqueda atrasada no puede sustituir el documento actual.

El botón S/N restablece el valor predeterminado. Si no se proporciona información, la factura conserva S/N con NIT 0 sin crear registros ficticios.

Para un documento nuevo se introduce NIT/CI y nombre. Guardar y usar cliente registra los datos inmediatamente. También se puede confirmar el cobro directamente: el cliente se guarda en la misma transacción que la venta y factura. Si el cobro falla, ese alta no queda guardada. El siguiente pedido comienza en S/N para evitar facturar al cliente anterior por accidente.

Clientes permite registrar y consultar clientes antes de vender. Se reutilizan el panel, formulario desplegable y tabla adaptable de la demo. Solo administrador y cajero acceden; el mesero no tiene permiso. No se implementan regalos de cumpleaños, promociones ni edición/eliminación de clientes.

## Persistencia y validaciones

- Tabla clientes: id, tipo NIT/CI, documento único, nombre y fecha de alta.
- NIT numérico de hasta 20 dígitos; CI numérica con complemento opcional separado por guion (hasta cinco caracteres alfanuméricos).
- Nombre entre 2 y 120 caracteres, espacios normalizados. S/N con documento 0 no requiere registro.
- No se comprueba la existencia oficial del documento en organismos externos: es el catálogo interno del proyecto.
- Un documento registrado se reutiliza sin modificar su nombre desde el cobro. El servidor utiliza el nombre del catálogo, no un nombre manipulado enviado desde el navegador.
- El snapshot de la factura conserva id, nombre, tipo y documento del cliente al emitirla. Volver a consultar o descargar el PDF no cambia el cliente histórico.
- El identificador y hash del cobro incluyen al cliente cuando corresponde. Un reintento no duplica clientes, ventas ni facturas, y no permite cambiar el cliente de una venta ya confirmada.
- S/N mantiene compatibilidad con los cobros pendientes de la versión previa.
- Consultas preparadas, permisos, CSRF en altas y bitácora cliente.alta.

## Archivos para defender

| Archivo | Qué hace |
| --- | --- |
| `app/Models/Cliente.php` | Consulta exacta, sugerencias, listado y alta en MariaDB. |
| `app/Services/ClienteService.php` | Valida y normaliza datos; reutiliza un documento existente o registra uno nuevo con auditoría. |
| `app/Controllers/ClienteController.php` | Atiende el catálogo, búsqueda JSON y formulario de alta. |
| `app/Views/clientes/index.php` | Formulario y listado con los componentes de la demo. |
| `public/assets/js/cliente-pos.js` | Autocompletado real, selección, S/N y nombre del cliente en el pedido. |
| `app/Services/VentaService.php` | Resuelve el cliente dentro de la transacción y lo guarda en el snapshot. |
| `app/Controllers/PosController.php` | Carga el JavaScript de cliente antes del flujo de cobro. |
| `app/Views/pos/index.php` | Habilita Cambiar e incorpora el buscador de la demo y campos necesarios. |
| `public/assets/js/pos.js` | Envía el cliente con el cobro, conserva los datos al reintentar y restablece S/N al terminar. |
| `public/assets/css/pos.css` | Ajustes locales para el buscador a 360 px y controles táctiles. |
| `app/rutas.php` | Rutas protegidas de clientes. |
| `config/modulos.php` | Activa Clientes en lugar del aviso de módulo pendiente. |
| `config/bitacora.php` | Etiqueta legible del alta de cliente. |
| `database/migraciones/032_hu32_clientes.sql` | Crea la tabla y el índice único sin cambiar las tablas anteriores. |
| `tests/Integracion/ClientesFacturaTest.php` | Verifica escenarios, permisos, validaciones, persistencia e idempotencia. |

La plantilla de factura y el PDF ya leían el cliente del snapshot. Se reutilizan sin modificarlos.

## QA y evidencias para Jira

1. Entrar como cajero. En Clientes crear NIT 1234567 con un nombre de prueba si aún no existe. Recargar y comprobar que permanece.
2. Escenario 1: en POS tocar Cambiar y escribir 1234567. El nombre se completa automáticamente. Capturar esa pantalla; cobrar una venta y capturar el nombre y documento en la factura/PDF.
3. Escenario 2: en otro pedido seleccionar S/N, cobrar y comprobar S/N y NIT 0 en la factura. Capturar factura o PDF.
4. Escenario 3: escribir un documento nuevo y nombre, cobrar, recargar POS y buscar ese documento. Debe completar el nombre registrado. Capturar el registro y la nueva búsqueda; comprobarlo también en Clientes.
5. Cambiar un documento registrado por otro: el nombre anterior no debe mantenerse. Buscar por prefijo y probar flechas/Enter.
6. Probar que un documento nuevo sin nombre no permite cobrar y que un cobro fallido no registra al cliente.
7. En teléfono: abrir el pedido, Cambiar, buscar, seleccionar, volver a S/N y consultar el PDF. Los campos no deben salir del ancho de pantalla.

## Verificación de la entrega

52 pruebas de HU-32/HU-12/HU-58, con 239 aserciones satisfactorias. Prueba de navegador con cajero: alta, búsqueda exacta, tres cobros con cliente registrado/SN/nuevo, recarga, selección por teclado y formularios a 360 px. Los tres PDF contienen el cliente esperado. PHP 8.3.6/MariaDB 10.11.7 en el entorno de comprobación; el proyecto conserva su Docker PHP 8.2/MariaDB 11.4. Falta tu QA manual y el de otro integrante.

```powershell
docker compose -p pahuichi exec -T app php vendor/bin/phpunit --filter 'ClientesFacturaTest|TicketsPdfTest|NumeroALetrasTest|MontoLiteralTest'
```

Las pruebas utilizan la base terminada en _pruebas, nunca la base de trabajo.
