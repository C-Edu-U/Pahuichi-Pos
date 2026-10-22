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
        </div>
      </div>

      <div class="tickets" id="tickets" data-factura="<?= (int) $f['id'] ?>">
        <!-- Comanda -->
        <section class="ticket-col" aria-labelledby="t-comanda">
          <h2 class="ticket-col__titulo" id="t-comanda">Comanda <span>para cocina</span></h2>
<?php $tipoTicket = 'comanda'; require __DIR__ . '/_ticket.php'; ?>
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