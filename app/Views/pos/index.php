<?php
/**
 * Punto de venta con caja abierta: cuadrícula (HU-28) y carrito (HU-29). Variables: $caja, $productos (activos, favoritos
 * primero), $categorias (activas con productos), $hayFavoritos, $promociones (activas, HU-46),
 * $contextoPromos y $ahora (HU-49: momento del servidor y usos de hoy).
 * Los datos de cada producto van en data-* para el JS (la CSP no permite scripts en línea).
 */
use App\Core\Auth;

require __DIR__ . '/_datos.php';
?>
    <main class="contenido contenido--pos" id="contenido">
      <p class="qr-dia-aviso" id="qr-dia-aviso" role="alert">Consultando el QR del día…</p>
      <div class="pos" data-caja="<?= (int) $caja['id'] ?>" data-usuario="<?= (int) Auth::id() ?>" id="pos" data-productos="<?= e(json_encode($datos, JSON_UNESCAPED_UNICODE)) ?>" data-promociones="<?= e(json_encode($promos, JSON_UNESCAPED_UNICODE)) ?>" data-ahora="<?= e($ahora->format('Y-m-d\TH:i:s')) ?>">
<?php $estadoCatalogo = '<p class="pos__turno" id="pos-turno"><span class="punto-estado" aria-hidden="true"></span>Caja abierta · ' . e(mb_strtolower($caja['turno'])) . ' desde las ' . e(substr($caja['abierta_en'], 11, 5)) . ' · ' . e($caja['cajero']) . '</p>'; ?>
<?php require __DIR__ . '/_catalogo.php'; ?>

        <section class="pos__pedido" id="pedido" aria-labelledby="t-pedido">
          <button class="pos__resumen-movil" type="button" id="ver-pedido" aria-expanded="false" aria-controls="pedido-cuerpo">
            <span class="pos__resumen-cuenta" id="resumen-cuenta">Pedido vacío</span>
            <span class="pos__resumen-total monto"><span class="monto__moneda">Bs</span><span id="resumen-total">0,00</span></span>
          </button>

          <div class="pos__pedido-cuerpo" id="pedido-cuerpo">
            <!-- HU-36: ventas guardadas sin red; se envían solas al volver la conexión -->
            <section class="cola-ventas" id="cola-ventas" aria-labelledby="t-cola" hidden>
              <div class="cola-ventas__cabeza">
                <h3 class="cola-ventas__titulo" id="t-cola">Ventas sin enviar <span class="cola-ventas__cuenta" id="cola-cuenta"></span></h3>
                <button class="boton boton--fantasma" type="button" id="cola-enviar" hidden><?= icono('reintentar', 'icono--sm') ?> Enviar ahora</button>
              </div>
              <ul class="cola-ventas__lista" id="cola-lista" role="list" aria-live="polite"></ul>
            </section>

            <div class="venta-lista" id="venta-lista" role="status" hidden>
              <svg class="icono" aria-hidden="true"><use href="#i-check"></use></svg>
              <div class="venta-lista__texto">
                <p class="venta-lista__titulo" id="venta-lista-titulo">Pedido cobrado</p>
                <p class="venta-lista__detalle" id="venta-lista-detalle">Factura guardada · lista para imprimir</p>
              </div>
              <a class="boton boton--fantasma venta-lista__ver" id="venta-lista-ver" href="/ventas">Ver tickets</a>
            </div>

            <div class="pos__pedido-cabeza">
              <h2 class="pos__pedido-titulo" id="t-pedido">Pedido</h2>
              <div class="tipo-pedido" role="group" aria-label="Tipo de pedido">
                <button class="tipo-pedido__opcion" type="button" data-tipo="mesa" aria-pressed="true">
                  <svg class="icono icono--sm" aria-hidden="true"><use href="#i-mesa"></use></svg>Mesa
                </button>
                <button class="tipo-pedido__opcion" type="button" data-tipo="llevar" aria-pressed="false">
                  <svg class="icono icono--sm" aria-hidden="true"><use href="#i-llevar"></use></svg>Llevar
                </button>
              </div>
            </div>
<?php $idMesa = 'mesa'; $idMesas = 'mesas'; $describeMesa = 'cobro-error'; require __DIR__ . '/_mesas.php'; ?>

            <div class="cliente-pos">
              <button class="cliente-pos__resumen" type="button" id="cliente-cambiar" aria-expanded="false" aria-controls="cliente-buscador">
                <svg class="icono icono--sm" aria-hidden="true"><use href="#i-persona"></use></svg>
                <span id="cliente-dato">S/N · NIT 0</span>
                <span class="cliente-pos__accion">Cambiar</span>
              </button>
              <div class="cliente-pos__buscador cliente-pos__buscador--hu32" id="cliente-buscador" hidden>
                <label class="visualmente-oculto" for="doc-cliente">NIT o CI del cliente</label>
                <input class="control" id="doc-cliente" type="text" inputmode="text" maxlength="26" placeholder="NIT o CI" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="cliente-sugerencias" aria-autocomplete="list">
                <button class="boton boton--fantasma" type="button" id="sin-datos">S/N</button>
                <ul class="cliente-pos__sugerencias" id="cliente-sugerencias" role="listbox" aria-label="Clientes encontrados" hidden></ul>
                <div class="campo campo--ancho"><label class="campo__etiqueta" for="tipo-cliente">Tipo de documento</label><select class="control" id="tipo-cliente"><option value="NIT">NIT</option><option value="CI">CI</option></select></div>
                <div class="campo campo--ancho"><label class="campo__etiqueta" for="nombre-cliente">Nombre o razón social</label><input class="control" id="nombre-cliente" type="text" minlength="2" maxlength="120" autocomplete="off"></div>
                <p class="campo__ayuda campo--ancho" id="cliente-estado" role="status" aria-live="polite">Escribe el NIT o CI. Los clientes nuevos también se guardan al confirmar el cobro.</p>
                <p class="campo__error campo--ancho" id="cliente-error" role="alert" hidden></p>
                <button class="boton boton--primario campo--ancho" type="button" id="cliente-usar">Guardar y usar cliente</button>
              </div>
            </div>

            <ul class="lineas" id="lineas" role="list" aria-label="Ítems del pedido"></ul>
            <div class="lineas-vacio" id="lineas-vacio">
              <?= icono('carrito', 'icono--lg') ?>
              <p id="lineas-vacio-texto">Toca un producto para agregarlo. Otro toque suma una unidad.</p>
            </div>

            <div class="totales">
              <dl class="totales__lista" id="desglose" hidden>
                <div><dt>Subtotal</dt><dd class="num" id="t-subtotal">0,00</dd></div>
                <div class="totales__descuento" id="fila-promociones" hidden><dt>Promociones</dt><dd class="num" id="t-promociones">0,00</dd></div>
                <div class="totales__descuento" id="fila-descuento" hidden><dt>Descuento <span class="totales__motivo" id="t-descuento-motivo"></span></dt><dd class="num" id="t-descuento">0,00</dd></div>
              </dl>
              <p class="totales__total">
                <span class="totales__etiqueta">Total</span>
                <span class="totales__monto"><span class="totales__moneda">Bs</span><span id="t-total">0,00</span></span>
              </p>
              <div class="totales__acciones">
                <button class="boton boton--primario boton--grande boton-cobro" type="button" id="efectivo-exacto" aria-keyshortcuts="F8" disabled>
                  <?= icono('billete') ?> Efectivo exacto <kbd class="atajo atajo--claro">F8</kbd>
                </button>
                <button class="boton boton--grande boton-cobro" type="button" id="cobrar" aria-keyshortcuts="F4" disabled>
                  <span id="cobrar-texto">Cobrar</span> <kbd class="atajo">F4</kbd>
                </button>
              </div>
              <p class="campo__error" id="cobro-error" role="alert" hidden></p>
              <div class="totales__secundarias">
                <!-- HU-35: cuentas por mesa de los meseros -->
                <button class="boton boton--fantasma" type="button" id="ver-mesas"><?= icono('mesa', 'icono--sm') ?>Mesas <span class="pestana__cuenta" id="mesas-cuenta">0</span></button>
                <button class="boton boton--fantasma" type="button" disabled title="Ventas en espera pendientes"><?= icono('pausa', 'icono--sm') ?>En espera</button>
                <button class="boton boton--fantasma" type="button" disabled title="Ventas en espera pendientes">Retomar <span class="pestana__cuenta">0</span></button>
                <button class="boton boton--fantasma" type="button" id="descuento" disabled>Descuento</button>
                <button class="boton boton--fantasma" type="button" id="vaciar" data-abrir-modal="modal-vaciar" aria-keyshortcuts="Escape" disabled>
                  <?= icono('basura', 'icono--sm') ?>Vaciar <kbd class="atajo">Esc</kbd>
                </button>
              </div>
            </div>
          </div>
        </section>
      </div>

      <dialog class="modal" id="modal-vaciar" aria-labelledby="modal-vaciar-titulo" aria-describedby="modal-vaciar-texto">
        <form method="dialog">
          <div class="modal__cuerpo">
            <h2 class="modal__titulo" id="modal-vaciar-titulo">¿Cancelar el pedido?</h2>
            <p class="modal__texto" id="modal-vaciar-texto">Se quitan todos los ítems del pedido.</p>
          </div>
          <div class="modal__acciones">
            <button class="boton" type="submit" value="seguir" autofocus>Seguir vendiendo</button>
            <button class="boton boton--peligro" type="submit" value="vaciar">Cancelar pedido</button>
          </div>
        </form>
      </dialog>

      <dialog class="modal modal--cobro" id="modal-cobro" aria-labelledby="cobro-titulo">
        <div class="cobro">
          <div class="cobro__cabeza">
            <h2 class="modal__titulo" id="cobro-titulo">Cobrar pedido</h2>
            <button class="boton boton--fantasma boton--icono" type="button" data-cerrar-modal aria-label="Cerrar (Esc)">
              <svg class="icono" aria-hidden="true"><use href="#i-cerrar"></use></svg>
            </button>
          </div>

          <p class="cobro__total">
            <span class="cobro__etiqueta">Total a cobrar</span>
            <span class="cobro__monto"><span class="totales__moneda">Bs</span><span id="cobro-total">0,00</span></span>
          </p>

          <div class="metodos" role="tablist" aria-label="Medio de pago">
            <button class="metodos__opcion" type="button" role="tab" id="tab-efectivo" aria-selected="true" aria-controls="cobro-efectivo" data-metodo="efectivo">Efectivo</button>
          </div>

          <!-- Efectivo (también la parte en efectivo del mixto) -->
          <div class="cobro__metodo" id="cobro-efectivo" role="tabpanel" aria-labelledby="tab-efectivo">
            <p class="cobro__parte" id="parte-efectivo" hidden>Resto en efectivo: <strong class="num" id="resto-efectivo">Bs 0,00</strong></p>
            <div class="campo">
              <label class="campo__etiqueta" for="recibido">Monto recibido</label>
              <div class="control-grupo">
                <span class="control-grupo__prefijo" aria-hidden="true">Bs</span>
                <input class="control control--numero control--grande" id="recibido" type="text" inputmode="decimal" autocomplete="off" aria-describedby="recibido-error">
              </div>
              <p class="campo__error" id="recibido-error" hidden></p>
            </div>
            <div class="billetes" role="group" aria-label="Billetes rápidos">
              <button class="boton billete" type="button" data-billete="exacto">Exacto</button>
              <button class="boton billete" type="button" data-billete="50">50</button>
              <button class="boton billete" type="button" data-billete="100">100</button>
              <button class="boton billete" type="button" data-billete="200">200</button>
            </div>
            <p class="cambio">
              <span class="cambio__etiqueta">Cambio</span>
              <output class="cambio__monto" id="cambio" for="recibido">Bs 0,00</output>
            </p>
          </div>

          <div class="modal__acciones"><button class="boton boton--primario" id="confirmar-cobro" type="button">Confirmar cobro</button></div>
        </div>
      </dialog>

      <!-- HU-35: mesas abiertas por los meseros: comandas de cada pedido y cobro de la cuenta -->
      <dialog class="modal modal--ancho" id="modal-cuentas" aria-labelledby="cuentas-titulo">
        <div class="modal__cuerpo">
          <div class="cobro__cabeza">
            <h2 class="modal__titulo" id="cuentas-titulo">Mesas abiertas</h2>
            <button class="boton boton--fantasma boton--icono" type="button" data-cerrar-modal aria-label="Cerrar (Esc)">
              <svg class="icono" aria-hidden="true"><use href="#i-cerrar"></use></svg>
            </button>
          </div>
          <div class="cuentas" id="lista-cuentas"></div>
          <p class="cuentas__vacio" id="cuentas-vacio">Ninguna mesa abierta. Los meseros abren la cuenta de una mesa con su primer pedido.</p>
        </div>
      </dialog>
<?php $conDescuento = true; require dirname(__DIR__) . '/cuentas/_dialogos.php'; ?>

      <!-- HU-06: descuento manual con el PIN del administrador -->
      <dialog class="modal" id="modal-descuento" aria-labelledby="descuento-titulo" aria-describedby="descuento-texto">
        <form method="dialog" id="form-descuento" novalidate>
          <div class="modal__cuerpo">
            <h2 class="modal__titulo" id="descuento-titulo">Descuento manual</h2>
            <p class="modal__texto" id="descuento-texto">Lo autoriza el administrador con su PIN y queda en la bitácora.</p>
            <div class="campo">
              <label class="campo__etiqueta" for="descuento-monto">Monto del descuento</label>
              <div class="control-grupo">
                <span class="control-grupo__prefijo" aria-hidden="true">Bs</span>
                <input class="control control--numero" id="descuento-monto" type="text" inputmode="decimal" autocomplete="off" aria-describedby="descuento-subtotal">
              </div>
              <p class="campo__ayuda" id="descuento-subtotal">Menor que el subtotal.</p>
            </div>
            <div class="campo">
              <label class="campo__etiqueta" for="descuento-motivo">Motivo</label>
              <input class="control" id="descuento-motivo" type="text" maxlength="120" autocomplete="off" placeholder="Por ejemplo, demora en cocina">
            </div>
            <div class="campo">
              <label class="campo__etiqueta" for="descuento-pin">PIN del administrador</label>
              <input class="control" id="descuento-pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off">
            </div>
            <p class="campo__error" id="descuento-error" role="alert" hidden></p>
          </div>
          <div class="modal__acciones">
            <button class="boton" type="button" data-cerrar-modal>Cancelar</button>
            <button class="boton boton--primario" type="submit" id="autorizar-descuento">Autorizar descuento</button>
          </div>
        </form>
      </dialog>

      <p class="visualmente-oculto" aria-live="polite" id="anuncio"></p>
    </main>
