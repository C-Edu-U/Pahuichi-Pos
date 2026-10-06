/* Punto de venta (prototipo). Contrato con el servidor (HANDOFF 3.3):
   el POS entrega carrito y total; el cobro devuelve método, monto recibido y
   cambio, o qr_id y referencia; FacturaBuilder devuelve número y CUF. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var D = window.PahuichiDatos;
  var $ = function (id) { return document.getElementById(id); };

  var lineas = [];           // [{ producto, cantidad }]
  var cliente = { nombre: 'S/N', doc: D.sinDatos };
  var categoria = '';
  var metodo = 'efectivo';
  var numeroFactura = D.siguienteFactura;
  var qrVencido = false;
  var cajaCerrada = false;
  try {
    qrVencido = window.localStorage.getItem('pahuichi.qrVencido') === '1';
    cajaCerrada = window.localStorage.getItem('pahuichi.turno') === 'cerrado';
  } catch (e) { /* sin almacenamiento */ }

  /* ------------------------------------------------------------------
     Grilla de productos
     ------------------------------------------------------------------ */
  var grilla = $('grilla');

  function pintarGrilla() {
    var q = P.normalizar($('pos-buscar').value.trim());
    var visibles = 0;
    grilla.innerHTML = '';
    D.productos.forEach(function (p) {
      if (categoria && p.categoria !== categoria) return;
      if (q && P.normalizar(p.nombre + ' ' + p.id).indexOf(q) === -1) return;
      visibles++;
      var li = document.createElement('li');
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'producto-pos';
      b.setAttribute('data-id', p.id);
      if (p.agotado) {
        b.disabled = true;
        b.classList.add('producto-pos--agotado');
      }
      var enPedido = cantidadDe(p.id);
      b.innerHTML =
        '<span class="producto-pos__nombre"></span>' +
        '<span class="producto-pos__pie">' +
        '<span class="producto-pos__precio monto"><span class="monto__moneda">Bs</span></span>' +
        (p.agotado ? '<span class="insignia insignia--aviso">Agotado hoy</span>' : '') +
        '</span>' +
        (enPedido ? '<span class="producto-pos__cuenta" aria-hidden="true">' + enPedido + '</span>' : '');
      b.querySelector('.producto-pos__nombre').textContent = p.nombre;
      b.querySelector('.producto-pos__precio').appendChild(document.createTextNode(P.bs(p.precio)));
      b.setAttribute('aria-label', p.nombre + ', ' + P.bs(p.precio) + ' bolivianos' +
        (p.agotado ? ', agotado hoy' : '') + (enPedido ? ', ' + enPedido + ' en el pedido' : ''));
      li.appendChild(b);
      grilla.appendChild(li);
    });
    $('pos-vacio').hidden = visibles !== 0;
  }

  grilla.addEventListener('click', function (e) {
    var b = e.target.closest('.producto-pos');
    if (!b || b.disabled) return;
    agregar(b.getAttribute('data-id'), 1);
    var id = b.getAttribute('data-id');
    var nuevo = grilla.querySelector('[data-id="' + id + '"]');
    if (nuevo) nuevo.focus();
  });

  $('pos-buscar').addEventListener('input', pintarGrilla);

  document.querySelectorAll('.pos__categorias .pestana').forEach(function (p, i, todas) {
    p.addEventListener('click', function () {
      categoria = p.getAttribute('data-categoria');
      todas.forEach(function (o) { o.setAttribute('aria-pressed', String(o === p)); });
      pintarGrilla();
    });
  });

  /* ------------------------------------------------------------------
     Pedido
     ------------------------------------------------------------------ */
  function buscarProducto(id) {
    return D.productos.filter(function (p) { return p.id === id; })[0];
  }

  function cantidadDe(id) {
    var l = lineas.filter(function (x) { return x.producto.id === id; })[0];
    return l ? l.cantidad : 0;
  }

  function agregar(id, delta) {
    var l = lineas.filter(function (x) { return x.producto.id === id; })[0];
    if (!l && delta > 0) {
      l = { producto: buscarProducto(id), cantidad: 0 };
      lineas.push(l);
    }
    if (!l) return;
    l.cantidad += delta;
    if (l.cantidad <= 0) lineas = lineas.filter(function (x) { return x !== l; });
    $('anuncio').textContent = l.producto.nombre + ': ' + Math.max(l.cantidad, 0) + ' en el pedido.';
    pintarPedido();
    pintarGrilla();
  }

  // Redondeo por línea y en el total: 2 decimales, mitad hacia arriba
  function redondear(n) { return Math.round((n + Number.EPSILON) * 100) / 100; }

  function total() {
    return redondear(lineas.reduce(function (s, l) { return s + redondear(l.producto.precio * l.cantidad); }, 0));
  }

  function pintarPedido() {
    var ul = $('lineas');
    ul.innerHTML = '';
    lineas.forEach(function (l) {
      var li = document.createElement('li');
      li.className = 'linea';
      li.innerHTML =
        '<div class="linea__texto"><span class="linea__nombre"></span><span class="linea__unitario num"></span></div>' +
        '<div class="paso" role="group">' +
        '<button class="boton boton--icono paso__boton" type="button" data-paso="-1"><svg class="icono" aria-hidden="true"><use href="#i-menos"></use></svg></button>' +
        '<span class="paso__valor num" aria-live="polite"></span>' +
        '<button class="boton boton--icono paso__boton" type="button" data-paso="1"><svg class="icono" aria-hidden="true"><use href="#i-mas"></use></svg></button>' +
        '</div>' +
        '<span class="linea__subtotal num"></span>';
      li.setAttribute('data-id', l.producto.id);
      li.querySelector('.linea__nombre').textContent = l.producto.nombre;
      li.querySelector('.linea__unitario').textContent = 'Bs ' + P.bs(l.producto.precio) + ' c/u';
      li.querySelector('.paso__valor').textContent = l.cantidad;
      li.querySelector('.paso').setAttribute('aria-label', 'Cantidad de ' + l.producto.nombre);
      li.querySelector('[data-paso="-1"]').setAttribute('aria-label', l.cantidad === 1 ? 'Quitar ' + l.producto.nombre : 'Una menos');
      li.querySelector('[data-paso="1"]').setAttribute('aria-label', 'Una más');
      li.querySelector('.linea__subtotal').textContent = P.bs(redondear(l.producto.precio * l.cantidad));
      ul.appendChild(li);
    });

    var t = total();
    var unidades = lineas.reduce(function (s, l) { return s + l.cantidad; }, 0);
    $('lineas-vacio').hidden = lineas.length > 0;
    $('t-subtotal').textContent = P.bs(t);
    $('t-total').textContent = P.bs(t);
    $('resumen-total').textContent = P.bs(t);
    $('resumen-cuenta').textContent = unidades ? (unidades === 1 ? '1 producto' : unidades + ' productos') : 'Pedido vacío';
    $('vaciar').disabled = !lineas.length;
    $('cobrar-efectivo').disabled = t <= 0 || cajaCerrada;   // sin facturas en cero ni sin turno
    $('cobrar-qr').disabled = t <= 0 || qrVencido || cajaCerrada;
    $('qr-vencido').hidden = !qrVencido;
    $('aviso-menor').hidden = !(t > 0 && t < 5 && cliente.doc === D.sinDatos);
  }

  $('lineas').addEventListener('click', function (e) {
    var b = e.target.closest('[data-paso]');
    if (!b) return;
    var id = b.closest('.linea').getAttribute('data-id');
    var delta = parseInt(b.getAttribute('data-paso'), 10);
    agregar(id, delta);
    var linea = $('lineas').querySelector('[data-id="' + id + '"] [data-paso="' + delta + '"]');
    (linea || $('pos-buscar')).focus();
  });

  $('vaciar').addEventListener('click', function () {
    lineas = [];
    pintarPedido();
    pintarGrilla();
    $('anuncio').textContent = 'Pedido vacío.';
    $('pos-buscar').focus();
  });

  // Pedido en móvil: barra inferior que se despliega
  $('ver-pedido').addEventListener('click', function () {
    var pedido = $('pedido');
    var abierto = pedido.classList.toggle('esta-abierto');
    this.setAttribute('aria-expanded', String(abierto));
  });

  /* ------------------------------------------------------------------
     Cliente para la factura
     ------------------------------------------------------------------ */
  function pintarCliente(texto) {
    var p = $('cliente-dato');
    p.innerHTML = '';
    if (texto) { p.textContent = texto; return; }
    p.appendChild(document.createTextNode('Factura a nombre de '));
    var s = document.createElement('strong');
    s.textContent = cliente.nombre;
    p.appendChild(s);
    p.appendChild(document.createTextNode(' · ' + cliente.doc));
    pintarPedido();
  }

  $('buscar-cliente').addEventListener('click', function () {
    var doc = $('doc-cliente').value.replace(/\D/g, '');
    if (!doc) { pintarCliente('Escribe un NIT o CI, o usa «Sin datos».'); $('doc-cliente').focus(); return; }
    var c = D.clientes.filter(function (x) { return x.doc === doc; })[0];
    if (c) {
      cliente = { nombre: c.nombre, doc: c.tipo + ' ' + c.doc + (c.complemento ? '-' + c.complemento : '') };
      pintarCliente();
    } else {
      pintarCliente('No hay un cliente con ' + doc + '. Regístralo en Clientes o usa «Sin datos».');
    }
  });

  $('doc-cliente').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); $('buscar-cliente').click(); }
  });

  $('sin-datos').addEventListener('click', function () {
    cliente = { nombre: 'S/N', doc: D.sinDatos };
    $('doc-cliente').value = '';
    pintarCliente();
  });

  /* ------------------------------------------------------------------
     Cobro
     ------------------------------------------------------------------ */
  var modal = $('modal-cobro');

  function abrirCobro(m) {
    metodo = m;
    var t = total();
    $('cobro-paso').hidden = false;
    $('cobro-listo').hidden = true;
    $('cobro-titulo').textContent = m === 'qr' ? 'Cobrar con QR' : 'Cobrar en efectivo';
    $('cobro-total').textContent = P.bs(t);
    $('cobro-efectivo').hidden = m !== 'efectivo';
    $('cobro-qr').hidden = m !== 'qr';
    $('recibido').value = '';
    $('referencia').value = '';
    P.marcarCampo('recibido', '');
    P.marcarCampo('referencia', '');
    actualizarCambio();
    var diferente = m === 'qr' && t !== D.qr.monto;
    $('qr-diferencia').hidden = !diferente;
    if (diferente) {
      $('qr-diferencia-texto').textContent = 'El QR cobra Bs ' + P.bs(D.qr.monto) + ' fijo y el total es Bs ' + P.bs(t) +
        '. Confirma solo si el cliente pagó el monto correcto.';
    }
    modal.showModal();
    (m === 'qr' ? $('referencia') : $('recibido')).focus();
  }

  $('cobrar-efectivo').addEventListener('click', function () { abrirCobro('efectivo'); });
  $('cobrar-qr').addEventListener('click', function () { abrirCobro('qr'); });

  function actualizarCambio() {
    var recibido = P.leerMonto($('recibido').value || '0');
    var cambio = recibido === null ? 0 : redondear(recibido - total());
    $('cambio').textContent = 'Bs ' + P.bs(Math.max(cambio, 0));
    $('cambio').classList.toggle('cambio__monto--falta', recibido !== null && recibido > 0 && cambio < 0);
  }

  $('recibido').addEventListener('input', function () {
    actualizarCambio();
    if (this.getAttribute('aria-invalid') === 'true') P.marcarCampo('recibido', '');
  });

  document.querySelectorAll('[data-billete]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = b.getAttribute('data-billete');
      $('recibido').value = P.bs(v === 'exacto' ? total() : parseFloat(v));
      actualizarCambio();
      P.marcarCampo('recibido', '');
    });
  });

  $('confirmar-cobro').addEventListener('click', function () {
    var t = total();
    var cambio = 0;
    if (metodo === 'efectivo') {
      var recibido = P.leerMonto($('recibido').value);
      if (recibido === null) { P.marcarCampo('recibido', 'Escribe cuánto te entregó el cliente.'); $('recibido').focus(); return; }
      if (recibido < t) {
        P.marcarCampo('recibido', 'Faltan Bs ' + P.bs(redondear(t - recibido)) + ' para cubrir el total.');
        $('recibido').focus();
        return;
      }
      cambio = redondear(recibido - t);
    } else if (!$('referencia').value.trim()) {
      P.marcarCampo('referencia', 'Escribe la referencia que aparece en la notificación del banco.');
      $('referencia').focus();
      return;
    }

    // Venta, detalle y factura en una sola transacción (en PHP)
    $('cobro-paso').hidden = true;
    $('cobro-listo').hidden = false;
    $('listo-texto').textContent = 'Factura N° ' + numeroFactura + ' a nombre de ' + cliente.nombre + ' por Bs ' + P.bs(t) + '.';
    $('listo-cambio').hidden = metodo !== 'efectivo' || cambio === 0;
    $('listo-cambio').textContent = 'Entrega de cambio: Bs ' + P.bs(cambio);
    $('ver-factura').href = 'factura.html?n=' + numeroFactura;
    $('listo-titulo').focus();
    numeroFactura++;
  });

  $('nueva-venta').addEventListener('click', function () {
    modal.close();
    lineas = [];
    cliente = { nombre: 'S/N', doc: D.sinDatos };
    $('doc-cliente').value = '';
    $('pedido').classList.remove('esta-abierto');
    $('ver-pedido').setAttribute('aria-expanded', 'false');
    pintarCliente();
    pintarGrilla();
    $('pos-buscar').focus();
  });

  // Sin turno abierto no se vende (HU-22)
  if (cajaCerrada) {
    $('caja-cerrada').hidden = false;
    document.querySelector('.pos__turno').hidden = true;
  }

  pintarGrilla();
  pintarPedido();
})();
