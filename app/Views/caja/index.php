<?php
/**
 * Caja. Variables: $abierta (caja abierta o null), $turnos, $cajeros, $usuario, $historial,
 * $errores, $bloqueo (motivo por el que no se pudo abrir), $viejo (turno_id, cajero_id, fondo).
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
              Cierre ciego: el efectivo esperado se muestra recién en el resumen, después de contar. Llega con HU-23 a HU-26.
            </p>
            <div class="panel-bloque__pie">
              <form class="form-inline" method="post" action="/caja/cerrar" onsubmit="return confirm('¿Cerrar esta caja? Después no se podrán registrar ventas hasta abrir otra.')">
                <?= csrf_campo() ?>
                <button class="boton boton--peligro" type="submit"><?= icono('cerrar') ?> Cerrar caja</button>
              </form>
              <a class="boton boton--primario" href="/pos"><?= icono('pos') ?> Ir al punto de venta</a>
            </div>
          </div>
        </section>

        <section class="caja__movimientos" aria-labelledby="t-movimientos">
          <div class="seccion__cabeza">
            <h2 id="t-movimientos">Ingresos y egresos</h2>
            <span class="insignia insignia--aviso">Próximamente · HU-22</span>
          </div>
          <p class="cabeza-pagina__texto">Movimientos de efectivo durante el turno, con monto, motivo y responsable.</p>
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
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
<?php endif; ?>
      <?php if ($historial !== [] && puede('caja.limpiar_historial')): ?>
        <form class="form-inline" method="post" action="/caja/limpiar-historial" onsubmit="return confirm('¿Borrar todas las cajas cerradas del historial? Esta acción no se deshace.')">
          <?= csrf_campo() ?>
          <button class="boton boton--peligro" type="submit"><?= icono('borrar') ?> Limpiar historial de cajas cerradas</button>
        </form>
      <?php endif; ?>
    </main>
