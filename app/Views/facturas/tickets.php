<?php /** Tickets de la venta guardada: se utiliza exclusivamente el snapshot. */ ?>
    <main class="contenido contenido--tickets" id="contenido">
      <div class="tickets-herramientas">
        <a class="volver" href="/ventas">
          <svg class="icono icono--sm" aria-hidden="true"><use href="#i-volver"></use></svg>
          Ventas y facturas
        </a>
        <div class="tickets-herramientas__acciones">
          <a class="boton" href="/pos">Volver al punto de venta</a>
          <div class="subnav subnav--botones" role="group" aria-label="Ancho del papel">
            <button class="subnav__enlace" type="button" data-ancho="76" aria-pressed="true">76 mm</button>
            <button class="subnav__enlace" type="button" data-ancho="58" aria-pressed="false">58 mm</button>
          </div>
          <button class="boton" type="button" id="descargar-pdf"<?= !$perfilesTickets['caja']['factura'] ? ' disabled title="Habilita Factura en la configuración de caja"' : '' ?>>
            <svg class="icono" aria-hidden="true"><use href="#i-pdf"></use></svg>
            Factura en PDF
          </button>
          <a class="boton boton--primario" href="/facturas/<?= (int) $f['id'] ?>/pdf?destino=caja" target="_blank" rel="noopener">Ver PDF de caja</a>
          <a class="boton" href="/facturas/<?= (int) $f['id'] ?>/pdf?destino=caja&amp;descargar=1">Descargar PDF de caja</a>
          <a class="boton" href="/facturas/<?= (int) $f['id'] ?>/pdf?destino=cocina" target="_blank" rel="noopener">Ver PDF de cocina</a>
          <a class="boton" href="/facturas/<?= (int) $f['id'] ?>/pdf?destino=cocina&amp;descargar=1">Descargar PDF de cocina</a>
          <button class="boton boton--peligro" type="button" data-abrir-modal="modal-anular">
            <svg class="icono" aria-hidden="true"><use href="#i-anular"></use></svg>
            Anular venta
          </button>
        </div>
      </div>

      <!-- HU-06: el PIN del administrador autoriza la anulación; anular la venta llega con HU-44 -->
      <dialog class="modal" id="modal-anular" aria-labelledby="anular-titulo" aria-describedby="anular-texto">
        <form method="dialog" id="form-anular" novalidate>
          <div class="modal__cuerpo">
            <h2 class="modal__titulo" id="anular-titulo">Anular venta <span class="insignia insignia--aviso">Próximamente · HU-44</span></h2>
            <p class="modal__texto" id="anular-texto">Anular una venta requiere el PIN del administrador. Por ahora solo se valida el PIN: la venta no se modifica.</p>
            <div class="campo">
              <label class="campo__etiqueta" for="anular-pin">PIN del administrador</label>
              <input class="control" id="anular-pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off">
            </div>
            <p class="campo__error" id="anular-error" role="alert" hidden></p>
            <p class="alerta alerta--info" id="anular-aviso" role="status" hidden></p>
          </div>
          <div class="modal__acciones">
            <button class="boton" type="button" data-cerrar-modal>Cerrar</button>
            <button class="boton boton--peligro" type="submit" id="anular-autorizar">Autorizar anulación</button>
          </div>
        </form>
      </dialog>

      <div class="tickets" id="tickets" data-factura="<?= (int) $f['id'] ?>">
        <!-- Comanda -->
        <section class="ticket-col" aria-labelledby="t-comanda">
          <h2 class="ticket-col__titulo" id="t-comanda">Comanda <span>para cocina</span></h2>
<?php if (!empty($s['cuenta'])): ?>
          <p class="ticket-col__nota">Cuenta de la mesa <?= e($s['mesa']) ?>: las comandas salieron con cada pedido (<?= (int) $s['cuenta']['rondas'] ?>) y no se repiten.</p>
<?php else: $tipoTicket = 'comanda'; require __DIR__ . '/_ticket.php'; endif; ?>
        </section>

        <!-- Copia de caja -->
        <section class="ticket-col" aria-labelledby="t-copia">
          <h2 class="ticket-col__titulo" id="t-copia">Copia de caja</h2>
<?php $tipoTicket = 'copia'; require __DIR__ . '/_ticket.php'; ?>
        </section>

        <!-- Factura -->
        <section class="ticket-col" aria-labelledby="t-factura">
          <h2 class="ticket-col__titulo" id="t-factura">Factura</h2>
<?php $tipoTicket = 'factura'; require __DIR__ . '/_ticket.php'; ?>
        </section>
      </div>
    </main>