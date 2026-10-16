/* Formulario de promoción (demo): los campos cambian según el tipo. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var D = window.PahuichiDatos;
  var $ = function (id) { return document.getElementById(id); };

  function opciones(select, incluirCategorias) {
    if (incluirCategorias) {
      var g = document.createElement('optgroup');
      g.label = 'Categorías';
      D.categorias.forEach(function (c) {
        var o = document.createElement('option');
        o.value = 'cat:' + c.id;
        o.textContent = 'Toda la categoría ' + c.nombre;
        g.appendChild(o);
      });
      select.appendChild(g);
    }
    var gp = document.createElement('optgroup');
    gp.label = 'Productos';
    D.productos.forEach(function (p) {
      var o = document.createElement('option');
      o.value = p.id;
      o.textContent = p.nombre + ' · Bs ' + P.bs(p.precio);
      gp.appendChild(o);
    });
    select.appendChild(gp);
  }

  opciones($('d-aplica'), true);
  ['c-uno', 'c-dos', 'r-compra', 'r-regalo'].forEach(function (id) { opciones($(id), false); });
  $('c-uno').value = 'PL-01';
  $('c-dos').value = 'RE-01';
  $('r-compra').value = 'PL-03';
  $('r-regalo').value = 'RE-01';

  function tipo() { return document.querySelector('[name="tipo"]:checked').value; }

  function mostrarTipo() {
    document.querySelectorAll('[data-tipo-campos]').forEach(function (f) { f.hidden = f.getAttribute('data-tipo-campos') !== tipo(); });
  }
  document.querySelectorAll('[name="tipo"]').forEach(function (r) { r.addEventListener('change', mostrarTipo); });

  function precio(id) { return D.productos.filter(function (p) { return p.id === id; })[0].precio; }
  function normal() {
    $('c-normal').textContent = 'Bs ' + P.bs(precio($('c-uno').value) + precio($('c-dos').value));
  }
  ['c-uno', 'c-dos'].forEach(function (id) { $(id).addEventListener('change', normal); });
  normal();

  // Edición de una promoción existente
  var id = parseInt(P.parametro('id'), 10);
  var promo = D.promociones.filter(function (p) { return p.id === id; })[0];
  if (promo) {
    $('titulo-promo').textContent = 'Editar promoción';
    $('p-nombre').value = promo.nombre;
    document.querySelector('[name="tipo"][value="' + promo.tipo + '"]').checked = true;
    $('h-desde').value = promo.desde;
    $('h-hasta').value = promo.hasta === '24:00' ? '23:59' : promo.hasta;
    $('m-acumulable').checked = promo.acumulable;
    $('m-prioridad').value = promo.prioridad;
    $('m-tope').value = promo.topeDia || '';
    document.querySelectorAll('.dias input').forEach(function (c) { c.checked = promo.dias.indexOf(parseInt(c.value, 10)) !== -1; });
    if (promo.tipo === 'combo') { $('c-uno').value = promo.componentes[0]; $('c-dos').value = promo.componentes[1]; $('c-precio').value = P.bs(promo.precioCombo); normal(); }
    if (promo.tipo === 'regalo') { $('r-compra').value = promo.condicion; $('r-cantidad').value = promo.cantidadMinima; $('r-regalo').value = promo.regalo; }
    if (promo.tipo === 'descuento') {
      $('d-aplica').value = promo.producto || 'cat:' + promo.categoria;
      $('d-valor').value = promo.valorTipo === 'porcentaje' ? promo.valor : P.bs(promo.valor);
      $('d-tipo-valor').value = promo.valorTipo;
    }
  }
  mostrarTipo();

  $('form-promo').addEventListener('submit', function (e) {
    e.preventDefault();
    var errores = { 'p-nombre': $('p-nombre').value.trim() ? '' : 'Escribe el nombre de la promoción.' };
    if (tipo() === 'descuento') {
      var v = P.leerMonto($('d-valor').value);
      errores['d-valor'] = !(v > 0) ? 'Escribe un valor mayor que cero.'
        : $('d-tipo-valor').value === 'porcentaje' && v > 100 ? 'El porcentaje no puede pasar de 100.' : '';
    }
    if (tipo() === 'combo') {
      var c = P.leerMonto($('c-precio').value);
      var n = precio($('c-uno').value) + precio($('c-dos').value);
      errores['c-precio'] = !(c > 0) ? 'Escribe el precio del combo.' : c >= n ? 'El combo debe costar menos que Bs ' + P.bs(n) + '.' : '';
    }
    errores['h-hasta'] = $('h-hasta').value <= $('h-desde').value ? 'La hora de fin debe ser posterior a la de inicio.' : '';
    errores['f-hasta'] = $('f-desde').value && $('f-hasta').value && $('f-hasta').value < $('f-desde').value ? 'La fecha de fin es anterior a la de inicio.' : '';
    var primero = null;
    Object.keys(errores).forEach(function (k) { P.marcarCampo(k, errores[k]); if (errores[k] && !primero) primero = k; });
    if (primero) { $(primero).focus(); return; }
    $('guardar-promo').setAttribute('aria-busy', 'true');
    $('guardar-promo').disabled = true;
    window.setTimeout(function () { window.location.href = 'promociones.html'; }, 400);
  });
})();
