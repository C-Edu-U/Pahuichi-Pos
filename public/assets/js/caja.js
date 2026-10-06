/* Caja (prototipo): apertura, arqueo por denominación y cierre.
   Efectivo esperado = monto inicial + ventas en efectivo − anuladas. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var ESPERADO = 1254.5;
  var CLAVE = 'pahuichi.turno';

  function estado() {
    try { return window.localStorage.getItem(CLAVE) || 'abierto'; } catch (e) { return 'abierto'; }
  }

  function fijar(e) {
    try { window.localStorage.setItem(CLAVE, e); } catch (x) { /* sin almacenamiento */ }
    pintarEstado();
  }

  function pintarEstado() {
    var abierto = estado() === 'abierto';
    $('caja-abierta').hidden = !abierto;
    $('caja-cerrada').hidden = abierto;
  }

  function recalcular() {
    var contado = 0;
    document.querySelectorAll('.control--conteo').forEach(function (input) {
      var n = parseInt(input.value.replace(/\D/g, ''), 10) || 0;
      var sub = Math.round(n * parseFloat(input.getAttribute('data-valor')) * 100) / 100;
      contado += sub;
      input.closest('tr').querySelector('[data-sub]').textContent = P.bs(sub);
    });
    contado = Math.round(contado * 100) / 100;
    $('contado').textContent = P.bs(contado);
    var dif = Math.round((contado - ESPERADO) * 100) / 100;
    var insignia = $('diferencia');
    if (dif === 0) {
      insignia.className = 'insignia insignia--ok';
      insignia.textContent = 'Cuadra';
    } else if (dif > 0) {
      insignia.className = 'insignia insignia--aviso';
      insignia.textContent = 'Sobran ' + P.bs(dif);
    } else {
      insignia.className = 'insignia insignia--peligro';
      insignia.textContent = 'Faltan ' + P.bs(-dif);
    }
    return dif;
  }

  document.querySelectorAll('.control--conteo').forEach(function (input) {
    input.addEventListener('input', function () {
      input.value = input.value.replace(/\D/g, '');
      recalcular();
    });
  });

  var modal = $('modal-cerrar');
  modal.addEventListener('pahuichi:abrir', function () {
    var dif = recalcular();
    $('modal-cerrar-texto').textContent = (dif === 0
      ? 'El arqueo cuadra.'
      : 'El arqueo tiene una diferencia: ' + (dif > 0 ? 'sobran' : 'faltan') + ' Bs ' + P.bs(Math.abs(dif)) + '. Quedará registrada con tu observación.') +
      ' Después de cerrar no podrás vender hasta abrir otro turno.';
  });

  modal.addEventListener('close', function () {
    if (modal.returnValue !== 'cerrar') return;
    fijar('cerrado');
    P.aviso($('resumen-menores').checked
      ? 'Turno cerrado. Se emitió la factura resumen de ventas menores (N° 130).'
      : 'Turno cerrado.');
    $('monto-inicial').focus();
  });

  $('form-abrir').addEventListener('submit', function (e) {
    e.preventDefault();
    var monto = P.leerMonto($('monto-inicial').value);
    if (monto === null) {
      P.marcarCampo('monto-inicial', 'Escribe el monto con el que empiezas, por ejemplo 200,00.');
      $('monto-inicial').focus();
      return;
    }
    P.marcarCampo('monto-inicial', '');
    fijar('abierto');
    P.aviso('Caja abierta con Bs ' + P.bs(monto) + '.');
  });

  pintarEstado();
  recalcular();
})();
