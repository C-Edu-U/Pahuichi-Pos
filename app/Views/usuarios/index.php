<?php
/**
 * Usuarios. Variables: $usuarios, $yo (id del usuario de la sesión), $errores, $viejo,
 * $credenciales (['nombre', 'claves'] tras crear o resetear, una sola vez).
 */
use App\Models\Usuario;

$acceso = [
    'administrador' => 'Contraseña y PIN de autorización',
    'cajero' => 'PIN y contraseña',
    'mesero' => 'Contraseña',
    'cocina' => 'Sin acceso al panel',
];
$abierto = $errores !== [];
?>
    <main class="contenido" id="contenido">
      <div class="cabeza-pagina">
        <p class="cabeza-pagina__texto">El cajero entra con PIN en la PC de caja; el resto, con usuario y contraseña. Cocina no entra al panel: recibe la comanda impresa.</p>
        <div class="cabeza-pagina__acciones">
          <button class="boton boton--primario" type="button" data-alternar="form-usuario" aria-expanded="<?= $abierto ? 'true' : 'false' ?>" aria-controls="form-usuario">
            <?= icono('usuarioMas') ?>
            Nuevo usuario
          </button>
        </div>
      </div>

      <form class="panel-bloque formulario-rapido" id="form-usuario" method="post" action="/usuarios" novalidate<?= $abierto ? '' : ' hidden' ?> aria-labelledby="t-nuevo-usuario">
        <?= csrf_campo() ?>
        <div class="panel-bloque__cabecera">
          <h2 class="panel-bloque__titulo" id="t-nuevo-usuario">Nuevo usuario</h2>
          <button class="boton boton--fantasma boton--icono" type="button" data-alternar="form-usuario" aria-label="Cerrar formulario">
            <?= icono('cerrar') ?>
          </button>
        </div>
        <div class="panel-bloque__cuerpo formulario-rapido__campos">
          <div class="campo campo--ancho">
            <label class="campo__etiqueta" for="u-nombre">Nombre completo</label>
            <input class="control" id="u-nombre" name="nombre" type="text" maxlength="80" value="<?= e($viejo['nombre']) ?>" autocomplete="off"<?= campo_error($errores, 'u-nombre') ?>>
            <?= mensaje_error($errores, 'u-nombre') ?>
          </div>
          <div class="campo">
            <label class="campo__etiqueta" for="u-usuario">Usuario</label>
            <input class="control" id="u-usuario" name="usuario" type="text" maxlength="20" value="<?= e($viejo['usuario']) ?>" autocomplete="off" autocapitalize="none" spellcheck="false"<?= campo_error($errores, 'u-usuario', 'u-usuario-ayuda') ?>>
            <p class="campo__ayuda" id="u-usuario-ayuda">Minúsculas y números, sin espacios.</p>
            <?= mensaje_error($errores, 'u-usuario') ?>
          </div>
          <div class="campo">
            <label class="campo__etiqueta" for="u-rol">Rol</label>
            <select class="control" id="u-rol" name="rol"<?= campo_error($errores, 'u-rol') ?>>
<?php foreach (Usuario::ROLES as $clave => $texto): ?>
              <option value="<?= e($clave) ?>"<?= $viejo['rol'] === $clave ? ' selected' : '' ?>><?= e($texto) ?></option>
<?php endforeach; ?>
            </select>
            <?= mensaje_error($errores, 'u-rol') ?>
          </div>
          <p class="campo--ancho formulario-rapido__nota" id="u-nota">Se genera una contraseña temporal y, para el cajero, un PIN de 4 dígitos. Se muestran una sola vez.</p>
        </div>
        <div class="panel-bloque__pie">
          <button class="boton boton--primario" type="submit">Crear usuario</button>
          <button class="boton" type="button" data-alternar="form-usuario">Cancelar</button>
        </div>
      </form>

      <div class="herramientas herramientas--simple">
        <div class="herramientas__busqueda control-grupo">
          <?= icono('buscar', 'control-grupo__icono') ?>
          <label class="visualmente-oculto" for="u-buscar">Buscar usuario</label>
          <input class="control" id="u-buscar" type="search" placeholder="Nombre o usuario" data-filtra="filas-usuarios" autocomplete="off">
        </div>
        <div class="herramientas__filtro">
          <label class="visualmente-oculto" for="u-filtro-rol">Rol</label>
          <select class="control" id="u-filtro-rol" data-filtra="filas-usuarios" data-campo="rol">
            <option value="">Todos los roles</option>
<?php foreach (Usuario::ROLES as $clave => $texto): ?>
            <option value="<?= e($clave) ?>"><?= e($texto) ?></option>
<?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="tabla-contenedor">
        <table class="tabla tabla--usuarios tabla--fichas">
          <caption class="visualmente-oculto">Usuarios del sistema</caption>
          <thead>
            <tr>
              <th scope="col">Usuario</th>
              <th scope="col">Rol</th>
              <th scope="col">Estado</th>
              <th scope="col">Último ingreso</th>
              <th scope="col" class="col-accion"><span class="visualmente-oculto">Acciones</span></th>
            </tr>
          </thead>
          <tbody id="filas-usuarios">
<?php foreach ($usuarios as $u):
    $id = (int) $u['id'];
    $esYo = $id === $yo;
    $bloqueo = Usuario::bloqueadoHasta($u);
?>
            <tr data-rol="<?= e($u['rol']) ?>">
              <td><span class="tabla__principal"><?= e($u['nombre']) ?><?= $esYo ? ' <span class="insignia insignia--neutra">Tú</span>' : '' ?></span><span class="tabla__secundario codigo"><?= e($u['usuario']) ?></span></td>
              <td>
<?php if ($esYo): ?>
                <span class="tabla__principal"><?= e(Usuario::ROLES[$u['rol']]) ?></span>
<?php else: ?>
                <form method="post" action="/usuarios/<?= $id ?>/rol" class="form-fila">
                  <?= csrf_campo() ?>
                  <select class="control control--fila" name="rol" aria-label="Rol de <?= e($u['nombre']) ?>" data-enviar-al-cambiar>
<?php foreach (Usuario::ROLES as $clave => $texto): ?>
                    <option value="<?= e($clave) ?>"<?= $u['rol'] === $clave ? ' selected' : '' ?>><?= e($texto) ?></option>
<?php endforeach; ?>
                  </select>
                  <noscript><button class="boton" type="submit">Cambiar</button></noscript>
                </form>
<?php endif; ?>
                <span class="tabla__secundario"><?= e($acceso[$u['rol']]) ?></span>
              </td>
              <td class="celda-estado">
<?php if (!(int) $u['activo']): ?>
                <span class="insignia insignia--neutra">Inactivo</span>
<?php elseif ($bloqueo !== null): ?>
                <span class="insignia insignia--aviso">Bloqueado hasta <?= e($bloqueo->format('H:i')) ?></span>
<?php else: ?>
                <span class="insignia insignia--ok">Activo</span>
<?php endif; ?>
              </td>
              <td class="<?= $u['ultimo_ingreso'] === null ? 'celda-vacia' : 'num' ?>"><?= e(fecha_corta($u['ultimo_ingreso'])) ?></td>
              <td class="col-accion">
                <div class="acciones-fila">
<?php if ($bloqueo !== null): ?>
                  <form method="post" action="/usuarios/<?= $id ?>/desbloquear"><?= csrf_campo() ?><button class="boton" type="submit">Desbloquear</button></form>
<?php endif; ?>
<?php if ($u['rol'] !== 'cocina'): ?>
                  <button class="boton" type="button" data-abrir-modal="modal-clave" data-id="<?= $id ?>" data-nombre="<?= e($u['nombre']) ?>"<?= in_array($u['rol'], ['cajero', 'administrador'], true) ? ' data-con-pin' : '' ?>>Resetear acceso</button>
<?php endif; ?>
<?php if (!$esYo): ?>
                  <form method="post" action="/usuarios/<?= $id ?>/estado">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="activo" value="<?= (int) $u['activo'] ? '0' : '1' ?>">
                    <button class="boton" type="submit"><?= (int) $u['activo'] ? 'Desactivar' : 'Activar' ?></button>
                  </form>
<?php endif; ?>
                </div>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
        <div class="vacio" data-vacio-de="filas-usuarios" hidden>
          <h2 class="vacio__titulo">Ningún usuario coincide</h2>
          <button class="boton" type="button" data-limpiar-filtros="filas-usuarios">Quitar filtros</button>
        </div>
      </div>

      <!-- Reseteo de contraseña o PIN: el botón elegido define qué se resetea -->
      <dialog class="modal" id="modal-clave" aria-labelledby="clave-titulo" aria-describedby="clave-texto">
        <form method="post" action="/usuarios" id="form-reseteo">
          <?= csrf_campo() ?>
          <div class="modal__cuerpo">
            <h2 class="modal__titulo" id="clave-titulo">Resetear el acceso</h2>
            <p class="modal__texto" id="clave-texto">Lo que elijas deja de funcionar y se genera uno temporal, que se muestra una sola vez.</p>
          </div>
          <div class="modal__acciones modal__acciones--columna">
            <button class="boton" type="button" data-cerrar-modal>Cancelar</button>
            <button class="boton" type="submit" id="resetear-pin">Resetear PIN</button>
            <button class="boton boton--primario" type="submit" id="resetear-clave">Resetear contraseña</button>
          </div>
        </form>
      </dialog>

<?php if ($credenciales): ?>
      <!-- Credenciales temporales: se muestran una sola vez -->
      <dialog class="modal" id="modal-credenciales" aria-labelledby="credenciales-titulo" data-abrir-al-cargar>
        <div class="modal__cuerpo">
          <h2 class="modal__titulo" id="credenciales-titulo">Acceso temporal de <?= e($credenciales['nombre']) ?></h2>
          <p class="modal__texto">Entrégalo en persona. Solo se muestra esta vez<?= isset($credenciales['claves']['Contraseña']) ? ' y la contraseña debe cambiarse al ingresar' : '' ?>.</p>
          <dl class="claves-temporales">
<?php foreach ($credenciales['claves'] as $tipo => $valor): ?>
            <div class="clave-temporal"><dt><?= e($tipo) ?></dt><dd><?= e($valor) ?></dd></div>
<?php endforeach; ?>
          </dl>
        </div>
        <div class="modal__acciones">
          <button class="boton boton--primario" type="button" data-cerrar-modal autofocus>Listo</button>
        </div>
      </dialog>
<?php endif; ?>
    </main>
