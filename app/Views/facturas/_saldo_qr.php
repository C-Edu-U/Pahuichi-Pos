<?php if ((float)($f['saldo_por_cobrar']??0)>0): ?>
<section class="panel-bloque" id="saldo-por-cobrar" aria-labelledby="saldo-titulo">
 <div class="panel-bloque__cabecera"><h2 class="panel-bloque__titulo" id="saldo-titulo">Saldo por cobrar: Bs <?= bs($f['saldo_por_cobrar']) ?></h2></div>
 <div class="panel-bloque__cuerpo">
  <p>El pago QR fue rechazado. Este importe no cuenta como QR confirmado. El efectivo ya cobrado se conserva. Puedes cobrar este saldo en efectivo sobre la misma venta.</p>
  <?php if ($cajaSaldoAbierta): ?>
  <form method="post" action="/facturas/<?= (int)$f['id'] ?>/cobrar-saldo" id="form-cobro-saldo" data-saldo="<?= e($f['saldo_por_cobrar']) ?>">
   <?= csrf_campo() ?>
   <input type="hidden" name="importe" value="<?= e($f['saldo_por_cobrar']) ?>">
   <div class="campo"><label class="campo__etiqueta" for="saldo-recibido">Efectivo recibido (Bs)</label><input class="control" id="saldo-recibido" name="recibido" inputmode="decimal" autocomplete="off" required></div>
   <p id="saldo-cambio" role="status" aria-live="polite">Escribe cuánto efectivo entrega el cliente.</p>
   <p class="campo__error" id="saldo-error" role="alert" hidden></p>
   <button class="boton boton--primario" type="submit" id="saldo-confirmar" disabled>Cobrar saldo en efectivo</button>
  </form>
  <?php else: ?><p class="alerta alerta--aviso">La caja de origen está cerrada. El administrador debe reabrirla antes de cobrar el saldo.</p><?php endif; ?>
 </div>
</section>
<?php elseif (!empty($recuperacionSaldo) && $f['estado']==='emitida'): ?>
<aside class="alerta alerta--ok" id="saldo-recuperado" role="status"><div>
 <strong>Saldo cobrado en efectivo: Bs <?= bs($recuperacionSaldo['monto']) ?></strong>
 <p>Recibido: Bs <?= bs($recuperacionSaldo['recibido']) ?> · Cambio: Bs <?= bs($recuperacionSaldo['cambio']) ?> · Saldo pendiente: Bs 0,00.</p>
 <p><?= e($recuperacionSaldo['cajero']) ?> · <?= e($recuperacionSaldo['creado_en']) ?> · Comprobante QR original: <?= e($recuperacionSaldo['comprobante']) ?>.</p>
</div></aside>
<?php endif; ?>
