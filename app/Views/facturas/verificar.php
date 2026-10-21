<main class="contenido" id="contenido">
  <h1>Verificación de factura</h1>
  <p>DOCUMENTO SIN VALOR FISCAL - PROYECTO ACADÉMICO</p>
  <dl><dt>Emisor</dt><dd><?= e($f['snapshot']['negocio']['razon_social']) ?></dd><dt>NIT</dt><dd><?= e($f['snapshot']['negocio']['nit']) ?></dd><dt>Factura N°</dt><dd><?= (int) $f['numero'] ?></dd><dt>CUF</dt><dd><?= e($f['cuf']) ?></dd><dt>Fecha</dt><dd><?= e($f['fecha_emision']) ?></dd><dt>Total Bs</dt><dd><?= bs($f['monto_total']) ?></dd><dt>Estado</dt><dd><?= e($f['estado']) ?></dd></dl>
  <p>DOCUMENTO SIN VALOR FISCAL - PROYECTO ACADÉMICO</p>
</main>
