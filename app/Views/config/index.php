<?php
/** Configuración. Variables: $negocio, $fiscal, $turnos, $errores, $leyendas (cantidad activa) */
use App\Core\Config;
use App\Models\ParametrosFiscales;
use App\Models\Turno;

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

<?php $pendiente('leyendas', 'Leyendas de la Ley N° 453', 'HU-10', "Hay {$leyendas} leyendas cargadas. Cada factura tomará una al azar entre las activas."); ?>

          <form class="panel-bloque config__seccion" id="turnos" method="post" action="/config/turnos" novalidate aria-labelledby="t-turnos">
            <?= csrf_campo() ?>
            <div class="panel-bloque__cabecera">
              <h2 class="panel-bloque__titulo" id="t-turnos">Turnos</h2>
            </div>
            <div class="panel-bloque__cuerpo turnos">
<?php foreach ($turnos as $t): $id = (int) $t['id']; ?>
              <fieldset class="turno">
                <legend><?= e($t['nombre']) ?></legend>
                <div class="campo campo--ancho">
                  <label class="campo__etiqueta" for="eliminar-turno-<?= $id ?>">
                    <input class="casilla__input" id="eliminar-turno-<?= $id ?>" name="turnos_eliminar[]" type="checkbox" value="<?= $id ?>">
                    Eliminar este turno
                  </label>
                  <p class="campo__ayuda">Solo se puede eliminar si no tiene cajas hoy y no está en curso.</p>
                </div>
                <div class="campo">
                  <label class="campo__etiqueta" for="t<?= $id ?>-ini">Desde</label>
                  <input class="control" id="t<?= $id ?>-ini" name="turnos[<?= $id ?>][inicio]" type="time" value="<?= e(Turno::horaControl($t['hora_inicio'])) ?>"<?= campo_error($errores, "t{$id}-ini") ?>>
                  <?= mensaje_error($errores, "t{$id}-ini") ?>
                </div>
                <div class="campo">
                  <label class="campo__etiqueta" for="t<?= $id ?>-fin">Hasta</label>
                  <input class="control" id="t<?= $id ?>-fin" name="turnos[<?= $id ?>][fin]" type="time" value="<?= e(Turno::horaControl($t['hora_fin'])) ?>"<?= campo_error($errores, "t{$id}-fin", "t{$id}-fin-ayuda") ?>>
                  <p class="campo__ayuda" id="t<?= $id ?>-fin-ayuda">23:59 es hasta medianoche.</p>
                  <?= mensaje_error($errores, "t{$id}-fin") ?>
                </div>
<?php if (Turno::estaEnCurso($id)): ?>
                <p class="campo__ayuda campo__ayuda--aviso">Este turno está en curso ahora: su horario no se puede editar hasta que termine.</p>
<?php endif; ?>
              </fieldset>
<?php endforeach; ?>

              <fieldset class="turno turno--nuevo">
                <legend>Agregar turno</legend>
                <div class="campo">
                  <label class="campo__etiqueta" for="nueva_clave">Clave (p. ej. "madrugada")</label>
                  <input class="control" id="nueva_clave" name="nueva_clave" type="text" maxlength="20" value="<?= e($viejo['nueva_clave'] ?? '') ?>"<?= campo_error($errores, 'nueva_clave') ?>>
                  <?= mensaje_error($errores, 'nueva_clave') ?>
                </div>
                <div class="campo">
                  <label class="campo__etiqueta" for="nuevo_nombre">Nombre</label>
                  <input class="control" id="nuevo_nombre" name="nuevo_nombre" type="text" maxlength="30" value="<?= e($viejo['nuevo_nombre'] ?? '') ?>"<?= campo_error($errores, 'nuevo_nombre') ?>>
                  <?= mensaje_error($errores, 'nuevo_nombre') ?>
                </div>
                <div class="campo">
                  <label class="campo__etiqueta" for="nuevo_inicio">Desde</label>
                  <input class="control" id="nuevo_inicio" name="nuevo_inicio" type="time" value="<?= e($viejo['nuevo_inicio'] ?? '') ?>"<?= campo_error($errores, 'nuevo_inicio') ?>>
                  <?= mensaje_error($errores, 'nuevo_inicio') ?>
                </div>
                <div class="campo">
                  <label class="campo__etiqueta" for="nuevo_fin">Hasta</label>
                  <input class="control" id="nuevo_fin" name="nuevo_fin" type="time" value="<?= e($viejo['nuevo_fin'] ?? '') ?>"<?= campo_error($errores, 'nuevo_fin') ?>>
                  <p class="campo__ayuda" id="nuevo_fin-ayuda">23:59 es hasta medianoche.</p>
                  <?= mensaje_error($errores, 'nuevo_fin') ?>
                </div>
                <div class="campo">
                  <label class="campo__etiqueta" for="nuevo_orden">Orden</label>
                  <input class="control control--numero" id="nuevo_orden" name="nuevo_orden" type="text" inputmode="numeric" value="<?= e($viejo['nuevo_orden'] ?? '') ?>"<?= campo_error($errores, 'nuevo_orden') ?>>
                  <?= mensaje_error($errores, 'nuevo_orden') ?>
                </div>
                <?= isset($errores['nuevo_general']) ? '<p class="alerta alerta--peligro campo--ancho" role="alert">' . e($errores['nuevo_general']) . '</p>' : '' ?>
              </fieldset>
              <p class="config__nota">Un turno no abre si la caja del anterior no está cerrada y arqueada. Un turno en curso no se puede editar ni eliminar.</p>
            </div>
            <div class="panel-bloque__pie">
              <button class="boton boton--primario" type="submit">Guardar cambios</button>
              <button class="boton boton--fantasma" type="submit" name="accion" value="agregar">Agregar turno</button>
              <button class="boton boton--peligro" type="submit" name="accion" value="eliminar" onclick="return confirm('¿Eliminar los turnos marcados?')">Eliminar marcados</button>
            </div>
          </form>

<?php
$pendiente('impresoras', 'Impresoras', 'HU-12', 'Ancho de 76 o 58 mm, columnas y qué ticket imprime cada una.');
?>

          <form class="panel-bloque config__seccion" id="caja-cobro" method="post" action="/config/caja-cobro" novalidate aria-labelledby="t-caja-cobro">
            <?= csrf_campo() ?>
            <div class="panel-bloque__cabecera">
              <h2 class="panel-bloque__titulo" id="t-caja-cobro">Caja y cobro</h2>
            </div>
            <div class="panel-bloque__cuerpo formulario-rapido__campos">
              <div class="campo">
                <label class="campo__etiqueta" for="f-umbral">Umbral de la observación obligatoria</label>
                <div class="control-grupo">
                  <span class="control-grupo__prefijo" aria-hidden="true">Bs</span>
                  <input class="control control--numero" id="f-umbral" name="umbral_observacion" type="text" inputmode="decimal" value="<?= e(bs($umbralObservacion)) ?>"<?= campo_error($errores, 'f-umbral', 'f-umbral-ayuda') ?>>
                </div>
                <p class="campo__ayuda" id="f-umbral-ayuda">Si la diferencia del arqueo llega a este monto, el cajero tiene que escribir una observación antes de cerrar.</p>
                <?= mensaje_error($errores, 'f-umbral') ?>
              </div>
            </div>
            <div class="panel-bloque__pie">
              <button class="boton boton--primario" type="submit">Guardar</button>
            </div>
            <div class="panel-bloque__cuerpo">
              <p class="config__nota">El monto fijo del QR del día llega con HU-38.</p>
            </div>
          </form>

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
