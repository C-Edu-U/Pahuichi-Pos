<?php
/** Configuración. Variables: $negocio, $errores, $leyendas (cantidad activa) */
use App\Core\Config;

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

<?php $pendiente('fiscal', 'Parámetros fiscales', 'HU-09', 'Sucursal, punto de venta, actividad, CUIS, CUFD con vigencia y código de control, número inicial y modalidad.'); ?>

<?php $pendiente('leyendas', 'Leyendas de la Ley N° 453', 'HU-10', "Hay {$leyendas} leyendas cargadas. Cada factura tomará una al azar entre las activas."); ?>

<?php $pendiente('turnos', 'Turnos', 'HU-11', 'Medio día de 11:00 a 16:00 y noche de 18:00 a 24:00.'); ?>

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
