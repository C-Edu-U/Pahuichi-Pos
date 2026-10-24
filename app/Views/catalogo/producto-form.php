<?php
/**
 * Alta y edición de producto. Variables: $producto (null en el alta), $v (valores del
 * formulario), $errores, $categorias, $historial (cambios de precio paginados desde la bitácora).
 */
use App\Models\Producto;

$accion = $producto ? '/catalogo/productos/' . (int) $producto['id'] : '/catalogo/productos/nuevo';
$etiquetas = [
    'codigo' => 'Código', 'categoria' => 'Categoría', 'nombre' => 'Nombre', 'corto' => 'Nombre corto',
    'precio' => 'Precio', 'unidad' => 'Unidad', 'descripcion' => 'Descripción', 'foto' => 'Foto',
];
$casillas = [
    'activo' => ['Activo', 'Aparece en el punto de venta.'],
    'favorito' => ['Favorito', 'Sale en la primera pantalla.'],
    'va_a_cocina' => ['Va a cocina', 'Se imprime en la comanda.'],
    'agotado' => ['Agotado', 'Se ve pero no se puede vender. No lo desactiva.'],
];
?>
    <main class="contenido" id="contenido">
      <a class="volver" href="/catalogo/productos">
        <?= icono('volver', 'icono--sm') ?>
        Catálogo
      </a>

<?php if ($errores): ?>
      <div class="alerta alerta--peligro resumen-errores" id="resumen-errores" tabindex="-1" role="alert">
        <?= icono('error') ?>
        <p class="alerta__titulo">Revisa los campos marcados</p>
        <ul class="alerta__texto resumen-errores__lista">
<?php foreach ($errores as $campo => $mensaje): ?>
          <li><a href="#<?= e($campo) ?>"><?= e($etiquetas[$campo] ?? $campo) ?>: <?= e($mensaje) ?></a></li>
<?php endforeach; ?>
        </ul>
      </div>
<?php endif; ?>

      <form class="formulario" id="form-producto" method="post" enctype="multipart/form-data" action="<?= e($accion) ?>" novalidate>
        <?= csrf_campo() ?>

        <div class="formulario__principal">
          <fieldset class="grupo-campos">
            <legend class="grupo-campos__titulo">Datos del producto</legend>
            <div class="campo">
              <label class="campo__etiqueta" for="codigo">Código</label>
<?php if ($producto): ?>
              <input class="control codigo-entrada" id="codigo" type="text" value="<?= e($v['codigo']) ?>" readonly aria-describedby="codigo-ayuda">
              <p class="campo__ayuda" id="codigo-ayuda">Fijo: las ventas y promociones lo usan para reconocer el producto.</p>
<?php else: ?>
              <input class="control codigo-entrada" id="codigo" name="codigo" type="text" maxlength="12" value="<?= e($v['codigo']) ?>" autocomplete="off" spellcheck="false"<?= campo_error($errores, 'codigo', 'codigo-ayuda') ?>>
              <p class="campo__ayuda" id="codigo-ayuda">Único. Se busca en el punto de venta. Ej.: PL-10</p>
              <?= mensaje_error($errores, 'codigo') ?>
<?php endif; ?>
            </div>
            <div class="campo">
              <label class="campo__etiqueta" for="categoria">Categoría</label>
              <select class="control" id="categoria" name="categoria_id"<?= campo_error($errores, 'categoria') ?>>
                <option value="">Elige una categoría</option>
<?php foreach ($categorias as $c): ?>
                <option value="<?= (int) $c['id'] ?>"<?= (int) $v['categoria_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['nombre']) ?><?= (int) $c['activo'] ? '' : ' (inactiva)' ?></option>
<?php endforeach; ?>
              </select>
              <?= mensaje_error($errores, 'categoria') ?>
            </div>
            <div class="campo campo--ancho">
              <label class="campo__etiqueta" for="nombre">Nombre</label>
              <input class="control" id="nombre" name="nombre" type="text" maxlength="80" value="<?= e($v['nombre']) ?>" autocomplete="off"<?= campo_error($errores, 'nombre') ?>>
              <?= mensaje_error($errores, 'nombre') ?>
            </div>
            <div class="campo">
              <label class="campo__etiqueta" for="corto">Nombre corto</label>
              <input class="control" id="corto" name="nombre_corto" type="text" maxlength="16" value="<?= e($v['nombre_corto']) ?>" autocomplete="off"<?= campo_error($errores, 'corto', 'corto-ayuda') ?>>
              <p class="campo__ayuda" id="corto-ayuda">El que se ve en el botón del punto de venta. Hasta 16 letras.</p>
              <?= mensaje_error($errores, 'corto') ?>
            </div>
            <div class="campo">
              <label class="campo__etiqueta" for="precio">Precio</label>
              <div class="control-grupo">
                <span class="control-grupo__prefijo" aria-hidden="true">Bs</span>
                <input class="control control--numero" id="precio" name="precio" type="text" inputmode="decimal" placeholder="0,00" value="<?= e((string) $v['precio']) ?>" autocomplete="off"<?= campo_error($errores, 'precio', 'precio-ayuda') ?>>
              </div>
              <p class="campo__ayuda" id="precio-ayuda">Con impuestos incluidos.</p>
              <?= mensaje_error($errores, 'precio') ?>
            </div>
            <div class="campo">
              <label class="campo__etiqueta" for="unidad">Unidad</label>
              <select class="control" id="unidad" name="unidad"<?= campo_error($errores, 'unidad') ?>>
<?php foreach (Producto::UNIDADES as $clave => $texto): ?>
                <option value="<?= e($clave) ?>"<?= $v['unidad'] === $clave ? ' selected' : '' ?>><?= e($texto) ?></option>
<?php endforeach; ?>
              </select>
              <?= mensaje_error($errores, 'unidad') ?>
            </div>
            <div class="campo campo--ancho">
              <label class="campo__etiqueta" for="descripcion">Descripción <span class="campo__opcional">(opcional)</span></label>
              <textarea class="control control--area" id="descripcion" name="descripcion" rows="2" maxlength="200"<?= campo_error($errores, 'descripcion') ?>><?= e($v['descripcion'] ?? '') ?></textarea>
              <?= mensaje_error($errores, 'descripcion') ?>
            </div>
          </fieldset>

          <section class="grupo-campos" aria-labelledby="t-modificadores">
            <h2 class="grupo-campos__titulo" id="t-modificadores">Modificadores <span class="insignia insignia--aviso">Próximamente · HU-18</span></h2>
            <p class="grupo-campos__ayuda">Opciones que el cajero agrega al pedido, sin costo (sin cebolla) o con costo (porción extra de arroz, +Bs 5).</p>
          </section>

<?php if ($producto): ?>
          <section class="grupo-campos historial-precios" aria-labelledby="t-historial-precio">
            <h2 class="grupo-campos__titulo" id="t-historial-precio">Cambios de precio</h2>
<?php if ($historial['filas'] === []): ?>
            <p class="grupo-campos__ayuda campo--ancho">Todavía no hay cambios de precio registrados.</p>
<?php else: ?>
            <div class="historial-precios__tabla campo--ancho" tabindex="0" role="region" aria-label="Historial de cambios de precio">
            <table class="tabla tabla--compacta">
              <caption class="visualmente-oculto">Cambios de precio</caption>
              <thead><tr><th scope="col">Fecha</th><th scope="col">Usuario</th><th scope="col" class="col-num">Antes</th><th scope="col" class="col-num">Después</th></tr></thead>
              <tbody>
<?php foreach ($historial['filas'] as $cambio): $d = json_decode((string) $cambio['detalle'], true) ?: []; ?>
                <tr><td class="num"><?= e((new DateTimeImmutable($cambio['creado_en']))->format('d/m/Y H:i')) ?></td><td><?= e($cambio['nombre'] ?? 'Usuario no disponible') ?></td><td class="col-num num"><?= bs($d['antes'] ?? 0) ?></td><td class="col-num num"><?= bs($d['despues'] ?? 0) ?></td></tr>
<?php endforeach; ?>
              </tbody>
            </table>
            </div>
            <p class="grupo-campos__ayuda campo--ancho"><?= (int) $historial['total'] ?> cambios registrados. Precios en bolivianos (Bs).</p>
<?php if ($historial['paginas'] > 1): ?>
            <nav class="paginacion campo--ancho" aria-label="Páginas de cambios de precio">
<?php if ($historial['pagina'] > 1): ?>
              <a class="boton boton--sm" href="<?= e($accion . '/editar?historial=' . ($historial['pagina'] - 1) . '#t-historial-precio') ?>">Anterior</a>
<?php else: ?><span></span><?php endif; ?>
              <span class="paginacion__estado num">Página <?= (int) $historial['pagina'] ?> de <?= (int) $historial['paginas'] ?></span>
<?php if ($historial['pagina'] < $historial['paginas']): ?>
              <a class="boton boton--sm" href="<?= e($accion . '/editar?historial=' . ($historial['pagina'] + 1) . '#t-historial-precio') ?>">Siguiente</a>
<?php else: ?><span></span><?php endif; ?>
            </nav>
<?php endif; ?>
<?php endif; ?>
          </section>
<?php endif; ?>
        </div>

        <div class="formulario__lateral">
          <?php require __DIR__ . '/_foto.php'; ?>

          <fieldset class="grupo-campos">
            <legend class="grupo-campos__titulo">En el punto de venta</legend>
<?php foreach ($casillas as $campo => [$titulo, $ayuda]): ?>
            <label class="casilla"><input type="checkbox" id="<?= e($campo) ?>" name="<?= e($campo) ?>" value="1"<?= (int) $v[$campo] ? ' checked' : '' ?>><span class="casilla__texto"><span class="casilla__titulo"><?= e($titulo) ?></span><span class="casilla__ayuda"><?= e($ayuda) ?></span></span></label>
<?php endforeach; ?>
          </fieldset>
        </div>

        <div class="formulario__acciones">
          <a class="boton" href="/catalogo/productos">Cancelar</a>
          <button class="boton boton--primario" type="submit" id="guardar">Guardar producto</button>
        </div>
      </form>
    </main>
