/* Motor de promociones (espejo de PromocionService::evaluar).
   Entrada: líneas del carrito [{ producto, cantidad }], momento { diaSemana,
   hora 'HH:MM' } y cliente { nacimiento 'AAAA-MM-DD' } opcional.
   Salida: líneas de descuento [{ promocion, etiqueta, monto }].
   Reglas: respeta días y franja horaria; una no acumulable compite con las
   demás que tocan los mismos productos y gana la de mayor beneficio; las
   acumulables se aplican por prioridad; ningún descuento deja un ítem en
   negativo. */
(function () {
  'use strict';

  var D = window.PahuichiDatos;

  function minutos(hhmm) {
    var p = hhmm.split(':');
    return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
  }

  function vigente(promo, momento) {
    if (!promo.activo) return false;
    if (promo.dias.indexOf(momento.diaSemana) === -1) return false;
    var m = minutos(momento.hora);
    return m >= minutos(promo.desde) && m < minutos(promo.hasta); // '24:00' = 1440
  }

  function cantidad(lineas, id) {
    return lineas.reduce(function (s, l) { return l.producto.id === id ? s + l.cantidad : s; }, 0);
  }

  function precio(id) {
    return D.productos.filter(function (p) { return p.id === id; })[0].precio;
  }

  function redondear(n) { return Math.round((n + Number.EPSILON) * 100) / 100; }

  // Beneficio de cada promoción sobre el carrito, sin mirar a las demás
  function calcular(promo, lineas, cliente, momento) {
    var r = { promocion: promo, monto: 0, etiqueta: promo.nombre, productos: [] };
    if (promo.tipo === 'combo') {
      var veces = Math.min.apply(null, promo.componentes.map(function (id) { return cantidad(lineas, id); }));
      if (veces > 0) {
        var normal = promo.componentes.reduce(function (s, id) { return s + precio(id); }, 0);
        r.monto = redondear((normal - promo.precioCombo) * veces);
        r.etiqueta = promo.nombre + (veces > 1 ? ' × ' + veces : '');
        r.productos = promo.componentes;
      }
    } else if (promo.tipo === 'regalo') {
      var compra = cantidad(lineas, promo.condicion);
      var regalos = Math.min(Math.floor(compra / promo.cantidadMinima), cantidad(lineas, promo.regalo));
      if (regalos > 0) {
        r.monto = redondear(precio(promo.regalo) * regalos);
        r.etiqueta = 'Regalo: ' + promo.nombre;
        r.productos = [promo.condicion, promo.regalo];
      } else if (compra >= promo.cantidadMinima) {
        r.sugerencia = 'Agrega un ' + D.productos.filter(function (p) { return p.id === promo.regalo; })[0].nombre.toLowerCase() + ': va de regalo.';
      }
    } else if (promo.tipo === 'descuento') {
      lineas.forEach(function (l) {
        var aplica = promo.producto ? l.producto.id === promo.producto : l.producto.categoria === promo.categoria;
        if (!aplica) return;
        var sub = l.producto.precio * l.cantidad;
        var d = promo.valorTipo === 'porcentaje' ? sub * promo.valor / 100 : promo.valor * l.cantidad;
        r.monto += Math.min(d, sub); // nunca deja el ítem en negativo
        r.productos.push(l.producto.id);
      });
      r.monto = redondear(r.monto);
    } else if (promo.tipo === 'cumpleanos' && cliente && cliente.nacimiento) {
      var hoy = D.ahora.fecha.slice(5);
      if (cliente.nacimiento.slice(5) === hoy) {
        var postres = lineas.filter(function (l) { return l.producto.categoria === promo.categoria; });
        if (postres.length) {
          var barato = postres.reduce(function (a, b) { return a.producto.precio <= b.producto.precio ? a : b; });
          r.monto = barato.producto.precio;
          r.etiqueta = 'Cumpleaños: ' + barato.producto.nombre.toLowerCase() + ' de regalo';
          r.productos = [barato.producto.id];
        }
      }
    }
    return r;
  }

  function evaluar(lineas, momento, cliente) {
    momento = momento || D.ahora;
    var candidatas = D.promociones
      .filter(function (p) { return vigente(p, momento); })
      .map(function (p) { return calcular(p, lineas, cliente, momento); });

    var sugerencias = candidatas.filter(function (c) { return c.sugerencia; }).map(function (c) { return c.sugerencia; });
    var conBeneficio = candidatas.filter(function (c) { return c.monto > 0; });

    // No acumulables: compiten con las que tocan los mismos productos
    var descartadas = [];
    conBeneficio.forEach(function (c) {
      if (c.promocion.acumulable) return;
      conBeneficio.forEach(function (o) {
        if (o === c) return;
        var comparten = o.productos.some(function (id) { return c.productos.indexOf(id) !== -1; });
        if (!comparten) return;
        if (o.monto > c.monto) descartadas.push(c); else descartadas.push(o);
      });
    });

    var aplicadas = conBeneficio
      .filter(function (c) { return descartadas.indexOf(c) === -1; })
      .sort(function (a, b) { return a.promocion.prioridad - b.promocion.prioridad; });

    // Tope: el total de descuentos nunca supera el subtotal
    var subtotal = lineas.reduce(function (s, l) { return s + l.producto.precio * l.cantidad; }, 0);
    var acumulado = 0;
    aplicadas.forEach(function (c) {
      c.monto = redondear(Math.min(c.monto, subtotal - acumulado));
      acumulado += c.monto;
    });

    return {
      lineas: aplicadas.filter(function (c) { return c.monto > 0; }).map(function (c) {
        return { promocion: c.promocion, etiqueta: c.etiqueta, monto: c.monto };
      }),
      descartadas: descartadas.map(function (c) { return c.promocion.nombre; }),
      sugerencias: sugerencias,
      vigentes: candidatas.map(function (c) { return c.promocion; })
    };
  }

  window.PahuichiPromociones = { evaluar: evaluar, vigente: vigente };
})();
