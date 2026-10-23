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
          <button class="boton boton--peligro" type="button" data-abrir-modal="modal-anular">
            <svg class="icono" aria-hidden="true"><use href="#i-anular"></use></svg>
            Anular venta
          </button>
        </div>
      </div>

      <?php if ($entregaInicial): ?>
      <section class="tickets-reimpresion" aria-labelledby="t-entrega-original">
        <h2 id="t-entrega-original"><?= $consultaOriginal ? 'Documentos emitidos de esta venta' : 'Entrega inicial de tickets' ?></h2>
        <p>Elige el PDF de Caja o de Cocina. Ver lo abre en otra pestaña; Descargar guarda el mismo archivo. Puedes usar ambos botones sin cambiar de documento.</p>
        <?php foreach (['caja' => 'Caja', 'cocina' => 'Cocina'] as $destinoOriginal => $nombreDestino):
            $tiposOriginales = \App\Services\EntregaInicialService::tipos((int) $f['id'], $destinoOriginal);
        ?>
        <form method="post" action="/facturas/<?= (int) $f['id'] ?>/original" target="_blank" rel="noopener" class="entrega-perfil">
          <?= csrf_campo() ?>
          <input type="hidden" name="entrega" value="<?= e($tokenEntrega) ?>">
          <input type="hidden" name="destino" value="<?= e($destinoOriginal) ?>">
          <h3><?= e($nombreDestino) ?></h3>
          <p>Incluye: <?= e(implode(' + ', array_map(static fn ($tipo) => \App\Models\ConfiguracionTickets::TIPOS[$tipo], $tiposOriginales))) ?>.</p>
          <div class="tickets-herramientas__acciones">
            <button class="boton boton--primario" type="submit" name="descargar" value="0">Ver PDF de <?= e($destinoOriginal) ?></button>
            <button class="boton" type="submit" name="descargar" value="1">Descargar PDF de <?= e($destinoOriginal) ?></button>
          </div>
        </form>
        <?php endforeach; ?>
        <p><?= $consultaOriginal ? 'Estos documentos corresponden a la venta ya registrada. Consultarlos no genera otra venta ni cambia su número, CUF o importes.' : 'Al terminar, abre Ventas y facturas o pasa a Reimpresiones.' ?></p>
        <a class="boton" href="/facturas/<?= (int) $f['id'] ?>"><?= $consultaOriginal ? 'Volver a reimpresiones' : 'Terminar entrega e ir a reimpresiones' ?></a>
      </section>
      <?php else: ?>
      <details class="tickets-reimpresion" open>
        <summary>Reimprimir un ticket</summary>
        <p>Genera una copia con la marca REIMPRESIÓN. Cada reimpresión se registra en bitácora.</p>
        <form method="post" action="/facturas/<?= (int) $f['id'] ?>/reimpresion" target="_blank" rel="noopener">
          <?= csrf_campo() ?>
          <div class="campo">
            <label class="campo__etiqueta" for="reimpresion-tipo">Ticket que deseas recuperar</label>
            <select class="control" id="reimpresion-tipo" name="tipo" required>
              <option value="copia">Copia de caja</option>
              <option value="comanda">Comanda de cocina</option>
              <option value="factura">Factura</option>
            </select>
          </div>
          <div class="tickets-herramientas__acciones">
            <button class="boton boton--primario" type="submit" name="descargar" value="0">Ver reimpresión en PDF</button>
            <button class="boton" type="submit" name="descargar" value="1">Descargar reimpresión</button>
          </div>
        </form>
        <?php if ($puedeConsultarOriginal): ?>
        <form method="post" action="/facturas/<?= (int) $f['id'] ?>/acceso-original" class="acceso-originales">
          <?= csrf_campo() ?>
          <p>Consulta los documentos de este mismo pedido sin generar otra venta. Esta consulta no añade la marca REIMPRESIÓN y queda registrada en bitácora.</p>
          <button class="boton" type="submit">Consultar documentos emitidos</button>
        </form>
        <?php endif; ?>
      </details>
      <?php endif; ?>

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
