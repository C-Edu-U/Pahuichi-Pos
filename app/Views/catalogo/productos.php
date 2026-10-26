<?php
/** Productos. Variables: $productos (con su categoría), $categorias (con cantidad), $estado (filtro inicial) */
use App\Models\Categoria;

$puedeEditar = puede('catalogo.editar');
$puedeAgotado = puede('catalogo.agotado');
?>
    <main class="contenido" id="contenido">
      <div class="catalogo-cabeza">
<?php $actual = 'productos'; require __DIR__ . '/_subnav.php'; ?>
<?php if ($puedeEditar): ?>
        <a class="boton boton--primario" href="/catalogo/productos/nuevo">
          <?= icono('mas') ?>
          Nuevo producto
        </a>
<?php endif; ?>
      </div>

      <div class="herramientas">
        <div class="herramientas__busqueda control-grupo">
          <?= icono('buscar', 'control-grupo__icono') ?>
          <label class="visualmente-oculto" for="buscar">Buscar producto</label>
          <input class="control" id="buscar" type="search" placeholder="Nombre o código" autocomplete="off">
        </div>
        <div class="herramientas__filtro">
          <label class="visualmente-oculto" for="estado">Estado</label>
          <select class="control" id="estado">
            <option value="">Todos</option>
<?php foreach (['favorito' => 'Favoritos', 'agotado' => 'Agotados', 'inactivo' => 'Inactivos', 'cocina' => 'Van a cocina'] as $clave => $texto): ?>
            <option value="<?= $clave ?>"<?= $estado === $clave ? ' selected' : '' ?>><?= $texto ?></option>
<?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="pestanas pestanas--categorias" id="pestanas" role="group" aria-label="Categoría">
        <button class="pestana" type="button" data-categoria="" aria-pressed="true">Todas <span class="pestana__cuenta"><?= count($productos) ?></span></button>
<?php foreach ($categorias as $c): ?>
        <button class="pestana" type="button" data-categoria="<?= (int) $c['id'] ?>" aria-pressed="false"><span class="punto-categoria <?= e(Categoria::clase($c['color'])) ?>" aria-hidden="true"></span><span><?= e($c['nombre']) ?></span> <span class="pestana__cuenta"><?= e($c['productos']) ?></span></button>
<?php endforeach; ?>
      </div>

      <p class="resultado"><span class="resultado__cuenta" id="cuenta"><?= count($productos) ?> productos</span></p>

      <div class="tabla-contenedor">
        <table class="tabla tabla--catalogo tabla--fichas">
          <caption class="visualmente-oculto">Productos del catálogo</caption>
          <thead>
            <tr>
              <th scope="col">Producto</th>
              <th scope="col">Categoría</th>
              <th scope="col" class="col-num">Precio</th>
              <th scope="col">Marcas</th>
              <th scope="col">Estado</th>
              <th scope="col" class="col-accion"><span class="visualmente-oculto">Acciones</span></th>
            </tr>
          </thead>
          <tbody id="filas">
<?php foreach ($productos as $p):
    $id = (int) $p['id'];
    $clase = Categoria::clase($p['categoria_color']);
    $activo = (bool) (int) $p['activo'];
    $agotado = (bool) (int) $p['agotado'];
    $favorito = (bool) (int) $p['favorito'];
?>
            <tr data-categoria="<?= (int) $p['categoria_id'] ?>" data-favorito="<?= (int) $favorito ?>" data-agotado="<?= (int) $agotado ?>" data-activo="<?= (int) $activo ?>" data-cocina="<?= (int) $p['va_a_cocina'] ?>" data-busqueda="<?= e($p['nombre'] . ' ' . $p['nombre_corto'] . ' ' . $p['codigo']) ?>">
              <td>
                <div class="producto-celda">
                  <span class="producto-celda__foto <?= e($clase) ?>" aria-hidden="true"><?php if (!empty($p['foto'])): ?><img src="/catalogo/productos/<?= (int) $p['id'] ?>/foto?v=<?= e($p['foto']) ?>" alt="" width="44" height="44"><?php else: ?><?= icono('productos') ?><?php endif; ?></span>
                  <span><span class="tabla__principal"><?= e($p['nombre']) ?></span><span class="tabla__secundario"><span class="codigo"><?= e($p['codigo']) ?></span> · <?= e($p['nombre_corto']) ?></span></span>
                </div>
              </td>
              <td><span class="categoria-celda"><span class="punto-categoria <?= e($clase) ?>" aria-hidden="true"></span><span><?= e($p['categoria']) ?></span></span></td>
              <td class="col-num"><span class="monto"><span class="monto__moneda">Bs</span><?= bs($p['precio']) ?></span></td>
              <td>
                <div class="marcas">
<?php if ($puedeEditar): ?>
                  <form method="post" action="/catalogo/productos/<?= $id ?>/favorito" data-marca="favorito"><?= csrf_campo() ?><button class="marca-toggle marca-toggle--favorito" type="submit" aria-pressed="<?= $favorito ? 'true' : 'false' ?>" aria-label="Favorito: <?= e($p['nombre']) ?>"><?= icono('estrella', 'icono--sm') ?>Favorito</button></form>
<?php elseif ($favorito): ?>
                  <span class="marca-fija"><?= icono('estrella', 'icono--sm') ?>Favorito</span>
<?php endif; ?>
<?php if ((int) $p['va_a_cocina']): ?>
                  <span class="marca-fija"><?= icono('cocina', 'icono--sm') ?>Cocina</span>
<?php endif; ?>
                </div>
              </td>
              <td class="celda-estado"><?= !$activo
                  ? '<span class="insignia insignia--neutra">Inactivo</span>'
                  : ($agotado ? '<span class="insignia insignia--aviso">Agotado</span>' : '<span class="insignia insignia--ok">Disponible</span>') ?></td>
              <td class="col-accion">
                <div class="acciones-fila">
<?php if ($puedeAgotado): ?>
                  <form method="post" action="/catalogo/productos/<?= $id ?>/agotado" data-marca="agotado"><?= csrf_campo() ?><button class="boton boton-agotado" type="submit" aria-pressed="<?= $agotado ? 'true' : 'false' ?>" aria-label="Agotado: <?= e($p['nombre']) ?>">Agotado</button></form>
<?php endif; ?>
<?php if ($puedeEditar): ?>
                  <a class="boton boton--icono" href="/catalogo/productos/<?= $id ?>/editar" aria-label="Editar <?= e($p['nombre']) ?>"><?= icono('editar') ?></a>
<?php endif; ?>
                </div>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
        <div class="vacio" id="vacio" hidden>
          <?= icono('buscar', 'icono--lg vacio__icono') ?>
          <h2 class="vacio__titulo">Ningún producto coincide</h2>
          <p class="vacio__texto">Prueba con otro nombre o código.</p>
          <button class="boton" type="button" id="limpiar">Quitar filtros</button>
        </div>
      </div>

      <p class="visualmente-oculto" aria-live="polite" id="anuncio"></p>
    </main>
