/* HU-10: imprimir los valores del snapshot, sin volver a elegir una leyenda. */
(function () {
  'use strict';
  var tickets = document.getElementById('tickets');
  if (!tickets) return;
  var enlace = document.querySelector('[data-campo="enlace-verificar"]');
  if (window.qrcode && enlace) {
    var qr = window.qrcode(0, 'M'); qr.addData(enlace.href); qr.make();
    document.querySelector('[data-campo="qr"]').innerHTML = qr.createSvgTag({ cellSize: 3, margin: 0, scalable: true });
  }
  document.querySelectorAll('[data-ancho]').forEach(function (b) {
    b.addEventListener('click', function () {
      tickets.classList.toggle('tickets--58', b.dataset.ancho === '58');
      document.querySelectorAll('[data-ancho]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
    });
  });
})();
