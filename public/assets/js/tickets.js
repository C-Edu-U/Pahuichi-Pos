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
  // Mantener el modo factura hasta que el navegador termine o cancele el diálogo.
  window.addEventListener('afterprint', function () { document.body.classList.remove('solo-factura'); });
  document.getElementById('descargar-pdf').addEventListener('click', function () {
    document.body.classList.add('solo-factura'); window.print();
  });
  document.getElementById('reimprimir').addEventListener('click', async function () {
    var boton = this; boton.disabled = true;
    try {
      var r = await fetch('/facturas/' + tickets.dataset.factura + '/reimpresion', { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-Token': document.body.dataset.csrf } });
      if (!r.ok) throw new Error('No se pudo registrar la reimpresión. Recarga e inténtalo de nuevo.');
      document.querySelectorAll('.ticket__reimpresion').forEach(function (p) { p.hidden = false; });
      document.getElementById('aviso-reimpresion').hidden = false;
      document.body.classList.remove('solo-factura'); window.print();
    } catch (err) { window.Pahuichi.aviso(err.message); }
    finally { boton.disabled = false; }
  });
})();
