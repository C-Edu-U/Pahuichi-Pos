/* Catálogo: filtros por categoría, búsqueda y estado sobre la tabla que dibuja
   el servidor. Favorito y agotado se marcan de un toque sin recargar; si el
   pedido falla, el formulario se envía de la forma normal. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var categoria = '';
  var filas = Array.prototype.slice.call($('filas').rows);

  function cumple(tr, estado) {
    var d = tr.dataset;
    if (estado === 'favorito') return d.favorito === '1';
    if (estado === 'agotado') return d.agotado === '1';
    if (estado === 'inactivo') return d.activo === '0';
    if (estado === 'cocina') return d.cocina === '1';
    return true;
  }

  function filtrar() {
    var q = P.normalizar($('buscar').value.trim());
    var estado = $('estado').value;
    var n = 0;
    filas.forEach(function (tr) {
      var ok = (!q || P.normalizar(tr.dataset.busqueda).indexOf(q) !== -1) &&
        (!categoria || tr.dataset.categoria === categoria) && cumple(tr, estado);
      tr.hidden = !ok;
      if (ok) n++;
    });
    $('cuenta').textContent = n === 1 ? '1 producto' : n + ' productos';
    $('vacio').hidden = n !== 0;
    document.querySelector('.tabla--catalogo').hidden = n === 0;
  }

  $('pestanas').addEventListener('click', function (e) {
    var b = e.target.closest('[data-categoria]');
    if (!b) return;
    categoria = b.getAttribute('data-categoria');
    $('pestanas').querySelectorAll('[data-categoria]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
    filtrar();
  });
  $('buscar').addEventListener('input', filtrar);
  $('estado').addEventListener('change', filtrar);
  $('limpiar').addEventListener('click', function () {
    $('buscar').value = '';
    $('estado').value = '';
    $('pestanas').querySelector('[data-categoria=""]').click();
  });

  // Favorito y agotado: un toque, sin recargar la página
  $('filas').addEventListener('submit', function (e) {
    var form = e.target.closest('[data-marca]');
    if (!form || !window.fetch) return;
    e.preventDefault();
    var campo = form.getAttribute('data-marca');
    var boton = form.querySelector('button');
    var tr = form.closest('tr');
    boton.disabled = true;
    fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' } })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then(function (datos) {
        boton.setAttribute('aria-pressed', String(datos[campo]));
        tr.dataset[campo] = datos[campo] ? '1' : '0';
        if (campo === 'agotado' && tr.dataset.activo === '1') {
          tr.querySelector('.celda-estado').innerHTML = datos.agotado
            ? '<span class="insignia insignia--aviso">Agotado</span>'
            : '<span class="insignia insignia--ok">Disponible</span>';
        }
        $('anuncio').textContent = datos.mensaje;
        P.aviso(datos.mensaje);
        filtrar();
      })
      .catch(function () { form.submit(); })
      .then(function () { boton.disabled = false; });
  });

  filtrar();
})();
