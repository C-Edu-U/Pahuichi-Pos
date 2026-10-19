/* Punto de venta (HU-28): pestañas de categoría y búsqueda instantánea sobre la
   cuadrícula que dibuja el servidor. Favoritos es la primera pantalla; la
   búsqueda recorre todos los productos sin importar la pestaña. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var grilla = $('grilla');
  if (!grilla) return;

  var buscador = $('pos-buscar');
  var barra = $('categorias-pos');
  var items = Array.prototype.slice.call(grilla.children);
  var categoria = grilla.getAttribute('data-inicial') || 'todo';

  function visible(li, q) {
    if (q) return P.normalizar(li.getAttribute('data-busqueda')).indexOf(q) !== -1;
    if (categoria === 'favoritos') return li.getAttribute('data-favorito') === '1';
    if (categoria === 'todo') return true;
    return li.getAttribute('data-categoria') === categoria;
  }

  function filtrar() {
    var q = P.normalizar(buscador.value.trim());
    var n = 0;
    items.forEach(function (li) {
      var ok = visible(li, q);
      li.hidden = !ok;
      if (ok) n++;
    });
    $('pos-vacio').hidden = n !== 0;
    return n;
  }

  barra.addEventListener('click', function (e) {
    var b = e.target.closest('[data-categoria]');
    if (!b) return;
    categoria = b.getAttribute('data-categoria');
    barra.querySelectorAll('[data-categoria]').forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
    buscador.value = '';
    filtrar();
  });

  buscador.addEventListener('input', function () {
    var n = filtrar();
    $('anuncio').textContent = buscador.value.trim() ? (n === 1 ? '1 producto' : n + ' productos') : '';
  });

  window.PahuichiPos = { filtrar: filtrar };
})();
