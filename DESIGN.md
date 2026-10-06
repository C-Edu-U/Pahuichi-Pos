# Design

Sistema visual de Pahuichi POS. Lo que dice este archivo está construido en `public/assets/css/`. Si el código y este documento difieren, se corrige el que esté mal y se avisa a Carlos, dueño del sistema de diseño.

## Dirección

Sobria y confiable, pensada para leer números rápido. Fondo blanco, el **riel verde** de la bandera cruceña como única superficie de color grande, y color solo donde comunica algo:

- **Dónde estás:** el módulo activo del riel va en blanco con texto verde.
- **Estado:** verde (correcto), ámbar (aviso, por vencer, agotado hoy), terracota (peligro, error, anular).
- **Datos:** dos series validadas para daltonismo (efectivo y QR).

Sin decoración: nada de costuras, texturas ni bandas que no tengan una función.

## Color

| Rol | Token | Valor | Uso |
|---|---|---|---|
| Riel | `--verde-700` | #13603e | Menú lateral, barra del pedido en móvil, portada |
| Primario | `--verde-600` | #177448 | Botón primario, enlaces (5,7:1 sobre blanco) |
| Texto | `--tinta-900` | #16201b | Texto principal |
| Texto secundario | `--tinta-600` | #55615a | Ayudas, metadatos, botones deshabilitados (6,4:1) |
| Placeholder | `--tinta-500` | #69756e | Solo placeholder (4,8:1) |
| Peligro | `--terracota-600` | #b2431e | Error, anular, marca de prueba de la factura |
| Aviso | `--ambar-800` sobre `--ambar-100` | #6e4700 / #fcefcf | Por vencer, agotado hoy, sobrante |
| Serie efectivo | `--serie-efectivo` | #177448 | Gráficos |
| Serie QR | `--serie-qr` | #4a6fb5 | Gráficos (ΔE 16,2 con efectivo para daltonismo) |
| Foco | `--color-foco` | #1c6fd1 | Anillo de foco de 3 px, distinto de cualquier estado |

El estado nunca se comunica solo con color: toda insignia lleva texto y todo gráfico tiene leyenda y vista de tabla.

## Tipografía

- **Trabajo:** fuente del sistema (`Segoe UI Variable` en Windows). Una sola familia para títulos, etiquetas, datos y botones.
- **Marca:** Archivo (variable, SIL OFL), servida desde `assets/fonts/`. Solo en el logotipo, el login y la portada.
- **Cifras:** `tabular-nums` en columnas y tablas. Los montos grandes (total del POS, cifra principal de reportes) usan cifras proporcionales y «Bs» más chico y más claro.
- **Escala fija en rem (relación ≈1,2):** 13, 14, 16, 18, 22, 28, 36, 48 (cifra principal) y 56 (portada).

## Espacio y forma

- Espaciado en múltiplos de 8 (`--esp-1` = 8 px … `--esp-10` = 80 px). 4 px solo para separaciones internas.
- Un solo radio: 6 px en controles y paneles, 4 px en insignias.
- Dos sombras teñidas de verde: `--sombra-1` (casi plana) y `--sombra-2` (cajón, modal, aviso flotante, hoja de factura).
- Jerarquía por filetes y rótulos, no por tamaños gigantes.

## Componentes

| Componente | Clase | Reglas |
|---|---|---|
| Botón | `.boton` + `--primario`, `--peligro`, `--fantasma`, `--icono`, `--grande` | 48 px de alto (56 px los de cobro). Responde al presionar. Estados: hover, foco, deshabilitado, ocupado. |
| Campo | `.campo`, `.control`, `.control-grupo` (prefijo «Bs», sufijo, ícono) | Etiqueta arriba, error debajo con ícono, `aria-invalid`. Nunca un placeholder como etiqueta. |
| Archivo | `.archivo` | Zona de arrastre sobre el input nativo; error en terracota. |
| Tabla | `.tabla-contenedor` > `.tabla` (`--fichas`, `--compacta`) | Encabezado fijo, una línea entre filas, `.col-num` a la derecha. Con `--fichas`, en móvil cada fila pasa a ficha con la etiqueta de su columna. |
| Filtros | `.filtros` | Una fila sobre lo que filtran; filtran por atributos `data-filtra` y `data-campo`. |
| Formulario rápido | `.formulario-rapido` + `[data-alternar]` | «Nuevo cliente», «Nuevo usuario», «Subir QR nuevo», «Agregar leyenda»: se despliegan en la página, sin modal. |
| Insignia | `.insignia--ok`, `--aviso`, `--peligro`, `--neutra` | Siempre con texto. |
| Alerta | `.alerta--aviso`, `--peligro`, `--info` | Borde fino y fondo tenue; nunca un borde lateral grueso. |
| Pestañas | `.pestana[aria-pressed]` | Filtros por categoría; la activa con subrayado verde. |
| Control segmentado | `.subnav`, `.subnav--botones` | Vistas hermanas: Productos/Categorías, Tablero/Libro, Rollo/Media hoja. |
| Modal | `dialog.modal` | Solo para cobrar, confirmar algo destructivo (anular, cerrar turno, retirar QR) o mostrar una clave temporal. |
| Aviso flotante | `.aviso-flotante` | Confirma una acción por 3 s sin interrumpir. Nunca lleva datos que haya que copiar. |
| Riel | `.riel`, `.menu__enlace[aria-current]` | 248 px en escritorio (plegable), 72 px con íconos en tableta, cajón en móvil con foco atrapado y Esc. En el POS empieza plegado. |
| Gráficos | `.grafico`, `.barras-h`, `.apilada`, `.leyenda` | Columnas ≤ 24 px con punta redondeada de 4 px, rejilla de 1 px, valor solo en el máximo, tooltip al pasar y con teclado, tabla en «Ver como tabla». |

Íconos: Tabler Icons (MIT), trazo de 1,75, sprite en `assets/img/iconos.svg`.

## Pantallas clave

- **Punto de venta:** grilla de productos (2 columnas en móvil, de 3 a 6 según el ancho) y pedido fijo a la derecha en escritorio. En móvil el pedido es una barra inferior con el total siempre visible que se despliega como hoja. El total va a 36 px.
- **Factura:** un solo marcado con dos formatos (`.factura--rollo` y `.factura--media`) y páginas de impresión con nombre (`@page rollo`, `@page media`). Lleva la marca «DOCUMENTO DE PRUEBA, SIN VALIDEZ FISCAL» y el QR de verificación al dominio piloto del SIN.

## Movimiento

Entre 120 y 240 ms, `ease-out` fuerte para lo que entra y `ease-in-out` para lo que se mueve en pantalla. Nada anima al cargar la página. Con movimiento reducido no hay desplazamientos ni escalas; se mantienen los fundidos de color y opacidad.

## Responsive

360 px (cajón, fichas, pedido como hoja), 768 px (riel de íconos), 1024 px (pedido lateral en el POS, dos columnas en el escritorio y en reportes) y 1200 px (riel completo). Áreas táctiles de al menos 44 px; los botones, 48 px.
