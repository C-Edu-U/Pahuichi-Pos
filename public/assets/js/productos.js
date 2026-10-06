/* Productos: búsqueda, filtros y «agotado hoy» (prototipo; en PHP el filtro
   puede seguir en el cliente y el cambio de estado será un POST con CSRF). */
(function () {
  'use strict';

  var filas = Array.prototype.slice.call(document.querySelectorAll('#filas tr'));
  var buscar = document.getElementById('buscar');
  var estado = document.getElementById('estado');
  var pestanas = Array.prototype.slice.call(document.querySelectorAll('.pestana[data-categoria]'));
  var cuenta = document.getElementById('cuenta');
  var vacio = document.getElementById('vacio');
  var vacioTexto = document.getElementById('vacio-texto');
  var tabla = document.querySelector('.tabla--productos');
  var anuncio = document.getElementById('anuncio');
  var categoria = '';

  function normalizar(texto) {
    return texto.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  function filtrar() {
    var q = normalizar(buscar.value.trim());
    var e = estado.value;
    var visibles = 0;

    filas.forEach(function (tr) {
      var texto = normalizar(tr.querySelector('td').textContent);
      var ok = (!q || texto.indexOf(q) !== -1) &&
        (!categoria || tr.getAttribute('data-categoria') === categoria) &&
        (!e ||
          (e === 'sin-sin' && tr.hasAttribute('data-sin-codigo')) ||
          (e !== 'sin-sin' && tr.getAttribute('data-estado') === e));
      tr.hidden = !ok;
      if (ok) visibles++;
    });

    cuenta.textContent = visibles === 1 ? '1 producto' : visibles + ' productos';
    tabla.hidden = visibles === 0;
    vacio.hidden = visibles !== 0;
    if (visibles === 0) {
      vacioTexto.textContent = q
        ? 'No hay productos con «' + buscar.value.trim() + '» en este filtro. Prueba con otro nombre o código.'
        : 'No hay productos en este filtro.';
    }
  }

  pestanas.forEach(function (p) {
    p.addEventListener('click', function () {
      categoria = p.getAttribute('data-categoria');
      pestanas.forEach(function (o) { o.setAttribute('aria-pressed', String(o === p)); });
      filtrar();
    });
  });

  buscar.addEventListener('input', filtrar);
  estado.addEventListener('change', filtrar);

  document.getElementById('limpiar').addEventListener('click', function () {
    buscar.value = '';
    estado.value = '';
    categoria = '';
    pestanas.forEach(function (o, i) { o.setAttribute('aria-pressed', String(i === 0)); });
    filtrar();
    buscar.focus();
  });

  // Agotado hoy: alterna el estado de la fila
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('.boton-agotado');
    if (!b) return;
    var tr = b.closest('tr');
    var agotado = b.getAttribute('aria-pressed') !== 'true';
    var nombre = tr.querySelector('.tabla__principal').textContent;
    b.setAttribute('aria-pressed', String(agotado));
    tr.setAttribute('data-estado', agotado ? 'agotado' : 'disponible');
    var celda = tr.querySelector('.celda-estado');
    celda.innerHTML = agotado
      ? '<span class="insignia insignia--aviso">Agotado hoy</span>'
      : '<span class="insignia insignia--ok">Disponible</span>';
    anuncio.textContent = agotado ? nombre + ' marcado como agotado hoy.' : nombre + ' vuelve a estar disponible.';
  });

  // Filtros desde la URL (p. ej. desde el escritorio)
  var desdeUrl = window.Pahuichi.parametro('estado');
  if (desdeUrl && estado.querySelector('option[value="' + desdeUrl + '"]')) estado.value = desdeUrl;
  if (window.Pahuichi.parametro('guardado')) document.querySelector('.aviso-guardado').hidden = false;

  filtrar();
})();
