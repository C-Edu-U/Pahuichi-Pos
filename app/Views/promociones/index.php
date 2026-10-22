<?php
/**
 * Promociones (HU-46). Variables: $promociones (con el nombre de su producto o categoría),
 * $usos (ventas cobradas en que descontó, por id), $contexto (HU-49: fecha, día, hora y usos de hoy).
 */
use App\Services\PromocionService;

// Estado en este momento: por qué no se aplica, si no se aplica
$estados = [
    'programada' => ['Programada', 'neutra'],
    'vencida' => ['Vencida', 'neutra'],
    'dia' => ['Hoy no aplica', 'neutra'],
    'hora' => ['Fuera de horario', 'neutra'],
    'tope' => ['Tope de hoy alcanzado', 'aviso'],
];
?>
    <main class="contenido" id="contenido">
      <div class="cabeza-pagina">
        <p class="cabeza-pagina__texto">Se aplican solas en el punto de venta al agregar el producto, cuando cumplen su horario y sus condiciones: el descuento se ve como una línea de descuento y el regalo se agrega a Bs 0. Si dos tocan el mismo producto, se aplica la de mayor beneficio.</p>
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
              <th scope="col">Cuándo</th>
              <th scope="col">Condiciones</th>
              <th scope="col" class="col-num">Usos hoy</th>
              <th scope="col">Ahora</th>
              <th scope="col" class="col-accion"><span class="visualmente-oculto">Acciones</span></th>
            </tr>
          </thead>
          <tbody id="filas-promociones">
<?php foreach ($promociones as $p):
    $id = (int) $p['id'];
    $activa = (bool) (int) $p['activo'];
    $motivo = PromocionService::motivoFueraDeVigencia($p, $contexto);
    $hoy = (int) ($contexto['usos'][$id] ?? 0);
?>
            <tr>
              <td><span class="tabla__principal"><?= e($p['nombre']) ?></span><span class="tabla__secundario"><?= $p['tipo'] === 'regalo' ? 'Regalo por compra' : 'Descuento' ?></span></td>
              <td data-etiqueta="Se aplica a"><?= $p['producto_id'] !== null ? e($p['producto']) : 'Categoría ' . e($p['categoria']) ?></td>
              <td data-etiqueta="Beneficio"><?= e(PromocionService::regla($p)) ?></td>
              <td data-etiqueta="Cuándo"><?= e(PromocionService::cuando($p)) ?></td>
              <td data-etiqueta="Condiciones"><?= e(PromocionService::condiciones($p)) ?></td>
              <td class="col-num num" data-etiqueta="Usos hoy"><?= $hoy ?><?= $p['tope_usos_dia'] !== null ? ' de ' . (int) $p['tope_usos_dia'] : '' ?><span class="tabla__secundario"><?= (int) ($usos[$id] ?? 0) ?> en total</span></td>
              <td class="celda-estado" data-etiqueta="Ahora"><?php if (!$activa): ?><span class="insignia insignia--neutra">Pausada</span><?php elseif ($motivo === null): ?><span class="insignia insignia--ok">Aplica ahora</span><?php else: [$texto, $tono] = $estados[$motivo]; ?><span class="insignia insignia--<?= $tono ?>"><?= e($texto) ?></span><?php endif; ?></td>
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

      <p class="promociones__pendiente"><span class="insignia insignia--aviso">Próximamente</span> Combos a precio especial (HU-47), acumulables con prioridad (HU-50) y simulador (HU-52).</p>
    </main>
