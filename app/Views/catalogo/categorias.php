<?php
/**
 * Categorías. Variables: $categorias (con cantidad de productos), $paleta, $errores,
 * $viejo (nombre, orden, color), $editando (categoría en edición o null).
 */
use App\Models\Categoria;

$puedeEditar = puede('catalogo.editar');
$accion = $editando ? '/catalogo/categorias/' . (int) $editando['id'] : '/catalogo/categorias';
?>
    <main class="contenido" id="contenido">
      <div class="catalogo-cabeza">
<?php $actual = 'categorias'; require __DIR__ . '/_subnav.php'; ?>
      </div>

      <div class="categorias">
<?php if ($puedeEditar): ?>
        <form class="nueva-categoria" id="form-categoria" method="post" action="<?= e($accion) ?>" novalidate>
          <?= csrf_campo() ?>
          <h2 class="nueva-categoria__titulo"><?= $editando ? 'Editar «' . e($editando['nombre']) . '»' : 'Nueva categoría' ?></h2>
          <div class="campo">
            <label class="campo__etiqueta" for="cat-nombre">Nombre</label>
            <input class="control" id="cat-nombre" name="nombre" type="text" maxlength="24" value="<?= e($viejo['nombre']) ?>" autocomplete="off"<?= campo_error($errores, 'cat-nombre') ?>>
            <?= mensaje_error($errores, 'cat-nombre') ?>
          </div>
          <div class="campo">
            <label class="campo__etiqueta" for="cat-orden">Orden en el punto de venta</label>
            <input class="control control--numero" id="cat-orden" name="orden" type="text" inputmode="numeric" maxlength="2" value="<?= e($viejo['orden']) ?>"<?= campo_error($errores, 'cat-orden', 'cat-orden-ayuda') ?>>
            <p class="campo__ayuda" id="cat-orden-ayuda">Los botones se muestran de menor a mayor.</p>
            <?= mensaje_error($errores, 'cat-orden') ?>
          </div>
          <fieldset class="colores"<?= isset($errores['colores']) ? ' aria-describedby="colores-error"' : '' ?>>
            <legend class="campo__etiqueta">Color del botón</legend>
            <p class="campo__ayuda">Todos tienen contraste AA con texto blanco.</p>
            <div class="colores__lista" id="colores">
<?php foreach ($paleta as $hex => [$clase, $nombre]): ?>
              <label class="color-opcion"><input type="radio" name="color" value="<?= e($hex) ?>" data-clase="cat--<?= e($clase) ?>"<?= $viejo['color'] === $hex ? ' checked' : '' ?>><span class="color-opcion__muestra cat--<?= e($clase) ?>" aria-hidden="true"></span><span class="visualmente-oculto"><?= e($nombre) ?></span></label>
<?php endforeach; ?>
            </div>
            <?= mensaje_error($errores, 'colores') ?>
          </fieldset>
          <p class="vista-boton">
            <span class="campo__etiqueta">Así se verá</span>
            <span class="categoria-pos categoria-pos--vista <?= e(Categoria::clase($viejo['color'])) ?>" id="vista-boton" aria-pressed="true"><?= e($viejo['nombre'] !== '' ? $viejo['nombre'] : 'Nueva categoría') ?></span>
          </p>
          <div class="nueva-categoria__acciones">
            <button class="boton boton--primario" type="submit">
<?php if ($editando): ?>
              Guardar cambios
<?php else: ?>
              <?= icono('mas') ?>
              Agregar categoría
<?php endif; ?>
            </button>
<?php if ($editando): ?>
            <a class="boton" href="/catalogo/categorias">Cancelar</a>
<?php endif; ?>
          </div>
        </form>
<?php endif; ?>

        <div class="tabla-contenedor">
          <table class="tabla tabla--fichas">
            <caption class="visualmente-oculto">Categorías del menú</caption>
            <thead>
              <tr>
                <th scope="col" class="col-num">Orden</th>
                <th scope="col">Categoría</th>
                <th scope="col" class="col-num">Productos</th>
                <th scope="col">Estado</th>
<?php if ($puedeEditar): ?>
                <th scope="col" class="col-accion"><span class="visualmente-oculto">Acciones</span></th>
<?php endif; ?>
              </tr>
            </thead>
            <tbody id="filas-categorias">
<?php foreach ($categorias as $c): $id = (int) $c['id']; $activa = (bool) (int) $c['activo']; ?>
              <tr>
                <td class="col-num num"><?= e($c['orden']) ?></td>
                <td><span class="categoria-pos categoria-pos--tabla <?= e(Categoria::clase($c['color'])) ?>" aria-pressed="true"><?= e($c['nombre']) ?></span></td>
                <td class="col-num num"><?= e($c['productos']) ?></td>
                <td class="celda-estado"><?= $activa ? '<span class="insignia insignia--ok">Activa</span>' : '<span class="insignia insignia--neutra">Inactiva</span>' ?></td>
<?php if ($puedeEditar): ?>
                <td class="col-accion">
                  <div class="acciones-fila">
                    <a class="boton boton--icono" href="/catalogo/categorias/<?= $id ?>/editar" aria-label="Editar <?= e($c['nombre']) ?>"><?= icono('editar') ?></a>
<?php if ($activa): ?>
                    <button class="boton" type="button" data-abrir-modal="modal-desactivar" data-id="<?= $id ?>" data-nombre="<?= e($c['nombre']) ?>" data-productos="<?= e($c['productos']) ?>">Desactivar</button>
<?php else: ?>
                    <form method="post" action="/catalogo/categorias/<?= $id ?>/estado"><?= csrf_campo() ?><input type="hidden" name="activo" value="1"><button class="boton" type="submit">Activar</button></form>
<?php endif; ?>
                  </div>
                </td>
<?php endif; ?>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

<?php if ($puedeEditar): ?>
      <dialog class="modal" id="modal-desactivar" aria-labelledby="modal-desactivar-titulo" aria-describedby="modal-desactivar-texto">
        <form method="post" action="/catalogo/categorias" id="form-desactivar">
          <?= csrf_campo() ?>
          <input type="hidden" name="activo" value="0">
          <div class="modal__cuerpo">
            <h2 class="modal__titulo" id="modal-desactivar-titulo">¿Desactivar la categoría?</h2>
            <p class="modal__texto" id="modal-desactivar-texto">Sus productos dejarán de verse en el punto de venta.</p>
          </div>
          <div class="modal__acciones">
            <button class="boton" type="button" data-cerrar-modal autofocus>Cancelar</button>
            <button class="boton boton--peligro" type="submit">Desactivar</button>
          </div>
        </form>
      </dialog>
<?php endif; ?>
    </main>
