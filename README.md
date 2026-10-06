# Pahuichi POS

Punto de venta web para un local de comida típica cruceña. Proyecto de Ingeniería de Software II. El alcance, la arquitectura y el backlog están en [HANDOFF.md](HANDOFF.md).

## Estado actual: prototipo de frontend

Esta etapa es **solo HTML, CSS y JavaScript**, sin PHP ni base de datos. Incluye el sistema de diseño y las pantallas del sprint 1 para pasarlas después a vistas PHP (`app/Views`) sin rediseñarlas.

| Pantalla | Archivo |
|---|---|
| Portada pública | `public/index.html` |
| Iniciar sesión (error, bloqueo 15 min, salida) | `public/login.html` |
| Escritorio por rol | `public/panel/escritorio.html` |
| Productos (búsqueda, filtros, agotado hoy) | `public/panel/productos.html` |
| Alta y edición de producto (código SIN, foto ≤ 2 MB) | `public/panel/producto-form.html` |
| Categorías (alta, desactivar con confirmación) | `public/panel/categorias.html` |
| Acceso denegado | `public/panel/403.html` |
| Módulo en construcción | `public/panel/proximamente.html?m=pos` |

### Cómo verlo

Abre `public/index.html` en el navegador, o sirve la carpeta: `npx serve public`.

- En el login entra con `cajero`, `analista` o `admin` y cualquier clave. Otro usuario cuenta como intento fallido; al quinto, la cuenta se bloquea 15 minutos.
- En el panel, el selector **«Prototipo: ver como»** cambia el rol y el menú se filtra según la matriz de la sección 4 del handoff. También sirve `?rol=cajero` en la URL.
- Si un rol entra por URL a un módulo que no le corresponde, va a `403.html`.

Todo lo de seguridad (permisos, CSRF, bloqueo, validación de subidas) se **simula** en el cliente para mostrar los estados. En PHP lo decide el servidor.

### Sistema de diseño

`public/assets/css/`:

- `tokens.css`: color, tipografía, espaciado (múltiplos de 8), radios, sombras y movimiento.
- `base.css`: reinicio, tipografía, foco visible, cifras tabulares.
- `components.css`: cinta, botón, campo, archivo, panel, tabla, insignia, alerta, pestañas, modal y estado vacío.
- `layout.css`: riel lateral (completo en escritorio, íconos en tableta, cajón en móvil) y barra superior.
- `pages.css`: estilos de cada pantalla. `print.css`: base para impresión.

La dirección visual es **«Tipoy y cinta»**: fondo blanco algodón, riel verde de la bandera cruceña y el color como cintas cosidas que marcan el módulo activo, los encabezados y los estados (verde, terracota y ámbar).

Íconos: [Tabler Icons](https://tabler.io/icons) (MIT), en un sprite local. Tipografía de marca: Archivo (SIL OFL), servida desde el propio sitio.
