/* HU-10: interruptores de la demo con persistencia y mínimo validado en servidor. */
(function () {
  'use strict';
  var lista = document.getElementById('lista-leyendas');
  if (!lista) return;
  lista.addEventListener('submit', async function (e) {
    var form = e.target;
    e.preventDefault();
    var boton = form.querySelector('button');
    if (boton.disabled) return;
    boton.disabled = true;
    try {
      var r = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
      var d = await r.json();
      if (!r.ok) throw new Error(d.error || 'No se pudo guardar. Recarga y vuelve a intentarlo.');
      var activo = form.elements.activo.value === '1';
      boton.setAttribute('aria-checked', String(activo));
      boton.setAttribute('aria-label', boton.getAttribute('aria-label').replace(/^(Activar|Desactivar)/, activo ? 'Desactivar' : 'Activar'));
      form.elements.activo.value = activo ? '0' : '1';
      window.Pahuichi.aviso(d.aviso);
    } catch (err) { window.Pahuichi.aviso(err.message); }
    finally { boton.disabled = false; }
  });
})();
