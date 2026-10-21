<?php
/**
 * Punto de venta con caja abierta: cuadrícula (HU-28) y carrito (HU-29). Variables: $caja, $productos (activos, favoritos
 * primero), $categorias (activas con productos), $hayFavoritos.
 * Los datos de cada producto van en data-* para el JS (la CSP no permite scripts en línea).
 */
use App\Models\Categoria;

$inicial = $hayFavoritos ? 'favoritos' : 'todo';
$datos = array_map(static fn (array $p): array => [
    'id' => (int) $p['id'],
    'codigo' => $p['codigo'],
    'nombre' => $p['nombre'],
    'corto' => $p['nombre_corto'],
    'precio' => (float) $p['precio'],
    'foto' => $p['foto'],
    'agotado' => (bool) (int) $p['agotado'],
], $productos);
?>
    <main class="contenido contenido--pos" id="contenido">
      <div class="pos" data-caja="<?= (int) $caja['id'] ?>" id="pos" data-productos="<?= e(json_encode($datos, JSON_UNESCAPED_UNICODE)) ?>">
        <section class="pos__catalogo" aria-labelledby="t-catalogo">
          <h2 class="visualmente-oculto" id="t-catalogo">Productos</h2>
          <div class="pos__busqueda">
            <div class="control-grupo">
              <?= icono('buscar', 'control-grupo__icono') ?>
              <label class="visualmente-oculto" for="pos-buscar">Buscar producto por nombre o código</label>
              <input class="control" id="pos-buscar" type="search" placeholder="Buscar plato o código" autocomplete="off">
            </div>
            <p class="pos__turno" id="pos-turno"><span class="punto-estado" aria-hidden="true"></span>Caja abierta · <?= e(mb_strtolower($caja['turno'])) ?> desde las <?= e(substr($caja['abierta_en'], 11, 5)) ?> · <?= e($caja['cajero']) ?></p>
          </div>

          <div class="categorias-pos" id="categorias-pos" role="group" aria-label="Categorías">
<?php if ($hayFavoritos): ?>
            <button class="categoria-pos categoria-pos--favoritos" type="button" data-categoria="favoritos" aria-pressed="true"><?= icono('estrella') ?>Favoritos</button>
<?php endif; ?>
<?php foreach ($categorias as $c): ?>
            <button class="categoria-pos <?= e(Categoria::clase($c['color'])) ?>" type="button" data-categoria="<?= (int) $c['id'] ?>" aria-pressed="false"><?= e($c['nombre']) ?></button>
<?php endforeach; ?>
            <button class="categoria-pos categoria-pos--todo" type="button" data-categoria="todo" aria-pressed="<?= $inicial === 'todo' ? 'true' : 'false' ?>">Todo</button>
          </div>

          <ul class="grilla-productos" id="grilla" role="list" data-inicial="<?= e($inicial) ?>">
<?php foreach ($productos as $p):
    $agotado = (bool) (int) $p['agotado'];
    $favorito = (bool) (int) $p['favorito'];
    $visible = $inicial === 'todo' || $favorito;
?>
            <li data-categoria="<?= (int) $p['categoria_id'] ?>" data-favorito="<?= (int) $favorito ?>" data-busqueda="<?= e($p['nombre'] . ' ' . $p['nombre_corto'] . ' ' . $p['codigo']) ?>"<?= $visible ? '' : ' hidden' ?>>
              <button class="producto-pos <?= e(Categoria::clase($p['categoria_color'])) ?>" type="button" data-id="<?= (int) $p['id'] ?>"<?= $agotado ? ' disabled' : '' ?> aria-label="<?= e($p['nombre'] . ', ' . bs($p['precio']) . ' bolivianos' . ($agotado ? ', agotado' : '')) ?>">
                <span class="producto-pos__foto" aria-hidden="true"><?php if ($p['foto'] !== null): ?><img src="/catalogo/productos/<?= (int) $p['id'] ?>/foto?v=<?= e(rawurlencode($p['foto'])) ?>" alt="" loading="lazy"><?php else: ?><?= icono(Categoria::icono($p['categoria']), 'producto-pos__icono') ?><?php endif; ?></span>
                <span class="producto-pos__nombre"><?= e($p['nombre_corto']) ?></span>
                <span class="producto-pos__pie"><span class="producto-pos__precio monto"><span class="monto__moneda">Bs</span><?= bs($p['precio']) ?></span><?= $agotado ? '<span class="insignia insignia--aviso">Agotado</span>' : '' ?></span>
              </button>
            </li>
<?php endforeach; ?>
          </ul>
          <div class="vacio" id="pos-vacio"<?= $productos === [] ? '' : ' hidden' ?>>
            <?= icono('buscar', 'icono--lg vacio__icono') ?>
<?php if ($productos === []): ?>
            <h3 class="vacio__titulo">No hay productos para vender</h3>
            <p class="vacio__texto">Activa productos y categorías en el Catálogo.</p>
<?php else: ?>
            <h3 class="vacio__titulo">Ningún producto coincide</h3>
            <p class="vacio__texto">Prueba con otro nombre o código, o vuelve a «Todo».</p>
<?php endif; ?>
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
              <h2 class="pos__pedido-titulo" id="t-pedido">Pedido</h2>
              <div class="tipo-pedido" role="group" aria-label="Tipo de pedido">
                <button class="tipo-pedido__opcion" type="button" data-tipo="mesa" aria-pressed="true">
                  <svg class="icono icono--sm" aria-hidden="true"><use href="#i-mesa"></use></svg>Mesa
                </button>
                <button class="tipo-pedido__opcion" type="button" data-tipo="llevar" aria-pressed="false">
                  <svg class="icono icono--sm" aria-hidden="true"><use href="#i-llevar"></use></svg>Llevar
                </button>
                <label class="visualmente-oculto" for="mesa">Número de mesa</label>
                <input class="control control--numero tipo-pedido__mesa" id="mesa" type="text" inputmode="numeric" maxlength="2" value="1">
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
              <?= icono('carrito', 'icono--lg') ?>
              <p id="lineas-vacio-texto">Toca un producto para agregarlo. Otro toque suma una unidad.</p>
            </div>

            <div class="totales">
              <p class="totales__total">
                <span class="totales__etiqueta">Total</span>
                <span class="totales__monto"><span class="totales__moneda">Bs</span><span id="t-total">0,00</span></span>
              </p>
              <div class="totales__acciones">
                <button class="boton boton--primario boton--grande boton-cobro" type="button" id="efectivo-exacto" disabled>
                  <?= icono('billete') ?> Efectivo exacto <kbd class="atajo atajo--claro">F8</kbd>
                </button>
                <button class="boton boton--grande boton-cobro" type="button" id="cobrar" disabled>
                  <span id="cobrar-texto">Cobrar</span> <kbd class="atajo">F4</kbd>
                </button>
              </div>
              <p class="campo__error" id="cobro-error" role="alert" hidden></p>
              <div class="totales__secundarias">
                <button class="boton boton--fantasma" type="button" disabled title="Ventas en espera pendientes"><?= icono('pausa', 'icono--sm') ?>En espera</button>
                <button class="boton boton--fantasma" type="button" disabled title="Ventas en espera pendientes">Retomar <span class="pestana__cuenta">0</span></button>
                <button class="boton boton--fantasma" type="button" disabled title="Descuento manual pendiente">Descuento</button>
                <button class="boton boton--fantasma" type="button" id="vaciar" data-abrir-modal="modal-vaciar" disabled>
                  <?= icono('basura', 'icono--sm') ?>Vaciar
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

      <p class="visualmente-oculto" aria-live="polite" id="anuncio"></p>
    </main>
