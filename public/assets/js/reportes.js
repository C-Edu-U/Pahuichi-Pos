/* Reportes (demo, solo Administrador). Contrato: ReporteService entrega
   arreglos por fecha y turno (ventas por hora, producto, categoría, medio de
   pago, cajero, promociones y anulaciones); la pantalla solo pinta. Las
   ventas usan el precio guardado en cada línea, nunca el precio actual. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var SVGNS = 'http://www.w3.org/2000/svg';

  // Día de ejemplo: medio día hasta las 12:41 y el turno de noche de ayer
  var BASE = {
    horas: [11, 12, 13, 14, 15, 18, 19, 20, 21, 22, 23],
    turnoHora: ['mediodia', 'mediodia', 'mediodia', 'mediodia', 'mediodia', 'noche', 'noche', 'noche', 'noche', 'noche', 'noche'],
    ventasHora: [318.5, 970, 0, 0, 0, 0, 0, 0, 0, 0, 0],
    platos: [['Majadito de charque', 14], ['Mocochinchi', 13], ['Picante de pollo', 9], ['Sonso de yuca', 8], ['Locro carretero', 6], ['Soda cola 500 ml', 6], ['Chicha cruceña', 4], ['Keperí al horno', 3]],
    metodo: { efectivo: 1285.5, qr: 0.5 }, // QR: solo confirmados
    tickets: 42,
    descuentos: 35
  };
  // La noche de ayer completa la semana y el mes
  var NOCHE = [0, 0, 0, 0, 0, 142, 388, 512.5, 466, 290, 113.5];
  var PERIODOS = {
    hoy: { f: 1, noche: 0, delta: 'Medio día en curso · +6,2 % frente al mismo horario de ayer' },
    ayer: { f: 1.32, noche: 1, delta: 'Medio día y noche completos' },
    semana: { f: 6.9, noche: 6, delta: '+4,8 % frente a la semana anterior' },
    mes: { f: 7.8, noche: 7, delta: 'Del 1 al 8 de octubre' }
  };

  function datos() {
    var per = PERIODOS[$('r-periodo').value];
    var turno = $('r-turno').value;
    var horas = BASE.ventasHora.map(function (v, i) {
      var valor = v * per.f + NOCHE[i] * per.noche;
      if (turno && BASE.turnoHora[i] !== turno) valor = 0;
      return Math.round(valor * 100) / 100;
    });
    var ventas = horas.reduce(function (s, v) { return s + v; }, 0);
    var escala = ventas / (BASE.metodo.efectivo + BASE.metodo.qr) || 0;
    var tickets = Math.round(BASE.tickets * escala);
    return {
      horas: horas,
      ventas: ventas,
      tickets: tickets,
      promedio: tickets ? ventas / tickets : 0,
      descuentos: Math.round(BASE.descuentos * escala * 100) / 100,
      platos: BASE.platos.map(function (p) { return [p[0], Math.round(p[1] * escala)]; }),
      metodo: { efectivo: Math.round(BASE.metodo.efectivo * escala * 100) / 100, qr: Math.round(BASE.metodo.qr * escala * 100) / 100 },
      escala: escala,
      delta: per.delta
    };
  }

  /* ------------------------------------------------------------------
     Tooltip compartido: el valor manda, la etiqueta acompaña
     ------------------------------------------------------------------ */
  var tip = document.createElement('div');
  tip.className = 'tooltip-grafico';
  tip.hidden = true;
  tip.innerHTML = '<strong class="tooltip-grafico__valor"></strong><span class="tooltip-grafico__etiqueta"></span>';
  document.body.appendChild(tip);

  function mostrarTip(el, valor, etiqueta) {
    tip.querySelector('.tooltip-grafico__valor').textContent = valor;
    tip.querySelector('.tooltip-grafico__etiqueta').textContent = etiqueta;
    tip.hidden = false;
    var r = el.getBoundingClientRect();
    var t = tip.getBoundingClientRect();
    var x = Math.min(Math.max(8, r.left + r.width / 2 - t.width / 2), window.innerWidth - t.width - 8);
    var y = r.top - t.height - 8 < 8 ? r.bottom + 8 : r.top - t.height - 8;
    tip.style.setProperty('--x', x + window.scrollX + 'px');
    tip.style.setProperty('--y', y + window.scrollY + 'px');
  }

  function ocultarTip() { tip.hidden = true; }

  function svg(nombre, attrs) {
    var el = document.createElementNS(SVGNS, nombre);
    Object.keys(attrs || {}).forEach(function (k) { el.setAttribute(k, attrs[k]); });
    return el;
  }

  // Barra con extremo redondeado de 4 px y base recta
  function barraVertical(x, y, w, h) {
    var r = Math.min(4, h, w / 2);
    return 'M' + x + ',' + (y + h) + 'V' + (y + r) + 'Q' + x + ',' + y + ' ' + (x + r) + ',' + y +
      'H' + (x + w - r) + 'Q' + (x + w) + ',' + y + ' ' + (x + w) + ',' + (y + r) + 'V' + (y + h) + 'Z';
  }

  function techo(max) {
    if (max <= 0) return 100;
    var paso = Math.pow(10, Math.floor(Math.log10(max)));
    var opciones = [1, 2, 2.5, 5, 10];
    for (var i = 0; i < opciones.length; i++) {
      if (opciones[i] * paso * 4 >= max) return opciones[i] * paso * 4;
    }
    return 10 * paso * 4;
  }

  /* ------------------------------------------------------------------
     Ventas por hora: columnas
     ------------------------------------------------------------------ */
  function pintarHoras(d) {
    var cont = $('g-hora');
    cont.innerHTML = '';
    var W = Math.max(cont.clientWidth, 280);
    var H = 240;
    var m = { arriba: 24, derecha: 8, abajo: 28, izquierda: 52 };
    var aw = W - m.izquierda - m.derecha;
    var ah = H - m.arriba - m.abajo;
    var max = techo(Math.max.apply(null, d.horas));
    var s = svg('svg', { viewBox: '0 0 ' + W + ' ' + H, width: W, height: H, 'aria-hidden': 'true', focusable: 'false' });

    for (var i = 0; i <= 4; i++) {
      var v = max / 4 * i;
      var y = m.arriba + ah - ah * i / 4;
      s.appendChild(svg('line', { x1: m.izquierda, x2: W - m.derecha, y1: y, y2: y, class: i === 0 ? 'eje' : 'rejilla' }));
      var t = svg('text', { x: m.izquierda - 8, y: y + 4, class: 'eje-texto', 'text-anchor': 'end' });
      t.textContent = P.bs(v).replace(/,00$/, '');
      s.appendChild(t);
    }

    var banda = aw / d.horas.length;
    var ancho = Math.min(24, banda * 0.6);
    var iMax = d.horas.indexOf(Math.max.apply(null, d.horas));
    d.horas.forEach(function (valor, i) {
      var h = valor / max * ah;
      var x = m.izquierda + banda * i + (banda - ancho) / 2;
      var y = m.arriba + ah - h;
      var g = svg('g', { class: 'marca' });
      g.appendChild(svg('rect', { x: m.izquierda + banda * i, y: m.arriba, width: banda, height: ah, class: 'zona-hit' }));
      if (h > 0) g.appendChild(svg('path', { d: barraVertical(x, y, ancho, h), class: 'barra' }));
      s.appendChild(g);
      var et = svg('text', { x: x + ancho / 2, y: H - 8, class: 'eje-texto', 'text-anchor': 'middle' });
      et.textContent = BASE.horas[i] + ':00';
      s.appendChild(et);
      if (i === iMax && valor > 0) {
        var vt = svg('text', { x: x + ancho / 2, y: y - 6, class: 'valor-texto', 'text-anchor': 'middle' });
        vt.textContent = P.bs(valor);
        s.appendChild(vt);
      }
      g.addEventListener('pointerenter', function () { g.classList.add('activa'); mostrarTip(g, 'Bs ' + P.bs(valor), BASE.horas[i] + ':00 a ' + (BASE.horas[i] + 1) + ':00'); });
      g.addEventListener('pointerleave', function () { g.classList.remove('activa'); ocultarTip(); });
    });
    cont.appendChild(s);

    // Acceso por teclado: botones invisibles sobre cada columna
    var capa = document.createElement('div');
    capa.className = 'grafico__teclado';
    d.horas.forEach(function (valor, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'grafico__foco';
      b.style.setProperty('--izq', (m.izquierda + banda * i) / W * 100 + '%');
      b.style.setProperty('--ancho', banda / W * 100 + '%');
      b.setAttribute('aria-label', BASE.horas[i] + ':00, Bs ' + P.bs(valor));
      b.addEventListener('focus', function () { mostrarTip(b, 'Bs ' + P.bs(valor), BASE.horas[i] + ':00 a ' + (BASE.horas[i] + 1) + ':00'); });
      b.addEventListener('blur', ocultarTip);
      capa.appendChild(b);
    });
    cont.appendChild(capa);

    tablaSimple($('t-hora'), ['Hora', 'Ventas (Bs)'], d.horas.map(function (v, i) { return [BASE.horas[i] + ':00', P.bs(v)]; }));
  }

  /* ------------------------------------------------------------------
     Platos más vendidos: barras horizontales con valor en la punta
     ------------------------------------------------------------------ */
  function pintarPlatos(d) {
    var cont = $('g-top');
    cont.innerHTML = '';
    var lista = document.createElement('ol');
    lista.className = 'barras-h';
    var max = Math.max.apply(null, d.platos.map(function (p) { return p[1]; })) || 1;
    d.platos.forEach(function (p) {
      var li = document.createElement('li');
      li.className = 'barras-h__fila';
      li.innerHTML = '<span class="barras-h__nombre"></span><span class="barras-h__pista"><span class="barras-h__barra"></span><span class="barras-h__valor num"></span></span>';
      li.querySelector('.barras-h__nombre').textContent = p[0];
      li.querySelector('.barras-h__valor').textContent = p[1];
      li.style.setProperty('--valor', p[1] / max);
      lista.appendChild(li);
    });
    cont.appendChild(lista);
    tablaSimple($('t-top'), ['Plato', 'Unidades'], d.platos.map(function (p) { return [p[0], String(p[1])]; }));
  }

  /* ------------------------------------------------------------------
     Método de pago: una barra apilada de dos partes + leyenda con valores
     ------------------------------------------------------------------ */
  function pintarMetodo(d) {
    var cont = $('g-metodo');
    cont.innerHTML = '';
    var total = d.metodo.efectivo + d.metodo.qr || 1;
    var barra = document.createElement('div');
    barra.className = 'apilada';
    [['efectivo', 'Efectivo'], ['qr', 'QR']].forEach(function (par) {
      var v = d.metodo[par[0]];
      if (v <= 0) return;
      var seg = document.createElement('span');
      seg.className = 'apilada__parte apilada__parte--' + par[0];
      seg.style.setProperty('--parte', v / total);
      seg.setAttribute('tabindex', '0');
      seg.setAttribute('aria-label', par[1] + ': Bs ' + P.bs(v));
      seg.addEventListener('pointerenter', function () { mostrarTip(seg, 'Bs ' + P.bs(v), par[1] + ' · ' + porcentaje(v / total)); });
      seg.addEventListener('pointerleave', ocultarTip);
      seg.addEventListener('focus', function () { mostrarTip(seg, 'Bs ' + P.bs(v), par[1] + ' · ' + porcentaje(v / total)); });
      seg.addEventListener('blur', ocultarTip);
      barra.appendChild(seg);
    });
    cont.appendChild(barra);

    var leyenda = $('leyenda-metodo');
    leyenda.innerHTML = '';
    [['efectivo', 'Efectivo'], ['qr', 'QR']].forEach(function (par) {
      var li = document.createElement('li');
      li.className = 'leyenda__item';
      li.innerHTML = '<span class="leyenda__muestra leyenda__muestra--' + par[0] + '" aria-hidden="true"></span><span class="leyenda__nombre"></span><span class="leyenda__valor num"></span>';
      li.querySelector('.leyenda__nombre').textContent = par[1];
      li.querySelector('.leyenda__valor').textContent = 'Bs ' + P.bs(d.metodo[par[0]]) + ' · ' + porcentaje(d.metodo[par[0]] / total);
      leyenda.appendChild(li);
    });
    tablaSimple($('t-metodo'), ['Método', 'Monto (Bs)', 'Parte'], [
      ['Efectivo', P.bs(d.metodo.efectivo), porcentaje(d.metodo.efectivo / total)],
      ['QR', P.bs(d.metodo.qr), porcentaje(d.metodo.qr / total)]
    ]);
  }

  function porcentaje(x) {
    var p = x * 100;
    return ((p > 0 && p < 1) || (p > 99 && p < 100) ? p.toFixed(1) : Math.round(p)).toString().replace('.', ',') + ' %';
  }

  function tablaSimple(tabla, cabeza, filas) {
    tabla.innerHTML = '';
    var thead = tabla.createTHead().insertRow();
    cabeza.forEach(function (c, i) {
      var th = document.createElement('th');
      th.scope = 'col';
      th.textContent = c;
      if (i > 0) th.className = 'col-num';
      thead.appendChild(th);
    });
    var tbody = tabla.createTBody();
    filas.forEach(function (f) {
      var tr = tbody.insertRow();
      f.forEach(function (c, i) {
        var td = tr.insertCell();
        td.textContent = c;
        if (i > 0) td.className = 'col-num num';
      });
    });
  }

  /* ------------------------------------------------------------------
     Resumen
     ------------------------------------------------------------------ */
  var ultimo = null;
  function pintarResumen() {
    var d = datos();
    ultimo = d;
    $('c-ventas').textContent = P.bs(d.ventas);
    $('c-tickets').textContent = d.tickets;
    $('c-promedio').textContent = P.bs(d.promedio);
    $('c-descuentos').textContent = P.bs(d.descuentos);
    $('c-delta').textContent = d.delta;
    pintarHoras(d);
    pintarPlatos(d);
    pintarMetodo(d);
  }

  /* ------------------------------------------------------------------
     Detalle: tablas por producto, categoría, pago, cajero, promociones
     y anulaciones (todas escalan con el periodo elegido)
     ------------------------------------------------------------------ */
  function escalar(n, e) { return Math.round(n * e); }
  function monto(n, e) { return P.bs(Math.round(n * e * 100) / 100); }

  function pintarDetalle() {
    var e = datos().escala;
    tablaSimple($('d-producto'), ['Producto', 'Unidades', 'Bs'], [
      ['Majadito de charque', 14, 560], ['Picante de pollo', 9, 405], ['Locro carretero', 6, 210], ['Keperí al horno', 3, 165],
      ['Sonso de yuca', 8, 120], ['Mocochinchi', 13, 130], ['Soda cola 500 ml', 6, 48], ['Chicha cruceña', 4, 40]
    ].map(function (f) { return [f[0], String(escalar(f[1], e)), monto(f[2], e)]; }));
    tablaSimple($('d-categoria'), ['Categoría', 'Unidades', 'Bs'], [
      ['Platos', 40, 1460], ['Refrescos', 19, 190], ['Sodas', 8, 64], ['Postres', 3, 36], ['Vinos', 0, 0]
    ].map(function (f) { return [f[0], String(escalar(f[1], e)), monto(f[2], e)]; }));
    tablaSimple($('d-pago'), ['Medio de pago', 'Pagos', 'Bs'], [
      ['Efectivo', 38, 1285.5], ['QR confirmados', 1, 0.5], ['QR pendientes', 4, 2], ['QR rechazados', 1, 0.5]
    ].map(function (f) { return [f[0], String(escalar(f[1], e)), monto(f[2], e)]; }));
    tablaSimple($('d-cajero'), ['Cajero', 'Ventas', 'Bs'], [
      ['Mariela Suárez', 42, 1288.5], ['Jorge Rivero', 0, 0]
    ].map(function (f) { return [f[0], String(escalar(f[1], e)), monto(f[2], e)]; }));
    tablaSimple($('d-promo'), ['Promoción', 'Usos', 'Bs descontados'], [
      ['Combo majadito + mocochinchi', 5, 25], ['2 picantes, mocochinchi de regalo', 1, 10], ['Postres 10 % de lunes a viernes', 3, 3.6], ['Postre gratis en tu cumpleaños', 0, 0]
    ].map(function (f) { return [f[0], String(escalar(f[1], e)), monto(f[2], e)]; }));
    tablaSimple($('d-anuladas'), ['Pedido', 'Motivo', 'Bs'], e ? [['037 · factura 123', 'Error en el pedido · autorizó Ana Lucía Vaca', '45,00']] : []);
  }

  /* ------------------------------------------------------------------
     Libro de Ventas (simulado)
     ------------------------------------------------------------------ */
  var LIBRO = [
    [114, '07/10/2026', '3398217', '', 'Saucedo Roca', 0.5, 0, 'V'],
    [115, '07/10/2026', '0', '', 'S/N', 45, 0, 'V'],
    [116, '07/10/2026', '4567891', '', 'Rodríguez Peña', 160, 10, 'V'],
    [117, '08/10/2026', '0', '', 'S/N', 35, 0, 'V'],
    [118, '08/10/2026', '0', '', 'S/N', 60, 5, 'V'],
    [119, '08/10/2026', '0', '', 'S/N', 28, 0, 'V'],
    [120, '08/10/2026', '1987654013', '', 'Distribuidora El Trompillo', 142, 0, 'V'],
    [121, '08/10/2026', '0', '', 'S/N', 0.5, 0, 'V'],
    [122, '08/10/2026', '3398217', '', 'Saucedo Roca', 70, 0, 'V'],
    [123, '08/10/2026', '0', '', 'S/N', 45, 0, 'A'],
    [124, '08/10/2026', '6234510', '1F', 'Gutiérrez Añez', 53, 12, 'V'],
    [125, '08/10/2026', '0', '', 'S/N', 0.5, 0, 'V'],
    [126, '08/10/2026', '1023987026', '', 'Constructora Piraí S.R.L.', 214, 5, 'V'],
    [127, '08/10/2026', '0', '', 'S/N', 45, 0, 'V'],
    [128, '08/10/2026', '4567891', '', 'Rodríguez Peña', 93.8, 6.2, 'V']
  ];

  function filasLibro() {
    return LIBRO.map(function (r, i) {
      var anulada = r[7] === 'A';
      var base = anulada ? 0 : r[5];
      var cuf = ((r[0] * 7919) >>> 0).toString(16).toUpperCase().padStart(8, '0') + '9C0D37E5A8B1C46F2D90';
      return [String(i + 1), r[1], String(r[0]), cuf, r[2], r[3], r[4], P.bs(anulada ? 0 : r[5]), P.bs(r[6]), '0,00', P.bs(base), P.bs(Math.round(base * 13) / 100), anulada ? 'Anulada' : 'Válida'];
    });
  }

  function pintarLibro() {
    var tbody = $('filas-libro');
    tbody.innerHTML = '';
    var numericas = [0, 2, 7, 8, 9, 10, 11];
    filasLibro().forEach(function (f) {
      var tr = tbody.insertRow();
      f.forEach(function (c, i) {
        var td = tr.insertCell();
        td.textContent = c;
        if (numericas.indexOf(i) !== -1) td.className = 'col-num num';
        if (i === 3) td.className = 'codigo';
      });
    });
  }

  /* ------------------------------------------------------------------
     Vistas, filtros y exportación
     ------------------------------------------------------------------ */
  var vista = 'tablero';
  function pintar() {
    if (vista === 'tablero') pintarResumen();
    else if (vista === 'detalle') pintarDetalle();
    else pintarLibro();
  }

  document.querySelectorAll('[data-vista]').forEach(function (b) {
    b.addEventListener('click', function () {
      vista = b.getAttribute('data-vista');
      document.querySelectorAll('[data-vista]').forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
      ['tablero', 'detalle', 'libro'].forEach(function (v) { $('vista-' + v).hidden = v !== vista; });
      $('filtros').hidden = vista === 'libro';
      pintar();
    });
  });

  ['r-periodo', 'r-turno'].forEach(function (id) { $(id).addEventListener('change', pintar); });

  var espera;
  window.addEventListener('resize', function () {
    window.clearTimeout(espera);
    espera = window.setTimeout(function () { if (vista === 'tablero' && ultimo) pintarHoras(ultimo); }, 150);
  });

  // Excel: CSV con separador «;» que Excel abre directo (con servidor, PhpSpreadsheet)
  function csv(cabeza, filas) {
    var esc = function (c) { return /[;"\n]/.test(c) ? '"' + String(c).replace(/"/g, '""') + '"' : c; };
    return '﻿' + [cabeza].concat(filas).map(function (f) { return f.map(esc).join(';'); }).join('\r\n');
  }

  function tablaACsv(tabla) {
    var filas = Array.prototype.map.call(tabla.rows, function (tr) {
      return Array.prototype.map.call(tr.cells, function (c) { return c.textContent.trim(); });
    });
    return csv(filas[0], filas.slice(1));
  }

  $('exportar').addEventListener('click', function () {
    var contenido;
    var nombre;
    if (vista === 'libro') {
      var cab = Array.prototype.map.call(document.querySelectorAll('#tabla-libro thead th'), function (th) { return th.textContent; });
      contenido = csv(cab, filasLibro());
      nombre = 'libro-de-ventas-2026-10.csv';
    } else if (vista === 'detalle') {
      contenido = ['d-producto', 'd-categoria', 'd-pago', 'd-cajero', 'd-promo', 'd-anuladas'].map(function (id) { return tablaACsv($(id)); }).join('\r\n\r\n');
      nombre = 'reporte-detalle-' + $('r-periodo').value + '.csv';
    } else {
      contenido = csv(['Hora', 'Ventas (Bs)'], ultimo.horas.map(function (v, i) { return [BASE.horas[i] + ':00', P.bs(v)]; }));
      nombre = 'ventas-por-hora-' + $('r-periodo').value + '.csv';
    }
    var a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([contenido], { type: 'text/csv;charset=utf-8' }));
    a.download = nombre;
    document.body.appendChild(a);
    a.click();
    window.setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
    P.aviso('Descargado ' + nombre + '.');
  });

  // PDF: la vista actual por la impresión del navegador (con servidor, dompdf)
  $('exportar-pdf').addEventListener('click', function () { window.print(); });

  pintar();
})();
