<?php
/** Sección de configuración que llega con otra historia. Variables: $id, $tituloSeccion, $historias, $texto */
?>
          <section class="panel-bloque config__seccion config__seccion--pendiente" id="<?= e($id) ?>" aria-labelledby="t-<?= e($id) ?>">
            <div class="panel-bloque__cabecera">
              <h2 class="panel-bloque__titulo" id="t-<?= e($id) ?>"><?= e($tituloSeccion) ?></h2>
              <span class="insignia insignia--aviso">Próximamente · <?= e($historias) ?></span>
            </div>
            <p class="config__nota"><?= e($texto) ?></p>
          </section>
