<?php
/**
 * Reporte de cierre de caja (HU-25): se imprime y se guarda en PDF.
 * Variables: $caja, $conteo, $esperado, $diferencia, $resumen, $movimientos, $negocio.
 */
use App\Models\Arqueo;
use App\Models\Turno;

$diferencia = (float) $diferencia;
$denominaciones = Arqueo::DENOMINACIONES;
$conteoGuardado = $conteo['denominaciones'] ?? [];
?>
    <main class="reporte" id="reporte">
      <div class="reporte__acciones">
        <button class="boton boton--primario" type="button" id="imprimir-reporte"><?= icono('imprimir') ?> Imprimir o guardar en PDF</button>
        <a class="boton boton--fantasma" href="/caja"><?= icono('caja') ?> Volver a caja</a>
      </div>

      <header class="reporte__cabecera">
        <div>
          <h1 class="reporte__titulo">Reporte de cierre de caja</h1>
          <p class="reporte__negocio"><strong><?= e($negocio['nombre_comercial'] ?? 'Pahuichi') ?></strong> · <?= e($negocio['razon_social'] ?? '') ?></p>
          <p class="reporte__datos">NIT <?= e($negocio['nit'] ?? '') ?> · <?= e($negocio['direccion'] ?? '') ?> · <?= e($negocio['municipio'] ?? '') ?></p>
          <?php if (!empty($negocio['telefono'])): ?>
            <p class="reporte__datos">Teléfono: <?= e($negocio['telefono']) ?></p>
          <?php endif; ?>
        </div>
        <div class="reporte__sello">
          <p class="reporte__sello-linea"><?= e(strtoupper((string) ($caja['turno'] ?? ''))) ?></p>
          <p class="reporte__sello-linea"><?= e(Turno::horario($caja)) ?></p>
        </div>
      </header>

      <section class="reporte__bloque">
        <h2 class="reporte__subtitulo">Datos del turno</h2>
        <dl class="reporte__datos-lista">
          <div><dt>Fecha</dt><dd><?= e((new DateTimeImmutable((string) $caja['fecha']))->format('d/m/Y')) ?></dd></div>
          <div><dt>Turno</dt><dd><?= e($caja['turno']) ?></dd></div>
          <div><dt>Horario</dt><dd><?= e(Turno::horario($caja)) ?></dd></div>
          <div><dt>Cajero</dt><dd><?= e($caja['cajero']) ?></dd></div>
          <div><dt>Abierta</dt><dd><?= e((new DateTimeImmutable((string) $caja['abierta_en']))->format('d/m/Y H:i')) ?></dd></div>
          <div><dt>Cerrada</dt><dd><?= !empty($caja['cerrada_en']) ? e((new DateTimeImmutable((string) $caja['cerrada_en']))->format('d/m/Y H:i')) : '—' ?></dd></div>
        </dl>
      </section>

      <section class="reporte__bloque">
        <h2 class="reporte__subtitulo">Conteo por denominación</h2>
        <table class="tabla tabla--fichas">
          <thead>
            <tr>
              <th scope="col">Denominación</th>
              <th scope="col" class="col-num">Cantidad</th>
              <th scope="col" class="col-num">Subtotal</th>
            </tr>
          </thead>
          <tbody>
<?php foreach ($denominaciones as $d): $cantidad = (int) ($conteoGuardado[$d['clave']] ?? 0); ?>
            <tr>
              <td><?= e($d['etiqueta']) ?></td>
              <td class="col-num num"><?= e($cantidad) ?></td>
              <td class="col-num num"><?= bs(Arqueo::subtotal($d['clave'], $cantidad)) ?></td>
            </tr>
<?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th scope="row">Total contado</th>
              <td></td>
              <td class="col-num num"><?= bs($conteo['total_contado']) ?></td>
            </tr>
          </tfoot>
        </table>
      </section>

      <section class="reporte__bloque">
        <h2 class="reporte__subtitulo">Arqueo</h2>
        <dl class="reporte__datos-lista">
          <div><dt>Fondo inicial</dt><dd><?= bs($caja['fondo_inicial']) ?></dd></div>
          <div><dt>Ventas en efectivo</dt><dd><?= bs($resumen['efectivo'] ?? 0) ?></dd></div>
          <div><dt>Ingresos del turno</dt><dd><?= bs($resumen['mov_ingresos'] ?? 0) ?></dd></div>
          <div><dt>Egresos del turno</dt><dd><?= bs($resumen['mov_egresos'] ?? 0) ?></dd></div>
          <div><dt>Efectivo esperado</dt><dd><?= bs($esperado) ?></dd></div>
          <div><dt>Total contado</dt><dd><?= bs($conteo['total_contado']) ?></dd></div>
          <div>
            <dt>Diferencia</dt>
            <dd>
<?php if ($diferencia < 0): ?>
              Faltante de Bs <?= bs(abs($diferencia)) ?>
<?php elseif ($diferencia > 0): ?>
              Sobrante de Bs <?= bs($diferencia) ?>
<?php else: ?>
              La caja cuadra
<?php endif; ?>
            </dd>
          </div>
<?php if (!empty($conteo['observacion'])): ?>
          <div><dt>Observación</dt><dd><?= e($conteo['observacion']) ?></dd></div>
<?php endif; ?>
        </dl>
      </section>

      <section class="reporte__bloque">
        <h2 class="reporte__subtitulo">Resumen del turno</h2>
        <dl class="reporte__datos-lista">
          <div><dt>Ventas</dt><dd><?= e($resumen['ventas'] ?? 0) ?></dd></div>
          <div><dt>Total vendido</dt><dd><?= bs($resumen['total'] ?? 0) ?></dd></div>
<?php foreach (($resumen['por_metodo'] ?? []) as $medio => $monto): ?>
          <div><dt><?= e($medio) ?></dt><dd><?= bs($monto) ?></dd></div>
<?php endforeach; ?>
          <div><dt>QR confirmados</dt><dd><?= bs($resumen['qr']['confirmados'] ?? 0) ?></dd></div>
          <div><dt>QR pendientes</dt><dd><?= bs($resumen['qr']['pendientes'] ?? 0) ?></dd></div>
          <div><dt>QR rechazados</dt><dd><?= bs($resumen['qr']['rechazados'] ?? 0) ?></dd></div>
          <div><dt>Descuentos</dt><dd><?= bs($resumen['descuentos'] ?? 0) ?></dd></div>
          <div><dt>Anulaciones</dt><dd><?= bs($resumen['anuladas'] ?? 0) ?></dd></div>
        </dl>
      </section>

<?php if ($movimientos !== []): ?>
      <section class="reporte__bloque">
        <h2 class="reporte__subtitulo">Ingresos y egresos del turno</h2>
        <table class="tabla tabla--fichas">
          <thead>
            <tr>
              <th scope="col">Hora</th>
              <th scope="col">Tipo</th>
              <th scope="col" class="col-num">Monto</th>
              <th scope="col">Motivo</th>
              <th scope="col">Responsable</th>
            </tr>
          </thead>
          <tbody>
<?php foreach ($movimientos as $m): ?>
            <tr>
              <td><?= e(substr((string) $m['creado_en'], 11, 5)) ?></td>
              <td><?= $m['tipo'] === 'ingreso' ? 'Ingreso' : 'Egreso' ?></td>
              <td class="col-num num"><?= bs($m['monto']) ?></td>
              <td><?= e($m['motivo']) ?></td>
              <td><?= e($m['usuario']) ?></td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </section>
<?php endif; ?>

      <footer class="reporte__pie">
        <p>Documento de prueba, sin validez fiscal.</p>
        <p>Generado el <?= e((new DateTimeImmutable('now', new DateTimeZone('America/La_Paz')))->format('d/m/Y H:i')) ?></p>
      </footer>
    </main>
