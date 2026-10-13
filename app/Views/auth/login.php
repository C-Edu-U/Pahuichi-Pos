<?php
/**
 * Inicio de sesión. Variables: $modo (pin|clave), $usuario, $errores, $resultado
 * (respuesta de AuthService al fallar), $salida (salida|inactividad), $demo.
 */
$motivo = $resultado['motivo'] ?? null;
$bloqueo = $motivo === 'bloqueado' ? $resultado['hasta']->format('H:i') : null;
$errorPin = $modo === 'pin' && $motivo === 'credenciales';
?>
      <h1 class="acceso__titulo">Iniciar sesión</h1>

      <div class="subnav subnav--botones acceso__modo" role="group" aria-label="Forma de ingreso">
        <button class="subnav__enlace" type="button" data-modo="pin" aria-pressed="<?= $modo === 'pin' ? 'true' : 'false' ?>">
          <?= icono('caja') ?>
          Caja con PIN
        </button>
        <button class="subnav__enlace" type="button" data-modo="clave" aria-pressed="<?= $modo === 'clave' ? 'true' : 'false' ?>">
          <?= icono('candado') ?>
          Contraseña
        </button>
      </div>

<?php if ($motivo === 'credenciales'): ?>
      <div class="alerta alerta--peligro" id="aviso-error" role="alert" data-de-modo="<?= e($modo) ?>">
        <?= icono('error') ?>
        <p class="alerta__titulo"><?= $modo === 'pin' ? 'PIN incorrecto' : 'Usuario o contraseña incorrectos' ?></p>
        <p class="alerta__texto"><?= $resultado['quedan'] === 1
            ? 'Te queda 1 intento. Al siguiente, el acceso se bloquea 15 minutos.'
            : 'Te quedan ' . e($resultado['quedan']) . ' intentos. Al quinto fallo, el acceso se bloquea 15 minutos.' ?></p>
      </div>
<?php elseif ($bloqueo !== null): ?>
      <div class="alerta alerta--aviso" id="aviso-bloqueo" role="alert" data-de-modo="<?= e($modo) ?>">
        <?= icono('candado') ?>
        <p class="alerta__titulo">Acceso bloqueado por 15 minutos</p>
        <p class="alerta__texto">Hubo 5 intentos fallidos seguidos y quedó registrado. Podrás volver a intentar a las <strong class="num"><?= e($bloqueo) ?></strong>, o pide al administrador que te desbloquee.</p>
      </div>
<?php elseif ($motivo === 'cocina'): ?>
      <div class="alerta alerta--info" id="aviso-cocina" role="alert" data-de-modo="clave">
        <?= icono('cocina') ?>
        <p class="alerta__titulo">Cocina no usa el panel</p>
        <p class="alerta__texto">Los pedidos llegan a cocina como comanda impresa. Si necesitas otro acceso, habla con el administrador.</p>
      </div>
<?php elseif ($motivo === 'inactivo'): ?>
      <div class="alerta alerta--aviso" id="aviso-inactivo" role="alert" data-de-modo="clave">
        <?= icono('candado') ?>
        <p class="alerta__titulo">Tu usuario está desactivado</p>
        <p class="alerta__texto">Habla con el administrador para que vuelva a activarlo.</p>
      </div>
<?php elseif ($salida !== null): ?>
      <div class="alerta" id="aviso-salida" role="status">
        <?= icono('ok') ?>
        <p class="alerta__titulo"><?= $salida === 'inactividad' ? 'Sesión cerrada por inactividad' : 'Cerraste sesión' ?></p>
        <p class="alerta__texto"><?= $salida === 'inactividad' ? 'Pasaron 15 minutos sin actividad. Ingresa de nuevo para continuar.' : 'Para volver, ingresa de nuevo.' ?></p>
      </div>
<?php endif; ?>

      <!-- PIN del cajero -->
      <form class="pin<?= $errorPin ? ' pin--error' : '' ?>" id="form-pin" method="post" action="/login" novalidate aria-labelledby="pin-etiqueta"<?= $modo !== 'pin' ? ' hidden' : '' ?><?= $bloqueo !== null && $modo === 'pin' ? ' data-bloqueado' : '' ?>>
        <?= csrf_campo() ?>
        <input type="hidden" name="modo" value="pin">
        <p class="pin__etiqueta" id="pin-etiqueta">Escribe tu PIN de cajero</p>
        <div class="pin__puntos" id="pin-puntos" aria-live="polite" aria-atomic="true">
          <span class="pin__punto"></span><span class="pin__punto"></span><span class="pin__punto"></span>
          <span class="pin__punto"></span><span class="pin__punto pin__punto--opcional"></span><span class="pin__punto pin__punto--opcional"></span>
          <span class="visualmente-oculto" id="pin-cuenta">0 dígitos</span>
        </div>
        <label class="visualmente-oculto" for="pin-entrada">PIN de 4 a 6 dígitos</label>
        <input class="pin__entrada" id="pin-entrada" name="pin" type="password" inputmode="numeric" autocomplete="off" maxlength="6" pattern="[0-9]*">
        <div class="pin__teclado" role="group" aria-label="Teclado numérico">
<?php foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $tecla): ?>
          <button class="pin__tecla" type="button" data-tecla="<?= $tecla ?>"><?= $tecla ?></button>
<?php endforeach; ?>
          <button class="pin__tecla pin__tecla--accion" type="button" data-tecla="borrar" aria-label="Borrar un dígito">
            <?= icono('borrarTecla', 'icono--lg') ?>
          </button>
          <button class="pin__tecla" type="button" data-tecla="0">0</button>
          <button class="pin__tecla pin__tecla--entrar" type="submit" id="pin-entrar" disabled>Entrar</button>
        </div>
      </form>

      <!-- Usuario y contraseña -->
      <form class="acceso__form" id="form-clave" method="post" action="/login" novalidate<?= $modo !== 'clave' ? ' hidden' : '' ?><?= $bloqueo !== null && $modo === 'clave' ? ' data-bloqueado' : '' ?>>
        <?= csrf_campo() ?>
        <input type="hidden" name="modo" value="clave">
        <div class="campo">
          <label class="campo__etiqueta" for="usuario">Usuario</label>
          <input class="control" id="usuario" name="usuario" type="text" value="<?= e($usuario) ?>" autocomplete="username" autocapitalize="none" spellcheck="false"<?= campo_error($errores, 'usuario') ?>>
          <?= mensaje_error($errores, 'usuario') ?>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="clave">Contraseña</label>
          <div class="control-grupo">
            <input class="control" id="clave" name="clave" type="password" autocomplete="current-password"<?= campo_error($errores, 'clave', 'clave-mayus') ?>>
            <button class="boton boton--fantasma boton--icono control-grupo__accion" type="button" id="ver-clave" aria-controls="clave" aria-pressed="false" aria-label="Mostrar contraseña">
              <?= icono('ver', 'icono-ver') ?>
              <?= icono('ocultar', 'icono-ocultar') ?>
            </button>
          </div>
          <p class="campo__ayuda campo__ayuda--aviso" id="clave-mayus" hidden>Las mayúsculas están activadas.</p>
          <?= mensaje_error($errores, 'clave') ?>
        </div>
        <button class="boton boton--primario boton--bloque" type="submit" id="ingresar">Ingresar</button>
      </form>

<?php if ($demo): ?>
      <p class="acceso__demo">
        <span class="ejemplo">Datos de prueba</span>
        <span class="acceso__demo-pin"<?= $modo !== 'pin' ? ' hidden' : '' ?>>Cajero: PIN <strong class="num">4821</strong> o <strong class="num">7350</strong>.</span>
        <span class="acceso__demo-clave"<?= $modo !== 'clave' ? ' hidden' : '' ?>>Usuarios <strong>admin</strong>, <strong>mariela</strong> o <strong>luis</strong>, contraseña <strong>Pahuichi2026</strong>.</span>
      </p>
<?php endif; ?>
