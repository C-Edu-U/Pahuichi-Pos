/* Caja: foco en el aviso de bloqueo, formato de los montos y validación del movimiento.
   Las reglas (una sola caja abierta, turno, cajero, motivo obligatorio) las aplica el servidor. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };

  var bloqueo = $('abrir-bloqueo');
  if (bloqueo) bloqueo.focus();

  /* Formato del monto al salir del campo: 50 -> 50,00 */
  var formatear = function (campo) {
    if (!campo) return;
    campo.addEventListener('blur', function () {
      var v = P.leerMonto(campo.value);
      if (v !== null) campo.value = P.bs(v);
    });
  };

  var fondo = $('fondo');
  if (fondo) {
    formatear(fondo);
    $('form-abrir').addEventListener('submit', function (e) {
      var ok = P.leerMonto(fondo.value) !== null;
      P.marcarCampo('fondo', ok ? '' : 'Escribe el fondo inicial, por ejemplo 300,00.');
      if (!ok) { e.preventDefault(); fondo.focus(); }
    });
  }

  var monto = $('mov-monto');
  if (monto) {
    formatear(monto);
    $('form-movimiento').addEventListener('submit', function (e) {
      var ok = P.leerMonto(monto.value) !== null;
      P.marcarCampo('monto', ok ? '' : 'Escribe el monto, por ejemplo 50,00.');
      if (!ok) { e.preventDefault(); monto.focus(); }
    });
  }
})();
