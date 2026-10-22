/* HU-06: «Anular venta» pide el PIN del administrador y el servidor lo valida (con el
   mismo bloqueo por intentos que el descuento). Anular la venta en sí llega con HU-44:
   por ahora, con el PIN correcto solo se avisa y la venta no cambia. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var dialogo = $('modal-anular');
  if (!dialogo) return;

  function mostrar(error, aviso) {
    $('anular-error').textContent = error || '';
    $('anular-error').hidden = !error;
    $('anular-aviso').textContent = aviso || '';
    $('anular-aviso').hidden = !aviso;
    if (error) $('anular-pin').setAttribute('aria-invalid', 'true'); else $('anular-pin').removeAttribute('aria-invalid');
  }

  dialogo.addEventListener('pahuichi:abrir', function () {
    $('anular-pin').value = '';
    mostrar('', '');
    $('anular-autorizar').hidden = false;
    setTimeout(function () { $('anular-pin').focus(); }, 0);
  });

  $('form-anular').addEventListener('submit', async function (e) {
    e.preventDefault();
    var pin = $('anular-pin').value;
    if (!/^\d{4,6}$/.test(pin)) { mostrar('Escribe el PIN del administrador, de 4 a 6 dígitos.'); $('anular-pin').focus(); return; }
    $('anular-autorizar').disabled = true;
    try {
      var r = await fetch('/autorizaciones', { method: 'POST',
        headers: { 'X-CSRF-Token': document.body.getAttribute('data-csrf') || '', Accept: 'application/json' },
        body: new URLSearchParams({ accion: 'anulacion', pin: pin }) });
      var datos = await r.json();
      $('anular-pin').value = '';
      if (!r.ok) { mostrar(datos.error || 'No se pudo validar el PIN. Intenta otra vez.'); $('anular-pin').focus(); return; }
      mostrar('', datos.aviso);
      $('anular-autorizar').hidden = true;
    } catch (err) {
      mostrar('No hay conexión con el servidor. Intenta otra vez.');
    } finally {
      $('anular-autorizar').disabled = false;
    }
  });
})();
