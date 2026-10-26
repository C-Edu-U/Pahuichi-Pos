<div class="verificacion verificacion--consulta">
  <header class="verificacion__cabeza">
    <p class="acceso__logo"><span class="marca__sello" aria-hidden="true"><span></span></span><span>Pahuichi</span></p>
  </header>
<main class="verificacion__cuerpo" id="contenido">
  <h1><?= $f ? 'Verificación de factura' : 'Factura no encontrada' ?></h1>
  <p class="verificacion__academica">DOCUMENTO SIN VALOR FISCAL - PROYECTO ACADÉMICO</p>
<?php if ($f): ?>
  <section class="verificacion__tarjeta" aria-labelledby="verificacion-numero">
    <div class="verificacion__resumen">
      <h2 id="verificacion-numero">Factura N° <?= (int) $f['numero'] ?></h2>
      <p class="verificacion__estado<?= $f['estado'] === 'anulada' ? ' verificacion__estado--anulada' : '' ?>" id="verificacion-estado">Estado: <strong><?= e(mb_strtoupper($f['estado'])) ?></strong></p>
    </div>
<?php if ($f['estado'] === 'anulada'): ?>
    <p class="verificacion__aviso">Esta factura está anulada.</p>
<?php endif; ?>
    <dl class="verificacion__datos">
      <div><dt>Emisor</dt><dd><?= e($f['snapshot']['negocio']['razon_social']) ?></dd></div>
      <div><dt>NIT</dt><dd><?= e($f['snapshot']['negocio']['nit']) ?></dd></div>
      <div><dt>Fecha de emisión</dt><dd><time datetime="<?= e(str_replace(' ', 'T', $f['fecha_emision']) . '-04:00') ?>"><?= e(date('d/m/Y H:i:s', strtotime($f['fecha_emision']))) ?></time></dd></div>
      <div><dt>Total Bs</dt><dd class="verificacion__total"><?= bs($f['monto_total']) ?></dd></div>
      <div class="verificacion__cuf"><dt>CUF</dt><dd><?= e($f['cuf']) ?></dd></div>
    </dl>
  </section>
<?php else: ?>
  <section class="verificacion__tarjeta">
    <p>No se encontró una factura que coincida con los datos del enlace. Vuelve a escanear el QR de tu factura.</p>
  </section>
<?php endif; ?>
  <p class="verificacion__academica">DOCUMENTO SIN VALOR FISCAL - PROYECTO ACADÉMICO</p>
</main>
</div>
