/* Clientes (prototipo): alta con validación por tipo de documento. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var form = $('form-cliente');
  var siguiente = 7;

  // El complemento solo aplica a la cédula de identidad
  $('c-tipo').addEventListener('change', function () {
    $('campo-complemento').hidden = this.value !== 'CI';
  });

  $('c-complemento').addEventListener('input', function () { this.value = this.value.toUpperCase(); });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var tipo = $('c-tipo').value;
    var numero = $('c-numero').value.trim();
    var nombre = $('c-nombre').value.trim();
    var email = $('c-email').value.trim();
    var errores = {
      'c-numero': !numero ? 'Escribe el número de documento.'
        : (tipo === 'NIT' || tipo === 'CI') && !/^\d{5,13}$/.test(numero) ? 'Solo números, entre 5 y 13 dígitos.'
        : /^9900[123]$/.test(numero) ? 'Ese es un código especial del SIN, no un cliente.' : '',
      'c-nombre': nombre ? '' : 'Escribe el nombre o la razón social.',
      'c-email': email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? 'Revisa el correo, por ejemplo nombre@dominio.bo.' : ''
    };
    var primero = null;
    Object.keys(errores).forEach(function (id) {
      P.marcarCampo(id, errores[id]);
      if (errores[id] && !primero) primero = id;
    });
    if (primero) { $(primero).focus(); return; }

    var comp = tipo === 'CI' && $('c-complemento').value ? '-' + $('c-complemento').value : '';
    var codigo = 'CLI-' + String(siguiente++).padStart(4, '0');
    var tr = document.createElement('tr');
    tr.setAttribute('data-tipo', tipo);
    tr.className = 'fila-nueva';
    tr.innerHTML = '<td><span class="tabla__principal"></span><span class="tabla__secundario codigo"></span></td>' +
      '<td><span class="insignia insignia--neutra"></span> <span class="num"></span></td><td></td>' +
      '<td class="col-num num">0</td><td>Sin compras</td><td class="col-accion"></td>';
    tr.querySelector('.tabla__principal').textContent = nombre;
    tr.querySelector('.codigo').textContent = codigo;
    tr.querySelector('.insignia').textContent = tipo;
    tr.querySelector('.num').textContent = numero + comp;
    tr.cells[2].textContent = email || 'Sin correo';
    if (!email) tr.cells[2].className = 'celda-vacia';
    $('filas-clientes').insertBefore(tr, $('filas-clientes').firstChild);
    form.reset();
    $('campo-complemento').hidden = false;
    P.filtrarTabla('filas-clientes');
    P.aviso('Cliente ' + nombre + ' registrado (' + codigo + ').');
    $('c-numero').focus();
  });
})();
