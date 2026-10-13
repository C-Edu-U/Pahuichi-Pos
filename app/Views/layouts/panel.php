<?php
/**
 * Marco del panel: riel lateral, barra superior y contenido.
 * Variables: $titulo, $modulo, $h1, $contenido; opcionales $css, $js, $claseBody, $rielPlegado.
 */
use App\Core\Config;
use App\Core\Sesion;

$modulos = array_filter(Config::get('modulos'), static fn (array $m): bool => $m['grupo'] > 0);
$aviso = Sesion::tomarFlash('aviso');
$js = $js ?? [];
?>
<!doctype html>
<html lang="es-BO">
<head>
  <?php require __DIR__ . '/_cabeza.php'; ?>
</head>
<body class="panel<?= isset($claseBody) ? ' ' . e($claseBody) : '' ?>" data-modulo="<?= e($modulo ?? '') ?>"<?= !empty($rielPlegado) ? ' data-riel-plegado' : '' ?><?= $aviso ? ' data-aviso="' . e($aviso) . '"' : '' ?>>
  <?php require __DIR__ . '/_sprite.php'; ?>
  <a class="saltar-contenido" href="#contenido">Saltar al contenido</a>

  <aside class="riel" id="riel" aria-label="Menú principal">
    <div class="riel__marca">
      <a class="marca" href="/">
        <span class="marca__sello" aria-hidden="true"><span></span></span>
        <span class="marca__texto">Pahuichi <small>Punto de venta</small></span>
      </a>
      <button class="boton boton--fantasma boton--icono riel__cerrar" type="button" data-cerrar-riel aria-label="Cerrar menú">
        <?= icono('cerrar') ?>
      </button>
    </div>
    <nav class="menu" aria-label="Módulos">
      <ul class="menu__lista" role="list">
<?php $grupo = null; foreach ($modulos as $clave => $m): ?>
<?php if ($grupo !== null && $m['grupo'] !== $grupo): ?>
        <li class="menu__separador" role="separator"></li>
<?php endif; $grupo = $m['grupo']; ?>
        <li class="menu__item" data-modulo="<?= e($clave) ?>"><a class="menu__enlace" href="<?= e($m['ruta']) ?>"<?= ($modulo ?? '') === $clave ? ' aria-current="page"' : '' ?>><?= icono($m['icono']) ?><span class="menu__texto"><?= e($m['texto']) ?></span></a></li>
<?php endforeach; ?>
      </ul>
    </nav>
    <div class="riel__pie">
      <button class="boton boton--fantasma riel__plegar" type="button" data-plegar-riel aria-controls="riel" aria-expanded="true">
        <?= icono('plegar', 'icono-plegar') ?>
        <?= icono('desplegar', 'icono-desplegar') ?>
        <span class="menu__texto">Plegar menú</span>
      </button>
    </div>
  </aside>
  <div class="velo" hidden></div>

  <div class="marco">
    <header class="cabecera">
      <div class="barra">
        <button class="boton boton--fantasma boton--icono barra__menu" type="button" data-abrir-riel aria-controls="riel" aria-expanded="false" aria-label="Abrir menú">
          <?= icono('menu') ?>
        </button>
        <h1 class="barra__titulo"><?= e($h1 ?? $titulo ?? '') ?></h1>
        <div class="barra__estado">
          <p class="estado-sistema estado-sistema--red" id="estado-red" hidden>
            <?= icono('sinRed', 'icono--sm') ?>
            <span>Sin conexión con el servidor</span>
          </p>
        </div>
      </div>
    </header>

<?= $contenido ?>
  </div>

  <script src="/assets/js/app.js"></script>
<?php foreach ($js as $archivo): ?>
  <script src="/assets/js/<?= e($archivo) ?>"></script>
<?php endforeach; ?>
</body>
</html>
