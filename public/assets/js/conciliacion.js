/* Conciliación QR (demo): confirmar con un toque; rechazar pide motivo y
   deja la venta con saldo por cobrar, con aviso a caja (RF-42). */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var modal = $('modal-rechazo');
  var filaRechazo = null;

  function contar() {
    var filas = Array.prototype.slice.call($('pagos-qr').rows).filter(function (tr) { return tr.getAttribute('data-dia') === '2026-10-08'; });
    ['pendiente', 'confirmado', 'rechazado'].forEach(function (e) {
      $('c-' + e + 's').textContent = filas.filter(function (tr) { return tr.getAttribute('data-estado') === e; }).length;
    });
    var aviso = document.querySelector('.menu__item[data-modulo="conciliacion"] .menu__aviso');
    if (aviso) {
      var n = $('c-pendientes').textContent;
      aviso.hidden = n === '0';
      aviso.innerHTML = '<span class="visualmente-oculto">Pagos por confirmar: </span>' + n;
    }
  }

  function resolver(tr, estado, detalle) {
    tr.setAttribute('data-estado', estado);
    tr.querySelector('.celda-estado').innerHTML = estado === 'confirmado'
      ? '<span class="insignia insignia--ok">Confirmado</span>'
      : '<span class="insignia insignia--peligro">Rechazado</span>';
    var acciones = tr.querySelector('.acciones-qr');
    if (acciones) acciones.remove();
    var linea = tr.querySelectorAll('.pago-qr__detalle')[1];
    linea.textContent = detalle;
    contar();
    // Dejarla visible un momento antes de que el filtro la saque
    tr.classList.add('fila-nueva');
    window.setTimeout(function () { P.filtrarTabla('pagos-qr'); }, 700);
  }

  $('pagos-qr').addEventListener('click', function (e) {
    var b = e.target.closest('[data-resolver]');
    if (!b) return;
    var tr = b.closest('tr');
    var comprobante = tr.querySelector('.pago-qr__comprobante').textContent;
    if (b.getAttribute('data-resolver') === 'confirmado') {
      resolver(tr, 'confirmado', 'Confirmado por ' + P.ROLES[P.rolActual()].usuario + ' a las 12:41');
      P.aviso('Pago ' + comprobante + ' confirmado.');
    } else {
      filaRechazo = tr;
      modal.showModal();
    }
  });

  modal.addEventListener('close', function () {
    if (modal.returnValue !== 'rechazar' || !filaRechazo) return;
    var comprobante = filaRechazo.querySelector('.pago-qr__comprobante').textContent;
    resolver(filaRechazo, 'rechazado', 'Rechazado: ' + $('rechazo-motivo').value.toLowerCase() + '. La venta quedó con saldo por cobrar.');
    P.aviso('Pago ' + comprobante + ' rechazado. La caja recibió el aviso.');
    filaRechazo = null;
  });

  contar();
})();
