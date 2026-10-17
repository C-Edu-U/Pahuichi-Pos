<?php /** Soporte de HU-10: marcado de demo con catálogo real y cobro en efectivo. */ ?>
    <main class="contenido contenido--pos" id="contenido">

      <div class="pos" id="pos" data-caja="<?= (int) $caja['id'] ?>" data-productos="<?= e(json_encode($productos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?>">
        <section class="pos__catalogo" aria-labelledby="t-catalogo">
          <h2 class="visualmente-oculto" id="t-catalogo">Productos</h2>
          <div class="pos__busqueda">
            <div class="control-grupo">
              <svg class="icono control-grupo__icono" aria-hidden="true"><use href="#i-buscar"></use></svg>
              <label class="visualmente-oculto" for="pos-buscar">Buscar producto por nombre o código</label>
              <input class="control" id="pos-buscar" type="search" placeholder="Buscar plato o código" autocomplete="off" aria-describedby="atajo-buscar">
              <kbd class="atajo control-grupo__atajo" id="atajo-buscar" aria-label="Atajo F2">F2</kbd>
            </div>
            <p class="pos__turno" id="pos-turno"><span class="punto-estado" aria-hidden="true"></span>Caja abierta · <?= e(mb_strtolower($caja['turno'])) ?> desde las <?= e(substr($caja['abierta_en'], 11, 5)) ?></p>
          </div>

          <div class="categorias-pos" role="group" aria-label="Categorías">
            <button class="categoria-pos categoria-pos--favoritos" type="button" data-categoria="favoritos" aria-pressed="true">
              <svg class="icono" aria-hidden="true"><use href="#i-estrella"></use></svg>Favoritos
            </button>
<?php foreach ($categorias as $categoria): ?>
            <button class="categoria-pos <?= e(\App\Models\Categoria::clase($categoria['color'])) ?>" type="button" data-categoria="<?= (int) $categoria['id'] ?>" aria-pressed="false"><?= e($categoria['nombre']) ?></button>
<?php endforeach; ?>
            <button class="categoria-pos categoria-pos--todo" type="button" data-categoria="todo" aria-pressed="false">Todo</button>
          </div>

          <ul class="grilla-productos" id="grilla" role="list"></ul>
          <div class="vacio" id="pos-vacio" hidden>
            <svg class="icono icono--lg vacio__icono" aria-hidden="true"><use href="#i-buscar"></use></svg>
            <h3 class="vacio__titulo">Ningún producto coincide</h3>
            <p class="vacio__texto">Prueba con otro nombre o código, o vuelve a «Favoritos».</p>
          </div>
        </section>

        <section class="pos__pedido" id="pedido" aria-labelledby="t-pedido">
          <button class="pos__resumen-movil" type="button" id="ver-pedido" aria-expanded="false" aria-controls="pedido-cuerpo">
            <span class="pos__resumen-cuenta" id="resumen-cuenta">Pedido vacío</span>
            <span class="pos__resumen-total monto"><span class="monto__moneda">Bs</span><span id="resumen-total">0,00</span></span>
          </button>

          <div class="pos__pedido-cuerpo" id="pedido-cuerpo">
            <div class="venta-lista" id="venta-lista" role="status" hidden>
              <svg class="icono" aria-hidden="true"><use href="#i-check"></use></svg>
              <div class="venta-lista__texto">
                <p class="venta-lista__titulo" id="venta-lista-titulo">Pedido cobrado</p>
                <p class="venta-lista__detalle" id="venta-lista-detalle">Factura guardada · lista para imprimir</p>
              </div>
              <a class="boton boton--fantasma venta-lista__ver" id="venta-lista-ver" href="/ventas">Ver tickets</a>
            </div>

            <div class="pos__pedido-cabeza">
              <h2 class="pos__pedido-titulo" id="t-pedido">Pedido <span class="num" id="numero-pedido"><?= e(str_pad((string) $proximoPedido, 3, '0', STR_PAD_LEFT)) ?></span></h2>
              <div class="tipo-pedido" role="group" aria-label="Tipo de pedido">
                <button class="tipo-pedido__opcion" type="button" data-tipo="mesa" aria-pressed="true">
                  <svg class="icono icono--sm" aria-hidden="true"><use href="#i-mesa"></use></svg>Mesa
                </button>
                <button class="tipo-pedido__opcion" type="button" data-tipo="llevar" aria-pressed="false">
                  <svg class="icono icono--sm" aria-hidden="true"><use href="#i-llevar"></use></svg>Llevar
                </button>
                <label class="visualmente-oculto" for="mesa">Número de mesa</label>
                <input class="control control--numero tipo-pedido__mesa" id="mesa" type="text" inputmode="numeric" maxlength="2" value="4">
              </div>
            </div>

            <div class="cliente-pos">
              <button class="cliente-pos__resumen" type="button" id="cliente-cambiar" disabled title="Registro de clientes pendiente" aria-expanded="false" aria-controls="cliente-buscador">
                <svg class="icono icono--sm" aria-hidden="true"><use href="#i-persona"></use></svg>
                <span id="cliente-dato">S/N · NIT 0</span>
                <span class="cliente-pos__accion">Cambiar</span>
              </button>
            </div>

            <ul class="lineas" id="lineas" role="list" aria-label="Ítems del pedido"></ul>
            <div class="lineas-vacio" id="lineas-vacio">
              <svg class="icono icono--lg" aria-hidden="true"><use href="#i-carrito"></use></svg>
              <p>Toca un producto para agregarlo. Otro toque suma una unidad.</p>
            </div>

            <div class="totales">
              <dl class="totales__lista">
                <div><dt>Subtotal</dt><dd class="num" id="t-subtotal">0,00</dd></div>
                <div class="totales__descuento"><dt>Descuentos</dt><dd class="num" id="t-descuento">0,00</dd></div>
              </dl>
              <p class="totales__total">
                <span class="totales__etiqueta">Total</span>
                <span class="totales__monto"><span class="totales__moneda">Bs</span><span id="t-total">0,00</span></span>
              </p>
              <div class="totales__acciones">
                <button class="boton boton--primario boton--grande boton-cobro" type="button" id="cobrar-exacto" disabled>
                  <svg class="icono" aria-hidden="true"><use href="#i-billete"></use></svg>
                  Efectivo exacto
                  <kbd class="atajo atajo--claro">F8</kbd>
                </button>
                <button class="boton boton--grande boton-cobro" type="button" id="cobrar" disabled>
                  Cobrar
                  <kbd class="atajo">F4</kbd>
                </button>
              </div>
              <div class="totales__secundarias">
                <button class="boton boton--fantasma" type="button" id="poner-espera" disabled title="Ventas en espera pendientes">
                  <svg class="icono icono--sm" aria-hidden="true"><use href="#i-pausa"></use></svg>En espera
                </button>
                <button class="boton boton--fantasma" type="button" id="ver-espera" disabled title="Ventas en espera pendientes" data-abrir-modal="modal-espera">
                  Retomar <span class="pestana__cuenta" id="cuenta-espera">0</span>
                </button>
                <button class="boton boton--fantasma" type="button" id="descuento-manual" disabled title="Descuento manual pendiente" data-abrir-modal="modal-descuento">Descuento</button>
                <button class="boton boton--fantasma" type="button" id="vaciar" disabled>Vaciar</button>
              </div>
              <p class="totales__qr-vencido" id="qr-vencido" hidden>
                <svg class="icono icono--sm" aria-hidden="true"><use href="#i-qrOff"></use></svg>
                No hay QR del día vigente: el cobro con QR está deshabilitado.
              </p>
            </div>
          </div>
        </section>
      </div>

      <!-- Cobro -->
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
            <button class="metodos__opcion" type="button" role="tab" id="tab-qr" disabled title="Cobro QR pendiente" aria-selected="false" aria-controls="cobro-qr" data-metodo="qr">QR</button>
            <button class="metodos__opcion" type="button" role="tab" id="tab-mixto" disabled title="Pago mixto pendiente" aria-selected="false" aria-controls="cobro-qr" data-metodo="mixto">Mixto</button>
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

          <!-- QR del día -->
          <div class="modal__acciones">
            <button class="boton" type="button" data-cerrar-modal>Cancelar <kbd class="atajo">Esc</kbd></button>
            <button class="boton boton--primario" type="button" id="confirmar-cobro">Confirmar venta <kbd class="atajo atajo--claro">Enter</kbd></button>
          </div>
        </div>
      </dialog>

      <p class="visualmente-oculto" aria-live="polite" id="anuncio"></p>
    </main>