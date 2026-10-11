# HU-14 Gestión de productos — corrección de la demo

Historia de Jira: **SCRUM-25**. Implementación para la **etapa HTML, CSS y JavaScript sin servidor**, no para producción.

## Qué se corrigió

- Se guarda realmente el alta y la edición de productos usando `localStorage` del navegador.
- Al recargar, los productos registrados siguen en el catálogo y aparecen en el POS si están activos.
- Se mantiene la validación de código único y se evita sobrescribir un producto existente.
- Se guarda la categoría, precio, unidad, descripción, nombre corto y las marcas activo, favorito, cocina y agotado.
- Se distingue **inactivo** (no aparece en POS ni en pedidos del mesero) de **agotado** (aparece en POS, pero no se puede vender).
- Los cambios rápidos de favorito y agotado se conservan tras actualizar la página.
- Los nuevos registros se cuentan en el catálogo; se agregó el filtro «Inactivos».
- El código de un producto existente se muestra en modo de solo lectura al editar para no romper referencias de ventas y promociones.
- Si el almacenamiento está bloqueado o lleno, no se muestra una falsa confirmación de guardado.

## Archivos modificados

- `public/assets/js/datos.js`
- `public/assets/js/producto-form.js`
- `public/assets/js/productos.js`
- `public/assets/js/pos.js`
- `public/assets/js/mesero.js`
- `herramientas/panel/fragmentos/productos.html`
- `public/panel/productos.html` (generado desde el fragmento)

Prueba automática añadida: `pruebas/hu14.test.mjs`.

## Cómo probar desde la computadora

Desde la raíz del proyecto, servir la demo con un servidor local, por ejemplo:

```bash
npx serve public
```

(Ejecutar el comando desde la **raíz** del proyecto, no desde `public`.)

Abrir la dirección que indique el comando; entrar como administrador.

1. En Catálogo, agregar `PL-100` con nombre «Picante de lengua», precio Bs 42,00, categoría Platos, «Activo» y «Va a cocina» marcados.
2. Volver al catálogo: deben aparecer **26** productos. Actualizar la página: continúa en 26.
3. Agregar `PL-200` con nombre «Tallarín salteado de pollo» y la marca **Activo desmarcada**. El catálogo debe mostrar 27 y la fila «Inactivo».
4. Abrir Punto de venta: `PL-100` debe estar; `PL-200` no.
5. Intentar crear otro producto con código `PL-100`: debe aparecer «Ese código ya lo usa otro producto» y no debe guardarse.
6. Editar `PL-100` para cambiar precio y nombre. Guardar, recargar y verificar que se mantienen.
7. Activar `PL-200` editándolo. Debe aparecer en Punto de venta.
8. Cobrar un pedido de prueba con un plato marcado «Va a cocina» y una bebida sin esa marca; verificar que solo el plato aparece en la comanda.
9. Revisar desde teléfono y escritorio, y pedir a **José (QA-Eleazar)** que valide las pruebas.

Prueba de lógica automática (requiere Node.js):

```bash
node --test pruebas/hu14.test.mjs
```

## Limitaciones y próximos pasos

- **Solo persiste en ese navegador y origen** mediante `localStorage`; no se comparte entre computadoras ni teléfonos. Si se borran los datos del sitio, se recuperan los 25 productos de demostración.
- **No hay backend PHP, MariaDB ni Docker Compose** en el ZIP recibido. Por tanto **no cumple aún la definición de hecho completa de Jira**, aunque la demo de HU-14 esté corregida.
- Las fotos (HU-15) y el historial de cambios de precio (HU-19) quedan fuera de este cambio.
- Integrar el almacenamiento real con el backend cuando esté disponible y validar por otra persona antes de pasar SCRUM-25 a «Hecho».

## Integración con GitHub

El ZIP es una copia de `develop`, no una rama de Git. Cuando el equipo confirme la rama de integración, clonar el repositorio, crear una rama propia (por ejemplo `feature/SCRUM-25-gestion-productos`) y aplicar `PAHUICHI_HU14_cambios.patch` desde la raíz (o copiar los archivos modificados). Revisar con `git diff` antes de abrir una Pull Request.
