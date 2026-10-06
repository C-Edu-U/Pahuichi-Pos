/* Configuración (prototipo): guardado por sección, leyendas y catálogos. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };

  document.querySelectorAll('form[data-guardar]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (form.id === 'datos-fiscales') {
        var errores = {
          'e-nit': /^\d{7,13}$/.test($('e-nit').value.trim()) ? '' : 'El NIT tiene solo números, entre 7 y 13 dígitos.',
          'e-razon': $('e-razon').value.trim() ? '' : 'Escribe la razón social.'
        };
        var primero = null;
        Object.keys(errores).forEach(function (id) {
          P.marcarCampo(id, errores[id]);
          if (errores[id] && !primero) primero = id;
        });
        if (primero) { $(primero).focus(); return; }
      }
      P.aviso(form.getAttribute('data-guardar'));
    });
  });

  $('form-leyenda').addEventListener('submit', function (e) {
    e.preventDefault();
    var texto = $('l-texto').value.trim();
    P.marcarCampo('l-texto', texto ? '' : 'Escribe el texto de la leyenda.');
    if (!texto) { $('l-texto').focus(); return; }
    var li = document.createElement('li');
    li.className = 'leyenda-fila fila-nueva';
    li.innerHTML = '<span class="codigo"></span><p></p>';
    li.querySelector('.codigo').textContent = $('l-actividad').value;
    li.querySelector('p').textContent = texto;
    $('lista-leyendas').appendChild(li);
    $('cuenta-leyendas').textContent = $('lista-leyendas').children.length;
    this.reset();
    P.aviso('Leyenda agregada.');
    $('l-texto').focus();
  });

  $('sincronizar').addEventListener('click', function () {
    var b = this;
    b.setAttribute('aria-busy', 'true');
    b.disabled = true;
    window.setTimeout(function () {
      b.removeAttribute('aria-busy');
      b.disabled = false;
      $('ultima-sincronizacion').textContent = 'Última actualización: hoy, recién.';
      P.aviso('Catálogos del SIN actualizados.');
    }, 900);
  });
})();
