<?php
/** <head> común. Variables: $titulo, $css (hojas extra). */
$css = $css ?? [];
?>
<meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo ?? 'Pahuichi POS') ?> · Pahuichi POS</title>
  <meta name="theme-color" content="#13603e">
  <link rel="manifest" href="/manifest.json">
  <link rel="icon" href="/assets/img/icono.svg" type="image/svg+xml">
  <link rel="preload" href="/assets/fonts/archivo-variable-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="/assets/css/tokens.css">
  <link rel="stylesheet" href="/assets/css/base.css">
  <link rel="stylesheet" href="/assets/css/components.css">
  <link rel="stylesheet" href="/assets/css/layout.css">
  <link rel="stylesheet" href="/assets/css/pages.css">
<?php foreach ($css as $hoja): ?>
  <link rel="stylesheet" href="/assets/css/<?= e($hoja) ?>">
<?php endforeach; ?>
  <link rel="stylesheet" href="/assets/css/print.css" media="print">
