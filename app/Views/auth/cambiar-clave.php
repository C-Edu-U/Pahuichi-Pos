<?php /** Cambio obligatorio de la contraseña temporal. Variables: $errores */ ?>
      <h1 class="acceso__titulo">Elige tu contraseña</h1>
      <p class="acceso__texto">Entraste con una contraseña temporal. Cámbiala para seguir; solo tú la vas a conocer.</p>

      <form class="acceso__form" method="post" action="/cambiar-clave" novalidate>
        <?= csrf_campo() ?>
        <div class="campo">
          <label class="campo__etiqueta" for="nueva">Contraseña nueva</label>
          <input class="control" id="nueva" name="nueva" type="password" autocomplete="new-password" autofocus<?= campo_error($errores, 'nueva', 'nueva-ayuda') ?>>
          <p class="campo__ayuda" id="nueva-ayuda">Al menos 8 caracteres, con letras y números.</p>
          <?= mensaje_error($errores, 'nueva') ?>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="repetir">Repite la contraseña</label>
          <input class="control" id="repetir" name="repetir" type="password" autocomplete="new-password"<?= campo_error($errores, 'repetir') ?>>
          <?= mensaje_error($errores, 'repetir') ?>
        </div>
        <button class="boton boton--primario boton--bloque" type="submit">Guardar y continuar</button>
      </form>

      <form method="post" action="/salir">
        <?= csrf_campo() ?>
        <button class="boton boton--fantasma boton--bloque" type="submit">Cerrar sesión</button>
      </form>
