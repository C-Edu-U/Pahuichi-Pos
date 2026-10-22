<?php /** Contenido compartido por la vista previa y el PDF; solo usa el snapshot $s. */ ?>
<?php if ($tipoTicket === 'comanda'): ?>
          <article class="ticket ticket--comanda" id="ticket-comanda<?= isset($pdfCopia) ? '-' . (int) $pdfCopia : '' ?>">
            <p class="ticket__reimpresion" hidden>REIMPRESIÓN</p>
            <p class="comanda__pedido">N° <span data-campo="pedido"><?= e($s['pedido']) ?></span></p>
            <p class="comanda__destino" data-campo="destino"><?= e($s['tipo'] === 'mesa' ? 'MESA ' . $s['mesa'] : 'PARA LLEVAR') ?></p>
            <p class="comanda__hora" data-campo="hora-larga"><?= e($s['fecha_larga']) ?></p>
            <ul class="comanda__items">
<?php $cocina = array_filter($s['lineas'], static fn (array $l): bool => $l['cocina']); foreach ($cocina as $l): ?>
              <li><span class="comanda__cantidad"><?= (int) $l['cantidad'] ?> ×</span><span class="comanda__nombre"><?= e($l['nombre']) ?></span><?php if ($l['nota']): ?><span class="comanda__nota">» <?= e(mb_strtoupper($l['nota'])) ?></span><?php endif; ?></li>
<?php endforeach; if (!$cocina): ?><li class="comanda__vacia">Sin platos para cocina.</li><?php endif; ?>
            </ul>
          </article>
<?php endif; ?>
<?php if ($tipoTicket === 'copia'): ?>
          <article class="ticket ticket--copia" id="ticket-copia<?= isset($pdfCopia) ? '-' . (int) $pdfCopia : '' ?>">
            <p class="ticket__reimpresion" hidden>REIMPRESIÓN</p>
            <p class="ticket__centro ticket__fuerte"><?= e($s['negocio']['nombre_comercial']) ?></p>
            <p class="ticket__centro">COPIA DE CAJA</p>
            <div class="ticket__separador"></div>
            <dl class="ticket__datos">
              <div><dt>Pedido</dt><dd data-campo="pedido"><?= e($s['pedido']) ?></dd></div>
              <div><dt>Factura</dt><dd data-campo="factura"><?= e($s['factura']) ?></dd></div>
              <div><dt>Fecha</dt><dd data-campo="hora-larga"><?= e($s['fecha_larga']) ?></dd></div>
              <div><dt>Cajero</dt><dd data-campo="cajero"><?= e($s['cajero']) ?></dd></div>
              <div><dt>Tipo</dt><dd data-campo="destino-corto"><?= e($s['tipo'] === 'mesa' ? 'Mesa ' . $s['mesa'] : 'Para llevar') ?></dd></div>
            </dl>
            <div class="ticket__separador"></div>
            <ul class="ticket__lineas">
<?php foreach ($s['lineas'] as $l): ?><li><span><?= (int) $l['cantidad'] ?> × <?= e($l['nombre']) ?></span><span class="num"><?= bs($l['subtotal']) ?></span></li><?php endforeach; ?>
            </ul>
            <div class="ticket__separador"></div>
            <dl class="ticket__totales"><div><dt>Subtotal</dt><dd><?= bs($s['subtotal']) ?></dd></div><div class="ticket__fuerte"><dt>TOTAL Bs</dt><dd><?= bs($s['total']) ?></dd></div></dl>
            <div class="ticket__separador"></div>
            <dl class="ticket__totales"><div><dt>Efectivo</dt><dd><?= bs($s['total']) ?></dd></div><div><dt>Recibido</dt><dd><?= bs($s['recibido']) ?></dd></div><div class="ticket__fuerte"><dt>Cambio</dt><dd><?= bs($s['cambio']) ?></dd></div></dl>
          </article>
<?php endif; ?>
<?php if ($tipoTicket === 'factura'): ?>
          <article class="ticket ticket--factura" id="ticket-factura<?= isset($pdfCopia) ? '-' . (int) $pdfCopia : '' ?>">
            <p class="ticket__reimpresion" hidden>REIMPRESIÓN</p>
            <p class="factura__academica">DOCUMENTO SIN VALOR FISCAL - PROYECTO ACADÉMICO</p>
            <p class="ticket__centro ticket__fuerte">FACTURA</p>
            <p class="ticket__centro">(Con Derecho a Crédito Fiscal)</p>
            <p class="ticket__centro ticket__fuerte" data-campo="razon"><?= e($s['negocio']['razon_social']) ?></p>
            <p class="ticket__centro">Sucursal <?= e($s['fiscal']['codigo_sucursal']) ?> · Punto de venta <?= e($s['fiscal']['codigo_punto_venta']) ?></p>
            <p class="ticket__centro"><?= e($s['negocio']['direccion']) ?> · Tel. <?= e($s['negocio']['telefono'] ?: 'por confirmar') ?></p>
            <p class="ticket__centro"><?= e($s['negocio']['municipio']) ?></p>
            <div class="ticket__separador"></div>
            <dl class="ticket__datos ticket__datos--apilado">
              <div><dt>NIT</dt><dd><?= e($s['negocio']['nit']) ?></dd></div>
              <div><dt>FACTURA N°</dt><dd data-campo="factura"><?= e($s['factura']) ?></dd></div>
              <div><dt>CÓD. AUTORIZACIÓN (CUF)</dt><dd class="ticket__cuf" data-campo="cuf"><?= e($s['cuf']) ?></dd></div>
            </dl>
            <div class="ticket__separador"></div>
            <dl class="ticket__datos">
              <div><dt>Nombre/Razón social</dt><dd data-campo="cliente"><?= e($s['cliente']['nombre']) ?></dd></div>
              <div><dt>NIT/CI/CEX</dt><dd data-campo="doc"><?= e($s['cliente']['doc']) ?></dd></div>
              <div><dt>Fecha de emisión</dt><dd data-campo="hora-larga"><?= e($s['fecha_larga']) ?></dd></div>
            </dl>
            <div class="ticket__separador"></div>
            <p class="ticket__fuerte">DETALLE</p>
            <ul class="ticket__lineas ticket__lineas--factura">
<?php foreach ($s['lineas'] as $l): ?>
              <li><span class="ticket__codigo"><?= e($l['id']) ?></span><span class="ticket__desc"><?= e($l['nombre']) ?></span><span class="ticket__unidad">Unidad: <?= e($l['unidad']) ?></span><span class="ticket__calculo num"><?= bs($l['cantidad']) ?> × <?= bs($l['precio']) ?> − 0,00</span><span class="ticket__sub num"><?= bs($l['subtotal']) ?></span></li>
<?php endforeach; ?>
            </ul>
            <div class="ticket__separador"></div>
            <dl class="ticket__totales"><div><dt>SUBTOTAL Bs</dt><dd><?= bs($s['subtotal']) ?></dd></div><div><dt>DESCUENTO Bs</dt><dd><?= bs($s['descuento']) ?></dd></div><div class="ticket__fuerte"><dt>TOTAL Bs</dt><dd><?= bs($s['total']) ?></dd></div><div><dt>MONTO GIFT CARD Bs</dt><dd>0,00</dd></div><div class="ticket__fuerte"><dt>MONTO A PAGAR Bs</dt><dd><?= bs($s['total']) ?></dd></div><div><dt>IMPORTE BASE CRÉDITO FISCAL Bs</dt><dd><?= bs($s['total']) ?></dd></div></dl>
<?php if (isset($s['literal'])): ?><p class="ticket__literal"><?= e('Son: ' . $s['literal']) ?></p><?php endif; ?>
            <div class="ticket__separador"></div>
            <p class="ticket__centro ticket__fuerte ticket__chico">ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS. EL USO ILÍCITO DE ÉSTA SERÁ SANCIONADO DE ACUERDO A LEY</p>
            <p class="ticket__centro ticket__chico" data-campo="leyenda"><?= e($s['leyenda']) ?></p>
            <p class="ticket__centro ticket__chico">“Este documento es la Representación Gráfica de un Documento Fiscal Digital emitido en una modalidad de facturación en línea”</p>
            <div class="ticket__qr" data-campo="qr" role="img" aria-label="Código QR para verificar la factura"><?php if (isset($qr)): ?><img src="<?= e($qr) ?>" alt="QR para verificar la factura"><?php endif; ?></div>
            <p class="ticket__centro ticket__chico"><a data-campo="enlace-verificar" href="<?= e($enlaceQr ?? '/verificar?' . http_build_query(['nit' => $s['negocio']['nit'], 'cuf' => $s['cuf'], 'numero' => $s['factura'], 'monto' => $s['total']])) ?>">Verificar esta factura</a></p>
            <p class="factura__academica">DOCUMENTO SIN VALOR FISCAL - PROYECTO ACADÉMICO</p>
          </article>
<?php endif; ?>
