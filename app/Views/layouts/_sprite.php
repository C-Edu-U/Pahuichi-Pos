<?php
/** Sprite de íconos en línea: los <use href="#i-..."> lo referencian sin pedir otro archivo. */
$sprite = preg_replace('/^<!--.*?-->\s*/s', '', (string) file_get_contents(\App\Core\App::raiz() . '/public/assets/img/iconos.svg'));
?>
<div class="sprite-iconos"><?= $sprite ?></div>
