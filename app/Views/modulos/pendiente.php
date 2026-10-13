<?php /** Módulo aún no construido. Variables: $m (config del módulo) */ ?>
    <main class="contenido" id="contenido">
      <section class="aviso-pagina" aria-labelledby="t-pendiente">
        <span class="aviso-pagina__icono aviso-pagina__icono--obra" aria-hidden="true"><?= icono('obra', 'icono--lg') ?></span>
        <p class="aviso-pagina__codigo">Próximamente</p>
        <h2 class="aviso-pagina__titulo" id="t-pendiente"><?= e($m['texto']) ?> todavía no está disponible</h2>
        <p class="aviso-pagina__texto"><?= e($m['pendiente']['texto']) ?></p>
        <p class="aviso-pagina__datos">Historias: <strong><?= e($m['pendiente']['historias']) ?></strong></p>
      </section>
    </main>
