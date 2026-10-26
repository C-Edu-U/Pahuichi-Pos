/* Pedidos del mesero (HU-35), en el celular.
   - Arriba, las mesas abiertas con sus pedidos: agregar otro pedido, cancelar uno propio y
     cerrar y cobrar la cuenta (cuentas.js). Se actualiza cada 20 segundos.
   - Abajo, el pedido nuevo: mesa (1 a 12 u otra), catálogo y líneas con sus notas para
     cocina. Las promociones se calculan como en caja (motor-promociones.js), a la hora del
     pedido. «Enviar a caja» lo suma a la cuenta de la mesa (la abre si es el primero).
   - El pedido en armado se guarda en el equipo y vuelve al recargar. Si el envío no se
     confirmó, el reintento lleva el mismo uuid: caja no lo recibe dos veces. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var C = window.PahuichiCuentas;
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
  var pendiente = null;   // {uuid, firma}: un envío sin confirmar se reintenta con el mismo uuid
  var clave = 'pahuichi.hu35.pedido.' + usuario;

  function anunciar(texto) { $('anuncio').textContent = texto; }
  function centavos(bs) { return Math.round(bs * 100); }

  // La hora del servidor al cargar más lo transcurrido (HU-49), para las promociones
  var ahoraServidor = Date.parse(raiz.getAttribute('data-ahora') + 'Z');
  var cargado = Date.now();
  function contexto() {
    var d = new Date(ahoraServidor + (Date.now() - cargado));
    var iso = d.toISOString();
    return {fecha: iso.slice(0, 10), dia: d.getUTCDay() || 7, hora: iso.slice(11, 19), usos: usos};
  }
  function evaluacion() { return Promos.evaluar(carrito.lineas, promociones, carrito.catalogo, contexto()); }
  function promoCentavos(ev) { return ev.lineas.reduce(function (s, a) { return s + a.centavos; }, 0); }
  function regalosCentavos(ev) { return ev.regalos.reduce(function (s, r) { return s + r.centavos; }, 0); }

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
    carrito.vaciar(); pendiente = null; pintar();
  });

  // Mesa: los botones llenan el campo (app.js); el título del pedido la nombra
  function pintarMesa() {
    var mesa = $('m-mesa').value;
    $('m-mesa-titulo').textContent = /^[1-9]\d?$/.test(mesa) ? 'de la mesa ' + mesa : 'sin mesa';
  }
  $('m-mesa').addEventListener('input', function () {
    var limpio = this.value.replace(/\D/g, '').replace(/^0+/, '');
    if (limpio !== this.value) { this.value = limpio; P.mesa(this); }
    error('');
    pintarMesa(); guardar();
  });

  function error(texto) { $('m-error').textContent = texto; $('m-error').hidden = !texto; }

  /* El pedido en armado se guarda en el equipo (y su envío sin confirmar) */
  var listo = false;
  function guardar() {
    if (!listo) return;
    try {
      localStorage.setItem(clave, JSON.stringify({mesa: $('m-mesa').value, pendiente: pendiente,
        lineas: carrito.lineas.map(function (l) { return {id: String(l.producto.id), cantidad: l.cantidad, nota: l.nota || ''}; })}));
    } catch (e) { /* sin almacenamiento: el pedido sigue en pantalla */ }
  }
  try {
    var guardado = JSON.parse(localStorage.getItem(clave) || 'null');
    if (guardado) {
      (guardado.lineas || []).forEach(function (g) {
        var l = carrito.agregar(g.id);
        if (!l) return;
        l.cantidad = Math.max(1, Math.min(999, parseInt(g.cantidad, 10) || 1));
        l.nota = String(g.nota || '').slice(0, 60);
      });
      $('m-mesa').value = /^[1-9]\d?$/.test(guardado.mesa || '') ? guardado.mesa : '';
      P.mesa($('m-mesa'));
      pendiente = guardado.pendiente || null;
    }
  } catch (e) { /* empieza vacío */ }
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
    var ev = evaluacion();
    var c = contexto();
    var lineas = JSON.stringify(carrito.lineas.map(function (l) { return {id: Number(l.producto.id), cantidad: l.cantidad, nota: (l.nota || '').trim()}; }));
    var datos = {mesa: mesa, lineas: lineas, total_esperado: carrito.total().toFixed(2),
      promociones: (promoCentavos(ev) / 100).toFixed(2), regalos: (regalosCentavos(ev) / 100).toFixed(2)};
    // Mismo pedido sin confirmar: mismo uuid y misma hora (si cambió algo, es otro pedido)
    var firma = JSON.stringify(datos);
    if (!pendiente || pendiente.firma !== firma) pendiente = {uuid: crypto.randomUUID(), firma: firma, momento: c.fecha + 'T' + c.hora};
    guardar();
    enviando = true; $('m-enviar').disabled = true; error('');
    try {
      var r = await C.pedir('/cuentas/rondas', Object.assign({uuid: pendiente.uuid, momento: pendiente.momento}, datos));
      pendiente = null;
      Promos.agrupar(ev.lineas).concat(ev.regalos).forEach(function (g) { usos[g.promocion.id] = (usos[g.promocion.id] || 0) + 1; });
      carrito.vaciar();
      P.aviso('Pedido ' + r.numero + ' de la mesa ' + r.mesa + ' enviado a caja.');
      anunciar('Pedido enviado a caja. La comanda sale en caja.');
      actualizar();
    } catch (e) {
      // Un rechazo del servidor no se reintenta igual: el próximo envío es otro pedido
      if (e.datos && e.datos.error) pendiente = null;
      error(e.datos && e.datos.error ? e.message : 'No se pudo enviar: revisa la conexión y vuelve a pulsar «Enviar a caja». No se duplicará.');
    } finally {
      enviando = false;
      pintar();
    }
  });

  /* ------------------------------------------------------------------ mesas abiertas */
  async function actualizar() {
    var r;
    try { r = await C.listar(); } catch (e) { return; }
    hayCaja = r.caja;
    var abiertas = r.cuentas.filter(function (c) { return c.estado === 'abierta'; });
    var cerradas = r.cuentas.filter(function (c) { return c.estado !== 'abierta'; });
    var opciones = {caja: false, hayCaja: hayCaja,
      alCobrar: function (c) { C.cobrar(c, cobrada); },
      alCancelar: function (ronda, c) { C.cancelar(ronda, c, actualizar); },
      alAgregar: function (c) {
        $('m-mesa').value = c.mesa; P.mesa($('m-mesa')); pintarMesa(); guardar();
        $('m-nuevo').scrollIntoView({behavior: 'smooth', block: 'start'});
        buscador.focus({preventScroll: true});
      }};
    $('m-cuentas').replaceChildren.apply($('m-cuentas'), abiertas.map(function (c) { return C.pintar(c, opciones); }));
    $('m-cuentas-vacio').hidden = abiertas.length !== 0;
    $('m-cobradas-panel').hidden = cerradas.length === 0;
    $('m-cobradas-cuenta').textContent = '(' + cerradas.length + ')';
    $('m-cobradas').replaceChildren.apply($('m-cobradas'), cerradas.map(function (c) { return C.pintar(c, opciones); }));
  }
  function cobrada(r, c) {
    P.aviso('Mesa ' + c.mesa + ' cobrada: factura N° ' + r.numero + ' · cambio Bs ' + P.bs(Number(r.cambio)) + '.');
    anunciar('Mesa ' + c.mesa + ' cobrada. Entrega el efectivo en caja.');
    actualizar();
  }
  $('m-actualizar').addEventListener('click', actualizar);
  actualizar();
  window.setInterval(function () { if (!document.hidden && !document.querySelector('dialog[open]')) actualizar(); }, 20000);
})();
