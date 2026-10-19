<?php /** Punto de venta con caja abierta. La venta llega en el Sprint 2. Variables: $caja */ ?>
    <main class="contenido" id="contenido">
      <p class="pos__turno" id="pos-turno"><span class="punto-estado" aria-hidden="true"></span>Caja abierta · <?= e(mb_strtolower($caja['turno'])) ?> desde las <?= e(substr($caja['abierta_en'], 11, 5)) ?> · <?= e($caja['cajero']) ?></p>
      <section class="aviso-pagina" aria-labelledby="t-pendiente">
        <span class="aviso-pagina__icono aviso-pagina__icono--obra" aria-hidden="true"><?= icono('obra', 'icono--lg') ?></span>
        <p class="aviso-pagina__codigo">Próximamente</p>
        <h2 class="aviso-pagina__titulo" id="t-pendiente">La venta todavía no está disponible</h2>
        <p class="aviso-pagina__texto">La caja ya está abierta. La cuadrícula de productos, el carrito y el cobro llegan con sus historias.</p>
        <p class="aviso-pagina__datos">Historias: <strong>HU-28 a HU-34, HU-37</strong></p>
      </section>
    </main>
