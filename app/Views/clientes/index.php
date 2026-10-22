<?php $v = $viejo; ?>
<main class="contenido" id="contenido">
  <div class="cabeza-pagina">
    <p class="cabeza-pagina__texto">Clientes con NIT o CI: el punto de venta los autocompleta. Sin datos, la factura sale a nombre de S/N con NIT 0.</p>
    <div class="cabeza-pagina__acciones"><button class="boton boton--primario" type="button" data-alternar="form-cliente" aria-expanded="<?= isset($errorCliente) ? 'true' : 'false' ?>" aria-controls="form-cliente"><?= icono('usuarioMas') ?>Nuevo cliente</button></div>
  </div>
  <form class="panel-bloque formulario-rapido" id="form-cliente" action="/clientes" method="post"<?= isset($errorCliente) ? '' : ' hidden' ?> aria-labelledby="t-nuevo-cliente">
    <?= csrf_campo() ?>
    <div class="panel-bloque__cabecera"><h2 class="panel-bloque__titulo" id="t-nuevo-cliente">Nuevo cliente</h2></div>
    <div class="panel-bloque__cuerpo formulario-rapido__campos">
      <?php if (isset($errorCliente)): ?><p class="campo__error campo--ancho" role="alert"><?= e($errorCliente) ?></p><?php endif; ?>
      <div class="campo"><label class="campo__etiqueta" for="c-tipo">Tipo de documento</label><select class="control" id="c-tipo" name="tipo"><option value="NIT">NIT</option><option value="CI"<?= ($v['tipo'] ?? '') === 'CI' ? ' selected' : '' ?>>Cédula de identidad (CI)</option></select></div>
      <div class="campo"><label class="campo__etiqueta" for="c-numero">NIT o CI</label><input class="control" id="c-numero" name="doc" type="text" maxlength="26" required autocomplete="off" value="<?= e(is_string($v['doc'] ?? null) ? $v['doc'] : '') ?>"><p class="campo__ayuda">Si tu CI tiene complemento, escríbelo con guion: 1234567-1F.</p></div>
      <div class="campo campo--ancho"><label class="campo__etiqueta" for="c-nombre">Nombre o razón social</label><input class="control" id="c-nombre" name="nombre" type="text" minlength="2" maxlength="120" required value="<?= e(is_string($v['nombre'] ?? null) ? $v['nombre'] : '') ?>"></div>
    </div>
    <div class="panel-bloque__pie"><button class="boton boton--primario" type="submit">Guardar cliente</button><button class="boton" type="button" data-alternar="form-cliente">Cancelar</button></div>
  </form>
  <form class="herramientas herramientas--simple" method="get" action="/clientes" role="search"><div class="control-grupo herramientas__busqueda"><label class="visualmente-oculto" for="c-buscar">Buscar cliente</label><input class="control" id="c-buscar" name="q" type="search" maxlength="120" placeholder="Nombre, NIT o CI" value="<?= e($q) ?>"></div><button class="boton" type="submit">Buscar</button></form>
  <p class="resultado"><?= count($clientes) ?> clientes<?= count($clientes) === 100 ? ' (primeros 100; filtra para encontrar uno)' : '' ?></p>
  <div class="tabla-contenedor"><table class="tabla tabla--fichas"><caption class="visualmente-oculto">Clientes registrados</caption><thead><tr><th>Nombre o razón social</th><th>Documento</th></tr></thead><tbody>
    <?php foreach ($clientes as $c): ?><tr><td data-etiqueta="Nombre"><span class="tabla__principal"><?= e($c['nombre']) ?></span></td><td data-etiqueta="Documento"><span class="insignia insignia--neutra"><?= e($c['tipo']) ?></span> <span class="num"><?= e($c['doc']) ?></span></td></tr><?php endforeach; ?>
  </tbody></table><?php if (!$clientes): ?><div class="vacio"><h2 class="vacio__titulo">No hay clientes que mostrar</h2><p class="vacio__texto">Busca otro documento o registra un cliente nuevo.</p></div><?php endif; ?></div>
</main>
