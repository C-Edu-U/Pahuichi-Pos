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

  var fiscal = $('fiscal');
  if (fiscal) {
    ['f-cuis', 'f-control'].forEach(function (id) {
      $(id).addEventListener('input', function () { this.value = this.value.toUpperCase(); });
    });
    fiscal.addEventListener('submit', function (e) {
      validar(fiscal, {
        'f-sucursal': function (v) { return /^\d{1,4}$/.test(v) ? '' : 'Escribe un número de 0 a 9999.'; },
        'f-pv': function (v) { return /^\d{1,4}$/.test(v) ? '' : 'Escribe un número de 0 a 9999.'; },
        'f-cuis': function (v) { return /^[A-Z0-9]{4,20}$/.test(v) ? '' : 'El CUIS tiene de 4 a 20 letras o números.'; },
        'f-control': function (v) { return /^[A-F0-9]{8,40}$/.test(v) ? '' : 'El código de control tiene de 8 a 40 caracteres hexadecimales (0-9 y A-F).'; },
        'f-vigencia': function (v) { return v ? '' : 'Elige la fecha y la hora de vigencia.'; },
        'f-inicial': function (v) { return /^[1-9]\d{0,8}$/.test(v) ? '' : 'El número inicial es un entero desde 1.'; }
      }, e);
    });
  }

  window.PahuichiConfig = { validar: validar };
})();
