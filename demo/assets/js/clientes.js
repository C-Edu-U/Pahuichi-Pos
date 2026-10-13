/* Clientes (demo): alta con validación por tipo de documento. Único por
   NIT/CI y complemento. Desde el POS llega ?nuevo=NUMERO para registrar. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var form = $('form-cliente');

  // El complemento solo aplica a la cédula de identidad
  $('c-tipo').addEventListener('change', function () {
    $('campo-complemento').hidden = this.value !== 'CI';
  });
  $('c-complemento').addEventListener('input', function () { this.value = this.value.toUpperCase(); });

  function existe(numero, comp) {
    return Array.prototype.some.call($('filas-clientes').rows, function (tr) {
      return tr.cells[1].textContent.replace(/^\s*\S+\s+/, '').trim() === numero + (comp ? '-' + comp : '');
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var tipo = $('c-tipo').value;
    var numero = $('c-numero').value.trim();
    var nombre = $('c-nombre').value.trim();
    var comp = tipo === 'CI' ? $('c-complemento').value.trim() : '';
    var errores = {
      'c-numero': !numero ? 'Escribe el número de documento.'
        : (tipo === 'NIT' || tipo === 'CI') && !/^\d{5,13}$/.test(numero) ? 'Solo números, entre 5 y 13 dígitos.'
        : existe(numero, comp) ? 'Ya hay un cliente con ese documento.' : '',
      'c-nombre': nombre ? '' : 'Escribe el nombre o la razón social.'
    };
    var primero = null;
    Object.keys(errores).forEach(function (id) {
      P.marcarCampo(id, errores[id]);
      if (errores[id] && !primero) primero = id;
    });
    if (primero) { $(primero).focus(); return; }

    var nac = $('c-nacimiento').value;
    var tr = document.createElement('tr');
    tr.setAttribute('data-tipo', tipo);
    tr.className = 'fila-nueva';
    tr.innerHTML = '<td><span class="tabla__principal"></span></td>' +
      '<td><span class="insignia insignia--neutra"></span> <span class="num"></span></td><td></td>' +
      '<td class="col-num num">0</td><td class="celda-vacia">Sin compras</td><td class="col-accion"></td>';
    tr.querySelector('.tabla__principal').textContent = nombre;
    tr.querySelector('.insignia').textContent = tipo;
    tr.querySelector('.num').textContent = numero + (comp ? '-' + comp : '');
    tr.cells[2].textContent = nac ? nac.slice(8, 10) + '/' + nac.slice(5, 7) : 'Sin dato';
    if (!nac) tr.cells[2].className = 'celda-vacia';
    $('filas-clientes').insertBefore(tr, $('filas-clientes').firstChild);
    form.reset();
    $('campo-complemento').hidden = false;
    P.filtrarTabla('filas-clientes');
    P.aviso('Cliente ' + nombre + ' registrado.');
    $('c-numero').focus();
  });

  // Alta directa desde el POS
  var nuevo = P.parametro('nuevo');
  if (nuevo && P.puede('clientes.crear')) {
    form.hidden = false;
    $('c-numero').value = nuevo.replace(/\D/g, '');
    $('c-nombre').focus();
  }
})();
