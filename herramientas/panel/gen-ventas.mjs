// Genera el fragmento de ventas con filas de ejemplo coherentes.
import { writeFileSync } from 'node:fs';

const bs = (n) => { const [e, d] = n.toFixed(2).split('.'); return e.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + d; };
const filas = [
  // n, dia, hora, cliente, doc, cajero, metodo, total, estado
  [128, '2026-10-06', '12:41', 'Rodríguez Peña', 'CI 4567891', 'Mariela Suárez', 'efectivo', 76, 'pagada'],
  [127, '2026-10-06', '12:33', 'S/N', '99002', 'Mariela Suárez', 'efectivo', 38, 'pagada'],
  [126, '2026-10-06', '12:20', 'Constructora Piraí S.R.L.', 'NIT 1023987026', 'Mariela Suárez', 'efectivo', 214, 'pagada'],
  [125, '2026-10-06', '12:02', 'S/N', '99002', 'Mariela Suárez', 'qr', 0.5, 'pagada'],
  [124, '2026-10-06', '11:47', 'Gutiérrez Añez', 'CI 6234510-1F', 'Mariela Suárez', 'efectivo', 53, 'pagada'],
  [123, '2026-10-06', '11:30', 'S/N', '99002', 'Mariela Suárez', 'efectivo', 30, 'anulada'],
  [122, '2026-10-06', '11:12', 'Saucedo Roca', 'CI 3398217', 'Mariela Suárez', 'efectivo', 95, 'pagada'],
  [121, '2026-10-06', '10:55', 'S/N', '99002', 'Mariela Suárez', 'qr', 0.5, 'pagada'],
  [120, '2026-10-06', '10:21', 'Distribuidora El Trompillo', 'NIT 1987654013', 'Mariela Suárez', 'efectivo', 142, 'pagada'],
  [119, '2026-10-06', '09:48', 'S/N', '99002', 'Mariela Suárez', 'efectivo', 18, 'pagada'],
  [118, '2026-10-06', '08:15', 'S/N', '99002', 'Mariela Suárez', 'qr', 0.5, 'pagada'],
  [117, '2026-10-06', '07:36', 'S/N', '99002', 'Mariela Suárez', 'efectivo', 24, 'pagada'],
  [116, '2026-10-05', '14:52', 'Rodríguez Peña', 'CI 4567891', 'Mariela Suárez', 'efectivo', 60, 'pagada'],
  [115, '2026-10-05', '13:10', 'S/N', '99002', 'Jorge Rivero', 'efectivo', 45, 'pagada'],
  [114, '2026-10-05', '09:02', 'Saucedo Roca', 'CI 3398217', 'Jorge Rivero', 'qr', 0.5, 'pagada'],
  [87, '2026-08-28', '12:15', 'Constructora Piraí S.R.L.', 'NIT 1023987026', 'Jorge Rivero', 'efectivo', 186, 'pagada'],
];

const fecha = (d) => d.split('-').reverse().join('/');
const tr = filas.map(([n, dia, hora, cli, doc, caj, met, tot, est]) => `              <tr data-dia="${dia}" data-cajero="${caj}" data-metodo="${met}" data-estado="${est}" data-factura="${n}" data-total="${tot}">
                <td><a class="tabla__principal enlace-fila" href="factura.html?n=${n}">N° ${n}</a><span class="tabla__secundario num">${fecha(dia)} · ${hora}</span></td>
                <td><span class="celda-cliente">${cli}</span><span class="tabla__secundario">${doc}</span></td>
                <td class="col-cajero">${caj}</td>
                <td>${met === 'qr' ? '<span class="metodo metodo--qr">QR</span>' : '<span class="metodo metodo--efectivo">Efectivo</span>'}</td>
                <td class="col-num"><span class="monto${est === 'anulada' ? ' monto--anulado' : ''}"><span class="monto__moneda">Bs</span>${bs(tot)}</span></td>
                <td class="celda-estado">${est === 'anulada' ? '<span class="insignia insignia--peligro">Anulada</span>' : '<span class="insignia insignia--ok">Pagada</span>'}</td>
                <td class="col-accion"><div class="acciones-fila">
                  <a class="boton boton--icono" href="factura.html?n=${n}" aria-label="Reimprimir factura ${n}"><svg class="icono" aria-hidden="true"><use href="#i-imprimir"></use></svg></a>
                  ${est === 'anulada' ? '' : `<button class="boton" type="button" data-requiere="ventas.anular" data-abrir-modal="modal-anular" data-factura="${n}">Anular</button>`}
                </div></td>
              </tr>`).join('\n');

const html = `<!-- {"titulo":"Ventas y facturas","modulo":"ventas","h1":"Ventas y facturas","js":["ventas.js"],"cssExtra":["modulos.css"]} -->
    <main class="contenido" id="contenido">
      <div class="cabeza-pagina">
        <p class="cabeza-pagina__texto" data-solo-rol="cajero" hidden>Tus ventas. Puedes reimprimir cualquier factura; para anular, pídeselo al administrador.</p>
        <p class="cabeza-pagina__texto" data-solo-rol="analista sysadmin">Todas las ventas con su factura. Las anuladas se conservan con su motivo.</p>
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
            <input class="control" id="v-buscar" type="search" placeholder="N° de factura, cliente o NIT/CI" data-filtra="filas-ventas" autocomplete="off">
          </div>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="v-dia">Día</label>
          <select class="control" id="v-dia" data-filtra="filas-ventas" data-campo="dia">
            <option value="2026-10-06" selected>Hoy, 06/10</option>
            <option value="2026-10-05">Ayer, 05/10</option>
            <option value="2026-08-28">28/08/2026</option>
            <option value="">Todos los días</option>
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
          <label class="campo__etiqueta" for="v-metodo">Método</label>
          <select class="control" id="v-metodo" data-filtra="filas-ventas" data-campo="metodo">
            <option value="">Todos</option>
            <option value="efectivo">Efectivo</option>
            <option value="qr">QR</option>
          </select>
        </div>
        <div class="campo">
          <label class="campo__etiqueta" for="v-estado">Estado</label>
          <select class="control" id="v-estado" data-filtra="filas-ventas" data-campo="estado">
            <option value="">Todos</option>
            <option value="pagada">Pagadas</option>
            <option value="anulada">Anuladas</option>
          </select>
        </div>
      </div>

      <p class="resultado">
        <span class="resultado__cuenta"><span data-cuenta="filas-ventas" data-singular="venta" data-plural="ventas">0 ventas</span> · <span class="num" id="v-suma">Bs 0,00</span> cobrados</span>
      </p>

      <div class="tabla-contenedor">
        <table class="tabla tabla--ventas tabla--fichas">
          <caption class="visualmente-oculto">Ventas y facturas</caption>
          <thead>
            <tr>
              <th scope="col">Factura</th>
              <th scope="col">Cliente</th>
              <th scope="col" class="col-cajero">Cajero</th>
              <th scope="col">Método</th>
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
        <form method="dialog" id="form-anular">
          <div class="modal__cuerpo">
            <h2 class="modal__titulo" id="anular-titulo">Anular la factura N° 128</h2>
            <p class="modal__texto" id="anular-texto">Puedes anularla directamente hasta el 9 de noviembre de 2026.</p>
            <div class="alerta alerta--aviso" id="anular-tramite" hidden>
              <svg class="icono" aria-hidden="true"><use href="#i-alerta"></use></svg>
              <p class="alerta__titulo">Fuera de plazo</p>
              <p class="alerta__texto">El plazo venció el 9 del mes siguiente. Quedará marcada como «requiere trámite ante el SIN».</p>
            </div>
            <div class="campo">
              <label class="campo__etiqueta" for="motivo">Motivo de anulación</label>
              <select class="control" id="motivo" aria-describedby="motivo-error">
                <option value="">Elige el motivo</option>
                <option value="1">1 · Factura mal emitida</option>
                <option value="2">2 · Nota de crédito-débito mal emitida</option>
                <option value="3">3 · Datos de emisión incorrectos</option>
                <option value="4">4 · Factura o nota devuelta</option>
              </select>
              <p class="campo__error" id="motivo-error" hidden></p>
            </div>
          </div>
          <div class="modal__acciones">
            <button class="boton" type="submit" value="cancelar" formnovalidate>Cancelar</button>
            <button class="boton boton--peligro" type="button" id="confirmar-anular">Anular factura</button>
          </div>
        </form>
      </dialog>
    </main>
`;
writeFileSync(process.argv[2], html);
console.log('ok');
