// Genera el fragmento de Ventas y facturas con filas de ejemplo coherentes.
// Uso: node herramientas/panel/gen-ventas.mjs herramientas/panel/fragmentos/ventas.html
import { writeFileSync } from 'node:fs';

const bs = (n) => { const [e, d] = n.toFixed(2).split('.'); return e.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + d; };

// factura, pedido, dia, hora, cliente, doc, cajero, tipo, mesa, metodo, estadoQr, total, estado
const filas = [
  [128, '042', '2026-10-08', '12:38', 'Rodríguez Peña', 'CI 4567891', 'Mariela Suárez', 'mesa', '4', 'efectivo', '', 95, 'pagada'],
  [127, '041', '2026-10-08', '12:36', 'S/N', 'NIT 0', 'Mariela Suárez', 'llevar', '', 'mixto', 'pendiente', 45, 'pagada'],
  [126, '040', '2026-10-08', '12:33', 'Constructora Piraí S.R.L.', 'NIT 1023987026', 'Mariela Suárez', 'mesa', '7', 'efectivo', '', 214, 'pagada'],
  [125, '039', '2026-10-08', '12:31', 'S/N', 'NIT 0', 'Mariela Suárez', 'llevar', '', 'qr', 'pendiente', 0.5, 'pagada'],
  [124, '038', '2026-10-08', '12:27', 'Gutiérrez Añez', 'CI 6234510-1F', 'Mariela Suárez', 'mesa', '2', 'efectivo', '', 53, 'pagada'],
  [123, '037', '2026-10-08', '12:24', 'S/N', 'NIT 0', 'Mariela Suárez', 'mesa', '5', 'efectivo', '', 45, 'anulada'],
  [122, '036', '2026-10-08', '12:20', 'Saucedo Roca', 'CI 3398217', 'Mariela Suárez', 'mesa', '1', 'mixto', 'rechazado', 70, 'saldo'],
  [121, '031', '2026-10-08', '11:47', 'S/N', 'NIT 0', 'Mariela Suárez', 'llevar', '', 'qr', 'confirmado', 0.5, 'pagada'],
  [120, '030', '2026-10-08', '11:44', 'Distribuidora El Trompillo', 'NIT 1987654013', 'Mariela Suárez', 'mesa', '8', 'efectivo', '', 142, 'pagada'],
  [119, '029', '2026-10-08', '11:31', 'S/N', 'NIT 0', 'Mariela Suárez', 'llevar', '', 'efectivo', '', 28, 'pagada'],
  [118, '028', '2026-10-08', '11:20', 'S/N', 'NIT 0', 'Mariela Suárez', 'mesa', '3', 'mixto', 'confirmado', 60, 'pagada'],
  [117, '027', '2026-10-08', '11:08', 'S/N', 'NIT 0', 'Mariela Suárez', 'mesa', '6', 'efectivo', '', 35, 'pagada'],
  [116, '026', '2026-10-07', '22:15', 'Rodríguez Peña', 'CI 4567891', 'Jorge Rivero', 'mesa', '4', 'efectivo', '', 160, 'pagada'],
  [115, '025', '2026-10-07', '21:02', 'S/N', 'NIT 0', 'Jorge Rivero', 'llevar', '', 'efectivo', '', 45, 'pagada'],
  [114, '024', '2026-10-07', '19:40', 'Saucedo Roca', 'CI 3398217', 'Jorge Rivero', 'mesa', '2', 'qr', 'confirmado', 0.5, 'pagada'],
];

const fecha = (d) => d.split('-').reverse().slice(0, 2).join('/');
const metodo = (m, e) => {
  const nombre = { efectivo: 'Efectivo', qr: 'QR', mixto: 'Mixto' }[m];
  const qr = e ? ` <span class="insignia insignia--${{ pendiente: 'aviso', confirmado: 'ok', rechazado: 'peligro' }[e]}">QR ${e}</span>` : '';
  return `<span class="metodo metodo--${m === 'efectivo' ? 'efectivo' : 'qr'}">${nombre}</span>${qr}`;
};
const estado = (e) => ({
  pagada: '<span class="insignia insignia--ok">Pagada</span>',
  anulada: '<span class="insignia insignia--peligro">Anulada</span>',
  saldo: '<span class="insignia insignia--aviso">Saldo por cobrar</span>',
}[e]);

const tr = filas.map(([n, ped, dia, hora, cli, doc, caj, tipo, mesa, met, eqr, tot, est]) => `            <tr data-dia="${dia}" data-cajero="${caj}" data-metodo="${met}" data-estado="${est}" data-factura="${n}" data-total="${tot}">
              <td><a class="tabla__principal enlace-fila" href="tickets.html?n=${n}">Pedido ${ped}</a><span class="tabla__secundario num">Factura ${n} · ${fecha(dia)} ${hora}</span></td>
              <td><span class="celda-cliente">${cli}</span><span class="tabla__secundario">${doc}</span></td>
              <td class="col-cajero">${caj}</td>
              <td>${tipo === 'mesa' ? `Mesa ${mesa}` : 'Para llevar'}</td>
              <td>${metodo(met, eqr)}</td>
              <td class="col-num"><span class="monto${est === 'anulada' ? ' monto--anulado' : ''}"><span class="monto__moneda">Bs</span>${bs(tot)}</span></td>
              <td class="celda-estado">${estado(est)}</td>
              <td class="col-accion"><div class="acciones-fila">
                <a class="boton" href="tickets.html?n=${n}&amp;reimpresion=1">
                  <svg class="icono" aria-hidden="true"><use href="#i-imprimir"></use></svg>Reimprimir</a>
                ${est === 'anulada' ? '' : `<button class="boton" type="button" data-abrir-modal="modal-anular" data-factura="${n}">Anular</button>`}
              </div></td>
            </tr>`).join('\n');

const html = `<!-- {"titulo":"Ventas y facturas","modulo":"ventas","h1":"Ventas y facturas","js":["ventas.js"],"cssExtra":["modulos.css"]} -->
    <main class="contenido" id="contenido">
      <div class="cabeza-pagina">
        <p class="cabeza-pagina__texto">Cada venta con su factura y sus tres tickets. Nada se borra: las anuladas conservan su número y su motivo.</p>
        <div class="cabeza-pagina__acciones">
          <a class="boton boton--primario" href="pos.html" data-requiere="modulo.pos">
            <svg class="icono" aria-hidden="true"><use href="#i-mas"></use></svg>
            Nueva venta
          </a>
        </div>
      </div>

      <div class="filtros" role="search" aria-label="Filtrar ventas">
        <div class="campo filtros__busqueda">
          <label class="campo__etiqueta" for="v-buscar">Buscar</label>
          <div class="control-grupo">
            <svg class="icono control-grupo__icono" aria-hidden="true"><use href="#i-buscar"></use></svg>
            <input class="control" id="v-buscar" type="search" placeholder="Pedido, factura, cliente o NIT/CI" data-filtra="filas-ventas" autocomplete="off">
          </div>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="v-dia">Día</label>
          <select class="control" id="v-dia" data-filtra="filas-ventas" data-campo="dia">
            <option value="2026-10-08" selected>Hoy, 08/10</option>
            <option value="2026-10-07">Ayer, 07/10</option>
            <option value="">Todos</option>
          </select>
        </div>
        <div class="campo" data-requiere="ventas.todas">
          <label class="campo__etiqueta" for="v-cajero">Cajero</label>
          <select class="control" id="v-cajero" data-filtra="filas-ventas" data-campo="cajero">
            <option value="">Todos</option>
            <option>Mariela Suárez</option>
            <option>Jorge Rivero</option>
          </select>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="v-metodo">Medio de pago</label>
          <select class="control" id="v-metodo" data-filtra="filas-ventas" data-campo="metodo">
            <option value="">Todos</option>
            <option value="efectivo">Efectivo</option>
            <option value="qr">QR</option>
            <option value="mixto">Mixto</option>
          </select>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="v-estado">Estado</label>
          <select class="control" id="v-estado" data-filtra="filas-ventas" data-campo="estado">
            <option value="">Todos</option>
            <option value="pagada">Pagadas</option>
            <option value="saldo">Saldo por cobrar</option>
            <option value="anulada">Anuladas</option>
          </select>
        </div>
      </div>

      <p class="resultado">
        <span class="resultado__cuenta"><span data-cuenta="filas-ventas" data-singular="venta" data-plural="ventas">0 ventas</span> · <span class="num" id="v-suma">Bs 0,00</span> vendidos</span>
      </p>

      <div class="tabla-contenedor">
        <table class="tabla tabla--ventas tabla--fichas">
          <caption class="visualmente-oculto">Ventas y facturas</caption>
          <thead>
            <tr>
              <th scope="col">Pedido</th>
              <th scope="col">Cliente</th>
              <th scope="col" class="col-cajero">Cajero</th>
              <th scope="col">Tipo</th>
              <th scope="col">Pago</th>
              <th scope="col" class="col-num">Total</th>
              <th scope="col">Estado</th>
              <th scope="col" class="col-accion"><span class="visualmente-oculto">Acciones</span></th>
            </tr>
          </thead>
          <tbody id="filas-ventas">
${tr}
          </tbody>
        </table>
        <div class="vacio" data-vacio-de="filas-ventas" hidden>
          <svg class="icono icono--lg vacio__icono" aria-hidden="true"><use href="#i-ventas"></use></svg>
          <h2 class="vacio__titulo">No hay ventas con estos filtros</h2>
          <p class="vacio__texto">Cambia el día o quita algún filtro.</p>
          <button class="boton" type="button" data-limpiar-filtros="filas-ventas">Quitar filtros</button>
        </div>
      </div>

      <dialog class="modal" id="modal-anular" aria-labelledby="anular-titulo">
        <form method="dialog" id="form-anular" novalidate>
          <div class="modal__cuerpo">
            <h2 class="modal__titulo" id="anular-titulo">Anular la venta</h2>
            <p class="modal__texto" id="anular-texto">La factura queda ANULADA y su número no se vuelve a usar.</p>
            <div class="campo">
              <label class="campo__etiqueta" for="motivo">Motivo de anulación</label>
              <select class="control" id="motivo" aria-describedby="motivo-error">
                <option value="">Elige el motivo</option>
                <option value="1">Factura mal emitida</option>
                <option value="2">Error en el pedido</option>
                <option value="3">Datos del cliente incorrectos</option>
                <option value="4">El cliente devolvió el pedido</option>
              </select>
              <p class="campo__error" id="motivo-error" hidden></p>
            </div>
            <div class="campo">
              <label class="campo__etiqueta" for="anular-pin">PIN del administrador</label>
              <input class="control" id="anular-pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off" aria-describedby="anular-pin-ayuda anular-pin-error">
              <p class="campo__ayuda" id="anular-pin-ayuda">Demo: 1234. La autorización queda en la bitácora.</p>
              <p class="campo__error" id="anular-pin-error" hidden></p>
            </div>
          </div>
          <div class="modal__acciones">
            <button class="boton" type="submit" value="cancelar" formnovalidate>Cancelar</button>
            <button class="boton boton--peligro" type="button" id="confirmar-anular">Anular venta</button>
          </div>
        </form>
      </dialog>
    </main>
`;
writeFileSync(process.argv[2], html);
console.log('ok');
