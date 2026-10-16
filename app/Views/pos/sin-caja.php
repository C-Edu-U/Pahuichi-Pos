<?php /** Sin caja abierta no se vende (HU-21): directo al botón «Abrir caja». */ ?>
    <main class="contenido" id="contenido">
      <section class="pos-sin-caja" id="pos-sin-caja" aria-labelledby="t-sin-caja">
        <span class="pos-sin-caja__icono" aria-hidden="true"><?= icono('candado', 'icono--lg') ?></span>
        <h2 id="t-sin-caja">No hay una caja abierta</h2>
        <p>Para vender, abre la caja del turno con su fondo inicial.</p>
        <a class="boton boton--primario boton--grande" href="/caja" autofocus>
          <?= icono('abrir') ?>
          Abrir caja
        </a>
      </section>
    </main>
