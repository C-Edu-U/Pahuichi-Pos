/* Pedidos del mesero (HU-35), en el celular.
   - Arriba, las mesas abiertas con sus pedidos: agregar otro pedido, cancelar uno propio y
     cerrar y cobrar la cuenta (cuentas.js). Se actualiza cada 20 segundos.
   - Abajo, el pedido nuevo: mesa (1 a 12 u otra), catálogo y líneas con sus notas para
     cocina. Las promociones se calculan como en caja (motor-promociones.js), a la hora del
     pedido. «Enviar a caja» lo suma a la cuenta de la mesa (la abre si es el primero).
   - Sin red (fase B): pedidos y cobros van a la cola del mesero (cola-ventas.js, clave
     propia) y se envían solos al volver la red, en orden: el cobro de una mesa sale después
     de sus pedidos. Las mesas se ven con la última lista que llegó más lo que falta enviar.
     El pedido en armado se guarda en el equipo y vuelve al recargar. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var C = window.PahuichiCuentas;
  var Cola = window.PahuichiColaVentas;
  var Promos = window.PahuichiPromociones;
  var $ = function (id) { return document.getElementById(id); };
  var raiz = $('mesero');
  if (!raiz) return;

  var usuario = raiz.getAttribute('data-usuario');
  var hayCaja = raiz.getAttribute('data-caja') === '1';
  var carrito = new window.PahuichiCarrito(JSON.parse(raiz.getAttribute('data-productos') || '[]'));
  var promociones = JSON.parse(raiz.getAttribute('data-promociones') || '[]');
  var usos = {};
  promociones.forEach(function (p) { usos[p.id] = p.usos; });
  var grilla = $('grilla');
  var buscador = $('pos-buscar');
  var barra = $('categorias-pos');
  var categoria = grilla.getAttribute('data-inicial') || 'todo';
  var enviando = false;
  var clave = 'pahuichi.hu35.pedido.' + usuario;
  var claveMesas = 'pahuichi.hu35.mesas.' + usuario;

  function anunciar(texto) { $('anuncio').textContent = texto; }
  function centavos(bs) { return Math.round(Number(bs) * 100); }
  function csrf() { return document.body.getAttribute('data-csrf') || ''; }
  function leer(k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } }
  function escribir(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* sin almacenamiento */ } }

  // La hora del servidor al cargar más lo transcurrido (HU-49). Si la página salió de la
  // caché sin red (HU-36), vale la última hora del servidor vista con red.
  var ahoraServidor = Date.parse(raiz.getAttribute('data-ahora') + 'Z');
  var cargado = Date.now();
  var reloj = leer('pahuichi.hu36.reloj');
  if (document.documentElement.hasAttribute('data-sin-red')) {
    if (reloj) { ahoraServidor = reloj.servidor; cargado = reloj.equipo; }
  } else {
    escribir('pahuichi.hu36.reloj', {servidor: ahoraServidor, equipo: cargado});
  }
  function contexto() {
    var d = new Date(ahoraServidor + (Date.now() - cargado));
    var iso = d.toISOString();
    return {fecha: iso.slice(0, 10), dia: d.getUTCDay() || 7, hora: iso.slice(11, 19), usos: usos};
  }
  function evaluacion() { return Promos.evaluar(carrito.lineas, promociones, carrito.catalogo, contexto()); }
  function promoCentavos(ev) { return ev.lineas.reduce(function (s, a) { return s + a.centavos; }, 0); }
  function regalosCentavos(ev) { return ev.regalos.reduce(function (s, r) { return s + r.centavos; }, 0); }

  /* ------------------------------------------------------------------ cola del mesero */
  var almacen;
  try { almacen = window.localStorage; almacen.getItem(clave); } catch (e) { almacen = null; }
  var cola = almacen ? Cola.crear({
    almacen: almacen, usuario: usuario, clave: 'pahuichi.hu35.cola', prefijo: 'M-',
    enviar: function (d) { return Cola.enviarAlServidor(d, csrf, d.accion === 'cierre' ? '/cuentas/cerrar' : '/cuentas/rondas'); },
    alCambiar: function () { pintarMesas(); }
  }) : null;

  /* ------------------------------------------------------------------ catálogo */
  function filtrar() {
    var q = P.normalizar(buscador.value.trim());
    var n = 0;
    Array.prototype.forEach.call(grilla.children, function (li) {
      var ok = q ? P.normalizar(li.getAttribute('data-busqueda')).indexOf(q) !== -1
        : categoria === 'todo' || (categoria === 'favoritos' ? li.getAttribute('data-favorito') === '1' : li.getAttribute('data-categoria') === categoria);
      li.hidden = !ok;
      if (ok) n++;
    });
    $('pos-vacio').hidden = n !== 0;
  }
  barra.addEventListener('click', function (e) {
    var b = e.target.closest('[data-categoria]');
    if (!b) return;
    categoria = b.getAttribute('data-categoria');
    barra.querySelectorAll('[data-categoria]').forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
    buscador.value = '';
    filtrar();
  });
  buscador.addEventListener('input', filtrar);
  grilla.addEventListener('click', function (e) {
    var b = e.target.closest('.producto-pos');
    if (!b || b.disabled) return;
    var l = carrito.agregar(b.getAttribute('data-id'));
    if (l) { anunciar(l.producto.nombre + ': ' + l.cantidad + ' en el pedido.'); pintar(); }
  });

  /* ------------------------------------------------------------------ pedido */
  function boton(clase, icono, atributo, etiqueta) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'boton boton--icono ' + clase;
    b.setAttribute(atributo, '');
    b.setAttribute('aria-label', etiqueta);
    b.innerHTML = '<svg class="icono" aria-hidden="true"><use href="#i-' + icono + '"></use></svg>';
    return b;
  }

  function pintar() {
    var ul = $('m-lineas');
    ul.innerHTML = '';
    carrito.lineas.forEach(function (l) {
      var p = l.producto;
      var li = document.createElement('li');
      li.className = 'linea';
      li.setAttribute('data-id', p.id);
      li.innerHTML = '<div class="linea__texto"><span class="linea__nombre"></span><span class="linea__unitario num"></span></div>' +
        '<span class="linea__subtotal num"></span><div class="linea__controles"><div class="paso" role="group"><span class="paso__valor num"></span></div></div>';
      li.querySelector('.linea__nombre').textContent = p.nombre;
      li.querySelector('.linea__unitario').textContent = 'Bs ' + P.bs(p.precio) + ' c/u';
      li.querySelector('.linea__subtotal').textContent = P.bs(carrito.subtotalDe(l));
      var paso = li.querySelector('.paso');
      paso.setAttribute('aria-label', 'Cantidad de ' + p.nombre);
      paso.querySelector('.paso__valor').textContent = l.cantidad;
      paso.insertBefore(boton('paso__boton', 'menos', 'data-menos', 'Una menos de ' + p.nombre), paso.firstChild);
      paso.appendChild(boton('paso__boton', 'mas', 'data-mas', 'Una más de ' + p.nombre));
      if (p.cocina) {
        var nota = document.createElement('label');
        nota.className = 'linea__nota';
        nota.innerHTML = '<span class="visualmente-oculto">Nota para cocina</span><input class="control" type="text" maxlength="60" enterkeyhint="done" placeholder="Nota para cocina, p. ej. sin cebolla">';
        nota.querySelector('input').value = l.nota || '';
        li.appendChild(nota);
      }
      li.querySelector('.linea__controles').appendChild(boton('boton--fantasma', 'basura', 'data-quitar', 'Quitar ' + p.nombre));
      ul.appendChild(li);
    });
    var ev = evaluacion();
    ev.regalos.forEach(function (r) {
      var li = document.createElement('li');
      li.className = 'linea linea--regalo';
      li.innerHTML = '<span class="linea__nombre"></span><span class="linea__subtotal num">0,00</span>';
      li.querySelector('.linea__nombre').textContent = r.cantidad + ' × ' + r.producto.nombre + ' · regalo (' + r.promocion.nombre + ')';
      ul.appendChild(li);
    });
    Promos.agrupar(ev.lineas).forEach(function (g) {
      var li = document.createElement('li');
      li.className = 'linea linea--promo';
      li.innerHTML = '<span class="linea__nombre"></span><span class="linea__subtotal num"></span>';
      li.querySelector('.linea__nombre').textContent = g.promocion.nombre;
      li.querySelector('.linea__subtotal').textContent = '−' + P.bs(g.centavos / 100);
      ul.appendChild(li);
    });
    var promo = promoCentavos(ev);
    $('m-desglose').hidden = !promo;
    $('m-subtotal').textContent = P.bs(carrito.total());
    $('m-promociones').textContent = '−' + P.bs(promo / 100);
    $('m-total').textContent = P.bs((centavos(carrito.total()) - promo) / 100);
    $('m-vacio').hidden = !carrito.vacio();
    $('m-vaciar').hidden = carrito.vacio();
    $('m-enviar').disabled = enviando || carrito.vacio();
    guardar();
  }

  $('m-lineas').addEventListener('click', function (e) {
    var li = e.target.closest('.linea[data-id]');
    if (!li) return;
    var id = li.getAttribute('data-id');
    if (e.target.closest('[data-mas]')) carrito.cambiar(id, 1);
    else if (e.target.closest('[data-menos]')) carrito.cambiar(id, -1);
    else if (e.target.closest('[data-quitar]')) carrito.quitar(id);
    else return;
    pintar();
  });
  $('m-lineas').addEventListener('input', function (e) {
    if (!e.target.matches('.linea__nota input')) return;
    var l = carrito.linea(e.target.closest('.linea').getAttribute('data-id'));
    if (l) { l.nota = e.target.value; guardar(); }
  });
  $('m-vaciar').addEventListener('click', function () {
    if (!window.confirm('¿Vaciar el pedido? Se quitan todos los ítems.')) return;
    carrito.vaciar(); pintar();
  });

  // Mesa: los botones llenan el campo (app.js); el título del pedido la nombra
  function pintarMesa() {
    var mesa = $('m-mesa').value;
    $('m-mesa-titulo').textContent = /^[1-9]\d?$/.test(mesa) ? 'de la mesa ' + mesa : 'sin mesa';
  }
  function elegirMesa(mesa) { $('m-mesa').value = mesa; P.mesa($('m-mesa')); pintarMesa(); guardar(); }
  $('m-mesa').addEventListener('input', function () {
    var limpio = this.value.replace(/\D/g, '').replace(/^0+/, '');
    if (limpio !== this.value) { this.value = limpio; P.mesa(this); }
    error('');
    pintarMesa(); guardar();
  });

  function error(texto) { $('m-error').textContent = texto; $('m-error').hidden = !texto; }

  /* El pedido en armado se guarda en el equipo */
  var listo = false;
  function guardar() {
    if (!listo) return;
    escribir(clave, {mesa: $('m-mesa').value,
      lineas: carrito.lineas.map(function (l) { return {id: String(l.producto.id), cantidad: l.cantidad, nota: l.nota || ''}; })});
  }
  function cargar(guardado) {
    carrito.vaciar();
    (guardado.lineas || []).forEach(function (g) {
      var l = carrito.agregar(String(g.id));
      if (!l) return;
      l.cantidad = Math.max(1, Math.min(999, parseInt(g.cantidad, 10) || 1));
      l.nota = String(g.nota || '').slice(0, 60);
    });
    elegirMesa(/^[1-9]\d?$/.test(guardado.mesa || '') ? guardado.mesa : '');
  }
  var guardado = leer(clave);
  if (guardado) cargar(guardado);
  listo = true;
  pintarMesa();
  pintar();

  /* ------------------------------------------------------------------ enviar a caja */
  $('m-enviar').addEventListener('click', async function () {
    if (enviando || carrito.vacio()) return;
    var mesa = $('m-mesa').value;
    if (!/^[1-9]\d?$/.test(mesa)) {
      error('Elige la mesa, de 1 a 99.');
      $('m-mesas').querySelector('.mesa-opcion').focus();
      return;
    }
    if (!cola) { error('Este equipo no deja guardar datos del sitio. Permite el almacenamiento para enviar pedidos sin perderlos.'); return; }
    var ev = evaluacion();
    var c = contexto();
    var neto = (centavos(carrito.total()) - promoCentavos(ev)) / 100;
    var datos = {accion: 'ronda', uuid: crypto.randomUUID(), mesa: mesa, momento: c.fecha + 'T' + c.hora,
      lineas: JSON.stringify(carrito.lineas.map(function (l) { return {id: Number(l.producto.id), cantidad: l.cantidad, nota: (l.nota || '').trim()}; })),
      total_esperado: carrito.total().toFixed(2), promociones: (promoCentavos(ev) / 100).toFixed(2), regalos: (regalosCentavos(ev) / 100).toFixed(2)};
    var item;
    try { item = cola.agregar(datos, {total: neto.toFixed(2), items: carrito.unidades(), hora: c.hora.slice(0, 5)}); }
    catch (e) { error('No se pudo guardar el pedido en este equipo. Libera espacio y vuelve a intentar.'); return; }

    enviando = true; $('m-enviar').disabled = true; error('');
    var r = await cola.procesar();
    enviando = false;
    var mio = function (v) { return v.uuid === datos.uuid; };
    var enviada = r.enviadas.filter(mio)[0];
    var rechazada = r.rechazadas.filter(mio)[0];
    avisar(r, datos.uuid);
    if (rechazada) {
      // Con red y rechazado: el pedido sigue en pantalla para corregirlo
      cola.quitar(datos.uuid);
      error(rechazada.motivo);
      pintar();
      return;
    }
    Promos.agrupar(ev.lineas).concat(ev.regalos).forEach(function (g) { usos[g.promocion.id] = (usos[g.promocion.id] || 0) + 1; });
    carrito.vaciar();
    if (enviada) {
      cola.quitar(datos.uuid);
      P.aviso('Pedido ' + enviada.factura.numero + ' de la mesa ' + mesa + ' enviado a caja.');
      anunciar('Pedido enviado a caja. La comanda sale en caja.');
    } else {
      P.aviso(r.detenido === 'sesion'
        ? 'Tu sesión terminó: el pedido de la mesa ' + mesa + ' quedó guardado. Entra de nuevo y se enviará.'
        : 'Sin conexión: pedido ' + item.provisional + ' de la mesa ' + mesa + ' guardado. Avisa a cocina; se envía solo al volver la red.', 'aviso');
      anunciar('Sin conexión. Pedido guardado en el celular; se enviará al volver la red.');
    }
    pintar();
    actualizar();
  });

  /* ------------------------------------------------------------------ cobrar la cuenta */
  // El cobro va por la misma cola, detrás de los pedidos de la mesa que esperan la red
  async function enviarCobro(datos, cuenta) {
    if (!cola) return C.pedir('/cuentas/cerrar', datos);
    var c = contexto();
    cola.agregar(Object.assign({accion: 'cierre'}, datos), {total: cuenta.total, items: 0, hora: c.hora.slice(0, 5)});
    var r = await cola.procesar();
    var mio = function (v) { return v.uuid === datos.uuid; };
    var enviada = r.enviadas.filter(mio)[0];
    var rechazada = r.rechazadas.filter(mio)[0];
    avisar(r, datos.uuid);
    if (enviada) { cola.quitar(datos.uuid); return enviada.factura; }
    if (rechazada) {
      cola.quitar(datos.uuid);
      var e = new Error(rechazada.motivo);
      e.datos = {error: rechazada.motivo, descuento: rechazada.descuento};
      throw e;
    }
    return {guardado: true, sesion: r.detenido === 'sesion'};
  }
  function cobrada(r, c) {
    if (r.guardado) {
      P.aviso('Sin conexión: el cobro de la mesa ' + c.mesa + ' quedó guardado. Guarda el efectivo; la factura sale al volver la red.', 'aviso');
      anunciar('Cobro guardado en el celular; se enviará al volver la red.');
    } else {
      P.aviso('Mesa ' + c.mesa + ' cobrada: factura N° ' + r.numero + ' · cambio Bs ' + P.bs(Number(r.cambio)) + '.');
      anunciar('Mesa ' + c.mesa + ' cobrada. Entrega el efectivo en caja.');
    }
    actualizar();
  }

  /* ------------------------------------------------------------------ mesas */
  // Última lista que llegó del servidor (también guardada, para verla sin red)
  var ultima = leer(claveMesas);
  var sinRed = false;

  async function actualizar() {
    try {
      var r = await C.listar();
      ultima = {caja: r.caja, cuentas: r.cuentas, hora: contexto().hora.slice(0, 5)};
      escribir(claveMesas, ultima);
      sinRed = false;
    } catch (e) {
      sinRed = true;
    }
    if (ultima) hayCaja = ultima.caja;
    pintarMesas();
  }

  function lineasDe(datos) {
    var lineas = [];
    try { lineas = JSON.parse(datos.lineas); } catch (e) { lineas = []; }
    return lineas.map(function (l) {
      var p = carrito.catalogo[String(l.id)];
      return {nombre: p ? p.nombre : 'Producto', cantidad: l.cantidad, nota: l.nota, regalo: false, promociones: []};
    });
  }

  function pintarMesas() {
    var cuentas = ultima ? JSON.parse(JSON.stringify(ultima.cuentas)) : [];
    var abiertas = cuentas.filter(function (c) { return c.estado === 'abierta'; });
    var cerradas = cuentas.filter(function (c) { return c.estado !== 'abierta'; });
    var porEnviar = cola ? cola.ventas().filter(function (v) { return v.estado !== 'enviada'; }) : [];
    // Lo que falta enviar se suma a su mesa (o abre la mesa, si su cuenta nació sin red)
    porEnviar.filter(function (v) { return v.estado === 'pendiente'; }).forEach(function (v) {
      var d = v.datos;
      var c = abiertas.filter(function (x) { return x.mesa === d.mesa; })[0];
      if (!c) {
        if (d.accion === 'cierre') return;
        c = {id: null, mesa: d.mesa, estado: 'abierta', abierta: v.hora, abierta_por: 'sin conexión', total: '0.00', nuevas: 0, rondas: []};
        abiertas.push(c);
      }
      if (d.accion === 'cierre') { c.cobroPendiente = true; return; }
      c.rondas.push({id: null, numero: v.provisional, hora: v.hora, usuario: 'por enviar', estado: 'pendiente',
        subtotal: v.total, lineas: lineasDe(d), cancelable: false, motivo: null});
      c.total = ((centavos(c.total) + centavos(v.total)) / 100).toFixed(2);
    });
    abiertas.sort(function (a, b) { return Number(a.mesa) - Number(b.mesa); });

    var opciones = {caja: false, hayCaja: hayCaja,
      alCobrar: function (c) { C.cobrar(c, cobrada, enviarCobro); },
      alCancelar: function (ronda, c) { C.cancelar(ronda, c, actualizar); },
      alAgregar: function (c) {
        elegirMesa(c.mesa);
        $('m-nuevo').scrollIntoView({behavior: 'smooth', block: 'start'});
        buscador.focus({preventScroll: true});
      }};
    $('m-cuentas').replaceChildren.apply($('m-cuentas'), abiertas.map(function (c) { return C.pintar(c, opciones); }));
    $('m-cuentas-vacio').hidden = abiertas.length !== 0;
    $('m-cobradas-panel').hidden = cerradas.length === 0;
    $('m-cobradas-cuenta').textContent = '(' + cerradas.length + ')';
    $('m-cobradas').replaceChildren.apply($('m-cobradas'), cerradas.map(function (c) { return C.pintar(c, opciones); }));
    $('m-sin-red').hidden = !sinRed;
    $('m-sin-red-texto').textContent = 'Sin conexión' + (ultima && ultima.hora ? ': las mesas son las de las ' + ultima.hora : '') +
      ', más lo que está por enviar. Los pedidos y cobros se guardan en el celular y se envían solos al volver la red.';
    pintarCola(porEnviar);
  }

  // Lo que no se registró queda a la vista con su motivo, para volverlo al pedido o descartarlo
  function pintarCola(porEnviar) {
    var pendientes = porEnviar.filter(function (v) { return v.estado === 'pendiente'; }).length;
    var rechazadas = porEnviar.filter(function (v) { return v.estado === 'rechazada'; });
    $('m-cola').hidden = !pendientes && !rechazadas.length;
    $('m-cola-cuenta').textContent = pendientes ? (pendientes === 1 ? '1 por enviar' : pendientes + ' por enviar') : '';
    $('m-cola-enviar').hidden = !pendientes;
    var ul = $('m-cola-lista');
    ul.innerHTML = '';
    rechazadas.forEach(function (v) {
      var d = v.datos;
      var li = document.createElement('li');
      li.className = 'cola-ventas__item cola-ventas__item--rechazada';
      li.setAttribute('data-uuid', v.uuid);
      li.innerHTML = '<div class="cola-ventas__texto"><span class="cola-ventas__nombre"></span><span class="cola-ventas__estado"></span></div><div class="cola-ventas__acciones"></div>';
      li.querySelector('.cola-ventas__nombre').textContent = (d.accion === 'cierre' ? 'Cobro de la mesa ' : v.provisional + ' · pedido de la mesa ') + d.mesa + ' · ' + v.hora + ' · Bs ' + P.bs(Number(v.total));
      li.querySelector('.cola-ventas__estado').textContent = 'No se registró: ' + v.motivo;
      var acciones = li.querySelector('.cola-ventas__acciones');
      if (d.accion === 'ronda') acciones.appendChild(accion('Volver al pedido', 'retomar'));
      acciones.appendChild(accion('Descartar', 'descartar'));
      ul.appendChild(li);
    });
  }
  function accion(texto, que) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'boton boton--fantasma'; b.setAttribute('data-cola', que); b.textContent = texto;
    return b;
  }
  $('m-cola-lista').addEventListener('click', function (e) {
    var b = e.target.closest('[data-cola]');
    if (!b) return;
    var uuid = b.closest('[data-uuid]').getAttribute('data-uuid');
    var v = cola.ventas().filter(function (x) { return x.uuid === uuid; })[0];
    if (!v) return;
    if (b.getAttribute('data-cola') === 'descartar') {
      if (!window.confirm('¿Descartar ' + (v.datos.accion === 'cierre' ? 'el cobro' : 'el pedido') + ' de la mesa ' + v.datos.mesa + '? No queda registrado.')) return;
      cola.quitar(uuid);
      return;
    }
    if (!carrito.vacio()) { P.aviso('Envía o vacía el pedido en armado antes de retomar otro.'); return; }
    var lineas = [];
    try { lineas = JSON.parse(v.datos.lineas); } catch (err) { lineas = []; }
    cargar({mesa: v.datos.mesa, lineas: lineas});
    cola.quitar(uuid);
    pintar();
    P.aviso('El pedido de la mesa ' + v.datos.mesa + ' volvió a la pantalla. Revísalo y envíalo otra vez.');
  });

  function avisar(r, propio) {
    r.enviadas.forEach(function (v) {
      if (v.uuid === propio) return;
      cola.quitar(v.uuid);
      P.aviso(v.datos.accion === 'cierre'
        ? 'Cobro de la mesa ' + v.datos.mesa + ' enviado: factura N° ' + v.factura.numero + '.'
        : 'Pedido ' + v.provisional + ' de la mesa ' + v.datos.mesa + ' enviado a caja.');
    });
    r.rechazadas.forEach(function (v) {
      if (v.uuid !== propio) P.aviso('No se registró ' + (v.datos.accion === 'cierre' ? 'el cobro' : 'el pedido') + ' de la mesa ' + v.datos.mesa + '. Revísalo en «Sin enviar».', 'peligro');
    });
  }

  async function enviarCola() {
    if (!cola || enviando || !cola.pendientes()) return;
    var r = await cola.procesar();
    avisar(r, null);
    if (r.enviadas.length || r.rechazadas.length) actualizar();
  }

  // Las fotos del catálogo quedan en la caché para abrir la pantalla sin red (HU-36)
  if ('serviceWorker' in navigator) {
    var fotos = Array.prototype.map.call(grilla.querySelectorAll('.producto-pos__foto img'), function (img) { return img.getAttribute('src'); });
    if (fotos.length) {
      navigator.serviceWorker.ready.then(function (reg) { if (reg.active) reg.active.postMessage({tipo: 'fotos', urls: fotos}); })
        .catch(function () { /* sin service worker: las fotos se piden a la red */ });
    }
  }

  $('m-actualizar').addEventListener('click', function () { enviarCola(); actualizar(); });
  $('m-cola-enviar').addEventListener('click', enviarCola);
  window.addEventListener('online', function () { enviarCola().then(actualizar); });
  pintarMesas();
  enviarCola();
  actualizar();
  window.setInterval(function () {
    if (document.hidden || document.querySelector('dialog[open]')) return;
    enviarCola();
    actualizar();
  }, 20000);
})();
