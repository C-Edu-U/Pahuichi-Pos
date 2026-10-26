<?php
/**
 * Reabrir una caja cerrada (HU-27): muestra el arqueo que se va a reemplazar y pide el motivo.
 * Variables: $caja, $arqueo (vigente o null), $motivo, $errores.
 */
use App\Models\Turno;

$diferencia = $arqueo !== null && $arqueo['diferencia'] !== null ? (float) $arqueo['diferencia'] : null;
?>
    <main class="contenido contenido--angosto" id="contenido">
      <div class="seccion__cabeza">
        <h2 class="seccion__titulo">Reabrir la caja</h2>
        <span class="insignia insignia--neutra"><?= e((new DateTimeImmutable((string) $caja['fecha']))->format('d/m')) ?> · <?= e($caja['turno']) ?> · <?= e(Turno::horario($caja)) ?></span>
      </div>

<?php if ($arqueo !== null): ?>
      <section class="panel-bloque" aria-labelledby="t-arqueo-actual">
        <div class="panel-bloque__cabecera">
          <h3 class="panel-bloque__titulo" id="t-arqueo-actual">Arqueo con el que se cerró</h3>
        </div>
        <div class="panel-bloque__cuerpo">
          <dl class="resumen resumen--sin-borde">
            <div class="resumen__fila"><dt>Cajero</dt><dd><?= e($caja['cajero']) ?></dd></div>
            <div class="resumen__fila"><dt>Cerrada</dt><dd class="num"><?= e(fecha_corta((string) $arqueo['cerrado_en'])) ?></dd></div>
            <div class="resumen__fila"><dt>Efectivo esperado</dt><dd class="num"><?= $arqueo['efectivo_esperado'] !== null ? bs($arqueo['efectivo_esperado']) : '—' ?></dd></div>
            <div class="resumen__fila"><dt>Total contado</dt><dd class="num"><?= bs($arqueo['total_contado']) ?></dd></div>
<?php if ($diferencia !== null): ?>
            <div class="resumen__fila"><dt>Diferencia</dt><dd class="num"><?= $diferencia < 0 ? 'Faltante de Bs ' . bs(abs($diferencia)) : ($diferencia > 0 ? 'Sobrante de Bs ' . bs($diferencia) : 'La caja cuadra') ?></dd></div>
<?php endif; ?>
          </dl>
        </div>
      </section>
<?php endif; ?>

      <form class="panel-bloque" id="form-reabrir" method="post" action="/caja/reabrir/<?= (int) $caja['id'] ?>" novalidate>
        <?= csrf_campo() ?>
        <div class="panel-bloque__cuerpo">
          <p class="cabeza-pagina__texto">La caja vuelve a quedar abierta para contar y cerrar otra vez. Este arqueo no se borra: queda en el reporte de cierre como arqueo anterior, con el motivo.</p>
          <div class="campo campo--ancho">
            <label class="campo__etiqueta" for="motivo">Motivo de la reapertura</label>
            <input class="control" id="motivo" name="motivo" type="text" maxlength="255" required value="<?= e($motivo) ?>"<?= campo_error($errores, 'motivo', 'motivo-ayuda') ?>>
            <p class="campo__ayuda" id="motivo-ayuda">Por ejemplo: «Se contó mal un billete de 100» o «Faltó registrar un egreso».</p>
            <?= mensaje_error($errores, 'motivo') ?>
          </div>
        </div>
        <div class="panel-bloque__pie">
          <button class="boton boton--primario" type="submit"><?= icono('reintentar') ?> Reabrir la caja</button>
          <a class="boton boton--fantasma" href="/caja"><?= icono('volver') ?> Volver a caja</a>
        </div>
      </form>
    </main>
