<?php
/**
 * Reporte de ventas en PDF (HU-63). HTML interno para Dompdf: estilos aquí y tablas.
 * Variables: $filtros, $resumen, $secciones (ReporteService::generar()), $negocio, $generado.
 */
use App\Services\ReporteExportar;
?>
<!doctype html><html lang="es"><head><meta charset="utf-8">
<title>Reporte de ventas</title>
<style>
@page { margin: 12mm 12mm 14mm; }
body { margin: 0; color: #111; font-family: "DejaVu Sans", sans-serif; font-size: 8pt; line-height: 1.3; }
h1 { margin: 0 0 1mm; font-size: 14pt; }
h2 { margin: 6mm 0 1.5mm; font-size: 9.5pt; text-transform: uppercase; letter-spacing: 0.04em; page-break-after: avoid; }
p { margin: 0; }
.gris { color: #555; }
.cabecera { padding-bottom: 2mm; border-bottom: 1.2pt solid #111; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 0.8mm 1.4mm; border-bottom: 0.5pt solid #ccc; text-align: left; vertical-align: top; }
thead { display: table-header-group; }
thead th { border-bottom: 1pt solid #111; font-size: 7.5pt; }
tfoot th, tfoot td { border-top: 1pt solid #111; border-bottom: 0; font-weight: bold; }
.num { text-align: right; white-space: nowrap; }
.codigo { font-family: "DejaVu Sans Mono", monospace; font-size: 7pt; }
.resumen { width: auto; }
.resumen th { font-weight: normal; color: #444; padding-right: 8mm; }
.nota { margin-top: 1.5mm; color: #555; font-size: 7.5pt; }
.vacio { color: #555; }
.pie { margin-top: 6mm; padding-top: 2mm; border-top: 0.5pt solid #999; font-size: 7.5pt; color: #555; text-align: center; }
</style>
</head><body>
<div class="cabecera">
  <h1>Reporte de ventas</h1>
  <p><strong><?= e($negocio['nombre_comercial'] ?? 'Pahuichi') ?></strong><?= !empty($negocio['razon_social']) ? ' · ' . e($negocio['razon_social']) : '' ?> · NIT <?= e($negocio['nit'] ?? '') ?></p>
  <p class="gris"><?= e(ReporteExportar::rango($filtros)) ?></p>
</div>

<h2>Resumen</h2>
<table class="resumen">
<?php foreach ($resumen as [$etiqueta, $valor, $tipo]): ?>
  <tr><th scope="row"><?= e($etiqueta) ?></th><td class="num"><?= $tipo === 'monto' ? 'Bs ' : '' ?><?= e(ReporteExportar::texto($valor, $tipo)) ?></td></tr>
<?php endforeach; ?>
</table>

<?php foreach ($secciones as $s): ?>
<h2><?= e($s['titulo']) ?></h2>
<?php if ($s['filas'] === []): ?>
<p class="vacio">Sin datos en este rango.</p>
<?php else: ?>
<table>
  <thead><tr>
<?php foreach ($s['columnas'] as [$texto, $tipo]): ?>
    <th scope="col"<?= $tipo !== 'texto' ? ' class="num"' : '' ?>><?= e($texto) ?></th>
<?php endforeach; ?>
  </tr></thead>
  <tbody>
<?php foreach ($s['filas'] as $fila): ?>
    <tr><?php foreach ($fila as $i => $valor): [$texto, $tipo] = $s['columnas'][$i]; ?><td<?= $tipo !== 'texto' ? ' class="num"' : ($texto === 'Código de autorización (CUF)' ? ' class="codigo"' : '') ?>><?= e(ReporteExportar::texto($valor, $tipo)) ?></td><?php endforeach; ?></tr>
<?php endforeach; ?>
  </tbody>
<?php if ($s['total'] !== null): ?>
  <tfoot><tr><?php foreach ($s['total'] as $i => $valor): $tipo = $i === 0 ? 'texto' : $s['columnas'][$i][1]; ?><?= $i === 0 ? '<th scope="row">' : '<td' . ($tipo !== 'texto' ? ' class="num"' : '') . '>' ?><?= e(ReporteExportar::texto($valor, $tipo)) ?><?= $i === 0 ? '</th>' : '</td>' ?><?php endforeach; ?></tr></tfoot>
<?php endif; ?>
</table>
<?php endif; ?>
<?php if ($s['nota'] !== null): ?>
<p class="nota"><?= e($s['nota']) ?></p>
<?php endif; ?>
<?php endforeach; ?>

<div class="pie">
  <p>Documento sin valor fiscal. Generado el <?= e($generado->format('d/m/Y H:i')) ?>.</p>
</div>
</body></html>
