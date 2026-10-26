<?php
/**
 * Diálogos de las cuentas por mesa (HU-35), para el mesero y para caja: cobrar la cuenta y
 * cancelar un pedido. QR y mixto llegan con HU-39. El descuento manual (con el PIN del
 * administrador) se ve solo en caja ($conDescuento).
 */
$conDescuento = $conDescuento ?? false;
?>
      <dialog class="modal modal--cobro" id="modal-cuenta" aria-labelledby="cuenta-titulo">
        <div class="cobro">
          <div class="cobro__cabeza">
            <h2 class="modal__titulo" id="cuenta-titulo">Cobrar la mesa</h2>
            <button class="boton boton--fantasma boton--icono" type="button" data-cerrar-modal aria-label="Cerrar (Esc)">
              <svg class="icono" aria-hidden="true"><use href="#i-cerrar"></use></svg>
            </button>
          </div>
          <p class="cuenta-cobro__detalle" id="cuenta-detalle"></p>
          <p class="cobro__total">
            <span class="cobro__etiqueta">Total a cobrar</span>
            <span class="cobro__monto"><span class="totales__moneda">Bs</span><span id="cuenta-total">0,00</span></span>
          </p>
          <p class="cuenta-cobro__descuento" id="cuenta-descuento-fila" hidden>Descuento <span id="cuenta-descuento-motivo"></span>: <strong class="num" id="cuenta-descuento">−0,00</strong></p>

          <div class="metodos" role="group" aria-label="Medio de pago" aria-describedby="cuenta-metodos-nota">
            <button class="metodos__opcion" type="button" aria-pressed="true">Efectivo</button>
            <button class="metodos__opcion" type="button" disabled>QR</button>
            <button class="metodos__opcion" type="button" disabled>Mixto</button>
          </div>
          <p class="cuenta-cobro__nota" id="cuenta-metodos-nota">QR y mixto: próximamente (HU-39).</p>

          <div class="cobro__metodo">
          <div class="campo">
            <label class="campo__etiqueta" for="cuenta-recibido">Monto recibido</label>
            <div class="control-grupo">
              <span class="control-grupo__prefijo" aria-hidden="true">Bs</span>
              <input class="control control--numero control--grande" id="cuenta-recibido" type="text" inputmode="decimal" autocomplete="off" aria-describedby="cuenta-error">
            </div>
          </div>
          <div class="billetes" role="group" aria-label="Billetes rápidos">
            <button class="boton billete" type="button" data-cuenta-billete="exacto">Exacto</button>
            <button class="boton billete" type="button" data-cuenta-billete="50">50</button>
            <button class="boton billete" type="button" data-cuenta-billete="100">100</button>
            <button class="boton billete" type="button" data-cuenta-billete="200">200</button>
          </div>
          <p class="cambio">
            <span class="cambio__etiqueta">Cambio</span>
            <output class="cambio__monto" id="cuenta-cambio" for="cuenta-recibido">Bs 0,00</output>
          </p>

          <details class="cuenta-cobro__cliente">
            <summary>Factura a nombre de… <span id="cuenta-cliente-resumen">S/N · NIT 0</span></summary>
            <div class="cuenta-cobro__campos">
              <div class="campo"><label class="campo__etiqueta" for="cuenta-cliente-tipo">Documento</label><select class="control" id="cuenta-cliente-tipo"><option value="NIT">NIT</option><option value="CI">CI</option></select></div>
              <div class="campo"><label class="campo__etiqueta" for="cuenta-cliente-doc">NIT o CI</label><input class="control" id="cuenta-cliente-doc" type="text" inputmode="text" maxlength="26" autocomplete="off" placeholder="0"></div>
              <div class="campo campo--ancho"><label class="campo__etiqueta" for="cuenta-cliente-nombre">Nombre o razón social</label><input class="control" id="cuenta-cliente-nombre" type="text" maxlength="120" autocomplete="off"></div>
            </div>
          </details>
<?php if ($conDescuento): ?>

          <details class="cuenta-cobro__cliente" id="cuenta-descuento-panel">
            <summary>Descuento manual (PIN del administrador)</summary>
            <div class="cuenta-cobro__campos">
              <div class="campo"><label class="campo__etiqueta" for="cuenta-descuento-monto">Monto</label><input class="control control--numero" id="cuenta-descuento-monto" type="text" inputmode="decimal" autocomplete="off"></div>
              <div class="campo"><label class="campo__etiqueta" for="cuenta-descuento-pin">PIN</label><input class="control" id="cuenta-descuento-pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off"></div>
              <div class="campo campo--ancho"><label class="campo__etiqueta" for="cuenta-descuento-texto">Motivo</label><input class="control" id="cuenta-descuento-texto" type="text" maxlength="120" autocomplete="off"></div>
              <button class="boton campo--ancho" type="button" id="cuenta-autorizar">Autorizar descuento</button>
            </div>
          </details>
<?php endif; ?>

          <p class="campo__error" id="cuenta-error" role="alert" hidden></p>
          </div>
          <div class="modal__acciones"><button class="boton boton--primario" id="cuenta-confirmar" type="button">Confirmar cobro</button></div>
        </div>
      </dialog>

      <dialog class="modal" id="modal-cancelar-ronda" aria-labelledby="cancelar-ronda-titulo">
        <form method="dialog" id="form-cancelar-ronda" novalidate>
          <div class="modal__cuerpo">
            <h2 class="modal__titulo" id="cancelar-ronda-titulo">Cancelar el pedido</h2>
            <p class="modal__texto" id="cancelar-ronda-texto">Se quita de la cuenta y queda en la bitácora. Avisa a cocina si ya lo estaba preparando.</p>
            <div class="campo">
              <label class="campo__etiqueta" for="cancelar-ronda-motivo">Motivo</label>
              <input class="control" id="cancelar-ronda-motivo" type="text" maxlength="120" autocomplete="off" placeholder="Por ejemplo, el cliente se equivocó de plato">
            </div>
            <p class="campo__error" id="cancelar-ronda-error" role="alert" hidden></p>
          </div>
          <div class="modal__acciones">
            <button class="boton" type="button" data-cerrar-modal>Volver</button>
            <button class="boton boton--peligro" type="submit" id="cancelar-ronda-confirmar">Cancelar pedido</button>
          </div>
        </form>
      </dialog>
