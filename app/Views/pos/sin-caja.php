<?php
/**
 * Sin caja abierta no se vende (HU-21): directo al botón «Abrir caja». Variable: $ajena
 * (HU-20: la caja abierta de otro cajero, o null), en la que este cajero no puede vender.
 */
$ajena ??= null;
?>
    <main class="contenido" id="contenido">
      <section class="pos-sin-caja" id="pos-sin-caja" aria-labelledby="t-sin-caja">
        <span class="pos-sin-caja__icono" aria-hidden="true"><?= icono('candado', 'icono--lg') ?></span>
<?php if ($ajena === null): ?>
        <h2 id="t-sin-caja">No hay una caja abierta</h2>
        <p>Para vender, abre la caja del turno con su fondo inicial.</p>
        <a class="boton boton--primario boton--grande" href="/caja" autofocus>
          <?= icono('abrir') ?>
          Abrir caja
        </a>
<?php else: ?>
        <h2 id="t-sin-caja">La caja abierta es de <?= e($ajena['cajero']) ?></h2>
        <p>Solo <?= e($ajena['cajero']) ?> vende en esa caja. Hay una sola caja abierta a la vez: podrás abrir la tuya cuando se cierre.</p>
        <a class="boton boton--grande" href="/caja" autofocus>
          <?= icono('caja') ?>
          Ver caja
        </a>
<?php endif; ?>
      </section>
    </main>
