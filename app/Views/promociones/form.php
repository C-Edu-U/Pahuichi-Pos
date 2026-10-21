<?php
/**
 * Alta y edición de promoción (HU-46). Variables: $promocion (null en el alta), $v (valores
 * del formulario: nombre, aplica «p:ID» o «c:ID», valor, valor_tipo, activo), $errores,
 * $productos (activos, con su categoría), $categorias (activas).
 */
$accion = $promocion ? '/promociones/' . (int) $promocion['id'] : '/promociones/nueva';
$etiquetas = ['nombre' => 'Nombre', 'aplica' => 'Se aplica a', 'valor' => 'Descuento'];
?>
    <main class="contenido" id="contenido">
      <a class="volver" href="/promociones">
        <?= icono('volver', 'icono--sm') ?>
        Promociones
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

      <form class="formulario formulario--una" id="form-promocion" method="post" action="<?= e($accion) ?>" novalidate>
        <?= csrf_campo() ?>

        <fieldset class="grupo-campos">
          <legend class="grupo-campos__titulo">Promoción</legend>
          <div class="campo campo--ancho">
            <label class="campo__etiqueta" for="nombre">Nombre</label>
            <input class="control" id="nombre" name="nombre" type="text" maxlength="40" value="<?= e($v['nombre']) ?>" autocomplete="off" placeholder="Por ejemplo, Martes de majadito"<?= campo_error($errores, 'nombre', 'nombre-ayuda') ?>>
            <p class="campo__ayuda" id="nombre-ayuda">Así se verá en el pedido y en la copia de caja.</p>
            <?= mensaje_error($errores, 'nombre') ?>
          </div>
        </fieldset>

        <fieldset class="grupo-campos">
          <legend class="grupo-campos__titulo">Descuento</legend>
          <div class="campo">
            <label class="campo__etiqueta" for="aplica">Se aplica a</label>
            <select class="control" id="aplica" name="aplica"<?= campo_error($errores, 'aplica') ?>>
              <option value="">Elige un producto o una categoría</option>
              <optgroup label="Una categoría completa">
<?php foreach ($categorias as $c): $clave = 'c:' . (int) $c['id']; ?>
                <option value="<?= e($clave) ?>"<?= $v['aplica'] === $clave ? ' selected' : '' ?>>Categoría <?= e($c['nombre']) ?></option>
<?php endforeach; ?>
              </optgroup>
              <optgroup label="Un producto">
<?php foreach ($productos as $p): $clave = 'p:' . (int) $p['id']; ?>
                <option value="<?= e($clave) ?>"<?= $v['aplica'] === $clave ? ' selected' : '' ?>><?= e($p['nombre']) ?> · Bs <?= bs($p['precio']) ?></option>
<?php endforeach; ?>
              </optgroup>
            </select>
            <?= mensaje_error($errores, 'aplica') ?>
          </div>
          <div class="campo">
            <label class="campo__etiqueta" for="valor">Descuento</label>
            <div class="control-grupo">
              <input class="control control--numero" id="valor" name="valor" type="text" inputmode="decimal" maxlength="9" value="<?= e($v['valor']) ?>" autocomplete="off"<?= campo_error($errores, 'valor', 'valor-ayuda') ?>>
              <select class="control control-grupo__select" id="valor_tipo" name="valor_tipo" aria-label="Tipo de descuento">
                <option value="porcentaje"<?= $v['valor_tipo'] === 'porcentaje' ? ' selected' : '' ?>>%</option>
                <option value="monto"<?= $v['valor_tipo'] === 'monto' ? ' selected' : '' ?>>Bs</option>
              </select>
            </div>
            <p class="campo__ayuda" id="valor-ayuda">En %, sobre el subtotal de la línea. En Bs, por cada unidad. Nunca deja un producto gratis ni en negativo.</p>
            <?= mensaje_error($errores, 'valor') ?>
          </div>
          <label class="casilla campo--ancho"><input type="checkbox" id="activo" name="activo" value="1"<?= (int) $v['activo'] ? ' checked' : '' ?>><span class="casilla__texto"><span class="casilla__titulo">Activa</span><span class="casilla__ayuda">Se aplica desde ya en el punto de venta. Pausada, no se aplica.</span></span></label>
        </fieldset>

        <div class="formulario__acciones">
          <a class="boton" href="/promociones">Cancelar</a>
          <button class="boton boton--primario" type="submit" id="guardar">Guardar promoción</button>
        </div>
      </form>
    </main>
