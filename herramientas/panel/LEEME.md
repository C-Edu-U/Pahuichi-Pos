# Armado de las páginas del panel (solo desarrollo)

Las páginas de `demo/panel/` comparten el marco (riel, barra superior, estado de impresora, selector de rol). Cada pantalla vive como fragmento en `fragmentos/` y este script le pone el marco.

```bash
# Desde la raíz del proyecto
node herramientas/panel/gen-ventas.mjs herramientas/panel/fragmentos/ventas.html
node herramientas/panel/gen-bitacora.mjs herramientas/panel/fragmentos/bitacora.html
node herramientas/panel/armar.mjs herramientas/panel/fragmentos demo/panel
```

- La primera línea de cada fragmento es un comentario con JSON: título, módulo del menú, `h1`, scripts y hojas extra.
- El menú y el marco se editan en `armar.mjs`, en un solo lugar.
- Edita el fragmento y vuelve a armar: los HTML de `demo/panel/` se sobrescriben.
- Solo hace falta Node en local. En la app PHP, el marco es `app/Views/layouts/panel.php` y cada fragmento pasa a su vista en `app/Views/`.
