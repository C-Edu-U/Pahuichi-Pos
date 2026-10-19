<?php
/**
 * Punto de venta con caja abierta (HU-28). Variables: $caja, $productos (activos, favoritos
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
      <div class="pos" id="pos" data-productos="<?= e(json_encode($datos, JSON_UNESCAPED_UNICODE)) ?>">
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
            <div class="pos__pedido-cabeza">
              <h2 class="pos__pedido-titulo" id="t-pedido">Pedido</h2>
            </div>

            <ul class="lineas" id="lineas" role="list" aria-label="Ítems del pedido"></ul>
            <div class="lineas-vacio" id="lineas-vacio">
              <?= icono('carrito', 'icono--lg') ?>
              <p id="lineas-vacio-texto">El carrito llega con HU-29: por ahora la cuadrícula solo muestra los productos.</p>
            </div>

            <div class="totales">
              <p class="totales__total">
                <span class="totales__etiqueta">Total</span>
                <span class="totales__monto"><span class="totales__moneda">Bs</span><span id="t-total">0,00</span></span>
              </p>
              <div class="totales__acciones">
                <button class="boton boton--primario boton--grande boton-cobro" type="button" id="cobrar" disabled aria-describedby="cobro-pendiente">
                  <?= icono('billete') ?>
                  Cobrar
                </button>
              </div>
              <p class="totales__nota" id="cobro-pendiente">El cobro llega en el Sprint 3 (HU-37).</p>
            </div>
          </div>
        </section>
      </div>

      <p class="visualmente-oculto" aria-live="polite" id="anuncio"></p>
    </main>
