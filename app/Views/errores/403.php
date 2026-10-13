<?php /** Acceso denegado, sin datos del módulo. Variables: $nombreModulo (o null si es una acción), $rol */ ?>
    <main class="contenido" id="contenido">
      <section class="aviso-pagina aviso-pagina--denegado" aria-labelledby="t-denegado">
        <span class="aviso-pagina__icono" aria-hidden="true"><?= icono('escudo', 'icono--lg') ?></span>
        <p class="aviso-pagina__codigo">Error 403</p>
        <h2 class="aviso-pagina__titulo" id="t-denegado"><?= $nombreModulo !== null
            ? 'No tienes permiso para entrar a ' . e($nombreModulo)
            : 'No tienes permiso para esta acción' ?></h2>
        <p class="aviso-pagina__texto">Tu rol, <strong><?= e($rol) ?></strong>, no incluye <?= $nombreModulo !== null ? 'este módulo' : 'esta acción' ?>. El intento quedó registrado en la bitácora con tu usuario, la hora y la dirección IP.</p>
        <p class="aviso-pagina__texto">Si lo necesitas para tu trabajo, pídele acceso al administrador.</p>
        <div class="aviso-pagina__acciones">
          <a class="boton boton--primario" href="<?= e(App\Core\Auth::inicio()) ?>"><?= icono('volver') ?> Volver a mi inicio</a>
        </div>
      </section>
    </main>
