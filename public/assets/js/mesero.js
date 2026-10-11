/* Pedidos del mesero (extra, demo): el pedido llega a caja como venta en
   espera para cobrarlo. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var D = window.PahuichiDatos;
  var $ = function (id) { return document.getElementById(id); };
  var lineas = [];
  var categoria = 'favoritos';

  function producto(id) { return D.productos.filter(function (p) { return p.id === id; })[0]; }

  [['favoritos', 'Favoritos']].concat(D.categorias.filter(function (c) { return c.id !== 'prueba'; }).map(function (c) { return [c.id, c.nombre]; })).forEach(function (c, i) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'categoria-pos categoria-pos--' + c[0];
    b.setAttribute('data-categoria', c[0]);
    b.setAttribute('aria-pressed', String(i === 0));
    b.textContent = c[1];
    $('m-categorias').appendChild(b);
  });

  function pintarGrilla() {
    var ul = $('m-grilla');
    ul.innerHTML = '';
    D.productos.filter(function (p) {
      if (p.activo === false) return false;
      return categoria === 'favoritos' ? p.favorito : p.categoria === categoria;
    }).forEach(function (p) {
      var li = document.createElement('li');
      li.innerHTML = '<button class="producto-pos producto-pos--' + p.categoria + '" type="button"><span class="producto-pos__nombre"></span><span class="producto-pos__pie"><span class="producto-pos__precio monto"><span class="monto__moneda">Bs</span></span></span></button>';
      var b = li.firstChild;
      b.setAttribute('data-id', p.id);
      b.disabled = !!p.agotado;
      b.querySelector('.producto-pos__nombre').textContent = p.corto;
      b.querySelector('.producto-pos__precio').appendChild(document.createTextNode(P.bs(p.precio)));
      ul.appendChild(li);
    });
  }

  function total() { return lineas.reduce(function (s, l) { return s + l.producto.precio * l.cantidad; }, 0); }

  function pintarPedido() {
    var ul = $('m-lineas');
    ul.innerHTML = '';
    lineas.forEach(function (l) {
      var li = document.createElement('li');
      li.className = 'linea';
      li.innerHTML = '<div class="linea__texto"><span class="linea__nombre"></span></div><span class="linea__subtotal num"></span>' +
        '<div class="linea__controles"><div class="paso" role="group"><button class="boton boton--icono paso__boton" type="button" data-paso="-1"><svg class="icono" aria-hidden="true"><use href="#i-menos"></use></svg></button><span class="paso__valor num"></span><button class="boton boton--icono paso__boton" type="button" data-paso="1"><svg class="icono" aria-hidden="true"><use href="#i-mas"></use></svg></button></div></div>';
      li.setAttribute('data-id', l.producto.id);
      li.querySelector('.linea__nombre').textContent = l.producto.nombre;
      li.querySelector('.linea__subtotal').textContent = P.bs(l.producto.precio * l.cantidad);
      li.querySelector('.paso__valor').textContent = l.cantidad;
      li.querySelector('[data-paso="-1"]').setAttribute('aria-label', 'Una menos de ' + l.producto.nombre);
      li.querySelector('[data-paso="1"]').setAttribute('aria-label', 'Una más de ' + l.producto.nombre);
      ul.appendChild(li);
    });
    $('m-vacio').hidden = lineas.length > 0;
    $('m-total').textContent = P.bs(total());
    $('m-enviar').disabled = !lineas.length;
  }

  function agregar(id, delta) {
    var l = lineas.filter(function (x) { return x.producto.id === id; })[0];
    if (!l) { l = { producto: producto(id), cantidad: 0 }; lineas.push(l); }
    l.cantidad += delta;
    if (l.cantidad <= 0) lineas = lineas.filter(function (x) { return x !== l; });
    pintarPedido();
  }

  $('m-categorias').addEventListener('click', function (e) {
    var b = e.target.closest('[data-categoria]');
    if (!b) return;
    categoria = b.getAttribute('data-categoria');
    $('m-categorias').querySelectorAll('[data-categoria]').forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
    pintarGrilla();
  });
  $('m-grilla').addEventListener('click', function (e) {
    var b = e.target.closest('.producto-pos');
    if (b && !b.disabled) agregar(b.getAttribute('data-id'), 1);
  });
  $('m-lineas').addEventListener('click', function (e) {
    var b = e.target.closest('[data-paso]');
    if (b) agregar(b.closest('.linea').getAttribute('data-id'), parseInt(b.getAttribute('data-paso'), 10));
  });
  document.querySelectorAll('[data-mesa]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('[data-mesa]').forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
      $('m-mesa').value = b.getAttribute('data-mesa');
      $('m-mesa-titulo').textContent = b.getAttribute('data-mesa');
    });
  });

  $('form-mesero').addEventListener('submit', function (e) {
    e.preventDefault();
    var espera = [];
    try { espera = JSON.parse(P.leer('pahuichi.espera') || '[]'); } catch (x) { espera = []; }
    var pedido = parseInt(P.leer('pahuichi.pedido'), 10) || D.siguientePedido;
    espera.push({
      pedido: String(pedido).padStart(3, '0'), hora: D.ahora.hora, total: total(), tipo: 'mesa', mesa: $('m-mesa').value, mesero: P.ROLES.mesero.usuario,
      lineas: lineas.map(function (l) { return { id: l.producto.id, cantidad: l.cantidad, nota: '' }; })
    });
    P.guardar('pahuichi.espera', JSON.stringify(espera));
    P.guardar('pahuichi.pedido', String(pedido + 1));
    P.aviso('Pedido de la mesa ' + $('m-mesa').value + ' enviado a caja.');
    lineas = [];
    pintarPedido();
  });

  pintarGrilla();
  pintarPedido();
})();
