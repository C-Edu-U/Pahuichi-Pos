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
  var menu = document.querySelector('.tickets-menu');
  if (menu) {
    document.addEventListener('click', function (evento) { if (!menu.contains(evento.target)) menu.open = false; });
    document.addEventListener('keydown', function (evento) {
      if (evento.key === 'Escape' && menu.open) { menu.open = false; menu.querySelector('summary').focus(); }
    });
  }
})();
