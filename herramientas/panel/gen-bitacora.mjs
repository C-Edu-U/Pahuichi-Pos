import { writeFileSync } from 'node:fs';

const filas = [
  ['06/10/2026 14:31', 'Ana Lucía Vaca', 'config', 'Configuración', 'Empresa', 'Cambió la modalidad a computarizada en línea', '190.181.24.10'],
  ['06/10/2026 12:58', 'Mariela Suárez', 'denegado', 'Acceso denegado', 'Usuarios', 'Intentó abrir /usuarios con rol Cajero', '190.181.24.37'],
  ['06/10/2026 12:41', 'Mariela Suárez', 'venta', 'Venta', 'Factura N° 128', 'Bs 76,00 en efectivo a Rodríguez Peña', '190.181.24.37'],
  ['06/10/2026 11:52', 'Mariela Suárez', 'producto', 'Producto', 'HO-06 Masaco de plátano', 'Marcado como agotado hoy', '190.181.24.37'],
  ['06/10/2026 11:34', 'Ana Lucía Vaca', 'anulacion', 'Anulación', 'Factura N° 123', 'Motivo 1: factura mal emitida', '190.181.24.10'],
  ['06/10/2026 10:47', 'Sistema', 'usuario', 'Usuario', 'jorge', 'Bloqueado 15 minutos tras 5 intentos fallidos', '181.115.60.92'],
  ['06/10/2026 07:40', 'Ana Lucía Vaca', 'sesion', 'Inicio de sesión', 'admin', 'Ingreso correcto', '190.181.24.10'],
  ['06/10/2026 07:02', 'Mariela Suárez', 'caja', 'Caja', 'Turno 214', 'Abrió caja con Bs 200,00', '190.181.24.37'],
  ['05/10/2026 16:40', 'Ana Lucía Vaca', 'qr', 'Pago QR', 'QR 32', 'Subió un QR nuevo de Bs 0,50 (BNB)', '190.181.24.10'],
  ['05/10/2026 15:12', 'Mariela Suárez', 'caja', 'Caja', 'Turno 213', 'Cerró caja. Arqueo cuadra', '190.181.24.37'],
  ['05/10/2026 09:20', 'Ana Lucía Vaca', 'usuario', 'Usuario', 'fabiola', 'Desactivó la cuenta', '190.181.24.10'],
];
const tono = { denegado: 'peligro', anulacion: 'peligro', usuario: 'aviso', qr: 'neutra', config: 'neutra', venta: 'ok', producto: 'neutra', sesion: 'neutra', caja: 'ok' };
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
      <p class="cabeza-pagina__texto bitacora__intro">Registro de quién hizo qué y cuándo. No se puede editar ni borrar.</p>
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
            <option>Sistema</option>
          </select>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="b-accion">Acción</label>
          <select class="control" id="b-accion" data-filtra="filas-bitacora" data-campo="accion">
            <option value="">Todas</option>
            <option value="venta">Ventas</option>
            <option value="anulacion">Anulaciones</option>
            <option value="caja">Caja</option>
            <option value="qr">Pagos QR</option>
            <option value="usuario">Usuarios</option>
            <option value="denegado">Accesos denegados</option>
            <option value="config">Configuración</option>
            <option value="producto">Productos</option>
            <option value="sesion">Inicios de sesión</option>
          </select>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="b-dia">Día</label>
          <select class="control" id="b-dia" data-filtra="filas-bitacora" data-campo="dia">
            <option value="">Todos</option>
            <option value="06/10/2026">Hoy, 06/10</option>
            <option value="05/10/2026">Ayer, 05/10</option>
          </select>
        </div>
      </div>
      <p class="resultado"><span class="resultado__cuenta" data-cuenta="filas-bitacora" data-singular="registro" data-plural="registros">0 registros</span></p>
      <div class="tabla-contenedor">
        <table class="tabla tabla--bitacora tabla--fichas">
          <caption class="visualmente-oculto">Bitácora de auditoría</caption>
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
