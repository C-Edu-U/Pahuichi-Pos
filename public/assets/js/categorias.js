/* Categorías (demo): orden, color con contraste AA y activar/desactivar. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var D = window.PahuichiDatos;
  var $ = function (id) { return document.getElementById(id); };

  // Paleta cerrada: texto blanco con contraste 4,9:1 a 10,4:1
  var PALETA = [
    ['#8a5300', 'Ámbar'], ['#1062a8', 'Azul'], ['#8e2c7c', 'Ciruela'], ['#6e2a1e', 'Vino'],
    ['#3f6f1f', 'Oliva'], ['#4f5a54', 'Gris'], ['#13603e', 'Verde'], ['#4a6fb5', 'Pizarra']
  ];

  PALETA.forEach(function (c, i) {
    var label = document.createElement('label');
    label.className = 'color-opcion';
    label.innerHTML = '<input type="radio" name="color" value="' + c[0] + '"' + (i === 6 ? ' checked' : '') + '><span class="color-opcion__muestra" aria-hidden="true"></span><span class="visualmente-oculto"></span>';
    label.querySelector('.color-opcion__muestra').style.setProperty('--muestra', c[0]);
    label.querySelector('.visualmente-oculto').textContent = c[1];
    $('colores').appendChild(label);
  });

  function vista() {
    var color = document.querySelector('[name="color"]:checked').value;
    $('vista-boton').style.setProperty('--cat', color);
    $('vista-boton').textContent = $('cat-nombre').value.trim() || 'Nueva categoría';
  }
  $('colores').addEventListener('change', vista);
  $('cat-nombre').addEventListener('input', vista);
  vista();

  function fila(c, cantidad, activa) {
    var tr = document.createElement('tr');
    tr.innerHTML = '<td class="col-num num"></td><td><span class="categoria-pos categoria-pos--tabla" aria-pressed="true"></span></td><td class="col-num num"></td>' +
      '<td class="celda-estado"></td><td class="col-accion"><div class="acciones-fila" data-requiere="catalogo.editar"><button class="boton" type="button"></button></div></td>';
    tr.cells[0].textContent = c.orden;
    var boton = tr.querySelector('.categoria-pos');
    boton.textContent = c.nombre;
    boton.style.setProperty('--cat', c.color);
    tr.cells[2].textContent = cantidad;
    var accion = tr.querySelector('button');
    accion.setAttribute('data-nombre', c.nombre);
    accion.setAttribute('data-productos', cantidad);
    estado(tr, activa);
    accion.hidden = !P.puede('catalogo.editar');
    return tr;
  }

  function estado(tr, activa) {
    tr.querySelector('.celda-estado').innerHTML = activa ? '<span class="insignia insignia--ok">Activa</span>' : '<span class="insignia insignia--neutra">Inactiva</span>';
    var b = tr.querySelector('.col-accion button');
    b.textContent = activa ? 'Desactivar' : 'Activar';
    if (activa) b.setAttribute('data-abrir-modal', 'modal-desactivar'); else b.removeAttribute('data-abrir-modal');
    b.toggleAttribute('data-activar', !activa);
  }

  D.categorias.forEach(function (c) {
    var n = D.productos.filter(function (p) { return p.categoria === c.id; }).length;
    $('filas-categorias').appendChild(fila(c, n, true));
  });

  var modal = $('modal-desactivar');
  var actual = null;
  modal.addEventListener('pahuichi:abrir', function (e) {
    actual = e.detail.origen.closest('tr');
    var nombre = e.detail.origen.getAttribute('data-nombre');
    var n = parseInt(e.detail.origen.getAttribute('data-productos'), 10);
    $('modal-desactivar-titulo').textContent = '¿Desactivar «' + nombre + '»?';
    $('modal-desactivar-texto').textContent = (n === 1 ? 'Su producto dejará' : 'Sus ' + n + ' productos dejarán') + ' de verse en el punto de venta. Puedes volver a activarla cuando quieras.';
  });
  modal.addEventListener('close', function () {
    if (modal.returnValue === 'desactivar' && actual) {
      estado(actual, false);
      $('anuncio').textContent = 'Categoría desactivada.';
      actual.querySelector('.col-accion button').focus();
    }
  });

  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-activar]');
    if (!b) return;
    estado(b.closest('tr'), true);
    $('anuncio').textContent = 'Categoría activada.';
  });

  $('form-categoria').addEventListener('submit', function (e) {
    e.preventDefault();
    var nombre = $('cat-nombre').value.trim();
    var existe = D.categorias.some(function (c) { return c.nombre.toLowerCase() === nombre.toLowerCase(); });
    var msg = !nombre ? 'Escribe el nombre de la categoría.' : existe ? 'Ya existe la categoría «' + nombre + '».' : '';
    P.marcarCampo('cat-nombre', msg);
    if (msg) { $('cat-nombre').focus(); return; }
    var c = { nombre: nombre, orden: $('cat-orden').value || '', color: document.querySelector('[name="color"]:checked').value };
    D.categorias.push(c);
    var tr = fila(c, 0, true);
    tr.classList.add('fila-nueva');
    $('filas-categorias').appendChild(tr);
    P.aviso('Categoría ' + nombre + ' agregada.');
    $('cat-nombre').value = '';
    $('cat-orden').value = String((parseInt($('cat-orden').value, 10) || 0) + 1);
    vista();
    $('cat-nombre').focus();
  });
})();
