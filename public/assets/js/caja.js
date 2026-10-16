/* Caja: foco en el aviso de bloqueo y formato del fondo inicial.
   Las reglas (una sola caja abierta, turno, cajero) las aplica el servidor. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };

  var bloqueo = $('abrir-bloqueo');
  if (bloqueo) bloqueo.focus();

  var fondo = $('fondo');
  if (!fondo) return;

  fondo.addEventListener('blur', function () {
    var v = P.leerMonto(fondo.value);
    if (v !== null) fondo.value = P.bs(v);
  });

  $('form-abrir').addEventListener('submit', function (e) {
    var ok = P.leerMonto(fondo.value) !== null;
    P.marcarCampo('fondo', ok ? '' : 'Escribe el fondo inicial, por ejemplo 300,00.');
    if (!ok) { e.preventDefault(); fondo.focus(); }
  });
})();
