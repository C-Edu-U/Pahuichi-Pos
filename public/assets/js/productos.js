/* Catálogo (demo). La tabla sale de los mismos datos que el POS. Favorito y
   agotado se marcan con un toque (agotado no desactiva el producto). */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var D = window.PahuichiDatos;
  var $ = function (id) { return document.getElementById(id); };
  var categoria = '';
  var ICONO = { platos: 'productos', refrescos: 'vaso', sodas: 'botella', vinos: 'copa', postres: 'helado', prueba: 'qr' };

  function cat(id) { return D.categorias.filter(function (c) { return c.id === id; })[0]; }

  // Pestañas por categoría, con su color
  D.categorias.forEach(function (c) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'pestana pestana--' + c.id;
    b.setAttribute('data-categoria', c.id);
    b.setAttribute('aria-pressed', 'false');
    var n = D.productos.filter(function (p) { return p.categoria === c.id; }).length;
    b.innerHTML = '<span class="punto-categoria punto-categoria--' + c.id + '" aria-hidden="true"></span><span></span> <span class="pestana__cuenta">' + n + '</span>';
    b.children[1].textContent = c.nombre;
    $('pestanas').appendChild(b);
  });

  function fila(p) {
    var tr = document.createElement('tr');
    tr.setAttribute('data-id', p.id);
    var c = cat(p.categoria);
    tr.innerHTML =
      '<td><div class="producto-celda"><span class="producto-celda__foto producto-celda__foto--' + p.categoria + '" aria-hidden="true"><svg class="icono"><use href="#i-' + ICONO[p.categoria] + '"></use></svg></span>' +
      '<span><span class="tabla__principal"></span><span class="tabla__secundario"><span class="codigo"></span> · <span class="corto"></span></span></span></div></td>' +
      '<td><span class="categoria-celda"><span class="punto-categoria punto-categoria--' + p.categoria + '" aria-hidden="true"></span><span></span></span></td>' +
      '<td class="col-num"><span class="monto"><span class="monto__moneda">Bs</span></span></td>' +
      '<td><div class="marcas">' +
      '<button class="marca-toggle marca-toggle--favorito" type="button" data-favorito aria-pressed="' + !!p.favorito + '"><svg class="icono icono--sm" aria-hidden="true"><use href="#i-estrella"></use></svg>Favorito</button>' +
      (p.cocina ? '<span class="marca-fija"><svg class="icono icono--sm" aria-hidden="true"><use href="#i-cocina"></use></svg>Cocina</span>' : '') +
      '</div></td>' +
      '<td class="celda-estado"></td>' +
      '<td class="col-accion"><div class="acciones-fila">' +
      '<button class="boton boton-agotado" type="button" aria-pressed="' + !!p.agotado + '" data-requiere="catalogo.agotado">Agotado</button>' +
      '<a class="boton boton--icono" href="producto-form.html?id=' + p.id + '" data-requiere="catalogo.editar"><svg class="icono" aria-hidden="true"><use href="#i-editar"></use></svg></a>' +
      '</div></td>';
    tr.querySelector('.tabla__principal').textContent = p.nombre;
    tr.querySelector('.codigo').textContent = p.id;
    tr.querySelector('.corto').textContent = p.corto;
    tr.querySelector('.categoria-celda span:last-child').textContent = c.nombre;
    tr.querySelector('.monto').appendChild(document.createTextNode(P.bs(p.precio)));
    tr.querySelector('[data-favorito]').setAttribute('aria-label', 'Favorito: ' + p.nombre);
    tr.querySelector('.boton-agotado').setAttribute('aria-label', 'Agotado: ' + p.nombre);
    tr.querySelector('a.boton').setAttribute('aria-label', 'Editar ' + p.nombre);
    pintarEstado(tr, p);
    return tr;
  }

  function pintarEstado(tr, p) {
    tr.querySelector('.celda-estado').innerHTML = p.activo === false
      ? '<span class="insignia insignia--neutra">Inactivo</span>'
      : p.agotado ? '<span class="insignia insignia--aviso">Agotado</span>'
      : '<span class="insignia insignia--ok">Disponible</span>';
  }

  D.productos.forEach(function (p) { $('filas').appendChild(fila(p)); });
  $('pestanas').querySelector('[data-categoria=""] .pestana__cuenta').textContent = D.productos.length;

  function filtrar() {
    var q = P.normalizar($('buscar').value.trim());
    var e = $('estado').value;
    var n = 0;
    Array.prototype.forEach.call($('filas').rows, function (tr) {
      var p = D.productos.filter(function (x) { return x.id === tr.getAttribute('data-id'); })[0];
      var ok = (!q || P.normalizar(p.nombre + ' ' + p.corto + ' ' + p.id).indexOf(q) !== -1) &&
        (!categoria || p.categoria === categoria) &&
        (!e || (e === 'favorito' && p.favorito) || (e === 'agotado' && p.agotado) || (e === 'cocina' && p.cocina) || (e === 'inactivo' && p.activo === false));
      tr.hidden = !ok;
      if (ok) n++;
    });
    $('cuenta').textContent = n === 1 ? '1 producto' : n + ' productos';
    document.querySelector('.tabla--catalogo').hidden = n === 0;
    $('vacio').hidden = n !== 0;
  }

  $('pestanas').addEventListener('click', function (e) {
    var b = e.target.closest('[data-categoria]');
    if (!b) return;
    categoria = b.getAttribute('data-categoria');
    $('pestanas').querySelectorAll('[data-categoria]').forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
    filtrar();
  });
  $('buscar').addEventListener('input', filtrar);
  $('estado').addEventListener('change', filtrar);
  $('limpiar').addEventListener('click', function () {
    $('buscar').value = '';
    $('estado').value = '';
    $('pestanas').querySelector('[data-categoria=""]').click();
  });

  // Favorito y agotado: un toque
  $('filas').addEventListener('click', function (e) {
    var tr = e.target.closest('tr');
    if (!tr) return;
    var p = D.productos.filter(function (x) { return x.id === tr.getAttribute('data-id'); })[0];
    var fav = e.target.closest('[data-favorito]');
    if (fav) {
      if (!P.puede('catalogo.editar')) { P.aviso('Solo el administrador cambia los favoritos.'); return; }
      var nuevoFavorito = !p.favorito;
      var actualizado = D.guardarProducto(Object.assign({}, p, { favorito: nuevoFavorito }), p.id);
      if (!actualizado.ok) { P.aviso(actualizado.error, 'error'); return; }
      fav.setAttribute('aria-pressed', String(nuevoFavorito));
      $('anuncio').textContent = p.nombre + (nuevoFavorito ? ' ahora es favorito.' : ' ya no es favorito.');
      return;
    }
    var ag = e.target.closest('.boton-agotado');
    if (ag) {
      var nuevoAgotado = !p.agotado;
      var actualizado = D.guardarProducto(Object.assign({}, p, { agotado: nuevoAgotado }), p.id);
      if (!actualizado.ok) { P.aviso(actualizado.error, 'error'); return; }
      ag.setAttribute('aria-pressed', String(nuevoAgotado));
      pintarEstado(tr, Object.assign({}, p, { agotado: nuevoAgotado }));
      $('anuncio').textContent = p.nombre + (nuevoAgotado ? ' marcado como agotado.' : ' vuelve a estar disponible.');
    }
  });

  if (P.parametro('guardado')) {
    document.querySelector('.aviso-guardado').hidden = false;
    if (P.parametro('inactivo')) document.querySelector('.aviso-guardado .alerta__texto').textContent = 'Guardado como inactivo; no aparece en el punto de venta.';
  }
  var desdeUrl = P.parametro('estado');
  if (desdeUrl) $('estado').value = desdeUrl;
  filtrar();
  // Permisos sobre las filas creadas después de app.js
  document.querySelectorAll('#filas [data-requiere]').forEach(function (el) { el.hidden = !P.puede(el.getAttribute('data-requiere')); });
})();
