<?php use App\Models\ConfiguracionTickets; ?>
          <section class="panel-bloque config__seccion" id="impresoras" aria-labelledby="t-impresoras">
            <div class="panel-bloque__cabecera">
              <h2 class="panel-bloque__titulo" id="t-impresoras">Tickets en PDF</h2>
            </div>
<?php if (isset($errorTickets)): ?><p class="config__nota" role="alert"><?= e($errorTickets) ?></p><?php endif; ?>
            <div class="impresoras">
<?php foreach (ConfiguracionTickets::DESTINOS as $destino => $nombre): $p = $perfilesTickets[$destino]; ?>
              <form class="impresora" method="post" action="/config/tickets/<?= e($destino) ?>">
                <?= csrf_campo() ?>
                <h3 class="impresora__nombre"><?= e($nombre) ?></h3>
                <div class="impresora__campos">
                  <div class="campo">
                    <label class="campo__etiqueta" for="i-<?= e($destino) ?>-ancho">Ancho</label>
                    <select class="control" id="i-<?= e($destino) ?>-ancho" name="ancho">
<?php foreach ([76, 58] as $ancho): ?><option value="<?= $ancho ?>"<?= $p['ancho'] === $ancho ? ' selected' : '' ?>><?= $ancho ?> mm</option><?php endforeach; ?>
                    </select>
                  </div>
                  <div class="campo">
                    <label class="campo__etiqueta" for="i-<?= e($destino) ?>-columnas">Columnas</label>
                    <input class="control control--numero" id="i-<?= e($destino) ?>-columnas" name="columnas" type="number" min="24" max="48" step="1" required value="<?= $p['columnas'] ?>">
                  </div>
                  <div class="campo campo--ancho">
                    <label class="campo__etiqueta" for="i-<?= e($destino) ?>-copias">Copias de cada ticket</label>
                    <input class="control control--numero" id="i-<?= e($destino) ?>-copias" name="copias" type="number" min="1" max="3" step="1" required value="<?= $p['copias'] ?>">
                    <p class="campo__ayuda">De 1 a 3. Columnas recomendadas: 42 para 76 mm y 32 para 58 mm.</p>
                  </div>
                  <fieldset class="opciones campo--ancho"><legend class="campo__etiqueta">Incluir en el PDF</legend>
<?php foreach (ConfiguracionTickets::TIPOS as $tipo => $etiqueta): ?>
                    <label class="opcion"><input type="checkbox" name="<?= e($tipo) ?>" value="1"<?= $p[$tipo] ? ' checked' : '' ?>> <span><?= e($etiqueta) ?></span></label>
<?php endforeach; ?>
                  </fieldset>
                </div>
                <div class="impresora__acciones"><button class="boton boton--primario" type="submit">Guardar</button></div>
              </form>
<?php endforeach; ?>
            </div>
            <p class="config__nota">Después de cobrar, abre Ver tickets y elige PDF de caja o PDF de cocina. Puedes ver o descargar el archivo PDF directamente. El sistema no envía trabajos a impresoras. Los cambios se aplican al siguiente documento generado.</p>
            <form method="post" action="/config/tickets-originales" class="impresora">
              <?= csrf_campo() ?>
              <h3 class="impresora__nombre">Consulta de documentos emitidos</h3>
              <p>El administrador siempre puede consultar los documentos emitidos desde Reimpresión. Activa esta opción para permitirlo también a los cajeros en las ventas a las que tienen acceso.</p>
              <label class="opcion"><input type="checkbox" name="originales_cajeros" value="1"<?= \App\Models\Configuracion::valor(\App\Models\Configuracion::ORIGINALES_CAJEROS, '0') === '1' ? ' checked' : '' ?>> <span>Permitir que los cajeros consulten los documentos emitidos</span></label>
              <p class="campo__ayuda">Cada consulta de documentos emitidos desde Reimpresión queda registrado en bitácora. Desactivar esta opción también revoca los accesos abiertos por cajeros.</p>
              <div class="impresora__acciones"><button class="boton boton--primario" type="submit">Guardar permiso</button></div>
            </form>
          </section>
