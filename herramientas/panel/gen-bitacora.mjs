// Genera el fragmento de la Bitácora con registros de ejemplo.
// Uso: node herramientas/panel/gen-bitacora.mjs herramientas/panel/fragmentos/bitacora.html
import { writeFileSync } from 'node:fs';

const filas = [
  ['08/10/2026 12:40', 'Mariela Suárez', 'denegado', 'Acceso denegado', 'Usuarios', 'Intentó abrir /usuarios con rol Cajero', '192.168.1.20'],
  ['08/10/2026 12:24', 'Ana Lucía Vaca', 'anulacion', 'Anulación', 'Factura 123', 'Autorizó con PIN. Motivo: error en el pedido', '192.168.1.20'],
  ['08/10/2026 12:22', 'Ana Lucía Vaca', 'qr', 'Pago QR', 'BNB-55098', 'Rechazado: no aparece en el banco. Pedido 036 con saldo', '192.168.1.31'],
  ['08/10/2026 12:15', 'Mariela Suárez', 'reimpresion', 'Reimpresión', 'Factura 118', 'Reimprimió los tres tickets', '192.168.1.20'],
  ['08/10/2026 12:10', 'Ana Lucía Vaca', 'descuento', 'Descuento manual', 'Pedido 033', 'Bs 10,00 por demora en cocina, autorizado con PIN', '192.168.1.20'],
  ['08/10/2026 12:05', 'Mariela Suárez', 'producto', 'Producto', 'PL-07 Fricasé de cerdo', 'Marcado como agotado', '192.168.1.20'],
  ['08/10/2026 11:52', 'Ana Lucía Vaca', 'qr', 'Pago QR', 'BNB-55120', 'Confirmado desde el celular', '192.168.1.31'],
  ['08/10/2026 11:30', 'Sistema', 'bloqueo', 'Bloqueo', 'jorge', '5 PIN incorrectos seguidos: bloqueado 15 minutos', '192.168.1.20'],
  ['08/10/2026 11:02', 'Mariela Suárez', 'caja', 'Caja', 'Medio día', 'Abrió caja con un fondo de Bs 300,00', '192.168.1.20'],
  ['08/10/2026 10:52', 'Ana Lucía Vaca', 'qr', 'QR del día', '08/10/2026', 'Subió el QR del día de Bs 0,50 (BNB)', '192.168.1.31'],
  ['08/10/2026 10:40', 'Ana Lucía Vaca', 'precio', 'Cambio de precio', 'PL-03 Picante de pollo', 'De Bs 40,00 a Bs 45,00', '192.168.1.31'],
  ['07/10/2026 23:48', 'Jorge Rivero', 'caja', 'Caja', 'Noche', 'Cerró caja. Arqueo cuadra', '192.168.1.20'],
  ['07/10/2026 16:10', 'Mariela Suárez', 'caja', 'Caja', 'Medio día', 'Cerró caja con faltante de Bs 10,00. Observación: cambio mal entregado', '192.168.1.20'],
];
const tono = { denegado: 'peligro', anulacion: 'peligro', bloqueo: 'aviso', qr: 'neutra', descuento: 'aviso', reimpresion: 'neutra', producto: 'neutra', precio: 'neutra', caja: 'ok' };
const tr = filas.map(([f, u, tipo, accion, ent, det, ip]) => `            <tr data-usuario="${u}" data-accion="${tipo}" data-dia="${f.slice(0, 10)}">
              <td class="num">${f}</td>
              <td>${u}</td>
              <td><span class="insignia insignia--${tono[tipo]}">${accion}</span></td>
              <td>${ent}</td>
              <td>${det}</td>
              <td class="codigo">${ip}</td>
            </tr>`).join('\n');

writeFileSync(process.argv[2], `<!-- {"titulo":"Bitácora","modulo":"bitacora","h1":"Bitácora","js":[],"cssExtra":["modulos.css"]} -->
    <main class="contenido" id="contenido">
      <p class="cabeza-pagina__texto bitacora__intro">Quién hizo qué y cuándo. Solo se agregan registros: no se pueden editar ni borrar.</p>
      <div class="filtros" role="search" aria-label="Filtrar bitácora">
        <div class="campo filtros__busqueda">
          <label class="campo__etiqueta" for="b-buscar">Buscar</label>
          <div class="control-grupo">
            <svg class="icono control-grupo__icono" aria-hidden="true"><use href="#i-buscar"></use></svg>
            <input class="control" id="b-buscar" type="search" placeholder="Detalle, entidad o IP" data-filtra="filas-bitacora" autocomplete="off">
          </div>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="b-usuario">Usuario</label>
          <select class="control" id="b-usuario" data-filtra="filas-bitacora" data-campo="usuario">
            <option value="">Todos</option>
            <option>Ana Lucía Vaca</option>
            <option>Mariela Suárez</option>
            <option>Jorge Rivero</option>
            <option>Sistema</option>
          </select>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="b-accion">Acción</label>
          <select class="control" id="b-accion" data-filtra="filas-bitacora" data-campo="accion">
            <option value="">Todas</option>
            <option value="anulacion">Anulaciones</option>
            <option value="descuento">Descuentos manuales</option>
            <option value="reimpresion">Reimpresiones</option>
            <option value="qr">Pagos y QR</option>
            <option value="precio">Cambios de precio</option>
            <option value="caja">Apertura y cierre de caja</option>
            <option value="denegado">Accesos denegados</option>
            <option value="bloqueo">Bloqueos</option>
            <option value="producto">Productos</option>
          </select>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="b-dia">Día</label>
          <select class="control" id="b-dia" data-filtra="filas-bitacora" data-campo="dia">
            <option value="">Todos</option>
            <option value="08/10/2026">Hoy, 08/10</option>
            <option value="07/10/2026">Ayer, 07/10</option>
          </select>
        </div>
      </div>
      <p class="resultado"><span class="resultado__cuenta" data-cuenta="filas-bitacora" data-singular="registro" data-plural="registros">0 registros</span></p>
      <div class="tabla-contenedor">
        <table class="tabla tabla--bitacora tabla--fichas">
          <caption class="visualmente-oculto">Bitácora</caption>
          <thead>
            <tr>
              <th scope="col">Fecha y hora</th>
              <th scope="col">Usuario</th>
              <th scope="col">Acción</th>
              <th scope="col">Entidad</th>
              <th scope="col">Detalle</th>
              <th scope="col">IP</th>
            </tr>
          </thead>
          <tbody id="filas-bitacora">
${tr}
          </tbody>
        </table>
        <div class="vacio" data-vacio-de="filas-bitacora" hidden>
          <h2 class="vacio__titulo">Ningún registro coincide</h2>
          <button class="boton" type="button" data-limpiar-filtros="filas-bitacora">Quitar filtros</button>
        </div>
      </div>
    </main>
`);
console.log('ok');
