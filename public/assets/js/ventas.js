/* Ventas y facturas (prototipo): el cajero solo ve sus ventas; el
   administrador anula con motivo. Plazo: hasta el día 9 del mes siguiente
   (RND 102600000025); después, «requiere trámite ante el SIN». */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var HOY = new Date(2026, 9, 6);
  var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  var filas = Array.prototype.slice.call($('filas-ventas').rows);

  // Cajero: solo sus ventas (en PHP lo filtra la consulta)
  function aplicarAlcance() {
    var cajero = P.rolActual() === 'cajero';
    filas.forEach(function (tr) {
      var ajena = cajero && tr.getAttribute('data-cajero') !== 'Mariela Suárez';
      tr.toggleAttribute('data-oculta-rol', ajena);
      if (ajena) tr.hidden = true;
    });
    document.querySelectorAll('.col-cajero').forEach(function (c) { c.hidden = cajero; });
    P.filtrarTabla('filas-ventas');
  }

  // Suma de lo cobrado (sin anuladas) en lo visible
  document.addEventListener('pahuichi:filtrado', function (e) {
    if (e.detail.id !== 'filas-ventas') return;
    var suma = filas.reduce(function (s, tr) {
      return tr.hidden || tr.getAttribute('data-estado') === 'anulada' ? s : s + parseFloat(tr.getAttribute('data-total'));
    }, 0);
    $('v-suma').textContent = 'Bs ' + P.bs(suma);
  });

  document.addEventListener('pahuichi:rol', aplicarAlcance);
  aplicarAlcance();

  /* Anulación */
  var modal = $('modal-anular');
  var filaActual = null;

  function limite(fechaIso) {
    var d = fechaIso.split('-').map(Number);
    return new Date(d[0], d[1], 9); // día 9 del mes siguiente
  }

  modal.addEventListener('pahuichi:abrir', function (e) {
    filaActual = e.detail.origen.closest('tr');
    var n = filaActual.getAttribute('data-factura');
    var lim = limite(filaActual.getAttribute('data-dia'));
    var enPlazo = HOY <= lim;
    $('anular-titulo').textContent = 'Anular la factura N° ' + n;
    $('anular-texto').textContent = enPlazo
      ? 'Puedes anularla directamente hasta el ' + lim.getDate() + ' de ' + MESES[lim.getMonth()] + ' de ' + lim.getFullYear() + '. La venta queda registrada como anulada.'
      : 'Se anulará en el sistema, pero ya no se puede anular directamente ante el SIN.';
    $('anular-tramite').hidden = enPlazo;
    $('motivo').value = '';
    P.marcarCampo('motivo', '');
  });

  $('confirmar-anular').addEventListener('click', function () {
    if (!$('motivo').value) {
      P.marcarCampo('motivo', 'Elige un motivo del catálogo del SIN.');
      $('motivo').focus();
      return;
    }
    var n = filaActual.getAttribute('data-factura');
    var tramite = !$('anular-tramite').hidden;
    filaActual.setAttribute('data-estado', 'anulada');
    filaActual.querySelector('.celda-estado').innerHTML = tramite
      ? '<span class="insignia insignia--aviso">Requiere trámite</span>'
      : '<span class="insignia insignia--peligro">Anulada</span>';
    filaActual.querySelector('.monto').classList.add('monto--anulado');
    var boton = filaActual.querySelector('[data-abrir-modal]');
    var reimprimir = filaActual.querySelector('.boton--icono');
    if (boton) boton.remove();
    modal.close();
    P.filtrarTabla('filas-ventas');
    P.aviso('Factura N° ' + n + (tramite ? ' marcada para trámite ante el SIN.' : ' anulada.'));
    if (reimprimir) reimprimir.focus();
  });
})();
