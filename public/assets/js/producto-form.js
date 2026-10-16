/* Producto: ayudas del formulario. El servidor valida y guarda; aquí solo el
   código en mayúsculas, el precio con formato y el aviso previo de errores. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };

  var resumen = $('resumen-errores');
  if (resumen) resumen.focus();

  var codigo = $('codigo');
  if (!codigo.readOnly) {
    codigo.addEventListener('input', function () { this.value = this.value.toUpperCase(); });
  }

  $('precio').addEventListener('blur', function () {
    var v = P.leerMonto(this.value);
    if (v !== null) this.value = P.bs(v);
  });

  var REGLAS = {
    codigo: function (v) {
      if (codigo.readOnly) return '';
      if (!v) return 'Escribe el código.';
      return /^[A-Z0-9-]{2,12}$/.test(v) ? '' : 'Usa de 2 a 12 letras, números o guion, sin espacios.';
    },
    categoria: function (v) { return v ? '' : 'Elige una categoría activa.'; },
    nombre: function (v) { return v ? '' : 'Escribe el nombre del producto.'; },
    corto: function (v) { return v ? '' : 'Escribe el nombre corto para el botón.'; },
    precio: function (v) {
      if (!v) return 'Escribe el precio.';
      var n = P.leerMonto(v);
      return n !== null && n > 0 ? '' : 'Escribe un precio mayor a 0, por ejemplo 45,50.';
    }
  };

  $('form-producto').addEventListener('submit', function (e) {
    var primero = null;
    Object.keys(REGLAS).forEach(function (id) {
      var mensaje = REGLAS[id]($(id).value.trim());
      P.marcarCampo(id, mensaje);
      if (mensaje && !primero) primero = id;
    });
    if (primero) { e.preventDefault(); $(primero).focus(); return; }
    $('guardar').setAttribute('aria-busy', 'true');
  });
})();
