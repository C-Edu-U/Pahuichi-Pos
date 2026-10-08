/* Punto de venta (demo). Contratos con el servidor:
   - Caja: sin caja abierta se muestra «Abrir caja».
   - Promociones: PromocionService::evaluar(carrito, momento, cliente).
   - Cobro: carrito, total y UUID; devuelve pagos (efectivo, QR o mixto).
   - Factura: en una transacción, venta + pagos + factura + 3 impresiones.
   La interfaz se actualiza antes de cualquier respuesta (menos de 100 ms). */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var D = window.PahuichiDatos;
  var Promos = window.PahuichiPromociones;
  var $ = function (id) { return document.getElementById(id); };

  var MONTO_QR = D.qr.monto;
  var MAX_ESPERA = 10;

  var estado = {
    lineas: [],              // [{ producto, cantidad, nota }]
    cliente: { nombre: D.cliente.nombre, doc: D.cliente.doc, nacimiento: '' },
    tipo: 'mesa',
    descuentoManual: null,   // { monto, motivo }
    categoria: 'favoritos',
    metodo: 'efectivo'
  };

  var pedido = parseInt(P.leer('pahuichi.pedido'), 10) || D.siguientePedido;
  var factura = parseInt(P.leer('pahuichi.factura'), 10) || D.siguienteFactura;
  var cajaCerrada = P.leer('pahuichi.turno') === 'cerrado';
  var sinQr = P.leer('pahuichi.qrVencido') === '1';
  var impresoraCaida = P.leer('pahuichi.impresora') === 'caida';

  // Comprobantes QR ya usados hoy (RN-04): con servidor, índice único
  var comprobantes = { 'BNB-55120': { pedido: '031', factura: 121, hora: '11:47' } };
  try { Object.assign(comprobantes, JSON.parse(P.leer('pahuichi.comprobantes') || '{}')); } catch (e) { /* sin datos */ }

  function redondear(n) { return Math.round((n + Number.EPSILON) * 100) / 100; }
  function tres(n) { return String(n).padStart(3, '0'); }
  function producto(id) { return D.productos.filter(function (p) { return p.id === id; })[0]; }
  // Sin foto cargada, la tarjeta muestra el ícono de su categoría
  var ICONO_CATEGORIA = { platos: 'productos', refrescos: 'vaso', sodas: 'botella', vinos: 'copa', postres: 'helado', prueba: 'qr' };

  /* ------------------------------------------------------------------
     Sin caja abierta no se vende
     ------------------------------------------------------------------ */
  if (cajaCerrada) {
    $('pos').hidden = true;
    $('pos-sin-caja').hidden = false;
    return;
  }

  /* ------------------------------------------------------------------
     Categorías: botones con el color de cada una
     ------------------------------------------------------------------ */
  var barraCategorias = document.querySelector('.categorias-pos');
  D.categorias.forEach(function (c) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'categoria-pos categoria-pos--' + c.id;
    b.setAttribute('data-categoria', c.id);
    b.setAttribute('aria-pressed', 'false');
    b.textContent = c.nombre;
    barraCategorias.appendChild(b);
  });
  var todo = document.createElement('button');
  todo.type = 'button';
  todo.className = 'categoria-pos categoria-pos--todo';
  todo.setAttribute('data-categoria', 'todo');
  todo.setAttribute('aria-pressed', 'false');
  todo.textContent = 'Todo';
  barraCategorias.appendChild(todo);

  barraCategorias.addEventListener('click', function (e) {
    var b = e.target.closest('[data-categoria]');
    if (!b) return;
    estado.categoria = b.getAttribute('data-categoria');
    barraCategorias.querySelectorAll('[data-categoria]').forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
    $('pos-buscar').value = '';
    pintarGrilla();
  });

  /* ------------------------------------------------------------------
     Grilla de productos
     ------------------------------------------------------------------ */
  var grilla = $('grilla');
  var conPromo = {};
  D.promociones.forEach(function (pr) {
    if (!Promos.vigente(pr, D.ahora)) return;
    (pr.componentes || []).concat(pr.condicion ? [pr.condicion] : [], pr.producto ? [pr.producto] : []).forEach(function (id) { conPromo[id] = true; });
    if (pr.tipo === 'descuento' && pr.categoria) D.productos.forEach(function (p) { if (p.categoria === pr.categoria) conPromo[p.id] = true; });
  });

  function visibles() {
    var q = P.normalizar($('pos-buscar').value.trim());
    return D.productos.filter(function (p) {
      if (q) return P.normalizar(p.nombre + ' ' + p.corto + ' ' + p.id).indexOf(q) !== -1;
      if (estado.categoria === 'favoritos') return p.favorito;
      if (estado.categoria === 'todo') return true;
      return p.categoria === estado.categoria;
    });
  }

  function cantidadDe(id) {
    return estado.lineas.reduce(function (s, l) { return l.producto.id === id ? s + l.cantidad : s; }, 0);
  }

  function pintarGrilla() {
    var lista = visibles();
    grilla.innerHTML = '';
    lista.forEach(function (p) {
      var li = document.createElement('li');
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'producto-pos producto-pos--' + p.categoria;
      b.setAttribute('data-id', p.id);
      if (p.agotado) b.disabled = true;
      var n = cantidadDe(p.id);
      b.innerHTML =
        '<span class="producto-pos__foto" aria-hidden="true"><svg class="icono producto-pos__icono"><use href="#i-' + ICONO_CATEGORIA[p.categoria] + '"></use></svg></span>' +
        '<span class="producto-pos__nombre"></span>' +
        '<span class="producto-pos__pie"><span class="producto-pos__precio monto"><span class="monto__moneda">Bs</span></span>' +
        (p.agotado ? '<span class="insignia insignia--aviso">Agotado</span>' : conPromo[p.id] ? '<span class="insignia insignia--promo">Promo</span>' : '') +
        '</span>' +
        (n ? '<span class="producto-pos__cuenta" aria-hidden="true">' + n + '</span>' : '');
      b.querySelector('.producto-pos__nombre').textContent = p.corto;
      b.querySelector('.producto-pos__precio').appendChild(document.createTextNode(P.bs(p.precio)));
      b.setAttribute('aria-label', p.nombre + ', ' + P.bs(p.precio) + ' bolivianos' + (p.agotado ? ', agotado' : '') + (n ? ', ' + n + ' en el pedido' : ''));
      li.appendChild(b);
      grilla.appendChild(li);
    });
    $('pos-vacio').hidden = lista.length !== 0;
  }

  grilla.addEventListener('click', function (e) {
    var b = e.target.closest('.producto-pos');
    if (!b || b.disabled) return;
    var id = b.getAttribute('data-id');
    agregar(id, 1);
    var nuevo = grilla.querySelector('[data-id="' + id + '"]');
    if (nuevo && document.activeElement === document.body) nuevo.focus();
  });

  $('pos-buscar').addEventListener('input', pintarGrilla);
  $('pos-buscar').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      var primero = visibles().filter(function (p) { return !p.agotado; })[0];
      if (primero) { agregar(primero.id, 1); this.select(); }
    }
  });

  /* ------------------------------------------------------------------
     Pedido
     ------------------------------------------------------------------ */
  function agregar(id, delta) {
    var l = estado.lineas.filter(function (x) { return x.producto.id === id; })[0];
    if (!l && delta > 0) {
      l = { producto: producto(id), cantidad: 0, nota: '' };
      estado.lineas.push(l);
    }
    if (!l) return;
    l.cantidad += delta;
    if (l.cantidad <= 0) estado.lineas = estado.lineas.filter(function (x) { return x !== l; });
    $('anuncio').textContent = l.producto.nombre + ': ' + Math.max(l.cantidad, 0) + ' en el pedido.';
    $('venta-lista').hidden = true;
    pintarPedido();
    pintarGrilla();
  }

  function calcular() {
    var subtotal = redondear(estado.lineas.reduce(function (s, l) { return s + redondear(l.producto.precio * l.cantidad); }, 0));
    var promos = Promos.evaluar(estado.lineas, D.ahora, estado.cliente);
    var descuentos = promos.lineas.slice();
    if (estado.descuentoManual) {
      var restante = subtotal - descuentos.reduce(function (s, d) { return s + d.monto; }, 0);
      descuentos.push({ etiqueta: 'Descuento manual: ' + estado.descuentoManual.motivo, monto: Math.min(estado.descuentoManual.monto, restante), manual: true });
    }
    var descuento = redondear(descuentos.reduce(function (s, d) { return s + d.monto; }, 0));
    return { subtotal: subtotal, descuentos: descuentos, descuento: descuento, total: redondear(subtotal - descuento), sugerencias: promos.sugerencias };
  }

  function pintarPedido() {
    var ul = $('lineas');
    var c = calcular();
    ul.innerHTML = '';
    estado.lineas.forEach(function (l) {
      var li = document.createElement('li');
      li.className = 'linea';
      li.setAttribute('data-id', l.producto.id);
      li.innerHTML =
        '<div class="linea__texto"><span class="linea__nombre"></span><span class="linea__unitario num"></span></div>' +
        '<span class="linea__subtotal num"></span>' +
        '<div class="linea__controles">' +
        '<div class="paso" role="group">' +
        '<button class="boton boton--icono paso__boton" type="button" data-paso="-1"><svg class="icono" aria-hidden="true"><use href="#i-menos"></use></svg></button>' +
        '<span class="paso__valor num"></span>' +
        '<button class="boton boton--icono paso__boton" type="button" data-paso="1"><svg class="icono" aria-hidden="true"><use href="#i-mas"></use></svg></button>' +
        '</div>' +
        '<button class="boton boton--fantasma boton--icono" type="button" data-nota aria-expanded="false"><svg class="icono" aria-hidden="true"><use href="#i-nota"></use></svg></button>' +
        '<button class="boton boton--fantasma boton--icono" type="button" data-quitar><svg class="icono" aria-hidden="true"><use href="#i-basura"></use></svg></button>' +
        '</div>' +
        '<label class="linea__nota" hidden><span class="visualmente-oculto">Nota para cocina</span><input class="control" type="text" maxlength="60" placeholder="Nota para cocina, p. ej. sin cebolla"></label>' +
        '<p class="linea__nota-texto" hidden></p>';
      var nombre = l.producto.nombre;
      li.querySelector('.linea__nombre').textContent = nombre;
      li.querySelector('.linea__unitario').textContent = 'Bs ' + P.bs(l.producto.precio) + ' c/u';
      li.querySelector('.paso__valor').textContent = l.cantidad;
      li.querySelector('.paso').setAttribute('aria-label', 'Cantidad de ' + nombre);
      li.querySelector('[data-paso="-1"]').setAttribute('aria-label', 'Una menos de ' + nombre);
      li.querySelector('[data-paso="1"]').setAttribute('aria-label', 'Una más de ' + nombre);
      li.querySelector('[data-nota]').setAttribute('aria-label', 'Nota para cocina de ' + nombre);
      li.querySelector('[data-quitar]').setAttribute('aria-label', 'Quitar ' + nombre);
      li.querySelector('.linea__subtotal').textContent = P.bs(redondear(l.producto.precio * l.cantidad));
      li.querySelector('.linea__nota input').value = l.nota;
      if (l.nota) {
        var t = li.querySelector('.linea__nota-texto');
        t.hidden = false;
        t.textContent = 'Nota: ' + l.nota;
      }
      ul.appendChild(li);
    });

    c.descuentos.forEach(function (d) {
      var li = document.createElement('li');
      li.className = 'linea linea--promo' + (d.manual ? ' linea--manual' : '');
      li.innerHTML = '<svg class="icono icono--sm" aria-hidden="true"><use href="#i-' + (d.manual ? 'llave' : 'promocion') + '"></use></svg><span class="linea__nombre"></span><span class="linea__subtotal num"></span>';
      li.querySelector('.linea__nombre').textContent = d.etiqueta;
      li.querySelector('.linea__subtotal').textContent = '−' + P.bs(d.monto);
      ul.appendChild(li);
    });

    c.sugerencias.forEach(function (s) {
      var li = document.createElement('li');
      li.className = 'linea linea--sugerencia';
      li.textContent = s;
      ul.appendChild(li);
    });

    var unidades = estado.lineas.reduce(function (s, l) { return s + l.cantidad; }, 0);
    $('lineas-vacio').hidden = estado.lineas.length > 0;
    $('t-subtotal').textContent = P.bs(c.subtotal);
    $('t-descuento').textContent = c.descuento ? '−' + P.bs(c.descuento) : '0,00';
    $('t-total').textContent = P.bs(c.total);
    $('resumen-total').textContent = P.bs(c.total);
    $('resumen-cuenta').textContent = unidades ? (unidades === 1 ? '1 ítem' : unidades + ' ítems') + ' · pedido ' + tres(pedido) : 'Pedido vacío';
    var vacio = !estado.lineas.length;
    ['vaciar', 'poner-espera', 'descuento-manual'].forEach(function (id) { $(id).disabled = vacio; });
    $('cobrar').disabled = c.total <= 0;          // sin facturas en cero
    $('cobrar-exacto').disabled = c.total <= 0;
    $('qr-vencido').hidden = !sinQr;
    publicarPantalla(c, false);
    return c;
  }

  // Pantalla del cliente (segundo monitor): con servidor, por SSE
  function publicarPantalla(c, qr) {
    P.guardar('pahuichi.pantalla', JSON.stringify({
      pedido: tres(pedido), total: c.total, descuento: c.descuento, qr: qr,
      lineas: estado.lineas.map(function (l) { return { nombre: l.producto.nombre, cantidad: l.cantidad, subtotal: l.producto.precio * l.cantidad }; })
    }));
  }

  $('lineas').addEventListener('click', function (e) {
    var li = e.target.closest('.linea');
    if (!li || !li.getAttribute('data-id')) return;
    var id = li.getAttribute('data-id');
    var paso = e.target.closest('[data-paso]');
    if (paso) {
      var delta = parseInt(paso.getAttribute('data-paso'), 10);
      agregar(id, delta);
      var mismo = $('lineas').querySelector('[data-id="' + id + '"] [data-paso="' + delta + '"]');
      (mismo || $('pos-buscar')).focus();
      return;
    }
    if (e.target.closest('[data-quitar]')) {
      agregar(id, -cantidadDe(id));
      $('pos-buscar').focus();
      return;
    }
    var nota = e.target.closest('[data-nota]');
    if (nota) {
      var campo = li.querySelector('.linea__nota');
      campo.hidden = !campo.hidden;
      nota.setAttribute('aria-expanded', String(!campo.hidden));
      if (!campo.hidden) campo.querySelector('input').focus();
    }
  });

  // Guardar la nota al salir del campo o con Enter
  $('lineas').addEventListener('change', function (e) {
    var input = e.target.closest('.linea__nota input');
    if (!input) return;
    var id = input.closest('.linea').getAttribute('data-id');
    estado.lineas.forEach(function (l) { if (l.producto.id === id) l.nota = input.value.trim(); });
    pintarPedido();
  });
  $('lineas').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.closest('.linea__nota input')) { e.preventDefault(); e.target.blur(); $('pos-buscar').focus(); }
  });

  function vaciar() {
    estado.lineas = [];
    estado.descuentoManual = null;
    estado.cliente = { nombre: D.cliente.nombre, doc: D.cliente.doc, nacimiento: '' };
    pintarCliente();
    pintarPedido();
    pintarGrilla();
  }

  $('vaciar').addEventListener('click', function () {
    vaciar();
    $('anuncio').textContent = 'Pedido vacío.';
    $('pos-buscar').focus();
  });

  // Pedido en móvil: barra inferior que se despliega
  $('ver-pedido').addEventListener('click', function () {
    var abierto = $('pedido').classList.toggle('esta-abierto');
    this.setAttribute('aria-expanded', String(abierto));
  });

  /* ------------------------------------------------------------------
     Tipo de pedido: mesa o para llevar
     ------------------------------------------------------------------ */
  document.querySelectorAll('[data-tipo]').forEach(function (b) {
    b.addEventListener('click', function () {
      estado.tipo = b.getAttribute('data-tipo');
      document.querySelectorAll('[data-tipo]').forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
      $('mesa').hidden = estado.tipo !== 'mesa';
      if (estado.tipo === 'mesa') $('mesa').select();
    });
  });
  $('mesa').addEventListener('input', function () { this.value = this.value.replace(/\D/g, ''); });

  /* ------------------------------------------------------------------
     Cliente: S/N con NIT 0 por defecto, autocompletado por NIT o CI
     ------------------------------------------------------------------ */
  function pintarCliente() {
    $('cliente-dato').textContent = estado.cliente.nombre + ' · ' + (estado.cliente.doc === '0' ? 'NIT 0' : estado.cliente.doc);
    $('cliente-cumple').hidden = !(estado.cliente.nacimiento && estado.cliente.nacimiento.slice(5) === D.ahora.fecha.slice(5));
  }

  function cerrarBuscador() {
    $('cliente-buscador').hidden = true;
    $('cliente-cambiar').setAttribute('aria-expanded', 'false');
    $('cliente-sugerencias').hidden = true;
  }

  $('cliente-cambiar').addEventListener('click', function () {
    var abrir = $('cliente-buscador').hidden;
    $('cliente-buscador').hidden = !abrir;
    this.setAttribute('aria-expanded', String(abrir));
    if (abrir) { $('doc-cliente').value = ''; $('doc-cliente').focus(); }
  });

  function elegirCliente(c) {
    estado.cliente = { nombre: c.nombre, doc: c.tipo + ' ' + c.doc + (c.complemento ? '-' + c.complemento : ''), nacimiento: c.nacimiento };
    pintarCliente();
    pintarPedido();
    cerrarBuscador();
    $('pos-buscar').focus();
  }

  $('doc-cliente').addEventListener('input', function () {
    var q = this.value.replace(/\D/g, '');
    var ul = $('cliente-sugerencias');
    ul.innerHTML = '';
    var encontrados = q.length >= 2 ? D.clientes.filter(function (c) { return c.doc.indexOf(q) === 0; }).slice(0, 5) : [];
    encontrados.forEach(function (c, i) {
      var li = document.createElement('li');
      li.setAttribute('role', 'option');
      li.id = 'sugerencia-' + i;
      li.className = 'cliente-pos__sugerencia';
      li.innerHTML = '<span class="num"></span><span></span>';
      li.children[0].textContent = c.tipo + ' ' + c.doc;
      li.children[1].textContent = c.nombre;
      li.addEventListener('click', function () { elegirCliente(c); });
      ul.appendChild(li);
    });
    if (q.length >= 5 && !encontrados.length) {
      var li = document.createElement('li');
      li.className = 'cliente-pos__sugerencia cliente-pos__sugerencia--nuevo';
      li.innerHTML = 'No está registrado. <a href="clientes.html?nuevo=' + encodeURIComponent(q) + '">Registrar cliente</a>';
      ul.appendChild(li);
    }
    ul.hidden = !ul.children.length;
    this.setAttribute('aria-expanded', String(!ul.hidden));
  });

  $('doc-cliente').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      var q = this.value.replace(/\D/g, '');
      var c = D.clientes.filter(function (x) { return x.doc.indexOf(q) === 0; })[0];
      if (c && q) elegirCliente(c);
    }
  });

  $('sin-datos').addEventListener('click', function () {
    estado.cliente = { nombre: D.cliente.nombre, doc: D.cliente.doc, nacimiento: '' };
    pintarCliente();
    pintarPedido();
    cerrarBuscador();
    $('pos-buscar').focus();
  });

  /* ------------------------------------------------------------------
     Cobro
     ------------------------------------------------------------------ */
  var modal = $('modal-cobro');

  function elegirMetodo(m) {
    estado.metodo = m;
    document.querySelectorAll('[data-metodo]').forEach(function (b) {
      b.setAttribute('aria-selected', String(b.getAttribute('data-metodo') === m));
      b.tabIndex = b.getAttribute('data-metodo') === m ? 0 : -1;
    });
    var total = calcular().total;
    $('cobro-efectivo').hidden = m === 'qr';
    $('cobro-qr').hidden = m === 'efectivo';
    $('parte-efectivo').hidden = m !== 'mixto';
    $('resto-efectivo').textContent = 'Bs ' + P.bs(Math.max(total - MONTO_QR, 0));
    var diferente = m === 'qr' && total !== MONTO_QR;
    $('qr-diferencia').hidden = !diferente;
    publicarPantalla(calcular(), m !== 'efectivo');
    if (diferente) $('qr-diferencia-texto').textContent = 'El total es Bs ' + P.bs(total) + '. Usa «Mixto»: Bs 0,50 por QR y el resto en efectivo.';
    $('recibido').value = '';
    P.marcarCampo('recibido', '');
    P.marcarCampo('comprobante', '');
    actualizarCambio();
  }

  document.querySelectorAll('[data-metodo]').forEach(function (b) {
    b.addEventListener('click', function () { elegirMetodo(b.getAttribute('data-metodo')); });
  });

  function abrirCobro() {
    var c = pintarPedido();
    if (c.total <= 0) return;
    $('cobro-total').textContent = P.bs(c.total);
    $('comprobante').value = '';
    $('pagador').value = '';
    ['tab-qr', 'tab-mixto'].forEach(function (id) { $(id).disabled = sinQr; });
    $('tab-mixto').disabled = sinQr || c.total <= MONTO_QR;
    elegirMetodo('efectivo');
    modal.showModal();
    $('recibido').focus();
  }

  function aCobrar() {
    var t = calcular().total;
    return estado.metodo === 'mixto' ? redondear(t - MONTO_QR) : t;
  }

  function actualizarCambio() {
    var recibido = P.leerMonto($('recibido').value || '0');
    var cambio = recibido === null ? 0 : redondear(recibido - aCobrar());
    $('cambio').textContent = 'Bs ' + P.bs(Math.max(cambio, 0));
    $('cambio').classList.toggle('cambio__monto--falta', recibido !== null && recibido > 0 && cambio < 0);
  }

  $('cobrar').addEventListener('click', abrirCobro);
  $('recibido').addEventListener('input', function () {
    actualizarCambio();
    if (this.getAttribute('aria-invalid') === 'true') P.marcarCampo('recibido', '');
  });
  $('comprobante').addEventListener('input', function () {
    this.value = this.value.toUpperCase();
    if (this.getAttribute('aria-invalid') === 'true') P.marcarCampo('comprobante', '');
  });

  document.querySelectorAll('[data-billete]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = b.getAttribute('data-billete');
      $('recibido').value = P.bs(v === 'exacto' ? aCobrar() : parseFloat(v));
      actualizarCambio();
      P.marcarCampo('recibido', '');
    });
  });

  function validarComprobante() {
    var num = $('comprobante').value.trim();
    if (!num) {
      P.marcarCampo('comprobante', 'Escribe el número de comprobante que muestra el banco.');
      $('comprobante').focus();
      return null;
    }
    var usado = comprobantes[num];
    if (usado) {
      P.marcarCampo('comprobante', 'Este comprobante ya se usó hoy en el pedido ' + usado.pedido + ' (factura N° ' + usado.factura + ', ' + usado.hora + '). Pide al cliente el comprobante de este pago.');
      $('comprobante').focus();
      return null;
    }
    return num;
  }

  function confirmar() {
    var c = calcular();
    var pagos = [];
    if (estado.metodo === 'qr' && c.total !== MONTO_QR) {
      P.marcarCampo('comprobante', 'El QR cobra Bs 0,50 y el total es Bs ' + P.bs(c.total) + '. Cobra con «Mixto».');
      return;
    }
    if (estado.metodo !== 'efectivo') {
      var num = validarComprobante();
      if (!num) return;
      pagos.push({ metodo: 'qr', monto: MONTO_QR, comprobante: num, estado: 'pendiente' });
    }
    if (estado.metodo !== 'qr') {
      var debe = aCobrar();
      var recibido = P.leerMonto($('recibido').value);
      if (recibido === null) { P.marcarCampo('recibido', 'Escribe cuánto entregó el cliente, o toca «Exacto».'); $('recibido').focus(); return; }
      if (recibido < debe) {
        P.marcarCampo('recibido', 'Faltan Bs ' + P.bs(redondear(debe - recibido)) + ' para cubrir el total.');
        $('recibido').focus();
        return;
      }
      pagos.push({ metodo: 'efectivo', monto: debe, recibido: recibido, cambio: redondear(recibido - debe) });
    }
    modal.close();
    finalizar(c, pagos);
  }

  $('confirmar-cobro').addEventListener('click', confirmar);
  modal.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.target.closest('button, summary, input[type=file]')) { e.preventDefault(); confirmar(); }
  });

  // Efectivo exacto: cobra en un toque (F8)
  function cobrarExacto() {
    var c = pintarPedido();
    if (c.total <= 0) return;
    finalizar(c, [{ metodo: 'efectivo', monto: c.total, recibido: c.total, cambio: 0 }]);
  }
  $('cobrar-exacto').addEventListener('click', cobrarExacto);

  /* La venta se registra y el POS queda listo para la siguiente */
  function finalizar(c, pagos) {
    var venta = {
      uuid: (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Date.now()),
      pedido: tres(pedido),
      factura: factura,
      tipo: estado.tipo,
      mesa: estado.tipo === 'mesa' ? $('mesa').value || '1' : '',
      cliente: estado.cliente,
      lineas: estado.lineas.map(function (l) {
        return { id: l.producto.id, nombre: l.producto.nombre, unidad: l.producto.unidad, cantidad: l.cantidad, precio: l.producto.precio, nota: l.nota, cocina: l.producto.cocina };
      }),
      descuentos: c.descuentos.map(function (d) { return { etiqueta: d.etiqueta, monto: d.monto }; }),
      subtotal: c.subtotal, descuento: c.descuento, total: c.total,
      pagos: pagos,
      fecha: D.ahora.fecha, hora: D.ahora.hora,
      cajero: P.ROLES[P.rolActual()].usuario
    };
    P.guardar('pahuichi.venta.' + factura, JSON.stringify(venta));
    pagos.forEach(function (p) {
      if (p.comprobante) comprobantes[p.comprobante] = { pedido: venta.pedido, factura: factura, hora: D.ahora.hora };
    });
    P.guardar('pahuichi.comprobantes', JSON.stringify(comprobantes));

    var efectivo = pagos.filter(function (p) { return p.metodo === 'efectivo'; })[0];
    var qr = pagos.filter(function (p) { return p.metodo === 'qr'; })[0];
    $('venta-lista-titulo').textContent = 'Pedido ' + venta.pedido + ' cobrado' + (venta.mesa ? ' · mesa ' + venta.mesa : ' · para llevar');
    var detalle = ['Factura N° ' + factura];
    if (efectivo && efectivo.cambio) detalle.push('cambio Bs ' + P.bs(efectivo.cambio));
    if (qr) detalle.push('QR pendiente de confirmar');
    detalle.push(impresoraCaida ? 'impresora sin conexión: 3 tickets en cola' : 'imprimiendo comanda, copia y factura');
    $('venta-lista-detalle').textContent = detalle.join(' · ');
    $('venta-lista').classList.toggle('venta-lista--alerta', impresoraCaida);
    $('venta-lista-ver').href = 'tickets.html?n=' + factura;
    $('venta-lista').hidden = false;

    pedido++;
    factura++;
    P.guardar('pahuichi.pedido', String(pedido));
    P.guardar('pahuichi.factura', String(factura));
    $('numero-pedido').textContent = tres(pedido);
    vaciar();
    $('pedido').classList.remove('esta-abierto');
    $('ver-pedido').setAttribute('aria-expanded', 'false');
    $('venta-lista').hidden = false;
    $('pos-buscar').focus();
  }

  /* ------------------------------------------------------------------
     Ventas en espera (hasta 10)
     ------------------------------------------------------------------ */
  function leerEspera() {
    try { return JSON.parse(P.leer('pahuichi.espera') || '[]'); } catch (e) { return []; }
  }

  function guardarEspera(lista) {
    P.guardar('pahuichi.espera', JSON.stringify(lista));
    $('cuenta-espera').textContent = lista.length;
    $('ver-espera').disabled = !lista.length;
  }

  $('poner-espera').addEventListener('click', function () {
    var lista = leerEspera();
    if (lista.length >= MAX_ESPERA) { P.aviso('Ya hay 10 ventas en espera. Retoma o cobra alguna antes.'); return; }
    var c = calcular();
    lista.push({
      pedido: tres(pedido), hora: D.ahora.hora, total: c.total, tipo: estado.tipo, mesa: $('mesa').value,
      lineas: estado.lineas.map(function (l) { return { id: l.producto.id, cantidad: l.cantidad, nota: l.nota }; })
    });
    guardarEspera(lista);
    P.aviso('Pedido ' + tres(pedido) + ' en espera.');
    pedido++;
    P.guardar('pahuichi.pedido', String(pedido));
    $('numero-pedido').textContent = tres(pedido);
    vaciar();
    $('pos-buscar').focus();
  });

  $('modal-espera').addEventListener('pahuichi:abrir', function () {
    var lista = leerEspera();
    var ul = $('lista-espera');
    ul.innerHTML = '';
    lista.forEach(function (v, i) {
      var li = document.createElement('li');
      li.className = 'espera__item';
      li.innerHTML = '<div><p class="espera__titulo"></p><p class="espera__detalle"></p></div><button class="boton" type="button">Retomar</button>';
      li.querySelector('.espera__titulo').textContent = 'Pedido ' + v.pedido + (v.mesa && v.tipo === 'mesa' ? ' · mesa ' + v.mesa : ' · para llevar');
      li.querySelector('.espera__detalle').textContent = v.lineas.length + (v.lineas.length === 1 ? ' producto' : ' productos') + ' · Bs ' + P.bs(v.total) + ' · desde las ' + v.hora;
      li.querySelector('button').addEventListener('click', function () {
        if (estado.lineas.length) { P.aviso('Cobra o pon en espera el pedido actual antes de retomar otro.'); return; }
        estado.lineas = v.lineas.map(function (x) { return { producto: producto(x.id), cantidad: x.cantidad, nota: x.nota }; });
        lista.splice(i, 1);
        guardarEspera(lista);
        $('modal-espera').close();
        pintarPedido();
        pintarGrilla();
        P.aviso('Pedido ' + v.pedido + ' retomado.');
      });
      ul.appendChild(li);
    });
    $('espera-vacio').hidden = lista.length > 0;
  });

  /* ------------------------------------------------------------------
     Descuento manual con PIN del administrador
     ------------------------------------------------------------------ */
  $('modal-descuento').addEventListener('pahuichi:abrir', function () {
    $('form-descuento').reset();
    ['descuento-monto', 'descuento-motivo', 'descuento-pin'].forEach(function (id) { P.marcarCampo(id, ''); });
  });

  $('aplicar-descuento').addEventListener('click', function () {
    var c = calcular();
    var monto = P.leerMonto($('descuento-monto').value);
    var motivo = $('descuento-motivo').value.trim();
    var errores = {
      'descuento-monto': monto === null || monto <= 0 ? 'Escribe un monto mayor que cero.' : monto >= c.total ? 'El descuento no puede dejar la venta en cero.' : '',
      'descuento-motivo': motivo ? '' : 'Escribe el motivo: queda en la bitácora.',
      'descuento-pin': $('descuento-pin').value === '1234' ? '' : 'PIN de administrador incorrecto.'
    };
    var primero = null;
    Object.keys(errores).forEach(function (id) { P.marcarCampo(id, errores[id]); if (errores[id] && !primero) primero = id; });
    if (primero) { $(primero).focus(); return; }
    estado.descuentoManual = { monto: monto, motivo: motivo };
    $('modal-descuento').close();
    pintarPedido();
    P.aviso('Descuento de Bs ' + P.bs(monto) + ' autorizado.');
  });

  /* ------------------------------------------------------------------
     Atajos de teclado: F2 buscar, F4 cobrar, F8 efectivo exacto, Esc
     ------------------------------------------------------------------ */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'F2') { e.preventDefault(); $('pos-buscar').focus(); $('pos-buscar').select(); }
    else if (e.key === 'F4') { e.preventDefault(); if (!modal.open) abrirCobro(); }
    else if (e.key === 'F8') { e.preventDefault(); if (!document.querySelector('dialog[open]')) cobrarExacto(); }
    else if (e.key === 'Escape' && !document.querySelector('dialog[open]')) {
      if ($('pos-buscar').value) { $('pos-buscar').value = ''; pintarGrilla(); }
      cerrarBuscador();
      $('pos-buscar').focus();
    }
  });

  /* ------------------------------------------------------------------
     Inicio
     ------------------------------------------------------------------ */
  if (sinQr) $('qr-vencido').hidden = false;
  $('numero-pedido').textContent = tres(pedido);
  guardarEspera(leerEspera());
  pintarCliente();
  pintarGrilla();
  pintarPedido();
  $('pos-buscar').focus();
})();
