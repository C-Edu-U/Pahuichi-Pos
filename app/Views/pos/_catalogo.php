<?php
/**
 * Catálogo del punto de venta (HU-28): búsqueda, categorías y grilla de productos. Lo usan
 * el POS y la pantalla del mesero (HU-35). Variables: las de _datos.php, $productos,
 * $categorias, $hayFavoritos y $estadoCatalogo (línea de estado debajo de la búsqueda).
 */
use App\Models\Categoria;
?>
        <section class="pos__catalogo" aria-labelledby="t-catalogo">
          <h2 class="visualmente-oculto" id="t-catalogo">Productos</h2>
          <div class="pos__busqueda">
            <div class="control-grupo">
              <?= icono('buscar', 'control-grupo__icono') ?>
              <label class="visualmente-oculto" for="pos-buscar">Buscar producto por nombre o código</label>
              <input class="control" id="pos-buscar" type="search" placeholder="Buscar plato o código" autocomplete="off" aria-keyshortcuts="F2">
              <kbd class="atajo control-grupo__atajo" aria-hidden="true">F2</kbd>
            </div>
            <?= $estadoCatalogo ?? '' ?>
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
    $promosProducto = $conPromo[(int) $p['id']] ?? [];
    $promoAhora = in_array(true, $promosProducto, true);
?>
            <li data-categoria="<?= (int) $p['categoria_id'] ?>" data-favorito="<?= (int) $favorito ?>" data-busqueda="<?= e($p['nombre'] . ' ' . $p['nombre_corto'] . ' ' . $p['codigo']) ?>"<?= $visible ? '' : ' hidden' ?>>
              <button class="producto-pos <?= e(Categoria::clase($p['categoria_color'])) ?>" type="button" data-id="<?= (int) $p['id'] ?>"<?= $promosProducto ? ' data-promos="' . e(implode(',', array_keys($promosProducto))) . '"' : '' ?><?= $agotado ? ' disabled' : '' ?> aria-label="<?= e($p['nombre'] . ', ' . bs($p['precio']) . ' bolivianos' . ($agotado ? ', agotado' : '')) ?>">
                <span class="producto-pos__foto" aria-hidden="true"><?php if ($p['foto'] !== null): ?><img src="/catalogo/productos/<?= (int) $p['id'] ?>/foto?v=<?= e(rawurlencode($p['foto'])) ?>" alt="" loading="lazy"><?php else: ?><?= icono(Categoria::icono($p['categoria']), 'producto-pos__icono') ?><?php endif; ?></span>
                <span class="producto-pos__nombre"><?= e($p['nombre_corto']) ?></span>
                <span class="producto-pos__pie"><span class="producto-pos__precio monto"><span class="monto__moneda">Bs</span><?= bs($p['precio']) ?></span><?= $agotado ? '<span class="insignia insignia--aviso">Agotado</span>' : ($promosProducto ? '<span class="insignia insignia--promo"' . ($promoAhora ? '' : ' hidden') . '>Promo</span>' : '') ?></span>
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
