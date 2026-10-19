<?php
/**
 * Cierre ciego (HU-23): conteo por denominación.
 * Variables: $abierta, $conteo (conteo ya guardado o null), $denominaciones, $errores, $viejo.
 * El efectivo esperado no se muestra aquí: el cajero cuenta a ciegas.
 */
use App\Models\Arqueo;
use App\Models\Turno;

$conteoGuardado = $conteo['denominaciones'] ?? [];
$viejo = $viejo ?? [];
$total = Arqueo::totalContado($conteoGuardado);
?>
    <main class="contenido" id="contenido">
      <div class="aviso-pagina aviso-pagina--ciego" role="note">
        <span class="aviso-pagina__icono" aria-hidden="true"><?= icono('ocultar', 'icono--lg') ?></span>
        <p class="aviso-pagina__codigo">Cierre ciego</p>
        <h2 class="aviso-pagina__titulo">Cuenta el efectivo sin ver lo que debería haber</h2>
        <p class="aviso-pagina__texto">El sistema no te muestra el efectivo esperado hasta que termines el conteo. Así el conteo es honesto.</p>
        <p class="aviso-pagina__datos">Caja: turno <strong><?= e($abierta['turno']) ?></strong> · <?= e($abierta['cajero']) ?></p>
      </div>

<?php if ($conteo !== null): ?>
      <section class="panel-bloque" aria-labelledby="t-conteo-guardado">
        <div class="panel-bloque__cabecera">
          <h2 class="panel-bloque__titulo" id="t-conteo-guardado">Conteo registrado</h2>
          <span class="insignia insignia--ok"><?= e(fecha_corta($conteo['creado_en'])) ?></span>
        </div>
        <div class="panel-bloque__cuerpo">
          <dl class="resumen resumen--sin-borde">
            <div class="resumen__fila"><dt>Total contado</dt><dd class="num"><?= bs($total) ?></dd></div>
<?php foreach ($denominaciones as $d): $cantidad = (int) ($conteoGuardado[$d['clave']] ?? 0); ?>
<?php if ($cantidad > 0): ?>
            <div class="resumen__fila"><dt><?= e($d['etiqueta']) ?></dt><dd class="num"><?= e($cantidad) ?> × <?= bs($d['valor']) ?> = <?= bs(Arqueo::subtotal($d['clave'], $cantidad)) ?></dd></div>
<?php endif; ?>
<?php endforeach; ?>
          </dl>
        </div>
        <div class="panel-bloque__pie">
          <a class="boton boton--secundario" href="/caja/cierre">Volver a contar</a>
        </div>
      </section>
<?php endif; ?>

      <form class="panel-bloque" id="form-conteo" method="post" action="/caja/conteo" novalidate>
        <?= csrf_campo() ?>
        <div class="panel-bloque__cabecera">
          <h2 class="panel-bloque__titulo"><?= $conteo !== null ? 'Cambiar el conteo' : 'Conteo por denominación' ?></h2>
        </div>
        <div class="panel-bloque__cuerpo">
<?php if (isset($errores['conteo'])): ?>
          <div class="alerta alerta--peligro" role="alert">
            <?= icono('error') ?>
            <p class="alerta__texto"><?= e($errores['conteo']) ?></p>
          </div>
<?php endif; ?>
          <div class="tabla-contenedor">
            <table class="tabla tabla--fichas">
              <caption class="visualmente-oculto">Cantidad contada por denominación</caption>
              <thead>
                <tr>
                  <th scope="col">Denominación</th>
                  <th scope="col" class="col-num">Cantidad</th>
                  <th scope="col" class="col-num">Subtotal</th>
                </tr>
              </thead>
              <tbody>
<?php foreach ($denominaciones as $d): $clave = $d['clave']; ?>
                <tr>
                  <td><?= e($d['etiqueta']) ?></td>
                  <td class="col-num">
                    <input class="control control--numero control--conteo"
                           id="conteo-<?= e($clave) ?>"
                           name="conteo[<?= e($clave) ?>]"
                           type="text"
                           inputmode="numeric"
                           pattern="[0-9]*"
                           autocomplete="off"
                           data-valor="<?= e(number_format($d['valor'], 2, '.', '')) ?>"
                           value="<?= e((string) ($viejo[$clave] ?? $conteoGuardado[$clave] ?? 0)) ?>">
                  </td>
                  <td class="col-num num" data-subtotal="<?= e($clave) ?>"><?= bs(Arqueo::subtotal($clave, (int) ($viejo[$clave] ?? $conteoGuardado[$clave] ?? 0))) ?></td>
                </tr>
<?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <th scope="row">Total contado</th>
                  <td></td>
                  <td class="col-num num" id="conteo-total"><?= bs($total) ?></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
        <div class="panel-bloque__pie">
          <button class="boton boton--primario" type="submit"><?= icono('check') ?> Guardar el conteo</button>
<?php if ($conteo !== null): ?>
          <a class="boton boton--secundario" href="/caja/arqueo">Ver el arqueo</a>
<?php endif; ?>
          <a class="boton boton--fantasma" href="/caja">Volver a caja</a>
        </div>
      </form>
    </main>
