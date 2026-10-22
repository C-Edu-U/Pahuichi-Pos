<?php
/**
 * Promociones (HU-46). Variables: $promociones (con el nombre de su producto o categoría),
 * $usos (veces que descontó en ventas cobradas, por id).
 */
use App\Services\PromocionService;
?>
    <main class="contenido" id="contenido">
      <div class="cabeza-pagina">
        <p class="cabeza-pagina__texto">Se aplican solas en el punto de venta al agregar el producto: el descuento se ve como una línea de descuento y el regalo se agrega a Bs 0. Si dos tocan el mismo producto, se aplica la de mayor beneficio.</p>
        <div class="cabeza-pagina__acciones">
          <a class="boton boton--primario" href="/promociones/nueva">
            <?= icono('mas') ?>
            Nueva promoción
          </a>
        </div>
      </div>

<?php if ($promociones === []): ?>
      <div class="vacio">
        <?= icono('promocion', 'icono--lg vacio__icono') ?>
        <h2 class="vacio__titulo">Todavía no hay promociones</h2>
        <p class="vacio__texto">Crea un descuento por producto o por categoría, en % o en Bs por unidad.</p>
      </div>
<?php else: ?>
      <div class="tabla-contenedor">
        <table class="tabla tabla--fichas">
          <caption class="visualmente-oculto">Promociones</caption>
          <thead>
            <tr>
              <th scope="col">Promoción</th>
              <th scope="col">Se aplica a</th>
              <th scope="col">Beneficio</th>
              <th scope="col" class="col-num">Usos</th>
              <th scope="col">Estado</th>
              <th scope="col" class="col-accion"><span class="visualmente-oculto">Acciones</span></th>
            </tr>
          </thead>
          <tbody id="filas-promociones">
<?php foreach ($promociones as $p): $id = (int) $p['id']; $activa = (bool) (int) $p['activo']; ?>
            <tr>
              <td><span class="tabla__principal"><?= e($p['nombre']) ?></span><span class="tabla__secundario"><?= $p['tipo'] === 'regalo' ? 'Regalo por compra' : 'Descuento' ?></span></td>
              <td data-etiqueta="Se aplica a"><?= $p['producto_id'] !== null ? e($p['producto']) : 'Categoría ' . e($p['categoria']) ?></td>
              <td data-etiqueta="Beneficio"><?= e(PromocionService::regla($p)) ?></td>
              <td class="col-num num" data-etiqueta="Usos"><?= (int) ($usos[$id] ?? 0) ?></td>
              <td class="celda-estado" data-etiqueta="Estado"><?= $activa ? '<span class="insignia insignia--ok">Activa</span>' : '<span class="insignia insignia--neutra">Pausada</span>' ?></td>
              <td class="col-accion">
                <div class="acciones-fila">
                  <a class="boton boton--icono" href="/promociones/<?= $id ?>/editar" aria-label="Editar <?= e($p['nombre']) ?>"><?= icono('editar') ?></a>
                  <form method="post" action="/promociones/<?= $id ?>/estado">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="activo" value="<?= $activa ? '0' : '1' ?>">
                    <button class="boton" type="submit" aria-label="<?= ($activa ? 'Pausar ' : 'Activar ') . e($p['nombre']) ?>"><?= $activa ? 'Pausar' : 'Activar' ?></button>
                  </form>
                </div>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>

      <p class="promociones__pendiente"><span class="insignia insignia--aviso">Próximamente</span> Combos a precio especial (HU-47), días y horarios (HU-49), acumulables con prioridad (HU-50) y simulador (HU-52).</p>
    </main>
