<?php
/**
 * Reporte de cierre de caja en PDF (HU-25). HTML interno para Dompdf, nunca se entrega
 * al navegador: por eso lleva sus estilos aquí y usa tablas (Dompdf no maneja grid ni flex).
 * Variables: $caja, $arqueo (con lo guardado al cerrar), $resumen, $movimientos, $negocio, $generado.
 */
use App\Models\Arqueo;
use App\Models\Turno;

$diferencia = (float) $arqueo['diferencia'];
$conteoGuardado = $arqueo['denominaciones'] ?? [];
$fecha = static fn (?string $sql, string $formato): string => $sql ? (new DateTimeImmutable($sql))->format($formato) : '—';
$fila = static fn (string $etiqueta, string $valor): string => '<tr><th scope="row">' . e($etiqueta) . '</th><td class="num">' . $valor . '</td></tr>';
?>
<!doctype html><html lang="es"><head><meta charset="utf-8">
<title>Reporte de cierre de caja</title>
<style>
@page { margin: 12mm 14mm; }
body { margin: 0; color: #111; font-family: "DejaVu Sans", sans-serif; font-size: 9pt; line-height: 1.3; }
h1 { margin: 0 0 1mm; font-size: 15pt; }
h2 { margin: 5mm 0 1.5mm; font-size: 10pt; text-transform: uppercase; letter-spacing: 0.04em; }
p { margin: 0; }
.cabecera { width: 100%; border-collapse: collapse; border-bottom: 1.2pt solid #111; }
.cabecera td { vertical-align: top; padding: 0 0 3mm; }
.cabecera .sello { text-align: right; font-weight: bold; }
.gris { color: #555; }
table.datos, table.lista { width: 100%; border-collapse: collapse; }
table.datos th, table.datos td, table.lista th, table.lista td { padding: 0.7mm 1.5mm; border-bottom: 0.5pt solid #ccc; text-align: left; }
table.datos th { font-weight: normal; color: #444; width: 55%; }
table.lista thead th { border-bottom: 1pt solid #111; font-size: 8.5pt; }
table.lista tfoot th, table.lista tfoot td { border-top: 1pt solid #111; border-bottom: 0; font-weight: bold; }
.num { text-align: right !important; white-space: nowrap; }
.dos { width: 100%; border-collapse: collapse; }
.dos > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; }
.dos > tbody > tr > td:first-child { padding-right: 5mm; }
.diferencia td, .diferencia th { font-weight: bold; color: #111 !important; }
.observacion { margin-top: 2mm; padding: 2mm; border: 0.5pt solid #999; }
h2 { page-break-after: avoid; }
thead { display: table-header-group; }
.pie { margin-top: 6mm; padding-top: 2mm; border-top: 0.5pt solid #999; font-size: 8pt; color: #555; text-align: center; }
</style>
</head><body>
<table class="cabecera"><tr>
  <td>
    <h1>Reporte de cierre de caja</h1>
    <p><strong><?= e($negocio['nombre_comercial'] ?? 'Pahuichi') ?></strong><?= !empty($negocio['razon_social']) ? ' · ' . e($negocio['razon_social']) : '' ?></p>
    <p class="gris">NIT <?= e($negocio['nit'] ?? '') ?> · <?= e($negocio['direccion'] ?? '') ?><?= !empty($negocio['municipio']) ? ' · ' . e($negocio['municipio']) : '' ?></p>
<?php if (!empty($negocio['telefono'])): ?>
    <p class="gris">Teléfono: <?= e($negocio['telefono']) ?></p>
<?php endif; ?>
  </td>
  <td class="sello">
    <p><?= e(mb_strtoupper((string) $caja['turno'])) ?></p>
    <p><?= e($fecha((string) $caja['fecha'], 'd/m/Y')) ?></p>
    <p><?= e(Turno::horario($caja)) ?></p>
  </td>
</tr></table>

<table class="dos"><tr>
  <td>
    <h2>Datos del turno</h2>
    <table class="datos">
      <?= $fila('Cajero', e($caja['cajero'])) ?>
      <?= $fila('Abrió', e($caja['abierta_por_nombre'])) ?>
      <?= $fila('Abierta', e($fecha($caja['abierta_en'], 'd/m/Y H:i'))) ?>
      <?= $fila('Cerrada', e($fecha($arqueo['cerrado_en'], 'd/m/Y H:i'))) ?>
    </table>

    <h2>Arqueo</h2>
    <table class="datos">
      <?= $fila('Fondo inicial', 'Bs ' . bs($caja['fondo_inicial'])) ?>
      <?= $fila('Ventas en efectivo', 'Bs ' . bs($resumen['efectivo'] ?? 0)) ?>
      <?= $fila('Ingresos del turno', 'Bs ' . bs($resumen['mov_ingresos'] ?? 0)) ?>
      <?= $fila('Egresos del turno', 'Bs ' . bs($resumen['mov_egresos'] ?? 0)) ?>
      <?= $fila('Efectivo esperado', 'Bs ' . bs($arqueo['efectivo_esperado'])) ?>
      <?= $fila('Total contado', 'Bs ' . bs($arqueo['total_contado'])) ?>
      <tr class="diferencia"><th scope="row">Diferencia</th><td class="num"><?php
        if ($diferencia < 0): ?>Faltante de Bs <?= bs(abs($diferencia)) ?><?php
        elseif ($diferencia > 0): ?>Sobrante de Bs <?= bs($diferencia) ?><?php
        else: ?>La caja cuadra<?php endif; ?></td></tr>
    </table>
<?php if (!empty($arqueo['observacion'])): ?>
    <p class="observacion"><strong>Observación:</strong> <?= e($arqueo['observacion']) ?></p>
<?php endif; ?>
  </td>
  <td>
    <h2>Conteo por denominación</h2>
    <table class="lista">
      <thead><tr><th scope="col">Denominación</th><th scope="col" class="num">Cantidad</th><th scope="col" class="num">Subtotal</th></tr></thead>
      <tbody>
<?php foreach (Arqueo::DENOMINACIONES as $d): $cantidad = (int) ($conteoGuardado[$d['clave']] ?? 0); ?>
        <tr><td><?= e($d['etiqueta']) ?></td><td class="num"><?= e($cantidad) ?></td><td class="num"><?= bs(Arqueo::subtotal($d['clave'], $cantidad)) ?></td></tr>
<?php endforeach; ?>
      </tbody>
      <tfoot><tr><th scope="row">Total contado</th><td></td><td class="num">Bs <?= bs($arqueo['total_contado']) ?></td></tr></tfoot>
    </table>
  </td>
</tr></table>

<h2>Resumen del turno</h2>
<table class="dos"><tr>
  <td>
    <table class="datos">
      <?= $fila('Ventas', e($resumen['ventas'] ?? 0)) ?>
      <?= $fila('Total vendido', 'Bs ' . bs($resumen['total'] ?? 0)) ?>
<?php foreach (($resumen['por_metodo'] ?? []) as $medio => $monto): ?>
      <?= $fila((string) $medio, 'Bs ' . bs($monto)) ?>
<?php endforeach; ?>
    </table>
  </td>
  <td>
    <table class="datos">
      <?= $fila('QR confirmados', 'Bs ' . bs($resumen['qr']['confirmados'] ?? 0)) ?>
      <?= $fila('QR pendientes', 'Bs ' . bs($resumen['qr']['pendientes'] ?? 0)) ?>
      <?= $fila('QR rechazados', 'Bs ' . bs($resumen['qr']['rechazados'] ?? 0)) ?>
      <?= $fila('Descuentos', 'Bs ' . bs($resumen['descuentos'] ?? 0)) ?>
      <?= $fila('Anulaciones', 'Bs ' . bs($resumen['anuladas'] ?? 0)) ?>
    </table>
  </td>
</tr></table>

<?php if ($movimientos !== []): ?>
<h2>Ingresos y egresos del turno</h2>
<table class="lista">
  <thead><tr><th scope="col">Hora</th><th scope="col">Tipo</th><th scope="col" class="num">Monto</th><th scope="col">Motivo</th><th scope="col">Responsable</th></tr></thead>
  <tbody>
<?php foreach ($movimientos as $m): ?>
    <tr><td><?= e(substr((string) $m['creado_en'], 11, 5)) ?></td><td><?= $m['tipo'] === 'ingreso' ? 'Ingreso' : 'Egreso' ?></td><td class="num">Bs <?= bs($m['monto']) ?></td><td><?= e($m['motivo']) ?></td><td><?= e($m['usuario']) ?></td></tr>
<?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<div class="pie">
  <p>Documento de prueba, sin validez fiscal.</p>
  <p>Generado el <?= e($generado->format('d/m/Y H:i')) ?></p>
</div>
</body></html>
