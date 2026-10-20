/* Punto de venta.
   HU-28: pestañas de categoría y búsqueda instantánea sobre la cuadrícula que
   dibuja el servidor. Favoritos es la primera pantalla; la búsqueda recorre
   todos los productos sin importar la pestaña.
   HU-29: carrito. Un toque agrega, otro suma una unidad; + y − por línea,
   quitar, total siempre visible y cancelar el pedido. La lógica está en carrito.js. */
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
  var carrito = new window.PahuichiCarrito(JSON.parse($('pos').getAttribute('data-productos') || '[]'));

  function anunciar(texto) { $('anuncio').textContent = texto; }

  /* ------------------------------------------------------------------
     Cuadrícula: pestañas y búsqueda (HU-28)
     ------------------------------------------------------------------ */
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
    anunciar(buscador.value.trim() ? (n === 1 ? '1 producto' : n + ' productos') : '');
  });

  // Enter agrega el primer producto disponible de la búsqueda
  buscador.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    var primero = grilla.querySelector('li:not([hidden]) .producto-pos:not([disabled])');
    if (primero) {
      agregar(primero.getAttribute('data-id'));
      buscador.select();
    }
  });

  /* ------------------------------------------------------------------
     Pedido (HU-29)
     ------------------------------------------------------------------ */
  function agregar(id) {
    var l = carrito.agregar(id);
    if (!l) return;
    anunciar(l.producto.nombre + ': ' + l.cantidad + ' en el pedido.');
    pintar();
  }

  grilla.addEventListener('click', function (e) {
    var b = e.target.closest('.producto-pos');
    if (!b || b.disabled) return;
    agregar(b.getAttribute('data-id'));
  });

  function boton(clase, icono, accion, etiqueta) {
    var b = document.createElement('button');
    b.className = 'boton boton--icono ' + clase;
    b.type = 'button';
    b.setAttribute(accion[0], accion[1]);
    b.setAttribute('aria-label', etiqueta);
    b.innerHTML = '<svg class="icono" aria-hidden="true"><use href="#i-' + icono + '"></use></svg>';
    return b;
  }

  function lineaHtml(l) {
    var p = l.producto;
    var li = document.createElement('li');
    li.className = 'linea';
    li.setAttribute('data-id', p.id);
    li.innerHTML =
      '<div class="linea__texto"><span class="linea__nombre"></span><span class="linea__unitario num"></span></div>' +
      '<span class="linea__subtotal num"></span>' +
      '<div class="linea__controles"><div class="paso" role="group"><span class="paso__valor num"></span></div></div>';
    li.querySelector('.linea__nombre').textContent = p.nombre;
    li.querySelector('.linea__unitario').textContent = 'Bs ' + P.bs(p.precio) + ' c/u';
    li.querySelector('.linea__subtotal').textContent = P.bs(carrito.subtotalDe(l));
    var paso = li.querySelector('.paso');
    paso.setAttribute('aria-label', 'Cantidad de ' + p.nombre);
    paso.querySelector('.paso__valor').textContent = l.cantidad;
    paso.insertBefore(boton('paso__boton', 'menos', ['data-paso', '-1'], l.cantidad === 1 ? 'Quitar ' + p.nombre : 'Una menos de ' + p.nombre), paso.firstChild);
    paso.appendChild(boton('paso__boton', 'mas', ['data-paso', '1'], 'Una más de ' + p.nombre));
    li.querySelector('.linea__controles').appendChild(boton('boton--fantasma', 'basura', ['data-quitar', ''], 'Quitar ' + p.nombre + ' del pedido'));
    return li;
  }

  function pintar() {
    var ul = $('lineas');
    ul.innerHTML = '';
    carrito.lineas.forEach(function (l) { ul.appendChild(lineaHtml(l)); });

    var total = P.bs(carrito.total());
    var unidades = carrito.unidades();
    $('lineas-vacio').hidden = !carrito.vacio();
    $('t-total').textContent = total;
    $('resumen-total').textContent = total;
    $('resumen-cuenta').textContent = unidades ? (unidades === 1 ? '1 ítem' : unidades + ' ítems') : 'Pedido vacío';
    $('vaciar').disabled = carrito.vacio();

    // Cuántas unidades de cada producto hay en el pedido, sobre su botón
    grilla.querySelectorAll('.producto-pos').forEach(function (b) {
      var n = carrito.cantidadDe(b.getAttribute('data-id'));
      var cuenta = b.querySelector('.producto-pos__cuenta');
      if (n && !cuenta) {
        cuenta = document.createElement('span');
        cuenta.className = 'producto-pos__cuenta';
        cuenta.setAttribute('aria-hidden', 'true');
        b.appendChild(cuenta);
      }
      if (cuenta) {
        if (n) cuenta.textContent = n; else cuenta.remove();
      }
      var base = b.getAttribute('data-etiqueta') || b.getAttribute('aria-label');
      b.setAttribute('data-etiqueta', base);
      b.setAttribute('aria-label', base + (n ? ', ' + n + ' en el pedido' : ''));
    });
  }

  $('lineas').addEventListener('click', function (e) {
    var li = e.target.closest('.linea');
    if (!li) return;
    var id = li.getAttribute('data-id');
    var nombre = li.querySelector('.linea__nombre').textContent;
    var paso = e.target.closest('[data-paso]');
    if (paso) {
      var delta = parseInt(paso.getAttribute('data-paso'), 10);
      var queda = carrito.cambiar(id, delta);
      anunciar(queda ? nombre + ': ' + queda + ' en el pedido.' : nombre + ' quitado del pedido.');
      pintar();
      // El foco se queda en el mismo botón si la línea sigue; si no, vuelve a la búsqueda
      var mismo = $('lineas').querySelector('[data-id="' + id + '"] [data-paso="' + delta + '"]');
      (mismo || buscador).focus();
      return;
    }
    if (e.target.closest('[data-quitar]')) {
      carrito.quitar(id);
      anunciar(nombre + ' quitado del pedido.');
      pintar();
      buscador.focus();
    }
  });

  // Cancelar el pedido: pide confirmación porque se pierde lo cargado
  var modal = $('modal-vaciar');
  modal.addEventListener('pahuichi:abrir', function () {
    var n = carrito.unidades();
    $('modal-vaciar-texto').textContent = 'Se quita' + (n === 1 ? ' 1 ítem' : 'n los ' + n + ' ítems') + ' del pedido.';
  });
  modal.addEventListener('close', function () {
    if (modal.returnValue !== 'vaciar') return;
    carrito.vaciar();
    pintar();
    anunciar('Pedido cancelado.');
    P.aviso('Pedido cancelado.');
    buscador.focus();
  });

  // Celular: el pedido es una barra inferior que se despliega
  $('ver-pedido').addEventListener('click', function () {
    var abierto = $('pedido').classList.toggle('esta-abierto');
    this.setAttribute('aria-expanded', String(abierto));
  });

  pintar();
  window.PahuichiPos = { filtrar: filtrar, carrito: carrito };
})();
