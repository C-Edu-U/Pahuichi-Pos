# HU-58 Monto total en literal

Base: HU-57 (commit publicado 1a393ff), con el cobro y los tres tickets de HU-10.
Alcance: convertir el total final de cada nueva factura a letras en español, en el servidor, y guardar el resultado con ella. No modifica el diseño de la demo.

## Funcionamiento y archivos

- Nuevo `app/Services/NumeroALetras.php`: recibe centavos enteros. Separa bolivianos con `intdiv($centavos, 100)` y centavos con `% 100`. Convierte unidades, decenas, centenas, miles y millones; escribe UN, VEINTIÚN y TREINTA Y UN antes de BOLIVIANOS; convierte a mayúsculas y agrega los dos dígitos `/100 BOLIVIANOS`. Usa UN MIL para respetar el escenario 3 de Jira.
- Modificado `app/Services/VentaService.php`: al emitir la factura agrega `'literal' => NumeroALetras::convertir($total)` al snapshot, dentro de la misma transacción de la venta. El total viene de precios y cantidades validados en el servidor; el navegador no envía el literal.
- Ya existentes y reutilizados: `app/Models/Factura.php::crear()` guarda el snapshot en `facturas.snapshot_json`; `FacturaController::mostrar()` lo recupera; `app/Views/facturas/tickets.php` muestra `Son: ...` cuando existe `literal`. La impresión/PDF utiliza ese mismo HTML.
- Nuevos `tests/Unit/NumeroALetrasTest.php` y `tests/Integracion/MontoLiteralTest.php`: formato, límites, los tres escenarios por la ruta MVC, persistencia, recarga, reimpresión y reintento de cobro.
- Este documento es nuevo. No se necesita migración SQL: se reutiliza `snapshot_json` de HU-57.

Las facturas anteriores conservan su contenido original; no se reescriben. Las emitidas después de instalar HU-58 incluyen el literal. No se recalcula el literal de una factura emitida al consultar el catálogo.

## QA manual: escritorio y celular

1. Como administrador, crear tres productos temporales y activos dentro de una categoría activa, con precios Bs 38,50; Bs 1,00; Bs 1.250,75. Usar códigos propios de QA para identificarlos. No cambiar productos que necesiten otros compañeros.
2. Como cajero con caja abierta, agregar una unidad del primer producto, comprobar total 38,50 y cobrar. Abrir Ver tickets y revisar la factura:
   `Son: TREINTA Y OCHO 50/100 BOLIVIANOS`.
3. Hacer otra venta de una unidad del producto de 1,00:
   `Son: UN 00/100 BOLIVIANOS`.
4. Hacer otra venta de una unidad del producto de 1.250,75:
   `Son: UN MIL DOSCIENTOS CINCUENTA 75/100 BOLIVIANOS`.
5. Recargar la factura, salir y volver desde Ventas y facturas: total, literal, número y CUF deben permanecer iguales.
6. Abrir Factura en PDF y elegir Guardar como PDF. Abrir el archivo y comprobar el mismo literal.
7. Cambiar el precio de uno de los productos temporales y volver a consultar su factura ya emitida: debe conservar el monto y el literal originales.
8. Repetir la consulta y al menos una venta desde el celular conectado a la misma red. El texto debe poder leerse en la factura y no quedar cortado.
9. Guardar capturas de cada escenario en Jira. Eduardo debe realizar el QA independiente antes de cerrar la historia.

## Consultar el dato guardado (solo lectura)

En MariaDB, con la base del proyecto seleccionada:

```sql
SELECT numero, monto_total,
       JSON_UNQUOTE(JSON_EXTRACT(snapshot_json, '$.literal')) AS literal
FROM facturas
ORDER BY id DESC
LIMIT 10;
```

## Pruebas automáticas específicas

```powershell
docker compose -p pahuichi exec -T app vendor/bin/phpunit --filter 'NumeroALetrasTest|MontoLiteralTest'
```

El bootstrap de pruebas existente prepara exclusivamente `<DB_NOMBRE>_pruebas`; no borra la base de trabajo. Cada prueba de integración revierte sus cambios al finalizar. No hace falta ejecutar toda la suite para revisar esta entrega.

## Git

Trabajar en `feacture/HU-58-Monto-total-en-literal`, creada desde la rama HU-57. No modificar develop ni hacer commit en la rama HU-57 para entregar HU-58. Solo se modifican/agregan cinco archivos en esta entrega.
