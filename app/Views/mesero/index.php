<?php
/**
 * Pedidos del mesero (HU-35), pensada para el celular: mesas abiertas con sus pedidos y un
 * pedido nuevo para la mesa elegida. Cada pedido llega a caja (comanda para cocina) y se
 * suma a la cuenta de la mesa; al terminar, el mesero cierra la cuenta y cobra.
 * Variables: $caja (abierta o null), $productos, $categorias, $promociones, $contextoPromos,
 * $hayFavoritos, $ahora.
 */
use App\Core\Auth;

require dirname(__DIR__) . '/pos/_datos.php';
$estadoCatalogo = $caja !== null
    ? '<p class="pos__turno"><span class="punto-estado" aria-hidden="true"></span>Caja abierta · ' . e(mb_strtolower($caja['turno'])) . '</p>'
    : '<p class="pos__turno pos__turno--sin-caja">Sin caja abierta: los pedidos se envían cuando caja abra el turno.</p>';
?>
    <main class="contenido contenido--mesero" id="contenido">
      <div class="mesero" id="mesero" data-usuario="<?= (int) Auth::id() ?>" data-caja="<?= $caja !== null ? '1' : '0' ?>" data-productos="<?= e(json_encode($datos, JSON_UNESCAPED_UNICODE)) ?>" data-promociones="<?= e(json_encode($promos, JSON_UNESCAPED_UNICODE)) ?>" data-ahora="<?= e($ahora->format('Y-m-d\TH:i:s')) ?>">
        <section class="mesero__mesas" aria-labelledby="t-mesas">
          <div class="seccion__cabeza">
            <h2 class="seccion__titulo" id="t-mesas">Mesas abiertas</h2>
            <button class="boton boton--fantasma" type="button" id="m-actualizar"><?= icono('reintentar', 'icono--sm') ?> Actualizar</button>
          </div>
          <div class="cuentas" id="m-cuentas" aria-live="polite"></div>
          <p class="cuentas__vacio" id="m-cuentas-vacio">Ninguna mesa abierta. El primer pedido de una mesa abre su cuenta.</p>
          <details class="mesero__cobradas" id="m-cobradas-panel" hidden>
            <summary>Cobradas hoy <span id="m-cobradas-cuenta"></span></summary>
            <div class="cuentas" id="m-cobradas"></div>
          </details>
        </section>

        <section class="mesero__nuevo" id="m-nuevo" aria-labelledby="t-nuevo">
          <h2 class="seccion__titulo" id="t-nuevo">Nuevo pedido</h2>
          <div class="mesero__mesa">
            <p class="campo__etiqueta" id="m-mesa-etiqueta">Mesa</p>
<?php $idMesa = 'm-mesa'; $idMesas = 'm-mesas'; $describeMesa = 'm-error'; require dirname(__DIR__) . '/pos/_mesas.php'; ?>
          </div>
<?php require dirname(__DIR__) . '/pos/_catalogo.php'; ?>

          <div class="mesero__pedido" aria-labelledby="t-mesero-pedido">
            <h2 class="pos__pedido-titulo" id="t-mesero-pedido">Pedido <span id="m-mesa-titulo">sin mesa</span></h2>
            <ul class="lineas" id="m-lineas" role="list" aria-label="Ítems del pedido"></ul>
            <p class="lineas-vacio" id="m-vacio">Toca un plato para agregarlo. Otro toque suma una unidad.</p>
            <dl class="totales__lista" id="m-desglose" hidden>
              <div><dt>Subtotal</dt><dd class="num" id="m-subtotal">0,00</dd></div>
              <div class="totales__descuento"><dt>Promociones</dt><dd class="num" id="m-promociones">0,00</dd></div>
            </dl>
            <p class="totales__total"><span class="totales__etiqueta">Total</span><span class="totales__monto"><span class="totales__moneda">Bs</span><span id="m-total">0,00</span></span></p>
            <p class="campo__error" id="m-error" role="alert" hidden></p>
            <button class="boton boton--primario boton--grande boton--bloque" type="button" id="m-enviar" disabled><?= icono('enviar') ?> Enviar a caja</button>
            <button class="boton boton--fantasma boton--bloque" type="button" id="m-vaciar" hidden>Vaciar el pedido</button>
          </div>
        </section>
      </div>
      <p class="visualmente-oculto" id="anuncio" role="status" aria-live="polite"></p>
<?php require dirname(__DIR__) . '/cuentas/_dialogos.php'; ?>
    </main>
