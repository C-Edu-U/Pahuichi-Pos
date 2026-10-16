/* Ventas y facturas (demo). Anular exige motivo y PIN del administrador
   (RF-05, RF-43): la factura queda ANULADA y conserva su número (RN-11). */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var filas = Array.prototype.slice.call($('filas-ventas').rows);

  // El cajero no filtra por cajero; la columna sobra
  function aplicarAlcance() {
    var admin = P.puede('ventas.todas');
    document.querySelectorAll('.col-cajero').forEach(function (c) { c.hidden = !admin; });
    P.filtrarTabla('filas-ventas');
  }

  // Suma de lo vendido (sin anuladas) en lo visible
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

  modal.addEventListener('pahuichi:abrir', function (e) {
    filaActual = e.detail.origen.closest('tr');
    var n = filaActual.getAttribute('data-factura');
    $('anular-titulo').textContent = 'Anular la venta con factura N° ' + n;
    $('form-anular').reset();
    P.marcarCampo('motivo', '');
    P.marcarCampo('anular-pin', '');
  });

  $('confirmar-anular').addEventListener('click', function () {
    var errores = {
      motivo: $('motivo').value ? '' : 'Elige un motivo.',
      'anular-pin': $('anular-pin').value === '1234' ? '' : 'PIN de administrador incorrecto.'
    };
    var primero = null;
    Object.keys(errores).forEach(function (id) { P.marcarCampo(id, errores[id]); if (errores[id] && !primero) primero = id; });
    if (primero) { $(primero).focus(); return; }

    var n = filaActual.getAttribute('data-factura');
    filaActual.setAttribute('data-estado', 'anulada');
    filaActual.querySelector('.celda-estado').innerHTML = '<span class="insignia insignia--peligro">Anulada</span>';
    filaActual.querySelector('.monto').classList.add('monto--anulado');
    var boton = filaActual.querySelector('[data-abrir-modal]');
    var reimprimir = filaActual.querySelector('a.boton');
    if (boton) boton.remove();
    modal.close();
    P.filtrarTabla('filas-ventas');
    P.aviso('Venta anulada. La factura N° ' + n + ' queda ANULADA con su número.');
    if (reimprimir) reimprimir.focus();
  });
})();
