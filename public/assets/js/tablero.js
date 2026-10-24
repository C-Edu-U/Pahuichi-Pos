/* Tablero del día (HU-64): cada minuto, mientras la página está a la vista, vuelve a
   pedir el tablero al servidor y reemplaza su contenido. Así el celular del dueño
   muestra las ventas al día sin recargar a mano. Si no hay red, deja lo último. */
(function () {
  'use strict';

  var tablero = document.getElementById('tablero');
  if (!tablero || !window.fetch || !window.DOMParser) return;

  async function actualizar() {
    if (document.hidden) return;
    try {
      var r = await fetch(window.location.pathname, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
      if (!r.ok || r.redirected) return;   // sesión vencida: el próximo clic lleva al login
      var nuevo = new DOMParser().parseFromString(await r.text(), 'text/html').getElementById('tablero');
      if (nuevo) tablero.innerHTML = nuevo.innerHTML;
    } catch (e) { /* sin red: queda la última versión */ }
  }

  setInterval(actualizar, 60000);
  // Al volver a la pestaña (o desbloquear el celular), se pone al día enseguida
  document.addEventListener('visibilitychange', function () { if (!document.hidden) actualizar(); });
})();
