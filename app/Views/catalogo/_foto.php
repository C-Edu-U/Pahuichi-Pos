<?php $tieneFoto = !empty($producto['foto']); ?>
          <fieldset class="grupo-campos">
            <legend class="grupo-campos__titulo">Foto</legend>
            <div class="campo">
              <div class="foto-producto" id="foto-vista"<?= $tieneFoto ? '' : ' hidden' ?>>
                <canvas class="foto-producto__lienzo" id="foto-lienzo" width="480" height="480" role="img" aria-label="Foto del producto recortada"<?= $tieneFoto ? ' data-actual="/catalogo/productos/' . (int) $producto['id'] . '/foto"' : '' ?>></canvas>
                <p class="foto-producto__peso" id="foto-peso"><?= $tieneFoto ? 'Foto guardada · 480 × 480 · 200 KB o menos' : '' ?></p>
              </div>
              <div class="archivo" id="zona-foto">
                <span class="archivo__icono" aria-hidden="true"><?= icono('foto', 'icono--lg') ?></span>
                <p class="archivo__titulo">Arrastra la foto o <span>elige un archivo</span></p>
                <p class="archivo__ayuda" id="foto-ayuda">JPG, PNG o WebP, hasta 10 MB. Se recorta cuadrada y se comprime a 200 KB o menos.</p>
                <label class="visualmente-oculto" for="foto">Foto del producto desde la computadora</label>
                <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" aria-describedby="foto-ayuda foto-error">
              </div>
              <button class="boton boton--bloque foto-camara" type="button" id="foto-camara"><?= icono('camara') ?> Tomar foto con la cámara</button>
              <label class="boton boton--bloque foto-camara" id="camara-nativa-etiqueta" hidden>Usar cámara del celular
                <input class="visualmente-oculto" id="camara-nativa" type="file" accept="image/jpeg,image/png,image/webp" capture="environment">
              </label>
              <section id="camara-panel" aria-label="Cámara en vivo" hidden>
                <video id="camara-video" class="foto-producto__lienzo" autoplay muted playsinline></video>
                <div class="foto-acciones">
                  <button type="button" class="boton boton--primario" id="camara-capturar" disabled>Capturar foto</button>
                  <button type="button" class="boton" id="camara-cerrar">Cerrar cámara</button>
                </div>
              </section>
              <input type="hidden" id="foto-quitar-valor" name="foto_quitar" value="0">
              <button class="boton boton--fantasma" type="button" id="foto-quitar"<?= $tieneFoto ? '' : ' hidden' ?>>Quitar foto</button>
              <p class="campo__error" id="foto-error" role="alert"<?= isset($errores['foto']) ? '' : ' hidden' ?>><?= e($errores['foto'] ?? '') ?></p>
              <p class="campo__ayuda">La foto queda asociada al producto al guardar. Si hay errores en otros campos, vuelve a seleccionarla.</p>
            </div>
          </fieldset>
