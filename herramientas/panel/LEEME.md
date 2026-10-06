# Armado de las páginas del panel (solo desarrollo)

Las páginas de `public/panel/` comparten el marco (riel, barra superior, selector de rol). Para no copiarlo a mano en 15 archivos, cada pantalla vive como fragmento en `fragmentos/` y este script le pone el marco.

```bash
# Desde la raíz del proyecto
node herramientas/panel/gen-ventas.mjs herramientas/panel/fragmentos/ventas.html
node herramientas/panel/gen-bitacora.mjs herramientas/panel/fragmentos/bitacora.html
node herramientas/panel/armar.mjs herramientas/panel/fragmentos public/panel
```

- La primera línea de cada fragmento es un comentario con JSON: título, módulo del menú, `h1`, scripts y hojas extra.
- El menú y el marco se editan en `armar.mjs`, en un solo lugar.
- Solo hace falta Node en local. No se sube al hosting. Al pasar a PHP, el marco se convierte en `app/Views/layouts/panel.php` y cada fragmento en su vista.
