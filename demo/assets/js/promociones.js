/* Promociones (demo): lista con su estado a la hora de la demo y simulador
   con el mismo motor que usa el POS. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var D = window.PahuichiDatos;
  var M = window.PahuichiPromociones;
  var $ = function (id) { return document.getElementById(id); };
  var DIAS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
  var TIPO = { descuento: 'Descuento', combo: 'Combo', regalo: 'Regalo', cumpleanos: 'Cumpleaños' };

  function producto(id) { return D.productos.filter(function (p) { return p.id === id; })[0]; }
  function nombre(id) { return producto(id).nombre; }

  function dias(lista) {
    if (lista.length === 7) return 'Todos los días';
    if (lista.join() === '1,2,3,4,5') return 'De lunes a viernes';
    return lista.map(function (d) { return DIAS[d]; }).join(', ');
  }

  function regla(p) {
    if (p.tipo === 'combo') return p.componentes.map(nombre).join(' + ') + ' a Bs ' + P.bs(p.precioCombo);
    if (p.tipo === 'regalo') return 'Por ' + p.cantidadMinima + ' ' + nombre(p.condicion).toLowerCase() + ', 1 ' + nombre(p.regalo).toLowerCase() + ' gratis';
    if (p.tipo === 'descuento') {
      var a = p.producto ? nombre(p.producto) : 'Categoría ' + p.categoria;
      return a + ': ' + (p.valorTipo === 'porcentaje' ? p.valor + ' %' : 'Bs ' + P.bs(p.valor)) + ' menos';
    }
    return 'Un postre gratis el día de su cumpleaños, una vez al año';
  }

  D.promociones.forEach(function (p) {
    var tr = document.createElement('tr');
    var vigente = M.vigente(p, D.ahora);
    tr.innerHTML =
      '<td><span class="tabla__principal"></span><span class="tabla__secundario"></span></td>' +
      '<td><span class="cuando"></span><span class="tabla__secundario num"></span></td>' +
      '<td><span class="regla"></span><span class="tabla__secundario"></span></td>' +
      '<td class="celda-estado"></td>' +
      '<td class="col-accion"><div class="acciones-fila">' +
      '<button class="interruptor-boton" type="button" role="switch" aria-checked="' + p.activo + '"><span class="visualmente-oculto">Activa</span></button>' +
      '<a class="boton boton--icono" href="promocion-form.html?id=' + p.id + '"><svg class="icono" aria-hidden="true"><use href="#i-editar"></use></svg></a></div></td>';
    tr.querySelector('.tabla__principal').textContent = p.nombre;
    tr.querySelector('.tabla__secundario').textContent = TIPO[p.tipo];
    tr.querySelector('.cuando').textContent = dias(p.dias);
    tr.cells[1].querySelector('.tabla__secundario').textContent = p.desde + ' a ' + p.hasta + (p.topeDia ? ' · tope ' + p.topeDia + ' por día' : '');
    tr.querySelector('.regla').textContent = regla(p);
    tr.cells[2].querySelector('.tabla__secundario').textContent = (p.acumulable ? 'Acumulable' : 'No acumulable') + ' · prioridad ' + p.prioridad;
    tr.querySelector('.celda-estado').innerHTML = !p.activo ? '<span class="insignia insignia--neutra">Pausada</span>'
      : vigente ? '<span class="insignia insignia--ok">Aplica ahora</span>' : '<span class="insignia insignia--neutra">Fuera de horario</span>';
    tr.querySelector('.interruptor-boton').setAttribute('aria-label', 'Activa: ' + p.nombre);
    tr.querySelector('a').setAttribute('aria-label', 'Editar ' + p.nombre);
    $('filas-promos').appendChild(tr);
  });

  $('filas-promos').addEventListener('click', function (e) {
    var s = e.target.closest('[role="switch"]');
    if (!s) return;
    var on = s.getAttribute('aria-checked') !== 'true';
    s.setAttribute('aria-checked', String(on));
    var celda = s.closest('tr').querySelector('.celda-estado');
    celda.innerHTML = on ? '<span class="insignia insignia--ok">Aplica ahora</span>' : '<span class="insignia insignia--neutra">Pausada</span>';
    P.aviso(on ? 'Promoción activada.' : 'Promoción pausada.');
  });

  /* Simulador */
  var carrito = [{ producto: producto('PL-01'), cantidad: 1 }, { producto: producto('RE-01'), cantidad: 1 }, { producto: producto('PO-01'), cantidad: 1 }];

  D.productos.forEach(function (p) {
    var o = document.createElement('option');
    o.value = p.id;
    o.textContent = p.nombre + ' · Bs ' + P.bs(p.precio);
    $('s-producto').appendChild(o);
  });

  function simular() {
    var ul = $('s-carrito');
    ul.innerHTML = '';
    carrito.forEach(function (l, i) {
      var li = document.createElement('li');
      li.className = 'simulador__linea';
      li.innerHTML = '<span></span><span class="num"></span><button class="boton boton--fantasma boton--icono" type="button"><svg class="icono" aria-hidden="true"><use href="#i-basura"></use></svg></button>';
      li.children[0].textContent = l.cantidad + ' × ' + l.producto.nombre;
      li.children[1].textContent = P.bs(l.producto.precio * l.cantidad);
      li.children[2].setAttribute('aria-label', 'Quitar ' + l.producto.nombre);
      li.children[2].addEventListener('click', function () { carrito.splice(i, 1); simular(); });
      ul.appendChild(li);
    });

    var cliente = D.clientes.filter(function (c) { return c.doc === $('s-cliente').value; })[0];
    var r = M.evaluar(carrito, { diaSemana: parseInt($('s-dia').value, 10), hora: $('s-hora').value || '12:00' }, cliente);
    var subtotal = carrito.reduce(function (s, l) { return s + l.producto.precio * l.cantidad; }, 0);
    var desc = r.lineas.reduce(function (s, l) { return s + l.monto; }, 0);
    var html = '';
    if (!r.lineas.length) html += '<p class="simulador__nada">Ninguna promoción aplica a este carrito a esa hora.</p>';
    html += '<ul class="simulador__lineas" role="list">' + r.lineas.map(function () { return '<li><span></span><span class="num"></span></li>'; }).join('') + '</ul>';
    $('s-resultado').innerHTML = html +
      (r.descartadas.length ? '<p class="simulador__nota" id="s-descartadas"></p>' : '') +
      '<dl class="simulador__totales"><div><dt>Subtotal</dt><dd class="num">' + P.bs(subtotal) + '</dd></div><div><dt>Descuentos</dt><dd class="num">−' + P.bs(desc) + '</dd></div><div class="simulador__total"><dt>Total</dt><dd class="num">Bs ' + P.bs(subtotal - desc) + '</dd></div></dl>';
    $('s-resultado').querySelectorAll('.simulador__lineas li').forEach(function (li, i) {
      li.children[0].textContent = r.lineas[i].etiqueta;
      li.children[1].textContent = '−' + P.bs(r.lineas[i].monto);
    });
    if (r.descartadas.length) $('s-descartadas').textContent = 'No se aplicó por no ser acumulable: ' + r.descartadas.join(', ') + '.';
  }

  $('s-agregar').addEventListener('click', function () {
    var id = $('s-producto').value;
    var l = carrito.filter(function (x) { return x.producto.id === id; })[0];
    if (l) l.cantidad++; else carrito.push({ producto: producto(id), cantidad: 1 });
    simular();
  });
  ['s-dia', 's-hora', 's-cliente'].forEach(function (id) { $(id).addEventListener('change', simular); });
  simular();
})();
