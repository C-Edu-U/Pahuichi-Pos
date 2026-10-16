<?php
/**
 * Bitácora (HU-05). Variables: $filas, $total, $pagina, $paginas, $filtro (desde, hasta,
 * usuario, accion, buscar), $filtrando, $usuarios, $opciones (filtro de acción agrupado).
 */
use App\Services\BitacoraTexto;

$enlace = static fn (int $p): string => '/bitacora?' . http_build_query(array_filter($filtro) + ['pagina' => $p]);
$desde = ($pagina - 1) * 50 + 1;
$hasta = min($total, $pagina * 50);
?>
    <main class="contenido" id="contenido">
      <p class="cabeza-pagina__texto bitacora__intro">Quién hizo qué y cuándo. Solo se agregan registros: no se pueden editar ni borrar.</p>

      <form class="filtros" method="get" action="/bitacora" role="search" aria-label="Filtrar bitácora">
        <div class="campo filtros__busqueda">
          <label class="campo__etiqueta" for="b-buscar">Buscar</label>
          <div class="control-grupo">
            <?= icono('buscar', 'control-grupo__icono') ?>
            <input class="control" id="b-buscar" name="buscar" type="search" maxlength="80" value="<?= e($filtro['buscar']) ?>" placeholder="Detalle, usuario o IP" autocomplete="off">
          </div>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="b-accion">Acción</label>
          <select class="control" id="b-accion" name="accion">
            <option value="">Todas</option>
            <option value="sensibles"<?= $filtro['accion'] === 'sensibles' ? ' selected' : '' ?>>Acciones sensibles (anulaciones, precios, caja…)</option>
<?php foreach ($opciones as $grupo => $acciones): ?>
            <optgroup label="<?= e($grupo) ?>">
<?php foreach ($acciones as $valor => $texto): ?>
              <option value="<?= e($valor) ?>"<?= $filtro['accion'] === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
<?php endforeach; ?>
            </optgroup>
<?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="b-usuario">Usuario</label>
          <select class="control" id="b-usuario" name="usuario">
            <option value="">Todos</option>
<?php foreach ($usuarios as $u): ?>
            <option value="<?= (int) $u['id'] ?>"<?= $filtro['usuario'] === (string) $u['id'] ? ' selected' : '' ?>><?= e($u['nombre']) ?></option>
<?php endforeach; ?>
            <option value="ninguno"<?= $filtro['usuario'] === 'ninguno' ? ' selected' : '' ?>>Sin usuario (intentos sin sesión)</option>
          </select>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="b-desde">Desde</label>
          <input class="control" id="b-desde" name="desde" type="date" value="<?= e($filtro['desde']) ?>">
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="b-hasta">Hasta</label>
          <input class="control" id="b-hasta" name="hasta" type="date" value="<?= e($filtro['hasta']) ?>">
        </div>
        <div class="filtros__acciones">
          <button class="boton boton--primario" type="submit"><?= icono('filtro') ?> Filtrar</button>
<?php if ($filtrando): ?>
          <a class="boton" href="/bitacora">Quitar filtros</a>
<?php endif; ?>
        </div>
      </form>

      <p class="resultado"><span class="resultado__cuenta" id="cuenta"><?= $total === 0
          ? 'Ningún registro'
          : ($total === 1 ? '1 registro' : ($paginas > 1 ? "{$desde} a {$hasta} de {$total} registros" : "{$total} registros")) ?></span></p>

<?php if ($filas === []): ?>
      <div class="vacio">
        <?= icono('bitacora', 'icono--lg vacio__icono') ?>
        <h2 class="vacio__titulo"><?= $filtrando ? 'Ningún registro coincide' : 'Todavía no hay registros' ?></h2>
<?php if ($filtrando): ?>
        <p class="vacio__texto">Prueba con otras fechas o con otra acción.</p>
        <a class="boton" href="/bitacora">Quitar filtros</a>
<?php endif; ?>
      </div>
<?php else: ?>
      <div class="tabla-contenedor">
        <table class="tabla tabla--bitacora tabla--fichas">
          <caption class="visualmente-oculto">Bitácora de acciones, de la más nueva a la más vieja</caption>
          <thead>
            <tr>
              <th scope="col">Fecha y hora</th>
              <th scope="col">Usuario</th>
              <th scope="col">Acción</th>
              <th scope="col">Entidad</th>
              <th scope="col">Detalle</th>
              <th scope="col">IP</th>
            </tr>
          </thead>
          <tbody id="filas-bitacora">
<?php foreach ($filas as $f): [$texto, $tono] = BitacoraTexto::accion($f['accion']); ?>
            <tr data-accion="<?= e($f['accion']) ?>">
<?php $momento = new DateTimeImmutable($f['creado_en']); ?>
              <td class="num"><span class="tabla__principal"><?= e($momento->format('d/m/Y')) ?></span><span class="tabla__secundario"><?= e($momento->format('H:i:s')) ?></span></td>
              <td<?= $f['usuario_nombre'] === null ? ' class="celda-vacia"' : '' ?>><?= e($f['usuario_nombre'] ?? 'Sin usuario') ?></td>
              <td><span class="insignia insignia--<?= e($tono) ?>"><?= e($texto) ?></span></td>
              <td><?= e(BitacoraTexto::entidad($f)) ?></td>
              <td><?= e(BitacoraTexto::detalle($f['detalle'])) ?></td>
              <td class="codigo"><?= e($f['ip'] ?? '') ?></td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>

<?php if ($paginas > 1): ?>
      <nav class="paginacion" aria-label="Páginas de la bitácora">
<?php if ($pagina > 1): ?>
        <a class="boton" href="<?= e($enlace($pagina - 1)) ?>" rel="prev"><?= icono('volver') ?> Más nuevos</a>
<?php else: ?>
        <span></span>
<?php endif; ?>
        <span class="paginacion__estado num">Página <?= $pagina ?> de <?= $paginas ?></span>
<?php if ($pagina < $paginas): ?>
        <a class="boton" href="<?= e($enlace($pagina + 1)) ?>" rel="next">Más viejos</a>
<?php else: ?>
        <span></span>
<?php endif; ?>
      </nav>
<?php endif; ?>
<?php endif; ?>
    </main>
