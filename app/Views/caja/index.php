<?php
/**
 * Caja. Variables: $abierta (caja abierta o null), $propia (HU-20: el usuario puede operarla),
 * $turnos, $cajeros, $usuario, $historial (del cajero, o todas para el administrador),
 * $errores, $bloqueo (motivo por el que no se pudo abrir), $viejo (turno_id, cajero_id, fondo,
 * tipo, monto, motivo), $movimientos, $totales.
 */
use App\Models\Turno;

$esCajero = $usuario['rol'] === 'cajero';
$fecha = static fn (string $momento): string => (new DateTimeImmutable($momento))->format('d/m/Y H:i');
?>
    <main class="contenido" id="contenido">
<?php if ($bloqueo !== null && $abierta !== null): ?>
      <div class="alerta alerta--peligro" role="alert" id="abrir-bloqueo" tabindex="-1">
        <?= icono('candado') ?>
        <p class="alerta__titulo">No se puede abrir otra caja</p>
        <p class="alerta__texto"><?= e($bloqueo) ?></p>
      </div>
<?php endif; ?>

<?php if ($abierta === null): ?>
      <!-- Sin caja abierta: abrir -->
      <section class="caja-abrir" id="vista-abrir" aria-labelledby="t-abrir">
        <form class="panel-bloque" id="form-abrir" method="post" action="/caja/abrir" novalidate>
          <?= csrf_campo() ?>
          <div class="panel-bloque__cabecera">
            <h2 class="panel-bloque__titulo" id="t-abrir">Abrir caja</h2>
          </div>
          <div class="panel-bloque__cuerpo formulario-rapido__campos">
<?php if ($bloqueo !== null): ?>
            <div class="alerta alerta--peligro campo--ancho" id="abrir-bloqueo" role="alert" tabindex="-1">
              <?= icono('candado') ?>
              <p class="alerta__titulo">No se pudo abrir la caja</p>
              <p class="alerta__texto"><?= e($bloqueo) ?></p>
            </div>
<?php endif; ?>
            <div class="campo">
              <label class="campo__etiqueta" for="abrir-turno">Turno</label>
              <select class="control" id="abrir-turno" name="turno_id">
<?php foreach ($turnos as $t): ?>
                <option value="<?= (int) $t['id'] ?>"<?= (int) $viejo['turno_id'] === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['nombre']) ?> · <?= e(Turno::horario($t)) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="campo">
<?php if ($esCajero): ?>
              <span class="campo__etiqueta">Cajero</span>
              <p class="control control--fijo"><?= e($usuario['nombre']) ?></p>
<?php else: ?>
              <label class="campo__etiqueta" for="abrir-cajero">Cajero</label>
              <select class="control" id="abrir-cajero" name="cajero_id">
<?php foreach ($cajeros as $c): ?>
                <option value="<?= (int) $c['id'] ?>"<?= (int) $viejo['cajero_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['nombre']) ?></option>
<?php endforeach; ?>
              </select>
<?php endif; ?>
            </div>
            <div class="campo">
              <label class="campo__etiqueta" for="fondo">Fondo inicial en efectivo</label>
              <div class="control-grupo">
                <span class="control-grupo__prefijo" aria-hidden="true">Bs</span>
                <input class="control control--numero" id="fondo" name="fondo" type="text" inputmode="decimal" value="<?= e($viejo['fondo']) ?>" autocomplete="off"<?= campo_error($errores, 'fondo') ?>>
              </div>
              <?= mensaje_error($errores, 'fondo') ?>
            </div>
          </div>
          <div class="panel-bloque__pie">
            <button class="boton boton--primario" type="submit">
              <?= icono('abrir') ?>
              Abrir caja
            </button>
          </div>
        </form>
      </section>
<?php elseif (!$propia): ?>
      <!-- HU-20: la caja abierta es de otro cajero; solo él o un administrador la operan -->
      <section class="panel-bloque" id="caja-ajena" aria-labelledby="t-caja-ajena">
        <div class="panel-bloque__cuerpo">
          <h2 class="panel-bloque__titulo" id="t-caja-ajena">La caja abierta es de <?= e($abierta['cajero']) ?></h2>
          <p class="cabeza-pagina__texto">Turno <?= e(mb_strtolower($abierta['turno'])) ?>, abierta <?= e(fecha_corta($abierta['abierta_en'])) ?>. Solo <?= e($abierta['cajero']) ?> o un administrador pueden registrar movimientos, contar y cerrarla. Hay una sola caja abierta a la vez: podrás abrir la tuya cuando se cierre.</p>
        </div>
      </section>
<?php else: ?>
      <!-- Caja abierta -->
      <div class="caja" id="vista-abierta">
        <section class="caja__turno" aria-labelledby="t-turno">
          <div class="seccion__cabeza">
            <h2 id="t-turno">Caja abierta</h2>
            <span class="insignia insignia--ok"><?= e($abierta['turno']) ?></span>
          </div>
          <div class="panel-bloque">
            <dl class="resumen resumen--sin-borde">
              <div class="resumen__fila"><dt>Cajero</dt><dd><?= e($abierta['cajero']) ?></dd></div>
              <div class="resumen__fila"><dt>Turno</dt><dd class="num"><?= e(Turno::horario($abierta)) ?></dd></div>
              <div class="resumen__fila"><dt>Abierta</dt><dd class="num"><?= e(fecha_corta($abierta['abierta_en'])) ?></dd></div>
<?php if ((int) $abierta['abierta_por'] !== (int) $abierta['cajero_id']): ?>
              <div class="resumen__fila"><dt>Abierta por</dt><dd><?= e($abierta['abierta_por_nombre']) ?></dd></div>
<?php endif; ?>
              <div class="resumen__fila"><dt>Fondo inicial</dt><dd class="num"><?= bs($abierta['fondo_inicial']) ?></dd></div>
            </dl>
            <p class="caja__ciego">
              <?= icono('ocultar', 'icono--sm') ?>
              Cierre ciego: el efectivo esperado se muestra recién en el arqueo, después de contar.
            </p>
            <div class="panel-bloque__pie">
              <a class="boton boton--primario" href="/caja/cierre"><?= icono('billete') ?> Contar y cerrar caja</a>
              <a class="boton boton--fantasma" href="/pos"><?= icono('pos') ?> Ir al punto de venta</a>
            </div>
          </div>
        </section>

        <section class="caja__movimientos" aria-labelledby="t-movimientos">
          <div class="seccion__cabeza">
            <h2 id="t-movimientos">Ingresos y egresos</h2>
          </div>

          <form class="panel-bloque" id="form-movimiento" method="post" action="/caja/movimientos" novalidate>
            <?= csrf_campo() ?>
            <div class="panel-bloque__cabecera">
              <h3 class="panel-bloque__titulo">Registrar movimiento</h3>
            </div>
            <div class="panel-bloque__cuerpo formulario-rapido__campos">
              <div class="campo">
                <label class="campo__etiqueta" for="mov-tipo">Tipo</label>
                <select class="control" id="mov-tipo" name="tipo"<?= campo_error($errores, 'tipo') ?>>
                  <option value="egreso"<?= $viejo['tipo'] === 'egreso' ? ' selected' : '' ?>>Egreso (sale dinero)</option>
                  <option value="ingreso"<?= $viejo['tipo'] === 'ingreso' ? ' selected' : '' ?>>Ingreso (entra dinero)</option>
                </select>
                <?= mensaje_error($errores, 'tipo') ?>
              </div>
              <div class="campo">
                <label class="campo__etiqueta" for="mov-monto">Monto</label>
                <div class="control-grupo">
                  <span class="control-grupo__prefijo" aria-hidden="true">Bs</span>
                  <input class="control control--numero" id="mov-monto" name="monto" type="text" inputmode="decimal" value="<?= e($viejo['monto']) ?>" autocomplete="off"<?= campo_error($errores, 'monto') ?>>
                </div>
                <?= mensaje_error($errores, 'monto') ?>
              </div>
              <div class="campo campo--ancho">
                <label class="campo__etiqueta" for="mov-motivo">Motivo</label>
                <input class="control" id="mov-motivo" name="motivo" type="text" maxlength="200" value="<?= e($viejo['motivo']) ?>" placeholder="Compra de hielo"<?= campo_error($errores, 'motivo') ?>>
                <?= mensaje_error($errores, 'motivo') ?>
              </div>
            </div>
            <div class="panel-bloque__pie">
              <button class="boton boton--primario" type="submit">Registrar</button>
            </div>
          </form>

<?php if ($movimientos !== []): ?>
          <dl class="resumen resumen--sin-borde">
            <div class="resumen__fila"><dt>Ingresos del turno</dt><dd class="num"><?= bs($totales['ingresos']) ?></dd></div>
            <div class="resumen__fila"><dt>Egresos del turno</dt><dd class="num"><?= bs($totales['egresos']) ?></dd></div>
            <div class="resumen__fila"><dt>Efectivo en caja por movimientos</dt><dd class="num"><?= bs($totales['neto']) ?></dd></div>
          </dl>

          <div class="tabla-contenedor">
            <table class="tabla tabla--fichas">
              <caption class="visualmente-oculto">Movimientos de efectivo del turno</caption>
              <thead>
                <tr>
                  <th scope="col">Hora</th>
                  <th scope="col">Tipo</th>
                  <th scope="col" class="col-num">Monto</th>
                  <th scope="col">Motivo</th>
                  <th scope="col">Responsable</th>
                </tr>
              </thead>
              <tbody>
<?php foreach ($movimientos as $m): ?>
                <tr>
                  <td class="num"><?= e(substr($m['creado_en'], 11, 5)) ?></td>
                  <td><?= $m['tipo'] === 'ingreso'
                      ? '<span class="insignia insignia--ok">Ingreso</span>'
                      : '<span class="insignia insignia--aviso">Egreso</span>' ?></td>
                  <td class="col-num num"><?= bs($m['monto']) ?></td>
                  <td><?= e($m['motivo']) ?></td>
                  <td><?= e($m['usuario']) ?></td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
<?php else: ?>
          <p class="cabeza-pagina__texto">Todavía no hay ingresos ni egresos en este turno.</p>
<?php endif; ?>
        </section>
      </div>
<?php endif; ?>

<?php if ($historial !== []): ?>
      <section class="seccion" id="seccion-historial" aria-labelledby="t-historial">
        <h2 class="seccion__titulo" id="t-historial">Cajas recientes</h2>
        <div class="tabla-contenedor">
          <table class="tabla tabla--fichas">
            <caption class="visualmente-oculto">Cajas recientes</caption>
            <thead>
              <tr>
                <th scope="col">Fecha y turno</th>
                <th scope="col">Cajero</th>
                <th scope="col">Abierta</th>
                  <th scope="col" class="col-num">Fondo</th>
                  <th scope="col">Estado</th>
                  <th scope="col"><span class="visualmente-oculto">Reporte</span></th>
                </tr>
              </thead>
              <tbody>
<?php foreach ($historial as $c): ?>
                <tr>
                  <td><span class="tabla__principal"><?= e((new DateTimeImmutable($c['fecha']))->format('d/m')) ?> · <?= e($c['turno']) ?></span><span class="tabla__secundario num"><?= e(Turno::horario($c)) ?></span></td>
                  <td><?= e($c['cajero']) ?></td>
                  <td class="num"><?= e($fecha($c['abierta_en'])) ?></td>
                  <td class="col-num num"><?= bs($c['fondo_inicial']) ?></td>
                  <td><?= $c['estado'] === 'abierta' ? '<span class="insignia insignia--ok">Abierta</span>' : '<span class="insignia insignia--neutra">Cerrada</span>' ?></td>
                  <td>
<?php if ($c['estado'] === 'cerrada'): ?>
                    <a class="boton boton--fantasma" href="/caja/reporte/<?= (int) $c['id'] ?>" target="_blank" rel="noopener"><?= icono('pdf') ?> Reporte PDF</a>
<?php endif; ?>
<?php if ($c['estado'] === 'cerrada' && puede('caja.reabrir')): ?>
                    <form class="form-inline" method="post" action="/caja/reabrir/<?= (int) $c['id'] ?>" data-confirmar="¿Reabrir esta caja? Se borra su conteo y su arqueo para poder cerrar el turno otra vez.">
                      <?= csrf_campo() ?>
                      <button class="boton boton--fantasma" type="submit"><?= icono('reintentar') ?> Reabrir</button>
                    </form>
<?php endif; ?>
                  </td>
                </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
<?php endif; ?>
      <?php if ($historial !== [] && puede('caja.limpiar_historial')): ?>
        <form class="form-inline" method="post" action="/caja/limpiar-historial" data-confirmar="¿Ocultar del historial todas las cajas cerradas? Sus arqueos, movimientos y ventas se conservan.">
          <?= csrf_campo() ?>
          <button class="boton boton--peligro" type="submit"><?= icono('ocultar') ?> Limpiar historial de cajas cerradas</button>
        </form>
      <?php endif; ?>
    </main>
