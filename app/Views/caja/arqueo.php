<?php
/**
 * Arqueo automático y resumen de cierre (HU-24).
 * Variables: $caja, $conteo, $esperado, $diferencia, $resumen, $movimientos,
 * $cerrada (bool), $observacion, $errores.
 */
use App\Models\Turno;

$diferencia = (float) $diferencia;
$faltante = $diferencia < 0;
$sobrante = $diferencia > 0;
?>
    <main class="contenido" id="contenido">
      <div class="seccion__cabeza">
        <h2 class="seccion__titulo">Arqueo del turno</h2>
        <span class="insignia insignia--neutra"><?= e($caja['turno']) ?> · <?= e(Turno::horario($caja)) ?></span>
      </div>

      <div class="arqueo" id="arqueo">
        <section class="panel-bloque" aria-labelledby="t-esperado">
          <div class="panel-bloque__cabecera">
            <h3 class="panel-bloque__titulo" id="t-esperado">Efectivo esperado contra lo contado</h3>
          </div>
          <div class="panel-bloque__cuerpo">
            <dl class="resumen resumen--sin-borde">
              <div class="resumen__fila"><dt>Fondo inicial</dt><dd class="num"><?= bs($caja['fondo_inicial']) ?></dd></div>
              <div class="resumen__fila"><dt>Ventas en efectivo</dt><dd class="num"><?= bs($resumen['efectivo'] ?? 0) ?></dd></div>
              <div class="resumen__fila"><dt>Ingresos del turno</dt><dd class="num"><?= bs($resumen['mov_ingresos'] ?? 0) ?></dd></div>
              <div class="resumen__fila"><dt>Egresos del turno</dt><dd class="num"><?= bs($resumen['mov_egresos'] ?? 0) ?></dd></div>
              <div class="resumen__fila"><dt>Efectivo esperado</dt><dd class="num"><strong><?= bs($esperado) ?></strong></dd></div>
              <div class="resumen__fila"><dt>Total contado</dt><dd class="num"><strong><?= bs($conteo['total_contado']) ?></strong></dd></div>
              <div class="resumen__fila">
                <dt>Diferencia</dt>
                <dd class="num">
<?php if ($faltante): ?>
                  <span class="insignia insignia--peligro">Faltante de Bs <?= bs(abs($diferencia)) ?></span>
<?php elseif ($sobrante): ?>
                  <span class="insignia insignia--aviso">Sobrante de Bs <?= bs($diferencia) ?></span>
<?php else: ?>
                  <span class="insignia insignia--ok">La caja cuadra</span>
<?php endif; ?>
                </dd>
              </div>
            </dl>
          </div>
        </section>

        <section class="panel-bloque" aria-labelledby="t-resumen">
          <div class="panel-bloque__cabecera">
            <h3 class="panel-bloque__titulo" id="t-resumen">Resumen del turno</h3>
          </div>
          <div class="panel-bloque__cuerpo">
            <dl class="resumen resumen--sin-borde">
              <div class="resumen__fila"><dt>Ventas</dt><dd class="num"><?= e($resumen['ventas'] ?? 0) ?></dd></div>
              <div class="resumen__fila"><dt>Total vendido</dt><dd class="num"><?= bs($resumen['total'] ?? 0) ?></dd></div>
<?php foreach (($resumen['por_metodo'] ?? []) as $medio => $monto): ?>
              <div class="resumen__fila"><dt><?= e($medio) ?></dt><dd class="num"><?= bs($monto) ?></dd></div>
<?php endforeach; ?>
              <div class="resumen__fila"><dt>QR confirmados</dt><dd class="num"><?= bs($resumen['qr']['confirmados'] ?? 0) ?></dd></div>
              <div class="resumen__fila"><dt>QR pendientes</dt><dd class="num"><?= bs($resumen['qr']['pendientes'] ?? 0) ?></dd></div>
              <div class="resumen__fila"><dt>QR rechazados</dt><dd class="num"><?= bs($resumen['qr']['rechazados'] ?? 0) ?></dd></div>
              <div class="resumen__fila"><dt>Descuentos</dt><dd class="num"><?= bs($resumen['descuentos'] ?? 0) ?></dd></div>
              <div class="resumen__fila"><dt>Anulaciones</dt><dd class="num"><?= bs($resumen['anuladas'] ?? 0) ?></dd></div>
              <div class="resumen__fila"><dt>Diferencia</dt><dd class="num"><?= bs($diferencia) ?></dd></div>
            </dl>
          </div>
        </section>

<?php if ($movimientos !== []): ?>
        <section class="panel-bloque" aria-labelledby="t-mov-arqueo">
          <div class="panel-bloque__cabecera">
            <h3 class="panel-bloque__titulo" id="t-mov-arqueo">Ingresos y egresos del turno</h3>
          </div>
          <div class="panel-bloque__cuerpo">
            <div class="tabla-contenedor">
              <table class="tabla tabla--fichas">
                <caption class="visualmente-oculto">Movimientos de efectivo del turno</caption>
                <thead>
                  <tr>
                    <th scope="col">Hora</th>
                    <th scope="col">Tipo</th>
                    <th scope="col" class="col-num">Monto</th>
                    <th scope="col">Motivo</th>
                  </tr>
                </thead>
                <tbody>
<?php foreach ($movimientos as $m): ?>
                  <tr>
                    <td class="num"><?= e(substr($m['creado_en'], 11, 5)) ?></td>
                    <td><?= $m['tipo'] === 'ingreso' ? 'Ingreso' : 'Egreso' ?></td>
                    <td class="col-num num"><?= bs($m['monto']) ?></td>
                    <td><?= e($m['motivo']) ?></td>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>
<?php endif; ?>

<?php if ($cerrada): ?>
        <div class="alerta alerta--ok" role="status">
          <?= icono('check') ?>
          <p class="alerta__titulo">Caja cerrada y arqueada</p>
          <p class="alerta__texto">Cerrada el <?= e(fecha_corta($conteo['cerrado_en'])) ?><?= $observacion !== '' ? ' · Observación: ' . e($observacion) : '' ?></p>
        </div>
        <div class="panel-bloque__pie">
          <a class="boton boton--primario" href="/caja/reporte/<?= (int) $caja['id'] ?>"><?= icono('imprimir') ?> Imprimir el reporte de cierre</a>
          <a class="boton boton--fantasma" href="/caja"><?= icono('caja') ?> Volver a caja</a>
        </div>
<?php else: ?>
        <form class="panel-bloque" id="form-arqueo" method="post" action="/caja/arqueo" novalidate>
          <?= csrf_campo() ?>
          <div class="panel-bloque__cabecera">
            <h3 class="panel-bloque__titulo">Confirmar el cierre</h3>
          </div>
          <div class="panel-bloque__cuerpo">
            <div class="campo campo--ancho">
              <label class="campo__etiqueta" for="observacion">Observación</label>
              <input class="control" id="observacion" name="observacion" type="text" maxlength="255" value="<?= e($observacion) ?>">
              <p class="campo__ayuda">Explica cualquier diferencia entre lo contado y lo esperado.</p>
            </div>
          </div>
          <div class="panel-bloque__pie">
            <button class="boton boton--primario" type="submit"><?= icono('check') ?> Confirmar cierre y arquear</button>
            <a class="boton boton--fantasma" href="/caja/cierre"><?= icono('borrar') ?> Corregir el conteo</a>
          </div>
        </form>
<?php endif; ?>
    </main>
