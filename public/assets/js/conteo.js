/* Cierre ciego (HU-23): suma del conteo por denominación mientras el cajero cuenta.
   El total esperado no se calcula aquí: solo lo que el cajero anotó. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };

  var form = $('form-conteo');
  if (!form) return;

  var inputs = form.querySelectorAll('.control--conteo');
  var totalEl = $('conteo-total');
  if (!inputs.length) return;

  var recalcular = function () {
    var total = 0;
    for (var i = 0; i < inputs.length; i++) {
      var input = inputs[i];
      var valor = parseFloat(input.getAttribute('data-valor')) || 0;
      var cantidad = parseInt(input.value, 10);
      if (isNaN(cantidad) || cantidad < 0) cantidad = 0;
      var subtotal = Math.round(valor * cantidad * 100) / 100;
      total += subtotal;
      var clave = input.id.replace('conteo-', '');
      var celda = form.querySelector('[data-subtotal="' + clave + '"]');
      if (celda) celda.textContent = P.bs(subtotal);
    }
    if (totalEl) totalEl.textContent = P.bs(Math.round(total * 100) / 100);
  };

  for (var i = 0; i < inputs.length; i++) {
    inputs[i].addEventListener('input', recalcular);
    inputs[i].addEventListener('blur', function () {
      var v = parseInt(this.value, 10);
      this.value = isNaN(v) || v < 0 ? 0 : v;
      recalcular();
    });
  }

  form.addEventListener('submit', function (e) {
    var ok = true;
    for (var i = 0; i < inputs.length; i++) {
      var v = inputs[i].value.trim();
      if (v !== '' && !/^\d+$/.test(v)) { ok = false; inputs[i].focus(); break; }
    }
    if (!ok) {
      e.preventDefault();
      return;
    }
    recalcular();
  });

  recalcular();
})();
