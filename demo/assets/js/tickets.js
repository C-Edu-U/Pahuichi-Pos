/* Tickets de un pedido (demo): comanda, copia de caja y factura.
   Con servidor salen del snapshot_json de la factura; aquí, de la venta
   guardada por el POS o de un ejemplo. CUF: SHA-256 de NIT, fecha y hora con
   milisegundos, sucursal, punto de venta, número y código de control del
   CUFD; primeros 28 caracteres en mayúsculas. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var NIT = '1023456021';
  var CONTROL_CUFD = '7A3F2C91D4E8B605';
  var LEYENDAS = [
    'Ley N° 453: El proveedor deberá entregar el producto en las modalidades y términos ofertados o convenidos.',
    'Ley N° 453: Tienes derecho a recibir información sobre las características y contenidos de los productos que consumes.',
    'Ley N° 453: Está prohibido importar, distribuir o comercializar productos expirados o prontos a expirar.',
    'Ley N° 453: Tienes derecho a un trato equitativo sin discriminación en la oferta de productos.'
  ];

  var EJEMPLO = {
    pedido: '042', factura: 128, tipo: 'mesa', mesa: '4', fecha: '2026-10-08', hora: '12:38',
    cliente: { nombre: 'Rodríguez Peña', doc: 'CI 4567891' }, cajero: 'Mariela Suárez Añez',
    lineas: [
      { id: 'PL-01', nombre: 'Majadito de charque', unidad: 'Plato', cantidad: 1, precio: 40, nota: 'sin huevo', cocina: true },
      { id: 'PL-09', nombre: 'Sonso de yuca', unidad: 'Porción', cantidad: 2, precio: 15, nota: '', cocina: true },
      { id: 'RE-01', nombre: 'Mocochinchi', unidad: 'Vaso', cantidad: 1, precio: 10, nota: '', cocina: false },
      { id: 'SO-01', nombre: 'Soda cola 500 ml', unidad: 'Botella', cantidad: 1, precio: 8, nota: '', cocina: false },
      { id: 'PO-01', nombre: 'Arroz con leche', unidad: 'Porción', cantidad: 1, precio: 12, nota: '', cocina: false }
    ],
    descuentos: [{ etiqueta: 'Combo majadito + mocochinchi', monto: 5 }, { etiqueta: 'Postres 10 % de lunes a viernes', monto: 1.2 }],
    pagos: [{ metodo: 'efectivo', monto: 93.8, recibido: 100, cambio: 6.2 }]
  };
  EJEMPLO.subtotal = 100;
  EJEMPLO.descuento = 6.2;
  EJEMPLO.total = 93.8;

  var n = parseInt(P.parametro('n'), 10);
  var venta = null;
  try { venta = JSON.parse(P.leer('pahuichi.venta.' + n) || 'null'); } catch (e) { venta = null; }
  if (!venta) { venta = EJEMPLO; if (n) venta.factura = n; }

  var fechaLarga = venta.fecha.split('-').reverse().join('/') + ' ' + venta.hora;
  var reimpresion = P.parametro('reimpresion') === '1';

  function texto(campo, valor) {
    document.querySelectorAll('[data-campo="' + campo + '"]').forEach(function (el) { el.textContent = valor; });
  }

  function filaTotal(dl, etiqueta, valor, fuerte) {
    var div = document.createElement('div');
    if (fuerte) div.className = 'ticket__fuerte';
    div.innerHTML = '<dt></dt><dd></dd>';
    div.children[0].textContent = etiqueta;
    div.children[1].textContent = valor;
    dl.appendChild(div);
  }

  $('titulo-tickets').textContent = 'Pedido ' + venta.pedido + ' · factura N° ' + venta.factura;
  document.title = 'Pedido ' + venta.pedido + ' · Pahuichi POS';
  texto('pedido', venta.pedido);
  texto('factura', String(venta.factura));
  texto('hora-larga', fechaLarga);
  texto('cajero', venta.cajero);
  texto('destino', venta.tipo === 'mesa' ? 'MESA ' + venta.mesa : 'PARA LLEVAR');
  texto('destino-corto', venta.tipo === 'mesa' ? 'Mesa ' + venta.mesa : 'Para llevar');
  texto('cliente', venta.cliente.nombre);
  texto('doc', venta.cliente.doc.replace(/^(CI|NIT|CEX|PAS) /, ''));
  texto('leyenda', LEYENDAS[venta.factura % LEYENDAS.length]);
  texto('literal', 'Son: ' + window.numeroALetras(venta.total));

  /* Comanda: solo lo que va a cocina, con cantidades y notas, sin precios */
  var cocina = venta.lineas.filter(function (l) { return l.cocina; });
  var ulCocina = document.querySelector('[data-campo="items-cocina"]');
  cocina.forEach(function (l) {
    var li = document.createElement('li');
    li.innerHTML = '<span class="comanda__cantidad"></span><span class="comanda__nombre"></span>';
    li.children[0].textContent = l.cantidad + ' ×';
    li.children[1].textContent = l.nombre;
    if (l.nota) {
      var nota = document.createElement('span');
      nota.className = 'comanda__nota';
      nota.textContent = '» ' + l.nota.toUpperCase();
      li.appendChild(nota);
    }
    ulCocina.appendChild(li);
  });
  if (!cocina.length) {
    var li = document.createElement('li');
    li.className = 'comanda__vacia';
    li.textContent = 'Sin platos para cocina: no se imprime comanda.';
    ulCocina.appendChild(li);
  }

  /* Copia de caja y detalle de factura */
  var ulCopia = document.querySelector('[data-campo="lineas-precio"]');
  var ulFactura = document.querySelector('[data-campo="lineas-factura"]');
  venta.lineas.forEach(function (l) {
    var sub = P.bs(l.precio * l.cantidad);
    var a = document.createElement('li');
    a.innerHTML = '<span></span><span class="num"></span>';
    a.children[0].textContent = l.cantidad + ' × ' + l.nombre;
    a.children[1].textContent = sub;
    ulCopia.appendChild(a);

    var b = document.createElement('li');
    b.innerHTML = '<span class="ticket__codigo"></span><span class="ticket__desc"></span><span class="ticket__unidad"></span><span class="ticket__calculo num"></span><span class="ticket__sub num"></span>';
    b.children[0].textContent = l.id;
    b.children[1].textContent = l.nombre;
    b.children[2].textContent = 'Unidad: ' + l.unidad;
    b.children[3].textContent = P.bs(l.cantidad) + ' × ' + P.bs(l.precio) + ' − 0,00';
    b.children[4].textContent = sub;
    ulFactura.appendChild(b);
  });

  var dlCopia = document.querySelector('[data-campo="totales"]');
  filaTotal(dlCopia, 'Subtotal', P.bs(venta.subtotal));
  venta.descuentos.forEach(function (d) { filaTotal(dlCopia, d.etiqueta, '−' + P.bs(d.monto)); });
  filaTotal(dlCopia, 'TOTAL Bs', P.bs(venta.total), true);

  var dlPagos = document.querySelector('[data-campo="pagos"]');
  venta.pagos.forEach(function (p) {
    if (p.metodo === 'efectivo') {
      filaTotal(dlPagos, 'Efectivo', P.bs(p.monto));
      filaTotal(dlPagos, 'Recibido', P.bs(p.recibido));
      filaTotal(dlPagos, 'Cambio', P.bs(p.cambio), true);
    } else {
      filaTotal(dlPagos, 'QR ' + p.comprobante, P.bs(p.monto));
      filaTotal(dlPagos, 'Estado del QR', 'Pendiente');
    }
  });

  var dlFactura = document.querySelector('[data-campo="totales-factura"]');
  filaTotal(dlFactura, 'SUBTOTAL Bs', P.bs(venta.subtotal));
  filaTotal(dlFactura, 'DESCUENTO Bs', P.bs(venta.descuento));
  filaTotal(dlFactura, 'TOTAL Bs', P.bs(venta.total));
  filaTotal(dlFactura, 'MONTO GIFT CARD Bs', '0,00');
  filaTotal(dlFactura, 'MONTO A PAGAR Bs', P.bs(venta.total), true);
  filaTotal(dlFactura, 'IMPORTE BASE CRÉDITO FISCAL Bs', P.bs(venta.total));

  /* CUF y QR a la verificación propia */
  function pintarQr(cuf) {
    var url = new URL('../verificar.html', window.location.href);
    url.searchParams.set('nit', NIT);
    url.searchParams.set('cuf', cuf);
    url.searchParams.set('numero', venta.factura);
    url.searchParams.set('monto', venta.total.toFixed(2));
    document.querySelector('[data-campo="enlace-verificar"]').href = url.href;
    if (window.qrcode) {
      var qr = window.qrcode(0, 'M');
      qr.addData(url.href);
      qr.make();
      document.querySelector('[data-campo="qr"]').innerHTML = qr.createSvgTag({ cellSize: 3, margin: 0, scalable: true });
    }
  }

  var base = [NIT, venta.fecha.replace(/-/g, '') + venta.hora.replace(':', '') + '00000', '0000', '0000', String(venta.factura).padStart(10, '0'), CONTROL_CUFD].join('');
  if (window.crypto && crypto.subtle && window.TextEncoder) {
    crypto.subtle.digest('SHA-256', new TextEncoder().encode(base)).then(function (h) {
      var hex = Array.prototype.map.call(new Uint8Array(h), function (b) { return b.toString(16).padStart(2, '0'); }).join('');
      var cuf = hex.slice(0, 28).toUpperCase();
      texto('cuf', cuf);
      pintarQr(cuf);
    });
  } else {
    texto('cuf', '4A1F6E2B9C0D37E5A8B1C46F2D90');
    pintarQr('4A1F6E2B9C0D37E5A8B1C46F2D90');
  }

  /* Reimpresión, ancho de papel e impresión */
  function marcarReimpresion(si) {
    document.querySelectorAll('.ticket__reimpresion').forEach(function (el) { el.hidden = !si; });
    $('aviso-reimpresion').hidden = !si;
  }
  marcarReimpresion(reimpresion);

  document.querySelectorAll('[data-ancho]').forEach(function (b) {
    b.addEventListener('click', function () {
      var ancho = b.getAttribute('data-ancho');
      document.querySelectorAll('[data-ancho]').forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
      $('tickets').classList.toggle('tickets--58', ancho === '58');
    });
  });

  $('reimprimir').addEventListener('click', function () {
    marcarReimpresion(true);
    P.aviso('Los tres tickets se enviaron a la cola de impresión con la marca REIMPRESIÓN.');
    window.print();
  });

  $('descargar-pdf').addEventListener('click', function () {
    document.body.classList.add('solo-factura');
    window.print();
    document.body.classList.remove('solo-factura');
  });
})();
