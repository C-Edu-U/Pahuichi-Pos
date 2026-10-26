          <section class="panel-bloque config__seccion" id="respaldos" aria-labelledby="t-respaldos"<?= con_demostraciones() ? ' data-simular' : '' ?>>
            <div class="panel-bloque__cabecera"><h2 class="panel-bloque__titulo" id="t-respaldos">Copias de seguridad</h2></div>
            <div class="panel-bloque__cuerpo">
              <p>Copia diaria a las <strong id="respaldos-hora"><?= e(getenv('BACKUP_HORA') ?: '02:00') ?></strong> (hora de Bolivia). Cada archivo se conserva <strong>30 días</strong>.</p>
              <p>Docker debe estar activo. Si estaba apagado a esa hora, la copia pendiente se genera al iniciar ese mismo día.</p>
              <div class="respaldo-reloj">
                <span id="respaldo-proxima-titulo">Próxima copia automática</span>
                <strong id="respaldo-proxima" class="num">Consultando…</strong>
                <button class="boton" type="button" id="respaldo-simular" disabled<?= con_demostraciones() ? '' : ' hidden' ?>>Simular próxima copia en 5 s</button>
              </div>
<?php if (con_demostraciones()): ?>
              <p class="grupo-campos__ayuda">La simulación crea una copia real sin cambiar el reloj ni el horario diario. «Simular vencimiento» reemplaza la copia elegida por una nueva antes de eliminarla.</p>
<?php endif; ?>
              <p id="respaldo-estado" role="status" aria-live="polite">Consultando el servicio de copias…</p>
              <div id="respaldo-lista" class="respaldos-lista"></div>
              <div id="restauracion-resultado" class="restauracion-resultado" hidden>
                <h3>Última restauración probada</h3>
                <p id="restauracion-mensaje"></p>
                <dl id="restauracion-datos" class="restauracion-datos"></dl>
                <a class="boton boton--primario" id="restauracion-abrir" target="_blank" rel="noopener" hidden>Abrir sistema restaurado</a>
                <p class="grupo-campos__ayuda">Esta instancia usa otra base. Puedes iniciar sesión, revisar ventas y hacer un cobro de prueba sin cambiar tus ventas habituales.</p>
              </div>
              <noscript>Activa JavaScript para ver los contadores y las copias.</noscript>
            </div>
          </section>
