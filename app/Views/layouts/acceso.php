<?php
/** Pantallas de acceso (login, cambio de contraseña). Variables: $titulo, $contenido, $js */
$js = $js ?? [];
?>
<!doctype html>
<html lang="es-BO">
<head>
  <?php require __DIR__ . '/_cabeza.php'; ?>
</head>
<body class="acceso">
  <?php require __DIR__ . '/_sprite.php'; ?>
  <a class="saltar-contenido" href="#contenido">Saltar al formulario</a>

  <aside class="acceso__marca" aria-label="Pahuichi POS">
    <p class="acceso__logo">
      <span class="marca__sello marca__sello--grande" aria-hidden="true"><span></span></span>
      <span>Pahuichi</span>
    </p>
    <div class="acceso__lema">
      <p class="acceso__lema-titulo">Punto de venta</p>
      <p class="acceso__lema-texto">Caja, cobros y facturas del restaurante.</p>
    </div>
    <div class="acceso__bandera" aria-hidden="true"><span></span><span></span><span></span></div>
  </aside>

  <main class="acceso__panel" id="contenido">
    <div class="acceso__caja">
<?= $contenido ?>
    </div>
  </main>

  <script src="/assets/js/app.js"></script>
<?php foreach ($js as $archivo): ?>
  <script src="/assets/js/<?= e($archivo) ?>"></script>
<?php endforeach; ?>
</body>
</html>
