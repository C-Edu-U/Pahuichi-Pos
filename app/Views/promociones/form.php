<?php
/**
 * Alta y edición de promoción (HU-46). Variables: $promocion (null en el alta), $v (valores
 * del formulario: nombre, aplica «p:ID» o «c:ID», valor, valor_tipo, activo y, de HU-49,
 * fecha_desde, fecha_hasta, dias (1 = lunes … 7 = domingo), hora_desde, hora_hasta,
 * cantidad_minima, monto_minimo, tope_usos_dia), $errores,
 * $productos (activos, con su categoría), $categorias (activas).
 */
use App\Services\PromocionService;

$accion = $promocion ? '/promociones/' . (int) $promocion['id'] : '/promociones/nueva';
$etiquetas = ['nombre' => 'Nombre', 'aplica' => 'Producto o categoría', 'valor' => 'Descuento', 'cantidad_compra' => 'Por cada', 'regalo' => 'Regalo', 'fecha_desde' => 'Desde', 'fecha_hasta' => 'Hasta',
    'dias' => 'Días', 'hora_hasta' => 'Horario', 'cantidad_minima' => 'Cantidad mínima', 'monto_minimo' => 'Monto mínimo', 'tope_usos_dia' => 'Tope por día'];
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
          <fieldset class="tipos campo--ancho">
            <legend class="campo__etiqueta">Tipo</legend>
            <label class="tipo-opcion"><input type="radio" name="tipo" value="descuento"<?= $v['tipo'] === 'descuento' ? ' checked' : '' ?>><span class="tipo-opcion__caja"><?= icono('promocion') ?><span class="tipo-opcion__titulo">Descuento</span><span class="tipo-opcion__ayuda">A un producto o categoría, en % o en Bs</span></span></label>
            <label class="tipo-opcion"><input type="radio" name="tipo" value="regalo"<?= $v['tipo'] === 'regalo' ? ' checked' : '' ?>><span class="tipo-opcion__caja"><?= icono('regalo') ?><span class="tipo-opcion__titulo">Regalo por compra</span><span class="tipo-opcion__ayuda">Compra X y se agrega Y gratis</span></span></label>
          </fieldset>
          <div class="campo campo--ancho">
            <label class="campo__etiqueta" for="aplica">Producto o categoría</label>
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
            <p class="campo__ayuda" id="aplica-ayuda">En un descuento, lo que se descuenta. En un regalo, lo que hay que comprar.</p>
            <?= mensaje_error($errores, 'aplica') ?>
          </div>
        </fieldset>

        <fieldset class="grupo-campos" data-tipo-campos="regalo"<?= $v['tipo'] === 'regalo' ? '' : ' hidden' ?>>
          <legend class="grupo-campos__titulo">Regalo por compra</legend>
          <div class="campo">
            <label class="campo__etiqueta" for="cantidad_compra">Por cada</label>
            <input class="control control--numero" id="cantidad_compra" name="cantidad_compra" type="text" inputmode="numeric" maxlength="2" value="<?= e($v['cantidad_compra']) ?>"<?= campo_error($errores, 'cantidad_compra', 'cantidad-compra-ayuda') ?>>
            <p class="campo__ayuda" id="cantidad-compra-ayuda">Unidades compradas por cada regalo.</p>
            <?= mensaje_error($errores, 'cantidad_compra') ?>
          </div>
          <div class="campo">
            <label class="campo__etiqueta" for="regalo">Se regala</label>
            <select class="control" id="regalo" name="regalo"<?= campo_error($errores, 'regalo', 'regalo-ayuda') ?>>
              <option value="">Elige el producto de regalo</option>
<?php foreach ($productos as $p): ?>
              <option value="<?= (int) $p['id'] ?>"<?= $v['regalo'] === (string) $p['id'] ? ' selected' : '' ?>><?= e($p['nombre']) ?> · Bs <?= bs($p['precio']) ?></option>
<?php endforeach; ?>
            </select>
            <p class="campo__ayuda" id="regalo-ayuda">Se agrega solo al pedido a Bs 0. Si está agotado, no se agrega.</p>
            <?= mensaje_error($errores, 'regalo') ?>
          </div>
        </fieldset>

        <fieldset class="grupo-campos" data-tipo-campos="descuento"<?= $v['tipo'] === 'descuento' ? '' : ' hidden' ?>>
          <legend class="grupo-campos__titulo">Descuento</legend>
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
        </fieldset>

        <fieldset class="grupo-campos">
          <legend class="grupo-campos__titulo">Estado</legend>
          <label class="casilla campo--ancho"><input type="checkbox" id="activo" name="activo" value="1"<?= (int) $v['activo'] ? ' checked' : '' ?>><span class="casilla__texto"><span class="casilla__titulo">Activa</span><span class="casilla__ayuda">Se aplica en el punto de venta cuando cumple lo de abajo. Pausada, no se aplica.</span></span></label>
        </fieldset>

        <fieldset class="grupo-campos">
          <legend class="grupo-campos__titulo">Cuándo aplica</legend>
          <div class="campo">
            <label class="campo__etiqueta" for="fecha_desde">Desde <span class="campo__opcional">(opcional)</span></label>
            <input class="control" id="fecha_desde" name="fecha_desde" type="date" value="<?= e($v['fecha_desde']) ?>"<?= campo_error($errores, 'fecha_desde') ?>>
            <?= mensaje_error($errores, 'fecha_desde') ?>
          </div>
          <div class="campo">
            <label class="campo__etiqueta" for="fecha_hasta">Hasta <span class="campo__opcional">(opcional)</span></label>
            <input class="control" id="fecha_hasta" name="fecha_hasta" type="date" value="<?= e($v['fecha_hasta']) ?>"<?= campo_error($errores, 'fecha_hasta', 'fecha-ayuda') ?>>
            <p class="campo__ayuda" id="fecha-ayuda">Las dos fechas se incluyen.</p>
            <?= mensaje_error($errores, 'fecha_hasta') ?>
          </div>
          <fieldset class="dias campo--ancho" id="dias"<?= isset($errores['dias']) ? ' aria-describedby="dias-error"' : '' ?>>
            <legend class="campo__etiqueta">Días</legend>
<?php foreach (PromocionService::DIAS as $n => $dia): ?>
            <label class="dia"><input type="checkbox" name="dias[]" value="<?= $n ?>"<?= in_array($n, $v['dias'], true) ? ' checked' : '' ?>><span><?= e($dia) ?></span></label>
<?php endforeach; ?>
            <?= mensaje_error($errores, 'dias') ?>
          </fieldset>
          <div class="campo">
            <label class="campo__etiqueta" for="hora_desde">Desde las <span class="campo__opcional">(opcional)</span></label>
            <input class="control" id="hora_desde" name="hora_desde" type="time" value="<?= e($v['hora_desde']) ?>">
          </div>
          <div class="campo">
            <label class="campo__etiqueta" for="hora_hasta">Hasta las <span class="campo__opcional">(opcional)</span></label>
            <input class="control" id="hora_hasta" name="hora_hasta" type="time" value="<?= e($v['hora_hasta']) ?>"<?= campo_error($errores, 'hora_hasta', 'hora-ayuda') ?>>
            <p class="campo__ayuda" id="hora-ayuda">Sin horas, vale todo el día. 23:59 es hasta medianoche.</p>
            <?= mensaje_error($errores, 'hora_hasta') ?>
          </div>
        </fieldset>

        <fieldset class="grupo-campos">
          <legend class="grupo-campos__titulo">Condiciones</legend>
          <div class="campo">
            <label class="campo__etiqueta" for="cantidad_minima">Cantidad mínima <span class="campo__opcional">(opcional)</span></label>
            <input class="control control--numero" id="cantidad_minima" name="cantidad_minima" type="text" inputmode="numeric" maxlength="3" value="<?= e($v['cantidad_minima']) ?>"<?= campo_error($errores, 'cantidad_minima', 'cantidad-ayuda') ?>>
            <p class="campo__ayuda" id="cantidad-ayuda">Unidades del producto o de la categoría en el pedido.</p>
            <?= mensaje_error($errores, 'cantidad_minima') ?>
          </div>
          <div class="campo">
            <label class="campo__etiqueta" for="monto_minimo">Monto mínimo del pedido <span class="campo__opcional">(opcional)</span></label>
            <div class="control-grupo">
              <span class="control-grupo__prefijo" aria-hidden="true">Bs</span>
              <input class="control control--numero" id="monto_minimo" name="monto_minimo" type="text" inputmode="decimal" maxlength="12" value="<?= e($v['monto_minimo']) ?>"<?= campo_error($errores, 'monto_minimo', 'monto-ayuda') ?>>
            </div>
            <p class="campo__ayuda" id="monto-ayuda">Subtotal del pedido, antes de descuentos.</p>
            <?= mensaje_error($errores, 'monto_minimo') ?>
          </div>
          <div class="campo">
            <label class="campo__etiqueta" for="tope_usos_dia">Tope de usos por día <span class="campo__opcional">(opcional)</span></label>
            <input class="control control--numero" id="tope_usos_dia" name="tope_usos_dia" type="text" inputmode="numeric" maxlength="4" value="<?= e($v['tope_usos_dia']) ?>" placeholder="Sin tope"<?= campo_error($errores, 'tope_usos_dia', 'tope-ayuda') ?>>
            <p class="campo__ayuda" id="tope-ayuda">Ventas por día en que se aplica. Al llegar al tope, deja de aplicarse hasta mañana.</p>
            <?= mensaje_error($errores, 'tope_usos_dia') ?>
          </div>
        </fieldset>

        <div class="formulario__acciones">
          <a class="boton" href="/promociones">Cancelar</a>
          <button class="boton boton--primario" type="submit" id="guardar">Guardar promoción</button>
        </div>
      </form>
    </main>
