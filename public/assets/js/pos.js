/* HU-10: componentes de demo conectados al cobro persistente en efectivo. */
(function () {
  'use strict';
  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var raiz = $('pos');
  if (!raiz) return;
  var productos = JSON.parse(raiz.getAttribute('data-productos'));
  var lineas = [], categoria = 'favoritos', tipo = 'mesa', enviando = false, pendiente = null;
  var modal = $('modal-cobro');
  var CLAVE = 'pahuichi.hu10.cobroPendiente';
  function centavos(v) { return Math.round(Number(v) * 100); }
  function total() { return lineas.reduce(function (s, l) { return s + centavos(l.producto.precio) * l.cantidad; }, 0); }
  function buscar(id) { return productos.find(function (p) { return Number(p.id) === Number(id); }); }
  function normalizar(s) { return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); }
  function icono(p) {
    var nombre = normalizar(p.categoria);
    return ({ platos: 'productos', refrescos: 'vaso', sodas: 'botella', vinos: 'copa', postres: 'helado', prueba: 'qr' })[nombre] || 'productos';
  }
  function guardarPendiente(d) { try { if (d) sessionStorage.setItem(CLAVE, JSON.stringify(d)); else sessionStorage.removeItem(CLAVE); } catch (e) { /* Sigue protegido por el UUID de esta página. */ } }
  function uuid() {
    var a = crypto.getRandomValues(new Uint8Array(16));
    a[6] = (a[6] & 15) | 64; a[8] = (a[8] & 63) | 128;
    var h = Array.from(a, function (x) { return x.toString(16).padStart(2, '0'); }).join('');
    return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
  }
  function pintarGrilla() {
    var q = normalizar($('pos-buscar').value.trim());
    var lista = productos.filter(function (p) {
      if (q) return normalizar(p.nombre + ' ' + p.nombre_corto + ' ' + p.codigo).includes(q);
      return categoria === 'todo' || (categoria === 'favoritos' ? Number(p.favorito) === 1 : String(p.categoria_id) === categoria);
    });
    $('grilla').replaceChildren();
    lista.forEach(function (p) {
      var li = document.createElement('li'), b = document.createElement('button');
      b.type = 'button'; b.className = 'producto-pos ' + p.clase_categoria;
      b.dataset.id = p.id; b.disabled = Boolean(Number(p.agotado)) || enviando || Boolean(pendiente);
      var l = lineas.find(function (x) { return x.producto.id === p.id; });
      b.innerHTML = '<span class="producto-pos__foto" aria-hidden="true"><svg class="icono producto-pos__icono"><use href="#i-' + icono(p) + '"></use></svg></span><span class="producto-pos__nombre"></span><span class="producto-pos__pie"><span class="producto-pos__precio monto"><span class="monto__moneda">Bs</span></span></span>';
      b.querySelector('.producto-pos__nombre').textContent = p.nombre_corto;
      b.querySelector('.producto-pos__precio').append(document.createTextNode(P.bs(p.precio)));
      if (Number(p.agotado)) { var etiqueta = document.createElement('span'); etiqueta.className = 'insignia insignia--aviso'; etiqueta.textContent = 'Agotado'; b.querySelector('.producto-pos__pie').append(etiqueta); }
      if (l) { var cuenta = document.createElement('span'); cuenta.className = 'producto-pos__cuenta'; cuenta.textContent = l.cantidad; b.append(cuenta); }
      b.setAttribute('aria-label', p.nombre + ', ' + P.bs(p.precio) + ' bolivianos' + (Number(p.agotado) ? ', agotado' : ''));
      li.append(b); $('grilla').append(li);
    });
    $('pos-vacio').hidden = lista.length > 0;
  }
  function pintarPedido() {
    $('lineas').replaceChildren();
    lineas.forEach(function (l) {
      var li = document.createElement('li'); li.className = 'linea'; li.dataset.id = l.producto.id;
      li.innerHTML = '<div class="linea__texto"><span class="linea__nombre"></span><span class="linea__unitario num"></span></div><span class="linea__subtotal num"></span><div class="linea__controles"><div class="paso" role="group"><button class="boton boton--icono paso__boton" type="button" data-paso="-1"><svg class="icono" aria-hidden="true"><use href="#i-menos"></use></svg></button><span class="paso__valor num"></span><button class="boton boton--icono paso__boton" type="button" data-paso="1"><svg class="icono" aria-hidden="true"><use href="#i-mas"></use></svg></button></div><button class="boton boton--fantasma boton--icono" type="button" data-nota aria-expanded="false"><svg class="icono" aria-hidden="true"><use href="#i-nota"></use></svg></button><button class="boton boton--fantasma boton--icono" type="button" data-quitar><svg class="icono" aria-hidden="true"><use href="#i-basura"></use></svg></button></div><label class="linea__nota" hidden><span class="visualmente-oculto">Nota para cocina</span><input class="control" type="text" maxlength="60" placeholder="Nota para cocina, p. ej. sin cebolla"></label><p class="linea__nota-texto" hidden></p>';
      li.querySelector('.linea__nombre').textContent = l.producto.nombre;
      li.querySelector('.linea__unitario').textContent = 'Bs ' + P.bs(l.producto.precio) + ' c/u';
      li.querySelector('.linea__subtotal').textContent = P.bs(centavos(l.producto.precio) * l.cantidad / 100);
      li.querySelector('.paso__valor').textContent = l.cantidad;
      li.querySelector('.paso').setAttribute('aria-label', 'Cantidad de ' + l.producto.nombre);
      li.querySelectorAll('[data-paso]').forEach(function (b) { b.setAttribute('aria-label', (b.dataset.paso === '1' ? 'Una más de ' : 'Una menos de ') + l.producto.nombre); });
      li.querySelector('[data-quitar]').setAttribute('aria-label', 'Quitar ' + l.producto.nombre);
      li.querySelector('[data-nota]').setAttribute('aria-label', 'Nota para cocina de ' + l.producto.nombre);
      li.querySelector('input').value = l.nota;
      if (l.nota) { li.querySelector('.linea__nota-texto').hidden = false; li.querySelector('.linea__nota-texto').textContent = 'Nota: ' + l.nota; }
      li.querySelectorAll('button, input').forEach(function (b) { b.disabled = enviando || Boolean(pendiente); });
      $('lineas').append(li);
    });
    var t = total();
    $('t-subtotal').textContent = $('t-total').textContent = $('resumen-total').textContent = P.bs(t / 100);
    $('lineas-vacio').hidden = lineas.length > 0;
    $('resumen-cuenta').textContent = lineas.length ? lineas.reduce(function (s, l) { return s + l.cantidad; }, 0) + ' ítems' : 'Pedido vacío';
    ['cobrar', 'cobrar-exacto'].forEach(function (id) { $(id).disabled = enviando || (!t && !pendiente); });
    $('confirmar-cobro').disabled = enviando;
    $('vaciar').disabled = !lineas.length || enviando || Boolean(pendiente);
    $('mesa').disabled = enviando || Boolean(pendiente);
    $('recibido').disabled = enviando || Boolean(pendiente);
    document.querySelectorAll('[data-tipo], [data-billete]').forEach(function (b) { b.disabled = enviando || Boolean(pendiente); });
    pintarGrilla();
  }
  function agregar(id, delta) {
    if (enviando || pendiente) return;
    var p = buscar(id); if (!p || Number(p.agotado)) return;
    var l = lineas.find(function (x) { return Number(x.producto.id) === Number(id); });
    if (!l) { l = { producto: p, cantidad: 0, nota: '' }; lineas.push(l); }
    if (l.cantidad + delta > 999) { P.aviso('Usa hasta 999 unidades por línea.'); return; }
    l.cantidad += delta; lineas = lineas.filter(function (x) { return x.cantidad > 0; });
    $('venta-lista').hidden = true; $('anuncio').textContent = p.nombre + ': ' + Math.max(l.cantidad, 0) + ' en el pedido.';
    pintarPedido();
  }
  document.querySelector('.categorias-pos').addEventListener('click', function (e) {
    var b = e.target.closest('[data-categoria]'); if (!b) return;
    categoria = b.dataset.categoria; $('pos-buscar').value = '';
    document.querySelectorAll('[data-categoria]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); }); pintarGrilla();
  });
  $('grilla').addEventListener('click', function (e) { var b = e.target.closest('[data-id]'); if (b && !b.disabled) agregar(Number(b.dataset.id), 1); });
  $('pos-buscar').addEventListener('input', pintarGrilla);
  $('pos-buscar').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); var b = $('grilla').querySelector('button:not(:disabled)'); if (b) b.click(); } });
  $('lineas').addEventListener('click', function (e) {
    if (enviando || pendiente) return;
    var li = e.target.closest('.linea'); if (!li) return;
    var l = lineas.find(function (x) { return Number(x.producto.id) === Number(li.dataset.id); });
    var paso = e.target.closest('[data-paso]');
    if (paso) agregar(l.producto.id, Number(paso.dataset.paso));
    if (e.target.closest('[data-quitar]')) agregar(l.producto.id, -l.cantidad);
    var nota = e.target.closest('[data-nota]');
    if (nota) { var campo = li.querySelector('.linea__nota'); campo.hidden = !campo.hidden; nota.setAttribute('aria-expanded', String(!campo.hidden)); if (!campo.hidden) campo.querySelector('input').focus(); }
  });
  $('lineas').addEventListener('input', function (e) { if (!e.target.matches('input') || pendiente) return; var id = Number(e.target.closest('.linea').dataset.id); lineas.find(function (l) { return Number(l.producto.id) === id; }).nota = e.target.value.trim(); });
  $('vaciar').addEventListener('click', function () { if (!pendiente) { lineas = []; pintarPedido(); } });
  $('ver-pedido').addEventListener('click', function () { this.setAttribute('aria-expanded', String($('pedido').classList.toggle('esta-abierto'))); });
  document.querySelectorAll('[data-tipo]').forEach(function (b) { b.addEventListener('click', function () { tipo = b.dataset.tipo; document.querySelectorAll('[data-tipo]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); }); $('mesa').hidden = tipo !== 'mesa'; }); });
  function cambio() { var v = P.leerMonto($('recibido').value); $('cambio').textContent = 'Bs ' + P.bs(Math.max(0, centavos(v || 0) - total()) / 100); }
  $('recibido').addEventListener('input', cambio);
  document.querySelectorAll('[data-billete]').forEach(function (b) { b.addEventListener('click', function () { $('recibido').value = b.dataset.billete === 'exacto' ? (total() / 100).toFixed(2) : b.dataset.billete; cambio(); }); });
  $('cobrar').addEventListener('click', function () { if (pendiente) { enviar(false); return; } $('cobro-total').textContent = P.bs(total() / 100); $('recibido').value = ''; cambio(); P.marcarCampo('recibido', ''); modal.showModal(); $('recibido').focus(); });
  $('cobrar-exacto').addEventListener('click', function () { enviar(true); });
  $('confirmar-cobro').addEventListener('click', function () { enviar(false); });
  modal.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.matches('input')) { e.preventDefault(); enviar(false); } });
  async function enviar(exacto) {
    if (enviando || (!lineas.length && !pendiente)) return;
    if (!pendiente) {
      var recibido = exacto ? total() / 100 : P.leerMonto($('recibido').value);
      if (recibido === null || !Number.isFinite(recibido) || centavos(recibido) < total()) { P.marcarCampo('recibido', 'Escribe un monto que cubra el total.'); $('recibido').focus(); return; }
      if (tipo === 'mesa' && !/^[1-9]\d?$/.test($('mesa').value.trim())) { P.aviso('Escribe el número de mesa, de 1 a 99.'); return; }
      pendiente = { uuid: uuid(), caja_id: raiz.dataset.caja, tipo: tipo, mesa: tipo === 'mesa' ? $('mesa').value.trim() : '', exacto: exacto ? '1' : '0', recibido: recibido.toFixed(2), total_esperado: (total() / 100).toFixed(2), lineas: lineas.map(function (l) { return { id: Number(l.producto.id), cantidad: l.cantidad, nota: l.nota }; }) };
      guardarPendiente(pendiente);
    }
    enviando = true; pintarPedido();
    try {
      var datos = new FormData(); Object.keys(pendiente).forEach(function (k) { datos.append(k, k === 'lineas' ? JSON.stringify(pendiente[k]) : pendiente[k]); });
      datos.append('_csrf', document.body.dataset.csrf);
      var r = await fetch('/pos/cobrar', { method: 'POST', body: datos, headers: { Accept: 'application/json' } });
      if (!r.headers.get('content-type').includes('application/json')) throw new Error('No se pudo confirmar la respuesta. Recarga e inicia sesión si hace falta; el pedido conserva su identificador para reintentar.');
      var d = await r.json();
      if (!r.ok) {
        if (r.status < 500) { pendiente = null; guardarPendiente(null); }
        throw new Error(d.error || 'No se pudo confirmar. Reintenta el mismo cobro.');
      }
      pendiente = null; guardarPendiente(null); lineas = [];
      if (modal.open) modal.close();
      $('venta-lista-titulo').textContent = 'Pedido ' + d.pedido + ' cobrado';
      $('venta-lista-detalle').textContent = 'Factura N° ' + d.numero + ' · cambio Bs ' + P.bs(d.cambio) + ' · lista para imprimir';
      $('venta-lista-ver').href = d.tickets; $('venta-lista').hidden = false; $('numero-pedido').textContent = String(Number(d.pedido) + 1).padStart(3, '0');
      $('pedido').classList.add('esta-abierto'); $('ver-pedido').setAttribute('aria-expanded', 'true');
      $('anuncio').textContent = 'Venta guardada. Abre Ver tickets para imprimir la factura.';
    } catch (err) { P.aviso(err.message + (pendiente ? ' Pulsa Cobrar para reintentar el mismo pedido sin duplicarlo.' : '')); }
    finally { enviando = false; pintarPedido(); }
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'F2') { e.preventDefault(); $('pos-buscar').focus(); }
    if (!modal.open && e.key === 'F4') { e.preventDefault(); if (!$('cobrar').disabled) $('cobrar').click(); }
    if (!modal.open && e.key === 'F8') { e.preventDefault(); if (!$('cobrar-exacto').disabled) $('cobrar-exacto').click(); }
  });
  try {
    var guardado = JSON.parse(sessionStorage.getItem(CLAVE) || 'null');
    if (guardado && Array.isArray(guardado.lineas)) {
      pendiente = guardado; tipo = guardado.tipo;
      lineas = guardado.lineas.map(function (l) { return { producto: buscar(l.id), cantidad: l.cantidad, nota: l.nota }; }).filter(function (l) { return l.producto; });
      P.aviso('Hay un cobro sin respuesta confirmada. Pulsa Cobrar para consultar o reintentar el mismo pedido.');
    }
  } catch (e) { guardarPendiente(null); }
  pintarPedido();
})();
