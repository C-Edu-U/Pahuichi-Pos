/* Categorías (prototipo): alta en línea y desactivación con confirmación. */
(function () {
  'use strict';

  var modal = document.getElementById('modal-desactivar');
  var anuncio = document.getElementById('anuncio');
  var filaActual = null;
  var nombreActual = '';

  // Al abrir: el texto del modal nombra la categoría y cuántos productos afecta
  modal.addEventListener('pahuichi:abrir', function (e) {
    var b = e.detail.origen;
    filaActual = b.closest('tr');
    nombreActual = b.getAttribute('data-nombre');
    var n = parseInt(b.getAttribute('data-productos'), 10);
    document.getElementById('modal-desactivar-titulo').textContent = '¿Desactivar «' + nombreActual + '»?';
    document.getElementById('modal-desactivar-texto').textContent = n === 0
      ? 'No tiene productos. Dejará de aparecer en el punto de venta y puedes volver a activarla cuando quieras.'
      : (n === 1 ? 'Su producto dejará' : 'Sus ' + n + ' productos dejarán') +
        ' de verse en el punto de venta. Puedes volver a activarla cuando quieras.';
  });

  modal.addEventListener('close', function () {
    if (modal.returnValue !== 'desactivar' || !filaActual) return;
    var activa = filaActual.querySelector('.celda-estado .insignia--ok');
    var boton = filaActual.querySelector('[data-abrir-modal]');
    if (activa) {
      filaActual.querySelector('.celda-estado').innerHTML = '<span class="insignia insignia--neutra">Inactiva</span>';
      boton.textContent = 'Activar';
      boton.removeAttribute('data-abrir-modal');
      boton.setAttribute('data-activar', '');
      anuncio.textContent = 'Categoría ' + nombreActual + ' desactivada.';
    }
    boton.focus();
  });

  // Reactivar no necesita confirmación: no hay nada que perder
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-activar]');
    if (!b) return;
    var tr = b.closest('tr');
    tr.querySelector('.celda-estado').innerHTML = '<span class="insignia insignia--ok">Activa</span>';
    b.textContent = 'Desactivar';
    b.removeAttribute('data-activar');
    b.setAttribute('data-abrir-modal', 'modal-desactivar');
    anuncio.textContent = 'Categoría ' + b.getAttribute('data-nombre') + ' activada.';
  });

  // Alta en línea
  var form = document.getElementById('form-categoria');
  var nombre = document.getElementById('cat-nombre');
  var error = document.getElementById('cat-nombre-error');
  var orden = document.getElementById('cat-orden');

  function existe(texto) {
    var t = texto.trim().toLowerCase();
    return Array.prototype.some.call(document.querySelectorAll('#filas-categorias .tabla__principal'), function (el) {
      return el.textContent.trim().toLowerCase() === t;
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var v = nombre.value.trim();
    var mensaje = !v ? 'Escribe el nombre de la categoría.' : (existe(v) ? 'Ya existe una categoría «' + v + '».' : '');
    if (mensaje) {
      error.innerHTML = '<svg class="icono icono--sm" aria-hidden="true"><use href="#i-error"></use></svg><span></span>';
      error.querySelector('span').textContent = mensaje;
      error.hidden = false;
      nombre.setAttribute('aria-invalid', 'true');
      nombre.focus();
      return;
    }
    error.hidden = true;
    nombre.removeAttribute('aria-invalid');

    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td class="col-num num"></td>' +
      '<td><span class="tabla__principal"></span><span class="tabla__secundario">Todavía sin productos</span></td>' +
      '<td class="col-num num">0</td>' +
      '<td class="celda-estado"><span class="insignia insignia--ok">Activa</span></td>' +
      '<td class="col-accion"><div class="acciones-fila"><button class="boton" type="button" data-abrir-modal="modal-desactivar" data-productos="0">Desactivar</button></div></td>';
    tr.querySelector('.col-num').textContent = orden.value || '';
    tr.querySelector('.tabla__principal').textContent = v;
    tr.querySelector('button').setAttribute('data-nombre', v);
    document.getElementById('filas-categorias').appendChild(tr);
    tr.classList.add('fila-nueva');
    anuncio.textContent = 'Categoría ' + v + ' agregada.';
    nombre.value = '';
    orden.value = String((parseInt(orden.value, 10) || 0) + 1);
    nombre.focus();
  });
})();
