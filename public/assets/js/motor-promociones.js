/* Motor de promociones (HU-46 a HU-50): espejo de App\Services\PromocionService::evaluar().
   Calcula en el navegador lo mismo que el servidor, para mostrar el pedido al
   instante; al cobrar, el servidor recalcula y rechaza si no coincide.
   - Descuento por producto o por categoría: % del subtotal de la línea, o Bs por unidad.
   - Nunca deja una línea en negativo.
   - HU-50: una no acumulable no se combina con ninguna otra; las acumulables se suman por
     prioridad; se aplica la opción de mayor beneficio para el cliente.
   - HU-49: solo valen las que cumplen fechas, día, franja horaria y tope de usos de hoy
     (contexto), y la cantidad mínima y el monto mínimo del pedido.
   - Regalo por compra: por cada N del producto o la categoría, 1 del producto regalo a Bs 0,
     si está disponible y la promoción vale. Espejo de PromocionService::regalos().
   Todo en centavos enteros. Las promociones llegan con su valor en centésimos (12,50 → 1250)
   y su monto mínimo en centavos. */
(function () {
  'use strict';

  function centavos(monto) { return Math.round(Number(monto) * 100); }

  function alcanza(p, producto) {
    return p.producto_id !== null ? p.producto_id === Number(producto.id) : p.categoria_id === Number(producto.categoria);
  }

  function descuento(p, bruto, cantidad) {
    var d = p.valor_tipo === 'porcentaje' ? Math.floor((bruto * p.valor + 5000) / 10000) : p.valor * cantidad;
    return Math.min(d, bruto);
  }

  /* Por qué no vale en ese momento ('programada', 'vencida', 'dia', 'hora', 'tope') o null.
     contexto: { fecha: 'AAAA-MM-DD', dia: 1 (lunes) … 7, hora: 'HH:MM:SS', usos: { id: n } } */
  function fueraDeVigencia(p, c) {
    if (p.fecha_desde && c.fecha < p.fecha_desde) return 'programada';
    if (p.fecha_hasta && c.fecha > p.fecha_hasta) return 'vencida';
    if (!((p.dias >> (c.dia - 1)) & 1)) return 'dia';
    if (p.hora_desde && (c.hora < p.hora_desde || c.hora >= p.hora_hasta)) return 'hora';
    if (p.tope_usos_dia !== null && (c.usos[p.id] || 0) >= p.tope_usos_dia) return 'tope';
    return null;
  }

  function cumple(p, lineas) {
    if (p.monto_minimo !== null) {
      var bruto = lineas.reduce(function (s, l) { return s + centavos(l.producto.precio) * l.cantidad; }, 0);
      if (bruto < p.monto_minimo) return false;
    }
    if (p.cantidad_minima !== null) {
      var unidades = lineas.reduce(function (s, l) { return s + (alcanza(p, l.producto) ? l.cantidad : 0); }, 0);
      if (unidades < p.cantidad_minima) return false;
    }
    return true;
  }

  /* Aplica las promociones en orden: cada una descuenta sobre lo que dejó la anterior.
     regalables: { id: centavos } con el precio de cada producto que se puede regalar. */
  function simular(lineas, lista, regalables) {
    var restante = lineas.map(function (l) { return centavos(l.producto.precio) * l.cantidad; });
    var resultado = lineas.map(function () { return { centavos: 0, partes: [] }; });
    var regalos = [];
    var beneficio = 0;
    lista.forEach(function (p) {
      if (p.tipo === 'descuento') {
        lineas.forEach(function (l, i) {
          if (!alcanza(p, l.producto)) return;
          var d = Math.min(descuento(p, restante[i], l.cantidad), restante[i]);
          if (d > 0) {
            restante[i] -= d;
            resultado[i].centavos += d;
            resultado[i].partes.push({ promocion: p, centavos: d });
            beneficio += d;
          }
        });
      } else if (p.tipo === 'regalo') {
        var unidades = lineas.reduce(function (s, l) { return s + (alcanza(p, l.producto) ? l.cantidad : 0); }, 0);
        var cantidad = Math.floor(unidades / Math.max(1, p.cantidad_compra));
        var precio = regalables[String(p.regalo_producto_id)];
        if (cantidad > 0 && precio !== undefined) {
          regalos.push({ promocion: p, producto_id: p.regalo_producto_id, cantidad: cantidad, centavos: precio * cantidad });
          beneficio += precio * cantidad;
        }
      }
    });
    return { lineas: resultado, regalos: regalos, beneficio: beneficio };
  }

  /* HU-50 (espejo de PromocionService::evaluar): cada no acumulable compite sola; las
     acumulables se aplican juntas por prioridad (menor primero); gana el mayor beneficio.
     A igual beneficio, las acumulables; entre no acumulables, la más antigua.
     lineas: [{ producto: {id, categoria, precio}, cantidad }]
     catalogo: { id: producto } con los productos que se pueden vender (para los regalos).
     Devuelve { lineas: [{ centavos, partes: [{ promocion, centavos }] }],
                regalos: [{ promocion, producto, cantidad, centavos }], beneficio }. */
  function evaluar(lineas, promociones, catalogo, contexto) {
    var regalables = {};
    Object.keys(catalogo || {}).forEach(function (id) {
      if (!catalogo[id].agotado) regalables[id] = centavos(catalogo[id].precio);
    });
    var validas = promociones.filter(function (p) {
      return (!contexto || fueraDeVigencia(p, contexto) === null) && cumple(p, lineas);
    });
    var mejor = simular(lineas, [], regalables);
    validas.forEach(function (p) {   // vienen ordenadas por id
      if (p.acumulable) return;
      var opcion = simular(lineas, [p], regalables);
      if (opcion.beneficio > mejor.beneficio) mejor = opcion;
    });
    var acumulables = validas.filter(function (p) { return p.acumulable; })
      .sort(function (a, b) { return (a.prioridad - b.prioridad) || (a.id - b.id); });
    if (acumulables.length) {
      var juntas = simular(lineas, acumulables, regalables);
      if (juntas.beneficio > 0 && juntas.beneficio >= mejor.beneficio) mejor = juntas;
    }
    mejor.regalos.forEach(function (r) { r.producto = catalogo[String(r.producto_id)]; });
    return mejor;
  }

  /* Agrupa por promoción para mostrarlas como líneas de descuento: [{ promocion, centavos }] */
  function agrupar(lineasEvaluadas) {
    var porId = {};
    var orden = [];
    lineasEvaluadas.forEach(function (l) {
      l.partes.forEach(function (x) {
        if (!porId[x.promocion.id]) { porId[x.promocion.id] = { promocion: x.promocion, centavos: 0 }; orden.push(x.promocion.id); }
        porId[x.promocion.id].centavos += x.centavos;
      });
    });
    return orden.map(function (id) { return porId[id]; });
  }

  window.PahuichiPromociones = { evaluar: evaluar, agrupar: agrupar, fueraDeVigencia: fueraDeVigencia };
})();
