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

      <div class="tickets" id="tickets" data-modo="<?= $reimpresion ? 'reimpresion' : 'normal' ?>" data-factura="<?= (int) $f['id'] ?>">
        <!-- Comanda -->
        <section class="ticket-col ticket-col--<?= (int) $perfilesTickets['cocina']['ancho'] ?>" data-ticket="comanda" aria-labelledby="t-comanda">
          <h2 class="ticket-col__titulo" id="t-comanda">Comanda <span>para cocina</span> <span class="ticket-medida"><?= (int) $perfilesTickets['cocina']['ancho'] ?> mm</span></h2>
<?php $tipoTicket = 'comanda'; require __DIR__ . '/_ticket.php'; ?>
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
