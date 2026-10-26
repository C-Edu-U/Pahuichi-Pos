<main class="contenido" id="contenido">
  <div class="herramientas"><a class="boton boton--primario" href="/pos"><?= icono('pos') ?> Nueva venta</a></div>
  <div class="tabla-contenedor"><table class="tabla tabla--fichas"><caption>Últimas facturas</caption>
    <thead><tr><th scope="col">Factura</th><th scope="col">Fecha</th><th scope="col">Total Bs</th><th scope="col">Estado de factura</th><th scope="col">Saldo por cobrar Bs</th><th scope="col">Factura</th></tr></thead>
    <tbody><?php foreach ($facturas as $f): ?><tr><td data-etiqueta="Factura"><?= (int) $f['numero'] ?></td><td data-etiqueta="Fecha"><?= e($f['fecha_emision']) ?></td><td data-etiqueta="Total Bs" class="num"><?= bs($f['monto_total']) ?></td><td data-etiqueta="Estado de factura"><?= e($f['estado']) ?></td><td data-etiqueta="Saldo por cobrar Bs" class="num"><?php if ((float) $f['saldo_por_cobrar'] > 0): ?><span class="insignia insignia--peligro">Saldo por cobrar: <?= bs($f['saldo_por_cobrar']) ?></span> <a class="boton boton--fantasma" href="/facturas/<?= (int)$f['id'] ?>#saldo-por-cobrar">Cobrar saldo</a><?php else: ?>0,00<?php endif; ?></td><td data-etiqueta="Factura"><a class="boton" href="/facturas/<?= (int) $f['id'] ?>">Ver tickets</a></td></tr><?php endforeach; ?></tbody>
  </table></div>
<?php if (!$facturas): ?><p class="vacio">Todavía no hay facturas. Registra una venta desde el punto de venta.</p><?php endif; ?>
</main>
