<?php
/** HTML interno para Dompdf, nunca se entrega al navegador como si fuera un PDF. */
$fuente = ((int) $perfil['ancho'] - 6) * 72 / 25.4 / (int) $perfil['columnas'] / 0.602;
?>
<!doctype html><html lang="es"><head><meta charset="utf-8">
<style>
@page { margin: 3mm; }
body { margin: 0; color: #000; font-family: "DejaVu Sans Mono", monospace; font-size: <?= $fuente ?>pt; line-height: 1.3; word-wrap: break-word; }
.pdf-hoja { page-break-before: always; }
.pdf-hoja:first-child { page-break-before: auto; }
p, dl, dt, dd, ul { margin: 0; padding: 0; }
ul { list-style: none; }
[hidden] { display: none; }
.ticket__centro, .ticket__datos--apilado { text-align: center; }
.ticket__fuerte, dt, .ticket__codigo, .ticket__sub { font-weight: bold; }
.ticket__chico { font-size: 90%; }
.ticket__separador { margin: 2mm 0; border-top: 0.5pt dashed #000; }
.ticket__datos div, .ticket__totales div { margin-bottom: 0.5mm; }
/* Dompdf: filas de dos celdas para mantener etiqueta y valor a la misma altura. */
.ticket__datos div { display: table; table-layout: fixed; width: 100%; }
.ticket__datos dt, .ticket__datos dd { display: table-cell; vertical-align: top; }
.ticket__datos dt { width: 42%; padding-right: 1mm; }
.ticket__datos dd { width: 58%; text-align: right; overflow-wrap: break-word; }
.ticket__datos--apilado div, .ticket__datos--apilado dt, .ticket__datos--apilado dd { display: block; width: auto; padding-right: 0; }
.ticket__datos--apilado dd { text-align: center; }
.ticket__totales div { display: table; width: 100%; }
.ticket__totales dt, .ticket__totales dd { display: table-cell; }
.ticket__totales dt { width: 65%; font-weight: normal; }
.ticket__totales dd { text-align: right; white-space: nowrap; }
/* Copia de caja: descripcion e importe en la misma fila; el nombre puede envolver. */
.ticket__lineas li { display: table; table-layout: fixed; width: 100%; margin-bottom: 1.5mm; }
.ticket__lineas li > span { display: table-cell; vertical-align: top; }
.ticket__lineas li > span:first-child { width: 70%; padding-right: 1mm; }
.ticket__lineas li > span.num { width: 30%; text-align: right; white-space: nowrap; }
/* Factura: conserva el detalle apilado; calculo e importe comparten la ultima fila. */
.ticket__lineas--factura li { display: block; }
.ticket__lineas--factura li > span { display: block; width: auto; padding-right: 0; }
.ticket__lineas--factura li > span.ticket__calculo { display: inline-block; width: 70%; vertical-align: top; white-space: normal; text-align: left; }
.ticket__lineas--factura li > span.ticket__sub { display: inline-block; width: 25%; margin-left: 5%; text-align: right; vertical-align: top; white-space: nowrap; }
.ticket__literal { margin-top: 1.5mm; }
.factura__academica { margin: 1mm 0 2mm; padding: 1mm; border: 0.5pt solid #000; font-weight: bold; text-align: center; }
.ticket__qr { text-align: center; margin: 2mm 0; }
.ticket__qr img { width: 30mm; height: 30mm; }
a { color: #000; }
.comanda__pedido { font-size: 2.5em; font-weight: bold; text-align: center; }
.comanda__destino { margin: 2mm 0 1mm; padding: 1mm; background: #000; color: #fff; font-size: 1.3em; font-weight: bold; text-align: center; }
.comanda__hora { margin-bottom: 2mm; text-align: center; }
.comanda__items li { padding: 1.5mm 0; border-top: 0.5pt dashed #000; font-weight: bold; }
.comanda__cantidad { margin-right: 1mm; }
.comanda__nota { display: block; }
</style></head><body>
<?php foreach ($tipos as $tipoTicket): for ($pdfCopia = 1; $pdfCopia <= (int) $perfil['copias']; $pdfCopia++): ?>
<section class="pdf-hoja">
<?php require __DIR__ . '/_ticket.php'; ?>
</section>
<?php endfor; endforeach; ?>
</body></html>
