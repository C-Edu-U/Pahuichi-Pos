<?php $estados=['pendiente'=>'Pendiente','confirmado'=>'Confirmado','rechazado'=>'Rechazado']; ?>
<main class="contenido" id="contenido">
 <div class="seccion__cabecera"><h2 class="seccion__titulo">Conciliación de pagos QR</h2></div>
 <p class="campo__ayuda">Compara cada pago con tu banca antes de confirmarlo o rechazarlo. Los pagos QR no forman parte del efectivo físico de caja.</p>
 <p id="conciliacion-resultado" role="status" aria-live="polite" class="alerta" hidden></p>
 <?php if(!empty($error)): ?><p class="alerta alerta--peligro" role="alert"><?= e($error) ?></p><?php endif; ?>
 <form method="get" action="/conciliacion" class="filtros conciliacion-filtros" role="search" aria-label="Filtrar pagos QR">
  <div class="campo"><label class="campo__etiqueta" for="q-fecha">Fecha de la venta</label><input class="control" id="q-fecha" name="fecha" type="date" value="<?= e($f['fecha']) ?>"><p class="campo__ayuda">Déjala vacía para ver todos los días.</p></div>
  <div class="campo"><label class="campo__etiqueta" for="q-turno">Turno</label><select class="control" id="q-turno" name="turno"><option value="">Todos</option><?php foreach($turnos as $t): ?><option value="<?= (int)$t['id'] ?>"<?= (string)$t['id']===$f['turno']?' selected':'' ?>><?= e($t['nombre']) ?></option><?php endforeach; ?></select></div>
  <div class="campo"><label class="campo__etiqueta" for="q-estado">Estado</label><select class="control" id="q-estado" name="estado"><option value="">Todos</option><?php foreach($estados as $clave=>$texto): ?><option value="<?= e($clave) ?>"<?= $clave===$f['estado']?' selected':'' ?>><?= e($texto) ?></option><?php endforeach; ?></select></div>
  <button class="boton boton--primario" type="submit">Filtrar</button><a class="boton" href="/conciliacion?fecha=&turno=&estado=">Ver todos</a>
 </form>
 <p id="conciliacion-total"><?= (int)$total ?> pagos encontrados.</p>
 <div class="conciliacion-lista" id="conciliacion-lista" data-filtro-estado="<?= e($f['estado']) ?>">
 <?php foreach($filas as $p): ?>
 <article class="panel-bloque conciliacion-pago" data-pago="<?= (int)$p['id'] ?>" data-estado="<?= e($p['estado']) ?>">
  <div class="panel-bloque__cuerpo conciliacion-datos">
   <div><h3 class="pago-qr__comprobante codigo"><?= e($p['comprobante']) ?></h3><p class="pago-qr__monto num">Bs <?= bs($p['monto']) ?></p>
    <p class="pago-qr__detalle"><?= e($p['creado_en']) ?> · <?= e($p['turno']) ?> · Caja <?= (int)$p['caja_id'] ?></p>
    <p class="pago-qr__detalle">Pedido <?= (int)$p['venta_id'] ?> · Factura N° <?= (int)$p['numero'] ?> · Cajero: <?= e($p['cajero']) ?></p>
    <?php if($p['pagador']): ?><p class="pago-qr__detalle">Pagador: <?= e($p['pagador']) ?></p><?php endif; ?>
    <?php if($p['administrador']): ?><p class="pago-qr__detalle"><?= e($p['administrador']) ?> · <?= e($p['resuelto_en']) ?></p><?php endif; ?>
    <div class="conciliacion-enlaces"><a class="boton boton--fantasma" href="/facturas/<?= (int)$p['factura_id'] ?>" target="_blank" rel="noopener">Ver venta</a><?php if($p['tiene_foto']): ?><a class="boton boton--fantasma" href="/conciliacion/<?= (int)$p['id'] ?>/foto" target="_blank" rel="noopener">Ver comprobante</a><?php endif; ?></div>
   </div>
   <div class="conciliacion-acciones">
    <span class="insignia <?= $p['estado']==='pendiente'?'insignia--aviso':($p['estado']==='confirmado'?'insignia--ok':'insignia--peligro') ?>" data-estado-texto><?= e($estados[$p['estado']]) ?></span>
    <?php if($p['estado']==='pendiente' && $p['venta_estado']==='cobrada'): ?>
    <form method="post" action="/conciliacion/<?= (int)$p['id'] ?>/resolver" class="acciones-qr" data-resolver-qr><?= csrf_campo() ?><button class="boton boton--primario" name="estado" value="confirmado" type="submit">Confirmar</button><button class="boton" name="estado" value="rechazado" type="submit">Rechazar</button></form>
    <?php elseif($p['venta_estado']!=='cobrada'): ?><p class="campo__ayuda">Venta anulada</p><?php endif; ?>
   </div>
  </div>
 </article>
 <?php endforeach; ?>
 </div>
 <p id="conciliacion-vacia" class="estado-vacio"<?= $filas?' hidden':'' ?>>No hay pagos QR que coincidan con los filtros.</p>
 <?php if($paginas>1): ?><nav class="conciliacion-paginas" aria-label="Páginas de pagos QR"><?php if($pagina>1): ?><a class="boton" href="?<?= e(http_build_query($f+['pagina'=>$pagina-1])) ?>">Anterior</a><?php endif; ?><span>Página <?= (int)$pagina ?> de <?= (int)$paginas ?></span><?php if($pagina<$paginas): ?><a class="boton" href="?<?= e(http_build_query($f+['pagina'=>$pagina+1])) ?>">Siguiente</a><?php endif; ?></nav><?php endif; ?>
</main>
