# Design

Sistema visual de Pahuichi POS. Todo lo que dice este archivo está en `public/assets/css/`. Las vistas PHP (`app/Views/`) usan estas clases; la demo estática en `demo/` guarda su copia de referencia.

## Principios

1. **La velocidad del cajero manda.** Favoritos en la primera pantalla, un toque por ítem, total siempre visible, cobro exacto con un toque (F8).
2. **Los números son el contenido.** Cifras tabulares en columnas, alineadas a la derecha, con «Bs» más chico que la cifra.
3. **Color solo con función.** Ubicación (módulo activo), estado (verde, ámbar, terracota), categoría y datos. Sin adornos.
4. **La venta nunca espera.** Avisos de cobro, QR pendiente e impresora en cola sin ventanas que bloqueen.

## Color

| Rol | Token | Valor | Uso |
|---|---|---|---|
| Riel | `--verde-700` | #13603e | Menú lateral, barra del pedido en móvil |
| Primario | `--verde-600` | #177448 | Botón primario, enlaces (5,7:1) |
| Texto | `--tinta-900` | #16201b | Texto principal |
| Texto secundario | `--tinta-600` | #55615a | Ayudas, botones deshabilitados (6,4:1) |
| Peligro | `--terracota-600` | #b2431e | Error, anular, rechazar |
| Aviso | `--ambar-800` sobre `--ambar-100` | #6e4700 / #fcefcf | Por confirmar, agotado, sobrante |
| Foco | `--color-foco` | #1c6fd1 | Anillo de 3 px |

### Categorías

Botones de color propio con texto blanco (contraste de 6,0 a 10,4:1), separados entre sí también para daltonismo. Siempre llevan el nombre escrito.

| Categoría | Token | Valor |
|---|---|---|
| Platos | `--cat-platos` | #8a5300 |
| Refrescos | `--cat-refrescos` | #1062a8 |
| Sodas | `--cat-sodas` | #8e2c7c |
| Vinos | `--cat-vinos` | #6e2a1e |
| Postres | `--cat-postres` | #3f6f1f |
| Prueba | `--cat-prueba` | #4f5a54 |

Las categorías nuevas eligen de una paleta cerrada de 8 colores, todos con contraste AA (`config/paleta.php`). Cada color es una clase `cat--ambar`, `cat--azul`… que fija `--cat` y `--muestra`: la política de seguridad de contenido no admite estilos en línea.

### Series de datos

`--serie-efectivo` #177448 y `--serie-qr` #4a6fb5, validadas para daltonismo.

## Tipografía

- **Trabajo:** fuente del sistema. Una sola familia.
- **Marca:** Archivo (variable, servida desde el sitio), solo en el logotipo, el login y la pantalla del cliente.
- **Tickets:** monoespaciada, en negro, como el papel térmico.
- **Escala en rem:** 13, 14, 16, 18, 22, 28, 36, 48. Texto base de 16 px.

## Espacio y forma

- Múltiplos de 8; 4 px solo dentro de un grupo.
- Radio de 6 px en controles y paneles, 4 px en insignias.
- Dos sombras teñidas de verde, solo para lo que flota (modal, cajón, aviso, desplegable).

## Componentes

| Componente | Reglas |
|---|---|
| Botón (`.boton`) | 48 px de alto (56 px los de cobro). Responde al presionar. Puede mostrar su atajo (`.atajo`). |
| Botón de categoría (`.categoria-pos`) | Borde y texto del color de la categoría; relleno cuando está activo. |
| Tarjeta de producto (`.producto-pos`) | Zona de foto (sin foto: ícono de su categoría), nombre corto, precio, insignia de promo o agotado, contador del pedido. |
| Campo (`.campo`, `.control`) | Etiqueta arriba, error debajo con ícono, `aria-invalid`. |
| Teclado de PIN (`.pin`) | Teclas de 64 px; también acepta el teclado físico. |
| Tabla (`.tabla`, `--fichas`) | En móvil cada fila pasa a ficha con la etiqueta de su columna. |
| Bandeja (`.bandeja`) | Lista de pagos para el celular, con confirmar y rechazar de un toque. |
| Interruptor (`.interruptor-boton`) | `role="switch"`, área de 44 px. |
| Insignia, alerta, modal, aviso flotante | Estado con texto, nunca solo con color. Modal solo para cobrar, autorizar o confirmar algo destructivo. Con tres o más acciones, `.modal__acciones--columna` las apila con la principal arriba. |
| Dato fijo (`.control--fijo`) | Valor que no se edita con forma de campo, por ejemplo el cajero de su propia caja. |
| Próximamente | Módulo o sección de una historia pendiente: `.aviso-pagina` con `.aviso-pagina__icono--obra`, o una insignia ámbar «Próximamente · HU-xx». |
| Riel | Completo en escritorio, íconos en tableta, cajón en móvil. En el POS empieza plegado. |
| Tickets (`.ticket`) | 76 o 58 mm. Comanda con número grande; factura con la leyenda académica arriba y abajo y QR propio. |

## Movimiento

De 120 a 240 ms, `ease-out` para lo que entra. Nada anima al cargar la página. El PIN incorrecto sacude los puntos una vez. Con movimiento reducido no hay desplazamientos ni escalas.

## Responsive

360 px (cajón, fichas, pedido como hoja inferior), 768 px (riel de íconos), 1024 px (pedido lateral en el POS) y 1200 px (riel completo). Áreas táctiles de 44 px o más.
