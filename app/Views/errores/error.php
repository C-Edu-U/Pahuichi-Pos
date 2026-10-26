<?php /** Variables: $titulo, $texto, $estado, $detalle */ ?>
  <main class="contenido contenido--suelto" id="contenido">
    <section class="aviso-pagina" aria-labelledby="t-error">
      <span class="aviso-pagina__icono" aria-hidden="true"><?= icono($estado >= 500 ? 'alerta' : 'info', 'icono--lg') ?></span>
      <p class="aviso-pagina__codigo">Error <?= e($estado) ?></p>
      <h1 class="aviso-pagina__titulo" id="t-error"><?= e($titulo) ?></h1>
      <p class="aviso-pagina__texto"><?= e($texto) ?></p>
<?php if ($detalle !== ''): ?>
      <p class="aviso-pagina__texto codigo">Detalle para desarrollo: <?= e($detalle) ?></p>
<?php endif; ?>
      <div class="aviso-pagina__acciones">
        <a class="boton boton--primario" href="/"><?= icono('volver') ?> Ir al inicio</a>
      </div>
    </section>
  </main>
