<?php
/**
 * Reportes de ventas (HU-63). Variables: $filtros (desde, hasta, turno, turno_nombre), $error,
 * $turnos, $reporte (ReporteService::generar() o null si hay error), $consulta (filtros para exportar).
 */
use App\Services\ReporteExportar;

$principal = 'Total vendido';
?>
    <main class="contenido" id="contenido">
      <form class="filtros" method="get" action="/reportes" role="search" aria-label="Filtrar reportes">
        <div class="campo">
          <label class="campo__etiqueta" for="r-desde">Desde</label>
          <input class="control" id="r-desde" name="desde" type="date" value="<?= e($filtros['desde']) ?>" required>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="r-hasta">Hasta</label>
          <input class="control" id="r-hasta" name="hasta" type="date" value="<?= e($filtros['hasta']) ?>" required>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="r-turno">Turno</label>
          <select class="control" id="r-turno" name="turno">
            <option value="">Todos los turnos</option>
<?php foreach ($turnos as $t): ?>
            <option value="<?= (int) $t['id'] ?>"<?= $filtros['turno'] === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['nombre']) ?></option>
<?php endforeach; ?>
          </select>
        </div>
        <div class="filtros__acciones">
          <button class="boton boton--primario" type="submit"><?= icono('filtro') ?> Generar</button>
        </div>
      </form>

<?php if ($error !== null): ?>
      <div class="alerta alerta--peligro" role="alert">
        <?= icono('error') ?>
        <p class="alerta__texto"><?= e($error) ?></p>
      </div>
<?php else: ?>
      <div class="reportes__barra">
        <p class="resultado" id="rango"><span class="resultado__cuenta"><?= e(ReporteExportar::rango($filtros)) ?></span></p>
<?php if (puede('reportes.exportar')): ?>
        <div class="reportes__exportar">
          <a class="boton" href="/reportes/exportar?<?= e($consulta) ?>&amp;formato=pdf"><?= icono('pdf') ?> Exportar a PDF</a>
          <a class="boton" href="/reportes/exportar?<?= e($consulta) ?>&amp;formato=xlsx"><?= icono('excel') ?> Exportar a Excel</a>
        </div>
<?php endif; ?>
      </div>

      <section class="cifras reportes__cifras" aria-label="Resumen del rango" id="resumen">
<?php foreach ($reporte['resumen'] as [$etiqueta, $valor, $tipo]): ?>
        <div class="cifra<?= $etiqueta === $principal ? ' cifra--principal' : '' ?>">
          <p class="cifra__etiqueta"><?= e($etiqueta) ?></p>
          <p class="cifra__valor num"><?= $tipo === 'monto' ? '<span class="cifra__moneda">Bs</span>' : '' ?><?= e(ReporteExportar::texto($valor, $tipo)) ?></p>
        </div>
<?php endforeach; ?>
      </section>

      <nav class="reportes__indice" aria-label="Reportes">
<?php foreach ($reporte['secciones'] as $s): ?>
        <a href="#r-<?= e($s['clave']) ?>"><?= e($s['titulo']) ?></a>
<?php endforeach; ?>
      </nav>

<?php foreach ($reporte['secciones'] as $s): ?>
      <section class="reportes__seccion" id="r-<?= e($s['clave']) ?>" aria-labelledby="t-<?= e($s['clave']) ?>">
        <h2 class="seccion__titulo" id="t-<?= e($s['clave']) ?>"><?= e($s['titulo']) ?></h2>
<?php if ($s['filas'] === []): ?>
        <p class="reportes__vacio">Sin datos en este rango.</p>
<?php else: ?>
        <div class="tabla-contenedor">
          <table class="tabla tabla--fichas">
            <thead>
              <tr>
<?php foreach ($s['columnas'] as [$texto, $tipo]): ?>
                <th scope="col"<?= $tipo !== 'texto' ? ' class="col-num"' : '' ?>><?= e($texto) ?></th>
<?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
<?php foreach ($s['filas'] as $fila): ?>
              <tr>
<?php foreach ($fila as $i => $valor): [$texto, $tipo] = $s['columnas'][$i]; ?>
                <td data-etiqueta="<?= e($texto) ?>"<?= $tipo !== 'texto' ? ' class="col-num num"' : ($texto === 'Código de autorización (CUF)' ? ' class="codigo"' : '') ?>><?= e(ReporteExportar::texto($valor, $tipo)) ?></td>
<?php endforeach; ?>
              </tr>
<?php endforeach; ?>
            </tbody>
<?php if ($s['total'] !== null): ?>
            <tfoot>
              <tr>
<?php foreach ($s['total'] as $i => $valor): [$texto, $tipo] = $s['columnas'][$i]; ?>
<?php if ($i === 0): ?>
                <th scope="row"><?= e($valor) ?></th>
<?php else: ?>
                <td data-etiqueta="<?= e($texto) ?>"<?= $tipo !== 'texto' ? ' class="col-num num"' : '' ?>><?= e(ReporteExportar::texto($valor, $tipo)) ?></td>
<?php endif; ?>
<?php endforeach; ?>
              </tr>
            </tfoot>
<?php endif; ?>
          </table>
        </div>
<?php endif; ?>
<?php if ($s['nota'] !== null): ?>
        <p class="reportes__nota"><?= e($s['nota']) ?></p>
<?php endif; ?>
      </section>
<?php endforeach; ?>
<?php endif; ?>
    </main>
