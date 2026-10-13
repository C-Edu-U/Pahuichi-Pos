/* Factura (prototipo): elige formato de impresión e imprime.
   En PHP la vista sale del snapshot_json guardado al emitir. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var factura = document.getElementById('factura');
  var n = P.parametro('n');

  if (n && /^\d+$/.test(n)) {
    document.getElementById('numero-factura').textContent = n;
    document.getElementById('titulo-factura').textContent = 'Factura N° ' + n;
    document.title = 'Factura N° ' + n + ' · Pahuichi POS';
  }

  function formato(f) {
    factura.classList.toggle('factura--rollo', f === 'rollo');
    factura.classList.toggle('factura--media', f === 'media');
    document.body.setAttribute('data-formato', f);
    document.querySelectorAll('[data-formato]').forEach(function (b) {
      if (b.tagName === 'BUTTON') b.setAttribute('aria-pressed', String(b.getAttribute('data-formato') === f));
    });
  }

  document.querySelectorAll('button[data-formato]').forEach(function (b) {
    b.addEventListener('click', function () { formato(b.getAttribute('data-formato')); });
  });

  document.getElementById('imprimir').addEventListener('click', function () { window.print(); });

  formato(P.parametro('formato') === 'media' ? 'media' : 'rollo');
})();
