<?php
/** Configuración. Variables: $negocio, $fiscal, $turnos, $errores, $leyendas (catálogo) */
use App\Core\Config;
use App\Models\ParametrosFiscales;
use App\Models\Turno;

// El modal de agregar turno se abre solo si el alta falló, para que se vea el error
$errorAlta = isset($errores['nuevo_nombre'])
    || isset($errores['nuevo_inicio'])
    || isset($errores['nuevo_fin'])
    || isset($errores['nuevo_orden'])
    || isset($errores['nuevo_general']);

// Datos de los turnos: resumen del día y avisos
$listaTurnos = $turnos ?? [];
$problemasTurnos = [];
$desdeTurnos = null;
$hastaTurnos = null;
foreach ($listaTurnos as $t) {
    $idTurno = (int) $t['id'];
    if (isset($errores["e{$idTurno}"])) {
        $problemasTurnos[] = $errores["e{$idTurno}"];
    }
    foreach (["t{$idTurno}-nombre", "t{$idTurno}-ini", "t{$idTurno}-fin"] as $campoTurno) {
        if (isset($errores[$campoTurno])) {
            $problemasTurnos[] = $errores[$campoTurno];
        }
    }
    if ($desdeTurnos === null) {
        $desdeTurnos = Turno::hora($t['hora_inicio']);
    }
    $hastaTurnos = Turno::hora($t['hora_fin']);
}
if (isset($errores['eliminar_general'])) {
    $problemasTurnos[] = $errores['eliminar_general'];
}
$resumenTurnos = $listaTurnos === []
    ? 'Todavía no hay turnos: agrega el primero para poder abrir caja.'
    : count($listaTurnos) . ' ' . (count($listaTurnos) === 1 ? 'turno' : 'turnos')
        . ' · de ' . $desdeTurnos . ' a ' . $hastaTurnos;

$pendiente = static function (string $id, string $titulo, string $historias, string $texto): void {
    $tituloSeccion = $titulo;
    require __DIR__ . '/_pendiente.php';
};
?>
    <main class="contenido" id="contenido">
      <div class="config">
        <nav class="config__indice" aria-label="Secciones de configuración">
          <a href="#negocio">Negocio</a>
          <a href="#fiscal">Parámetros fiscales</a>
          <a href="#leyendas">Leyendas</a>
          <a href="#turnos">Turnos</a>
          <a href="#impresoras">Impresoras</a>
          <a href="#caja-cobro">Caja y cobro</a>
          <a href="#seguridad">Seguridad</a>
          <a href="#respaldos">Copias de seguridad</a>
        </nav>

        <div class="config__secciones">
          <form class="panel-bloque config__seccion" id="negocio" method="post" action="/config/negocio" enctype="multipart/form-data" novalidate aria-labelledby="t-negocio">
            <?= csrf_campo() ?>
            <div class="panel-bloque__cabecera">
              <h2 class="panel-bloque__titulo" id="t-negocio">Datos del negocio</h2>
            </div>
            <div class="panel-bloque__cuerpo formulario-rapido__campos">
              <div class="campo">
                <label class="campo__etiqueta" for="n-razon">Razón social</label>
                <input class="control" id="n-razon" name="razon_social" type="text" maxlength="120" value="<?= e($negocio['razon_social']) ?>"<?= campo_error($errores, 'n-razon') ?>>
                <?= mensaje_error($errores, 'n-razon') ?>
              </div>
              <div class="campo">
                <label class="campo__etiqueta" for="n-comercial">Nombre comercial</label>
                <input class="control" id="n-comercial" name="nombre_comercial" type="text" maxlength="80" value="<?= e($negocio['nombre_comercial']) ?>"<?= campo_error($errores, 'n-comercial') ?>>
                <?= mensaje_error($errores, 'n-comercial') ?>
              </div>
              <div class="campo">
                <label class="campo__etiqueta" for="n-nit">NIT</label>
                <input class="control codigo-entrada" id="n-nit" name="nit" type="text" inputmode="numeric" maxlength="13" value="<?= e($negocio['nit']) ?>"<?= campo_error($errores, 'n-nit') ?>>
                <?= mensaje_error($errores, 'n-nit') ?>
              </div>
              <div class="campo">
                <label class="campo__etiqueta" for="n-telefono">Teléfono <span class="campo__opcional">(opcional)</span></label>
                <input class="control" id="n-telefono" name="telefono" type="tel" maxlength="20" value="<?= e($negocio['telefono'] ?? '') ?>"<?= campo_error($errores, 'n-telefono') ?>>
                <?= mensaje_error($errores, 'n-telefono') ?>
              </div>
              <div class="campo campo--ancho">
                <label class="campo__etiqueta" for="n-direccion">Dirección</label>
                <input class="control" id="n-direccion" name="direccion" type="text" maxlength="160" value="<?= e($negocio['direccion']) ?>"<?= campo_error($errores, 'n-direccion') ?>>
                <?= mensaje_error($errores, 'n-direccion') ?>
              </div>
              <div class="campo">
                <label class="campo__etiqueta" for="n-municipio">Municipio</label>
                <input class="control" id="n-municipio" name="municipio" type="text" maxlength="60" value="<?= e($negocio['municipio']) ?>"<?= campo_error($errores, 'n-municipio') ?>>
                <?= mensaje_error($errores, 'n-municipio') ?>
              </div>
              <div class="campo">
                <label class="campo__etiqueta" for="n-logo">Logo <span class="campo__opcional">(opcional)</span></label>
<?php if (!empty($negocio['logo'])): ?>
                <p class="logo-actual"><img src="/negocio/logo" alt="Logo actual del negocio" width="160" height="64"></p>
<?php endif; ?>
                <input class="control control--archivo" id="n-logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"<?= campo_error($errores, 'n-logo', 'n-logo-ayuda') ?>>
                <p class="campo__ayuda" id="n-logo-ayuda">PNG, JPG o WebP de hasta 1 MB.</p>
                <?= mensaje_error($errores, 'n-logo') ?>
<?php if (!empty($negocio['logo'])): ?>
                <label class="casilla"><input type="checkbox" name="quitar_logo" value="1"><span class="casilla__texto"><span class="casilla__titulo">Quitar el logo</span></span></label>
<?php endif; ?>
              </div>
            </div>
            <div class="panel-bloque__pie">
              <button class="boton boton--primario" type="submit">Guardar</button>
            </div>
          </form>

<?php
$vigencia = substr(str_replace(' ', 'T', (string) $fiscal['cufd_vigencia']), 0, 16);
$cufdVigente = !isset($errores['f-vigencia']) && ParametrosFiscales::cufdVigente($fiscal);
?>
          <form class="panel-bloque config__seccion" id="fiscal" method="post" action="/config/fiscal" novalidate aria-labelledby="t-fiscal">
            <?= csrf_campo() ?>
            <div class="panel-bloque__cabecera">
              <h2 class="panel-bloque__titulo" id="t-fiscal">Parámetros fiscales</h2>
              <span class="insignia insignia--neutra">Simulados</span>
            </div>
            <div class="panel-bloque__cuerpo formulario-rapido__campos">
              <div class="campo">
                <label class="campo__etiqueta" for="f-sucursal">Sucursal</label>
                <input class="control control--numero" id="f-sucursal" name="codigo_sucursal" type="text" inputmode="numeric" maxlength="4" value="<?= e($fiscal['codigo_sucursal']) ?>"<?= campo_error($errores, 'f-sucursal', 'f-sucursal-ayuda') ?>>
                <p class="campo__ayuda" id="f-sucursal-ayuda">0 es la casa matriz.</p>
                <?= mensaje_error($errores, 'f-sucursal') ?>
              </div>
              <div class="campo">
                <label class="campo__etiqueta" for="f-pv">Punto de venta</label>
                <input class="control control--numero" id="f-pv" name="codigo_punto_venta" type="text" inputmode="numeric" maxlength="4" value="<?= e($fiscal['codigo_punto_venta']) ?>"<?= campo_error($errores, 'f-pv') ?>>
                <?= mensaje_error($errores, 'f-pv') ?>
              </div>
              <div class="campo campo--ancho">
                <label class="campo__etiqueta" for="f-actividad">Actividad económica</label>
                <input class="control" id="f-actividad" name="actividad_economica" type="text" maxlength="120" value="<?= e($fiscal['actividad_economica']) ?>"<?= campo_error($errores, 'f-actividad') ?>>
                <?= mensaje_error($errores, 'f-actividad') ?>
              </div>
              <div class="campo">
                <label class="campo__etiqueta" for="f-cuis">CUIS</label>
                <input class="control codigo-entrada" id="f-cuis" name="cuis" type="text" maxlength="20" value="<?= e($fiscal['cuis']) ?>" autocomplete="off" spellcheck="false"<?= campo_error($errores, 'f-cuis') ?>>
                <?= mensaje_error($errores, 'f-cuis') ?>
              </div>
              <div class="campo">
                <label class="campo__etiqueta" for="f-modalidad">Modalidad</label>
                <select class="control" id="f-modalidad" name="modalidad"<?= campo_error($errores, 'f-modalidad') ?>>
<?php foreach (ParametrosFiscales::MODALIDADES as $clave => $texto): ?>
                  <option value="<?= e($clave) ?>"<?= $fiscal['modalidad'] === $clave ? ' selected' : '' ?>><?= e($texto) ?></option>
<?php endforeach; ?>
                </select>
                <?= mensaje_error($errores, 'f-modalidad') ?>
              </div>
              <div class="campo campo--ancho">
                <label class="campo__etiqueta" for="f-cufd">CUFD</label>
                <input class="control codigo-entrada" id="f-cufd" name="cufd" type="text" maxlength="120" value="<?= e($fiscal['cufd']) ?>" autocomplete="off" spellcheck="false"<?= campo_error($errores, 'f-cufd') ?>>
                <?= mensaje_error($errores, 'f-cufd') ?>
              </div>
              <div class="campo">
                <label class="campo__etiqueta" for="f-vigencia">Vigencia del CUFD</label>
                <input class="control" id="f-vigencia" name="cufd_vigencia" type="datetime-local" value="<?= e($vigencia) ?>"<?= campo_error($errores, 'f-vigencia', 'f-vigencia-ayuda') ?>>
                <p class="campo__ayuda" id="f-vigencia-ayuda">Si no corresponde al día, no se emiten facturas.</p>
                <?= mensaje_error($errores, 'f-vigencia') ?>
              </div>
              <div class="campo">
                <label class="campo__etiqueta" for="f-control">Código de control</label>
                <input class="control codigo-entrada" id="f-control" name="cufd_codigo_control" type="text" maxlength="40" value="<?= e($fiscal['cufd_codigo_control']) ?>" autocomplete="off" spellcheck="false"<?= campo_error($errores, 'f-control') ?>>
                <?= mensaje_error($errores, 'f-control') ?>
              </div>
              <div class="campo">
                <label class="campo__etiqueta" for="f-inicial">Número inicial de factura</label>
                <input class="control control--numero" id="f-inicial" name="numero_inicial" type="text" inputmode="numeric" maxlength="9" value="<?= e($fiscal['numero_inicial']) ?>"<?= campo_error($errores, 'f-inicial') ?>>
                <?= mensaje_error($errores, 'f-inicial') ?>
              </div>
              <dl class="datos campo--ancho">
                <dt>Estado del CUFD</dt>
                <dd><?= $cufdVigente
                    ? '<span class="insignia insignia--ok">Vigente hoy</span>'
                    : '<span class="insignia insignia--aviso">Vencido: no corresponde al día de hoy</span>' ?></dd>
                <dt>Próxima factura</dt><dd class="num">N° <?= e($fiscal['numero_inicial']) ?></dd>
              </dl>
            </div>
            <div class="panel-bloque__pie">
              <button class="boton boton--primario" type="submit">Guardar</button>
            </div>
          </form>

<?php require __DIR__ . '/_leyendas.php'; ?>

          <section class="panel-bloque config__seccion" id="turnos" data-turnos aria-labelledby="t-turnos">
            <div class="panel-bloque__cabecera">
              <div class="turnos__titulos">
                <h2 class="panel-bloque__titulo" id="t-turnos">Turnos</h2>
                <p class="turnos__resumen" data-resumen-turnos><?= e($resumenTurnos) ?></p>
              </div>
              <button class="boton" type="button" data-abrir-modal="modal-turno">
                <?= icono('mas') ?> Agregar turno
              </button>
            </div>
<?php if ($problemasTurnos !== []): ?>
            <div class="alerta alerta--peligro turnos__alerta" role="alert">
              <?= icono('error') ?>
              <div>
                <p class="alerta__titulo">No se pudo guardar el cambio</p>
                <ul class="alerta__lista">
<?php foreach ($problemasTurnos as $problemaTurno): ?>
                  <li><?= e($problemaTurno) ?></li>
<?php endforeach; ?>
                </ul>
              </div>
            </div>
<?php endif; ?>
            <form class="panel-bloque__cuerpo turnos" id="form-turnos" method="post" action="/config/turnos" novalidate>
              <?= csrf_campo() ?>
<?php foreach ($listaTurnos as $t): $id = (int) $t['id']; ?>
              <fieldset class="turno" data-turno="<?= $id ?>" data-nombre="<?= e($t['nombre']) ?>" data-activo="<?= (int) $t['activo'] ?>">
                <legend class="visualmente-oculto">Turno <?= e($t['nombre']) ?></legend>
                <div class="turno__cabecera">
                  <label class="visualmente-oculto" for="t<?= $id ?>-nombre">Nombre del turno</label>
                  <input class="turno__nombre" id="t<?= $id ?>-nombre" name="turnos[<?= $id ?>][nombre]" type="text" maxlength="30" size="<?= max(6, min(20, mb_strlen($t['nombre']) + 2)) ?>" value="<?= e($t['nombre']) ?>" data-nombre-campo autocomplete="off"<?= campo_error($errores, "t{$id}-nombre") ?>>
                  <span class="turno__horario" data-horario><?= e(Turno::horario($t)) ?></span>
                  <span class="insignia insignia--neutra turno__orden" title="Orden en la lista"><?= (int) $t['orden'] ?>.º</span>
                </div>
                <?= mensaje_error($errores, "t{$id}-nombre") ?>
                <div class="campo">
                  <label class="campo__etiqueta" for="t<?= $id ?>-ini">Desde</label>
                  <input class="control" id="t<?= $id ?>-ini" name="turnos[<?= $id ?>][inicio]" type="time" value="<?= e(Turno::hora($t['hora_inicio'])) ?>"<?= campo_error($errores, "t{$id}-ini") ?>>
                  <?= mensaje_error($errores, "t{$id}-ini") ?>
                </div>
                <div class="campo">
                  <label class="campo__etiqueta" for="t<?= $id ?>-fin">Hasta</label>
                  <input class="control" id="t<?= $id ?>-fin" name="turnos[<?= $id ?>][fin]" type="time" value="<?= e(Turno::hora($t['hora_fin'])) ?>"<?= campo_error($errores, "t{$id}-fin") ?>>
                  <?= mensaje_error($errores, "t{$id}-fin") ?>
                </div>
                <div class="turno__pie">
                  <?= mensaje_error($errores, "e{$id}") ?>
                  <button class="boton boton--icono boton--peligro turno__eliminar" type="button" data-eliminar-turno="<?= $id ?>" data-nombre-turno="<?= e($t['nombre']) ?>" aria-label="Eliminar el turno <?= e($t['nombre']) ?>">
                    <?= icono('basura') ?>
                  </button>
                </div>
              </fieldset>
<?php endforeach; ?>
              <p class="config__nota">Los turnos se eligen al abrir caja según lo configurado aquí. Un turno no abre si la caja del anterior no está cerrada y arqueada. Cualquier turno se puede editar o eliminar, sin importar la hora. El turno siempre termina después de empezar: 23:59 es hasta medianoche.</p>
            </form>
            <div class="panel-bloque__pie">
              <button class="boton boton--primario" type="submit" form="form-turnos">Guardar cambios</button>
            </div>

            <dialog class="modal modal--turno" id="modal-turno" aria-labelledby="t-modal-turno"<?= $errorAlta ? ' data-modal-al-cargar' : '' ?>>
              <form method="post" action="/config/turnos" novalidate>
                <?= csrf_campo() ?>
                <div class="modal__cuerpo">
                  <h2 class="modal__titulo" id="t-modal-turno">Agregar turno</h2>
                  <p class="modal__texto">El turno se elige al abrir caja. Escribe el nombre y el horario; la clave y el orden los pone el sistema.</p>

                  <div class="campo">
                    <label class="campo__etiqueta" for="nuevo_nombre">Nombre</label>
                    <input class="control" id="nuevo_nombre" name="nuevo_nombre" type="text" maxlength="30" value="<?= e($viejo['nuevo_nombre'] ?? '') ?>"<?= campo_error($errores, 'nuevo_nombre') ?>>
                    <?= mensaje_error($errores, 'nuevo_nombre') ?>
                  </div>

                  <div class="turno__campos">
                    <div class="campo">
                      <label class="campo__etiqueta" for="nuevo_inicio">Desde</label>
                      <input class="control" id="nuevo_inicio" name="nuevo_inicio" type="time" value="<?= e($viejo['nuevo_inicio'] ?? '') ?>"<?= campo_error($errores, 'nuevo_inicio') ?>>
                      <?= mensaje_error($errores, 'nuevo_inicio') ?>
                    </div>
                    <div class="campo">
                      <label class="campo__etiqueta" for="nuevo_fin">Hasta</label>
                      <input class="control" id="nuevo_fin" name="nuevo_fin" type="time" value="<?= e($viejo['nuevo_fin'] ?? '') ?>"<?= campo_error($errores, 'nuevo_fin') ?>>
                      <p class="campo__ayuda">23:59 es hasta medianoche.</p>
                      <?= mensaje_error($errores, 'nuevo_fin') ?>
                    </div>
                  </div>

                  <div class="campo">
                    <label class="campo__etiqueta" for="nuevo_orden">Orden</label>
                    <input class="control control--numero" id="nuevo_orden" name="nuevo_orden" type="text" inputmode="numeric" value="<?= e($viejo['nuevo_orden'] ?? $ordenTurno) ?>" readonly<?= campo_error($errores, 'nuevo_orden', 'nuevo_orden-ayuda') ?>>
                    <p class="campo__ayuda" id="nuevo_orden-ayuda">Lo pone el sistema: el turno nuevo entra al final de la lista.</p>
                    <?= mensaje_error($errores, 'nuevo_orden') ?>
                  </div>

                  <?= isset($errores['nuevo_general']) ? '<p class="alerta alerta--peligro" role="alert">' . icono('error') . '<span>' . e($errores['nuevo_general']) . '</span></p>' : '' ?>
                </div>
                <div class="modal__acciones">
                  <button class="boton" type="button" data-cerrar-modal>Cancelar</button>
                  <button class="boton boton--primario" type="submit" name="agregar" value="1">Agregar turno</button>
                </div>
              </form>
            </dialog>

            <dialog class="modal" id="modal-eliminar-turno" aria-labelledby="t-modal-eliminar">
              <form method="post" action="/config/turnos" novalidate>
                <?= csrf_campo() ?>
                <div class="modal__cuerpo">
                  <h2 class="modal__titulo" id="t-modal-eliminar">Eliminar turno</h2>
                  <p class="modal__texto">Se va a eliminar el turno <strong id="turno-eliminar-nombre"></strong>. Solo se puede eliminar si no tiene cajas registradas.</p>
                  <input type="hidden" name="eliminar" id="turno-eliminar-id" value="">
                </div>
                <div class="modal__acciones">
                  <button class="boton" type="button" data-cerrar-modal>Cancelar</button>
                  <button class="boton boton--peligro" type="submit">Eliminar turno</button>
                </div>
              </form>
            </dialog>
          </section>

<?php $pendiente('impresoras', 'Impresoras', 'HU-12', 'Ancho de 76 o 58 mm, columnas y qué ticket imprime cada una.'); ?>

<?php $pendiente('caja-cobro', 'Caja y cobro', 'HU-26, HU-38', 'Umbral de la observación obligatoria en el cierre (Bs 10) y monto fijo del QR del día (Bs 0,50).'); ?>

          <section class="panel-bloque config__seccion" id="seguridad" aria-labelledby="t-seguridad">
            <div class="panel-bloque__cabecera">
              <h2 class="panel-bloque__titulo" id="t-seguridad">Seguridad</h2>
            </div>
            <dl class="resumen">
              <div class="resumen__fila"><dt>Cierre de sesión sin actividad</dt><dd class="num"><?= e(Config::get('app.sesion.inactividad_min')) ?> min (no aplica al cajero)</dd></div>
              <div class="resumen__fila"><dt>Bloqueo tras</dt><dd class="num"><?= e(Config::get('app.sesion.intentos_max')) ?> intentos fallidos seguidos</dd></div>
              <div class="resumen__fila"><dt>Duración del bloqueo</dt><dd class="num"><?= e(Config::get('app.sesion.bloqueo_min')) ?> min</dd></div>
            </dl>
          </section>

<?php $pendiente('respaldos', 'Copias de seguridad', 'HU-07', 'Copia diaria de la base con 30 días de retención y restauración probada.'); ?>
        </div>
      </div>
    </main>
