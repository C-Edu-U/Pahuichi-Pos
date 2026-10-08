/* Monto a literal en español (espejo del servicio NumeroALetras):
   95.5 → "Noventa y cinco 50/100 Bolivianos". */
(function () {
  'use strict';

  var UNIDADES = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez',
    'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte',
    'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];
  var DECENAS = ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
  var CENTENAS = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

  function menorMil(n) {
    if (n === 0) return '';
    if (n === 100) return 'cien';
    var c = Math.floor(n / 100);
    var r = n % 100;
    var texto = CENTENAS[c];
    var resto;
    if (r < 30) resto = UNIDADES[r];
    else resto = DECENAS[Math.floor(r / 10)] + (r % 10 ? ' y ' + UNIDADES[r % 10] : '');
    return (texto + ' ' + resto).trim();
  }

  function entero(n) {
    if (n === 0) return 'cero';
    var millones = Math.floor(n / 1000000);
    var miles = Math.floor((n % 1000000) / 1000);
    var resto = n % 1000;
    var partes = [];
    if (millones) partes.push(millones === 1 ? 'un millón' : entero(millones) + ' millones');
    if (miles) partes.push(miles === 1 ? 'mil' : menorMil(miles).replace(/uno$/, 'un') + ' mil');
    if (resto) partes.push(menorMil(resto));
    return partes.join(' ');
  }

  window.numeroALetras = function (monto) {
    var n = Math.round(monto * 100);
    var texto = entero(Math.floor(n / 100));
    var centavos = String(n % 100).padStart(2, '0');
    return texto.charAt(0).toUpperCase() + texto.slice(1) + ' ' + centavos + '/100 Bolivianos';
  };
})();
