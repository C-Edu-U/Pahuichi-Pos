/* Cola de impresión (demo). Con servidor: el puente consulta
   GET /api/impresion/pendientes cada segundo y marca POST .../hecho. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };

  function caida() { return P.leer('pahuichi.impresora') === 'caida'; }

  function filaPendiente(ticket, pedido, hora) {
    var tr = document.createElement('tr');
    tr.setAttribute('data-estado', 'pendiente');
    tr.className = 'fila-pendiente';
    tr.innerHTML = '<td></td><td class="num"></td><td>Caja</td><td class="num"></td><td class="col-num num">3</td>' +
      '<td class="celda-estado"><span class="insignia insignia--aviso">En cola</span></td>' +
      '<td class="col-accion"><div class="acciones-fila"><button class="boton" type="button" data-reintentar><svg class="icono" aria-hidden="true"><use href="#i-reintentar"></use></svg>Reintentar</button></div></td>';
    tr.cells[0].textContent = ticket;
    tr.cells[1].textContent = pedido;
    tr.cells[3].textContent = hora;
    return tr;
  }

  function pintar() {
    var c = caida();
    $('caja-insignia').className = 'insignia ' + (c ? 'insignia--peligro' : 'insignia--ok');
    $('caja-insignia').textContent = c ? 'Sin conexión' : 'En línea';
    $('caja-detalle').textContent = c ? 'El servicio puente no responde desde las 12:39' : 'Último ticket hace 3 s';
    $('estado-caja').classList.toggle('impresora-estado--alerta', c);
    $('simular-caida').textContent = c ? 'Volver a conectar la impresora' : 'Simular impresora de caja sin conexión';
    document.querySelectorAll('.fila-pendiente').forEach(function (tr) { tr.remove(); });
    if (c) {
      var cuerpo = $('filas-cola');
      [['Factura', '043', '12:41:02'], ['Copia de caja', '043', '12:41:01']].forEach(function (t) {
        cuerpo.insertBefore(filaPendiente(t[0], t[1], t[2]), cuerpo.firstChild);
      });
    }
  }

  $('simular-caida').addEventListener('click', function () {
    P.guardar('pahuichi.impresora', caida() ? 'ok' : 'caida');
    pintar();
    var chip = document.getElementById('estado-impresora');
    if (chip) {
      chip.classList.toggle('estado-sistema--alerta', caida());
      chip.querySelector('.estado-sistema__texto').textContent = caida() ? 'Impresora sin conexión · 2 en cola' : 'Impresora lista';
    }
    P.aviso(caida() ? 'La impresora de caja no responde. Las ventas siguen y los tickets esperan en cola.' : 'Impresora conectada. Se imprimieron los tickets en cola.');
  });

  $('filas-cola').addEventListener('click', function (e) {
    var b = e.target.closest('[data-reintentar]');
    if (!b) return;
    if (caida()) { P.aviso('La impresora sigue sin conexión. Revisa el cable o el papel e inténtalo de nuevo.'); return; }
    var tr = b.closest('tr');
    tr.querySelector('.celda-estado').innerHTML = '<span class="insignia insignia--ok">Impreso</span>';
    b.remove();
  });

  pintar();
})();
