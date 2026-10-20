          <section class="panel-bloque config__seccion" id="leyendas" aria-labelledby="t-leyendas">
            <div class="panel-bloque__cabecera">
              <h2 class="panel-bloque__titulo" id="t-leyendas">Leyendas de la Ley N° 453</h2>
              <button class="boton" type="button" data-alternar="form-leyenda" aria-expanded="<?= isset($errores['l-texto']) ? 'true' : 'false' ?>" aria-controls="form-leyenda">
                <?= icono('mas') ?> Agregar leyenda
              </button>
            </div>
            <form class="panel-bloque__cuerpo form-leyenda" id="form-leyenda" method="post" action="/config/leyendas#leyendas" novalidate<?= isset($errores['l-texto']) ? '' : ' hidden' ?>>
              <?= csrf_campo() ?>
              <div class="campo">
                <label class="campo__etiqueta" for="l-texto">Texto de la leyenda</label>
                <textarea class="control control--area" id="l-texto" name="texto" rows="2" maxlength="255"<?= campo_error($errores, 'l-texto') ?>><?= e($leyendaTexto ?? '') ?></textarea>
                <?= mensaje_error($errores, 'l-texto') ?>
              </div>
              <button class="boton boton--primario" type="submit">Agregar</button>
            </form>
            <p class="config__nota">Cada factura toma una al azar entre las activas. Mínimo 8.</p>
<?php if (isset($errorLeyendas)): ?>
            <p class="config__nota campo__error" role="alert"><?= e($errorLeyendas) ?></p>
<?php endif; ?>
            <ul class="leyendas" id="lista-leyendas" role="list">
<?php foreach ($leyendas as $i => $l): ?>
              <li class="leyenda-fila">
                <span class="num leyenda-fila__n"><?= $i + 1 ?></span>
                <p><?= e($l['texto']) ?></p>
                <form method="post" action="/config/leyendas/<?= (int) $l['id'] ?>/estado#leyendas">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="activo" value="<?= $l['activo'] ? '0' : '1' ?>">
                  <button class="interruptor-boton" type="submit" role="switch" aria-checked="<?= $l['activo'] ? 'true' : 'false' ?>" aria-label="<?= $l['activo'] ? 'Desactivar' : 'Activar' ?> leyenda <?= $i + 1 ?>"></button>
                </form>
              </li>
<?php endforeach; ?>
            </ul>
          </section>
