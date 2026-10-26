<?php /** Tickets de la venta guardada: se utiliza exclusivamente el snapshot. */ ?>
    <main class="contenido contenido--tickets" id="contenido">
      <div class="tickets-herramientas">
        <a class="volver" href="/ventas">
          <svg class="icono icono--sm" aria-hidden="true"><use href="#i-volver"></use></svg>
          Ventas y facturas
        </a>
        <div class="tickets-herramientas__acciones">
          <a class="boton" href="/pos">Volver al punto de venta</a>
          <a class="boton<?= !$reimpresion ? ' boton--primario' : '' ?>" href="/facturas/<?= (int) $f['id'] ?>"<?= !$reimpresion ? ' aria-current="page"' : '' ?>>Ver tickets</a>
          <a class="boton<?= $reimpresion ? ' boton--primario' : '' ?>" href="/facturas/<?= (int) $f['id'] ?>?reimpresion=1"<?= $reimpresion ? ' aria-current="page"' : '' ?>>Reimpresión</a>
          <details class="tickets-menu">
            <summary class="boton">PDF de tickets <span aria-hidden="true">▾</span></summary>
            <form class="tickets-menu__panel" method="get" action="/facturas/<?= (int) $f['id'] ?>/pdf" target="_blank" rel="noopener">
              <input type="hidden" name="reimpresion" value="<?= $reimpresion ? '1' : '0' ?>">
              <div class="campo">
                <label class="campo__etiqueta" for="pdf-seleccion">Documentos que deseas incluir</label>
                <select class="control" name="seleccion" id="pdf-seleccion" required>
                  <?php foreach (\App\Services\TicketsPdf::SELECCIONES as $clave => $etiqueta): ?>
                  <option value="<?= e($clave) ?>"<?= $clave === 'caja' ? ' selected' : '' ?>><?= e($etiqueta) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="campo">
                <label class="campo__etiqueta" for="pdf-accion">Qué deseas hacer</label>
                <select class="control" name="descargar" id="pdf-accion">
                  <option value="0">Ver PDF</option>
                  <option value="1">Descargar PDF</option>
                </select>
              </div>
              <button class="boton boton--primario" type="submit">Generar PDF</button>
            </form>
          </details>
<?php if (!$anulada && puede('ventas.anular')): ?>
          <button class="boton boton--peligro" type="button" data-abrir-modal="modal-anular">
            <svg class="icono" aria-hidden="true"><use href="#i-anular"></use></svg>
            Anular venta
          </button>
<?php endif; ?>
        </div>
      </div>

<?php if ($anulada): ?>
      <aside class="alerta alerta--peligro" id="venta-anulada" role="status">
        <div><strong>FACTURA ANULADA · N° <?= (int)$f['numero'] ?></strong>
        <?php if ($anulacion): ?><p>Motivo: <?= e($anulacion['motivo']) ?></p><p><?= e($anulacion['administrador']) ?> · <?= e($anulacion['creado_en']) ?></p><?php endif; ?></div>
      </aside>
<?php endif; ?>
<?php if (!$anulada): ?>
<?php require __DIR__ . '/_saldo_qr.php'; ?>
<?php endif; ?>

      <!-- HU-44: anulación real con PIN, motivo y registro atómico. -->
<?php if (!$anulada && puede('ventas.anular')): ?>
      <dialog class="modal" id="modal-anular" aria-labelledby="anular-titulo" aria-describedby="anular-texto">
        <form method="post" action="/facturas/<?= (int)$f['id'] ?>/anular" id="form-anular" novalidate>
          <div class="modal__cuerpo">
            <h2 class="modal__titulo" id="anular-titulo">Anular venta</h2>
            <p class="modal__texto" id="anular-texto">Confirma con tu PIN y explica el motivo. La venta y la factura quedarán anuladas; su número se conserva. Si se recibió dinero, corresponde devolverlo al cliente.</p>
            <div class="campo">
              <label class="campo__etiqueta" for="anular-pin">PIN del administrador</label>
              <input class="control" id="anular-pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off">
            </div>
            <div class="campo"><label class="campo__etiqueta" for="anular-motivo">Motivo de anulación</label><textarea class="control" id="anular-motivo" name="motivo" minlength="3" maxlength="120" rows="3" required></textarea></div>
            <p class="campo__error" id="anular-error" role="alert" hidden></p>
            <p class="alerta alerta--info" id="anular-aviso" role="status" hidden></p>
          </div>
          <div class="modal__acciones">
            <button class="boton" type="button" data-cerrar-modal>Cerrar</button>
            <button class="boton boton--peligro" type="submit" id="anular-autorizar">Confirmar anulación</button>
          </div>
        </form>
      </dialog>
<?php endif; ?>

      <div class="tickets" id="tickets" data-modo="<?= $reimpresion ? 'reimpresion' : 'normal' ?>" data-factura="<?= (int) $f['id'] ?>">
        <!-- Comanda -->
        <section class="ticket-col ticket-col--<?= (int) $perfilesTickets['cocina']['ancho'] ?>" data-ticket="comanda" aria-labelledby="t-comanda">
          <h2 class="ticket-col__titulo" id="t-comanda">Comanda <span>para cocina</span> <span class="ticket-medida"><?= (int) $perfilesTickets['cocina']['ancho'] ?> mm</span></h2>
<?php if (!empty($s['cuenta'])): ?>
          <p class="ticket-col__nota">Cuenta de la mesa <?= e($s['mesa']) ?>: las comandas salieron con cada pedido (<?= (int) $s['cuenta']['rondas'] ?>) y no se repiten.</p>
<?php else: $tipoTicket = 'comanda'; require __DIR__ . '/_ticket.php'; endif; ?>
        </section>

        <!-- Copia de caja -->
        <section class="ticket-col ticket-col--<?= (int) $perfilesTickets['caja']['ancho'] ?>" data-ticket="copia" aria-labelledby="t-copia">
          <h2 class="ticket-col__titulo" id="t-copia">Copia de caja <span class="ticket-medida"><?= (int) $perfilesTickets['caja']['ancho'] ?> mm</span></h2>
<?php $tipoTicket = 'copia'; require __DIR__ . '/_ticket.php'; ?>
        </section>

        <!-- Factura -->
        <section class="ticket-col ticket-col--<?= (int) $perfilesTickets['caja']['ancho'] ?>" data-ticket="factura" aria-labelledby="t-factura">
          <h2 class="ticket-col__titulo" id="t-factura">Factura <span class="ticket-medida"><?= (int) $perfilesTickets['caja']['ancho'] ?> mm</span></h2>
<?php $tipoTicket = 'factura'; require __DIR__ . '/_ticket.php'; ?>
        </section>
      </div>
    </main>
