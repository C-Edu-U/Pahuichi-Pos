<?php $avisoQr = \App\Core\Sesion::tomarFlash('aviso'); ?>
    <main class="contenido" id="contenido">
<?php if ($avisoQr): ?>
      <div class="alerta alerta--ok" role="status"><p class="alerta__titulo"><?= e($avisoQr) ?></p></div>
<?php endif; ?>
<?php if (!empty($erroresQr)): ?>
      <div class="alerta alerta--peligro" role="alert" id="qr-error-general">
        <p class="alerta__titulo">No se guardó el QR</p>
        <p class="alerta__texto"><?= e(reset($erroresQr)) ?></p>
      </div>
<?php endif; ?>
      <?php require __DIR__ . '/_contenido.php'; ?>
      <?php if (puede('qr.subir')) require __DIR__ . '/_comprobantes_demo.php'; ?>
    </main>
