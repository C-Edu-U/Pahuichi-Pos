<?php
/**
 * Tablero del día (HU-64). Variables: $esCajero, $t (TableroService::delDia() o delCajero()),
 * $ahora. tablero.js vuelve a pedir la página cada minuto y reemplaza #tablero.
 */
use App\Models\Turno;

$hora = static fn (string $momento): string => substr($momento, 11, 5);
?>
    <main class="contenido" id="contenido">
      <div id="tablero" data-actualizado="<?= e($ahora->format('H:i')) ?>">
<?php if (!$esCajero): ?>
      <div class="tablero-dia">
        <section class="tablero-dia__hoy panel-bloque" aria-labelledby="t-hoy">
          <div class="panel-bloque__cuerpo">
            <h2 class="hoy__etiqueta" id="t-hoy">Ventas de hoy<?= $t['caja'] ? ' · ' . e(mb_strtolower($t['caja']['turno'])) . ' en curso' : '' ?></h2>
            <p class="hoy__valor num" id="ventas-hoy"><span class="cifra__moneda">Bs</span><?= bs($t['total']) ?></p>
            <dl class="tablero-dia__cifras">
              <div><dt>Ventas</dt><dd class="num" id="ventas-cantidad"><?= (int) $t['ventas'] ?></dd></div>
              <div><dt>Ticket promedio</dt><dd class="num">Bs <?= bs($t['promedio']) ?></dd></div>
              <div><dt>Frente a ayer</dt><dd class="num"><?php if ($t['variacion'] === null): ?><span class="tablero-dia__sin-dato">Sin ventas ayer a esta hora</span><?php else: ?><span class="tablero-dia__variacion tablero-dia__variacion--<?= $t['variacion'] >= 0 ? 'sube' : 'baja' ?>"><?= ($t['variacion'] > 0 ? '+' : '') . e(number_format($t['variacion'], 1, ',', '.')) ?> %</span><?php endif; ?></dd></div>
            </dl>
            <p class="hoy__detalle">Ventas cobradas desde las 00:00. Actualizado a las <span data-actualizado-texto><?= e($ahora->format('H:i')) ?></span>.</p>
          </div>
        </section>

        <a class="tablero-dia__qr" href="/conciliacion">
          <?= icono('qr') ?>
          <span class="tablero-dia__qr-texto"><strong class="num"><?= (int) $t['qr']['cantidad'] ?></strong> <?= $t['qr']['cantidad'] === 1 ? 'pago QR por confirmar' : 'pagos QR por confirmar' ?><?= $t['qr']['cantidad'] ? ' · Bs ' . bs($t['qr']['monto']) : '' ?></span>
          <span class="tablero-dia__qr-accion">Revisar</span>
        </a>

        <section class="tablero-dia__avisos" aria-labelledby="t-avisos">
          <h2 class="seccion__titulo" id="t-avisos">Para atender</h2>
<?php if ($t['avisos'] === []): ?>
          <p class="tablero-dia__en-orden"><?= icono('ok') ?> Todo en orden.</p>
<?php else: ?>
          <ul class="pendientes" role="list">
<?php foreach ($t['avisos'] as $a): ?>
            <li class="pendiente<?= $a['tono'] !== 'neutra' ? ' pendiente--' . e($a['tono']) : '' ?>">
              <?= icono($a['icono'], 'pendiente__icono') ?>
              <div class="pendiente__texto">
                <p class="pendiente__titulo"><?= e($a['titulo']) ?></p>
                <p class="pendiente__detalle"><?= e($a['detalle']) ?></p>
              </div>
              <a class="boton pendiente__accion" href="<?= e($a['enlace']) ?>"><?= e($a['accion']) ?></a>
            </li>
<?php endforeach; ?>
          </ul>
<?php endif; ?>
        </section>

        <section class="tablero-dia__top" aria-labelledby="t-top">
          <div class="seccion__cabeza">
            <h2 id="t-top">Más vendidos hoy</h2>
          </div>
<?php if ($t['masVendidos'] === []): ?>
          <p class="cabeza-pagina__texto">Todavía no hay ventas hoy.</p>
<?php else: ?>
          <ol class="ranking" role="list" id="mas-vendidos">
<?php foreach ($t['masVendidos'] as $p): ?>
            <li><span class="ranking__nombre"><?= e($p['nombre']) ?></span><span class="ranking__valor num"><?= (int) $p['unidades'] ?></span></li>
<?php endforeach; ?>
          </ol>
<?php endif; ?>
        </section>

        <section class="tablero-dia__caja" aria-labelledby="t-caja">
          <h2 class="seccion__titulo" id="t-caja">Caja</h2>
<?php if ($t['caja'] === null): ?>
          <p class="cabeza-pagina__texto">No hay una caja abierta.</p>
<?php else: $c = $t['caja']; ?>
          <dl class="resumen panel-bloque">
            <div class="resumen__fila"><dt>Turno</dt><dd><?= e($c['turno']) ?> · abierta desde las <?= e($hora($c['abierta_en'])) ?></dd></div>
            <div class="resumen__fila"><dt>Cajero</dt><dd><?= e($c['cajero']) ?></dd></div>
            <div class="resumen__fila"><dt>Ventas del turno</dt><dd class="num"><?= (int) $c['ventas'] ?> · Bs <?= bs($c['vendido']) ?></dd></div>
          </dl>
<?php endif; ?>
        </section>
      </div>
<?php else: ?>
      <!-- Cajero: su turno, sin montos (cierre ciego) -->
      <div class="tablero-dia tablero-dia--cajero">
<?php if ($t['caja'] === null): ?>
        <section class="panel-bloque" aria-labelledby="t-turno">
          <div class="panel-bloque__cuerpo">
            <h2 class="seccion__titulo" id="t-turno">No tienes una caja abierta</h2>
            <p class="cabeza-pagina__texto"><?= $t['otraCaja'] ? 'La caja abierta es de ' . e($t['otraCaja']['cajero']) . '.' : 'Abre tu caja para empezar a vender.' ?></p>
          </div>
          <div class="panel-bloque__pie"><a class="boton boton--primario" href="/caja"><?= icono('caja') ?> Ir a caja</a></div>
        </section>
<?php else: $c = $t['caja']; ?>
        <section class="panel-bloque" aria-labelledby="t-turno">
          <h2 class="visualmente-oculto" id="t-turno">Tu turno</h2>
          <dl class="resumen resumen--sin-borde">
            <div class="resumen__fila"><dt>Turno</dt><dd><?= e($c['turno']) ?> · <?= e(Turno::horario($c)) ?></dd></div>
            <div class="resumen__fila"><dt>Caja abierta</dt><dd class="num">Desde las <?= e($hora($c['abierta_en'])) ?></dd></div>
            <div class="resumen__fila"><dt>Ventas cobradas</dt><dd class="num" id="ventas-cantidad"><?= (int) $t['ventas'] ?></dd></div>
            <div class="resumen__fila"><dt>Pagos QR por confirmar</dt><dd class="num"><?= (int) $t['qr']['cantidad'] ?></dd></div>
          </dl>
          <div class="panel-bloque__pie">
            <a class="boton boton--primario" href="/pos"><?= icono('pos') ?> Ir al punto de venta</a>
            <a class="boton" href="/caja">Caja</a>
          </div>
        </section>
<?php endif; ?>
      </div>
<?php endif; ?>
      </div>
    </main>
