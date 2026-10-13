<?php /** Pestañas del catálogo. Variable: $actual (productos|categorias) */ ?>
        <nav class="subnav" aria-label="Catálogo">
          <a class="subnav__enlace" href="/catalogo/productos"<?= $actual === 'productos' ? ' aria-current="page"' : '' ?>>Productos</a>
          <a class="subnav__enlace" href="/catalogo/categorias"<?= $actual === 'categorias' ? ' aria-current="page"' : '' ?>>Categorías</a>
        </nav>
