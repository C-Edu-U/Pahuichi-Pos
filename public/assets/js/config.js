/* Configuración: validación previa de los formularios (el servidor valida igual). */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };

  function validar(form, reglas, e) {
    var primero = null;
    Object.keys(reglas).forEach(function (id) {
      var mensaje = reglas[id]($(id).value.trim());
      P.marcarCampo(id, mensaje);
      if (mensaje && !primero) primero = id;
    });
    if (primero) { e.preventDefault(); $(primero).focus(); }
  }

  var negocio = $('negocio');
  if (negocio) {
    negocio.addEventListener('submit', function (e) {
      validar(negocio, {
        'n-razon': function (v) { return v ? '' : 'Escribe la razón social.'; },
        'n-comercial': function (v) { return v ? '' : 'Escribe el nombre comercial.'; },
        'n-nit': function (v) { return /^\d{7,13}$/.test(v.replace(/\s/g, '')) ? '' : 'El NIT tiene solo números, entre 7 y 13 dígitos.'; },
        'n-direccion': function (v) { return v ? '' : 'Escribe la dirección.'; },
        'n-municipio': function (v) { return v ? '' : 'Escribe el municipio.'; }
      }, e);
      var logo = $('n-logo').files[0];
      if (!e.defaultPrevented && logo && logo.size > 1048576) {
        P.marcarCampo('n-logo', 'La imagen pesa más de 1 MB. Elige una más liviana.');
        e.preventDefault();
        $('n-logo').focus();
      }
    });
  }

  window.PahuichiConfig = { validar: validar };
})();
