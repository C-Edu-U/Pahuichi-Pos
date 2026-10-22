/* Formulario de promoción: muestra solo los campos del tipo elegido (descuento o regalo).
   El servidor ignora los campos del otro tipo; sin JS, ambos grupos quedan visibles. */
(function () {
  'use strict';

  var form = document.getElementById('form-promocion');
  if (!form) return;

  function mostrar() {
    var elegido = form.querySelector('input[name="tipo"]:checked');
    var tipo = elegido ? elegido.value : 'descuento';
    form.querySelectorAll('[data-tipo-campos]').forEach(function (g) {
      g.hidden = g.getAttribute('data-tipo-campos') !== tipo;
    });
  }

  form.addEventListener('change', function (e) {
    if (e.target.name === 'tipo') mostrar();
  });
  mostrar();
})();
