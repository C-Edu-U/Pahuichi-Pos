# Design

Sistema visual de Pahuichi POS. Lo que dice este archivo está construido en `public/assets/css/`; si el código y este documento difieren, se corrige el que esté mal y se avisa a Carlos (dueño del sistema de diseño).

## Dirección: «Tipoy y cinta»

El panel viste como el tipoy cruceño: algodón blanco liso, y el color solo aparece como **cintas cosidas**. Una cinta es una banda de color con una costura punteada blanca en el centro. Marca tres cosas y nada más:

1. **Dónde estás:** el módulo activo del riel queda atado con una cinta blanca, y la barra superior cierra con una cinta verde.
2. **Estado:** verde (correcto), ámbar (aviso, por vencer, agotado hoy), terracota (peligro, error, eliminar).
3. **Avance:** la barra de progreso de homologación SIN es una cinta.

Si una cinta no cumple una de esas funciones, sobra.

**Movimiento distintivo:** al cambiar de módulo, la cinta del riel se desliza desde el módulo anterior al nuevo (220 ms, `--ease-in-out`). Con `prefers-reduced-motion` salta sin deslizarse.

## Color

Estrategia contenida: neutros con un punto de verde, un riel verde comprometido y tres colores de estado. Nunca crema ni beige.

| Rol | Token | Valor | Uso |
|---|---|---|---|
| Riel | `--verde-700` | #13603e | Menú lateral, bandas de la portada |
| Primario | `--verde-600` | #177448 | Botón primario, enlaces (5,7:1 sobre blanco) |
| Texto | `--tinta-900` | #16201b | Texto principal |
| Texto secundario | `--tinta-600` | #55615a | Ayudas, metadatos (6,4:1) |
| Placeholder | `--tinta-500` | #69756e | Solo placeholder y ayudas (4,8:1) |
| Peligro | `--terracota-600` | #b2431e | Error, eliminar, costura de la portada |
| Aviso | `--ambar-800` sobre `--ambar-100` | #6e4700 / #fcefcf | Por vencer, agotado hoy |
| Foco | `--color-foco` | #1c6fd1 | Anillo de foco de 3 px, distinto de cualquier estado |

El estado nunca se comunica solo con color: toda insignia lleva texto.

## Tipografía

- **Trabajo:** fuente del sistema (`Segoe UI Variable` en Windows). Una sola familia para títulos, etiquetas, datos y botones.
- **Marca:** Archivo (variable, SIL OFL), servida desde `assets/fonts/`. Solo en el logotipo, el login y la portada.
- **Cifras:** `tabular-nums` en todo número que se compara. Montos alineados a la derecha con «Bs» más chico y más claro que la cifra.
- **Escala fija en rem (relación ≈1,2):** 13, 14, 16, 18, 22, 28, 36 y 56 (este último solo en la portada).

## Espacio y forma

- Espaciado en múltiplos de 8 (`--esp-1` = 8 px … `--esp-10` = 80 px). 4 px solo para separaciones internas.
- Un solo radio: 6 px en controles y paneles, 4 px en insignias.
- Dos sombras: `--sombra-1` (casi plana, para lo que se apoya) y `--sombra-2` (cajón y modal). Teñidas de verde, nunca negras.
- Jerarquía por filetes y rótulos, no por tamaños gigantes: los grupos de un formulario se separan con un filete bajo su título.

## Componentes

| Componente | Clase | Reglas |
|---|---|---|
| Botón | `.boton` + `--primario`, `--peligro`, `--fantasma`, `--icono`, `--bloque` | 48 px de alto. Responde al presionar (`scale(0.97)`). Estados: hover, foco, deshabilitado y ocupado (`aria-busy`). |
| Campo | `.campo` > `.campo__etiqueta`, `.control`, `.campo__ayuda`, `.campo__error` | Etiqueta arriba, error debajo con ícono, `aria-invalid` en el control. Nunca un placeholder como etiqueta. |
| Archivo | `.archivo` | Zona de arrastre sobre el input nativo; error en terracota. |
| Tabla | `.tabla-contenedor` > `.tabla` | Encabezado fijo, una sola línea entre filas, `.col-num` a la derecha. En móvil, la de productos pasa a fichas. |
| Insignia | `.insignia--ok`, `--aviso`, `--peligro`, `--neutra` | Siempre con texto. |
| Alerta | `.alerta--aviso`, `--peligro`, `--info` | Sin borde lateral grueso. |
| Pestañas | `.pestana[aria-pressed]` | La activa lleva una cinta de 4 px debajo. |
| Subnavegación | `.subnav` | Control segmentado entre vistas hermanas (Productos / Categorías). |
| Modal | `dialog.modal` | Solo para confirmar acciones destructivas. Entra con fundido y escala 0,97. |
| Riel | `.riel`, `.menu__enlace[aria-current]` | 248 px en escritorio (plegable), 72 px con íconos en tableta, cajón en móvil con foco atrapado y Esc. |

Íconos: Tabler Icons (MIT), trazo de 1,75, sprite en `assets/img/iconos.svg`.

## Movimiento

Entre 120 y 240 ms, `ease-out` fuerte para lo que entra y `ease-in-out` para lo que se mueve en pantalla. Nada anima al cargar la página. Con movimiento reducido no hay desplazamientos ni escalas; se mantienen los fundidos de color y opacidad.

## Responsive

360 px (cajón y fichas), 768 px (riel de íconos, la tabla cede la columna Categoría), 1200 px o más (riel completo). Áreas táctiles de al menos 44 px; los botones, 48 px.

## Pendiente

- Plantillas de factura para rollo de 80 mm y media hoja (HU-24); `print.css` es la base.
- Fotos reales del local y de los platos: hoy son marcadores honestos.
