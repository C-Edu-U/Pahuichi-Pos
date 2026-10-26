<?php
$qrDia = \App\Services\QrDiarioService::estado();
$erroresQr = $erroresQr ?? [];
$valoresQr = $valoresQr ?? [];
?>
<section class="panel-bloque config__seccion" id="qr-diario" aria-labelledby="t-qr-diario">
 <div class="panel-bloque__cabecera"><h2 class="panel-bloque__titulo" id="t-qr-diario">QR del día</h2></div>
 <div class="panel-bloque__cuerpo">
  <p data-qr-dia-estado role="status"><?= e($qrDia['aviso']) ?><?php if ($qrDia['vigente']): ?> <?= (float) $qrDia['monto'] === 0.0 ? 'Importe libre' : 'Monto fijo: Bs ' . bs($qrDia['monto']) ?> · <?= e($qrDia['fecha']) ?><?php endif; ?></p>
  <img class="qr-dia-imagen" data-qr-dia-imagen alt="QR de cobro de hoy"<?php if ($qrDia['vigente']): ?> src="/qr/<?= (int) $qrDia['id'] ?>/imagen"<?php else: ?> hidden<?php endif; ?>>
 </div>
 <?php if (puede('qr.subir') && $qrDia['demo_disponible'] && con_demostraciones()): ?>
 <div class="panel-bloque__cuerpo qr-demo" aria-labelledby="qr-demo-titulo">
  <h3 id="qr-demo-titulo">Demostración del vencimiento</h3>
  <p class="campo__ayuda">Hace vencer este QR en 5 segundos para comprobar el bloqueo en caja. No cambia la hora del equipo ni modifica las ventas. Usa «Restablecer vigencia» para volver al vencimiento habitual de hoy.</p>
  <p id="qr-demo-cuenta" role="status" aria-live="polite">La vigencia habitual termina a medianoche.</p>
  <div class="qr-demo__acciones">
   <button class="boton" type="button" id="qr-demo-vencer"<?= !$qrDia['vigente'] || $qrDia['simulado'] ? ' disabled' : '' ?>>Simular vencimiento en 5 segundos</button>
   <button class="boton" type="button" id="qr-demo-restablecer"<?= !$qrDia['simulado'] ? ' disabled' : '' ?>>Restablecer vigencia</button>
  </div>
  <p class="campo__error" role="alert" id="qr-demo-error" hidden></p>
 </div>
 <?php endif; ?>
 <?php if (!$qrDia['disponible']): ?><p class="panel-bloque__cuerpo">Esta copia restaurada es anterior a HU-38; conserva sus datos y el cobro en efectivo.</p><?php endif; ?>
 <?php if (puede('qr.subir') && $qrDia['disponible']): ?>
 <form action="/qr/subir" method="post" enctype="multipart/form-data">
  <?= csrf_campo() ?>
  <div class="panel-bloque__cuerpo formulario-rapido__campos">
   <div class="campo"><label class="campo__etiqueta" for="qr-fecha">Fecha del QR</label><input class="control" type="date" id="qr-fecha" name="fecha" value="<?= e($valoresQr['fecha'] ?? $qrDia['fecha']) ?>"<?= campo_error($erroresQr, 'qr-fecha') ?> required><?= mensaje_error($erroresQr, 'qr-fecha') ?></div>
   <div class="campo"><label class="campo__etiqueta" for="qr-monto">Monto del QR (Bs; 0 = importe libre)</label><input class="control" id="qr-monto" name="monto" inputmode="decimal" value="<?= e($valoresQr['monto'] ?? $qrDia['monto'] ?? '0.50') ?>"<?= campo_error($erroresQr, 'qr-monto') ?> required><?= mensaje_error($erroresQr, 'qr-monto') ?></div>
   <div class="campo campo--ancho"><label class="campo__etiqueta" for="qr-imagen">Imagen del QR del banco</label><input class="control" type="file" id="qr-imagen" name="qr" accept="image/png,image/jpeg,image/webp"<?= campo_error($erroresQr, 'qr-imagen') ?> required><p class="campo__ayuda">PNG, JPG o WebP, hasta 2 MB. Indica la fecha y el monto del QR de la imagen. Escribe 0 solo si el QR bancario permite que el cliente ingrese el importe; caja usará el total del pedido. Vence a medianoche, hora de Bolivia.</p><?= mensaje_error($erroresQr, 'qr-imagen') ?><?php if ($erroresQr): ?><p class="campo__ayuda">Vuelve a seleccionar la imagen antes de guardar.</p><?php endif; ?></div>
  </div>
  <div class="panel-bloque__cuerpo qr-previa" id="qr-previa" hidden aria-labelledby="qr-previa-titulo">
   <img class="qr-previa__imagen" id="qr-previa-imagen" alt="Vista previa de la imagen seleccionada">
   <div class="qr-previa__datos">
    <h3 id="qr-previa-titulo">Imagen seleccionada · sin guardar</h3>
    <p id="qr-previa-nombre"></p>
    <p class="campo__ayuda" id="qr-previa-detalle"></p>
    <p class="campo__ayuda">Comprueba que sea el QR del banco y que corresponda a la fecha y al monto indicados. El QR vigente solo cambia al presionar «Guardar QR del día».</p>
    <button class="boton" type="button" id="qr-previa-quitar">Quitar imagen seleccionada</button>
   </div>
  </div>
  <p class="campo__error qr-previa-mensaje" id="qr-previa-error" role="alert" hidden></p>
  <p class="campo__ayuda qr-previa-mensaje" id="qr-previa-estado" role="status" aria-live="polite" hidden></p>
  <div class="panel-bloque__pie"><button class="boton boton--primario" type="submit" id="qr-guardar">Guardar QR del día</button></div>
 </form>
 <?php endif; ?>
</section>
