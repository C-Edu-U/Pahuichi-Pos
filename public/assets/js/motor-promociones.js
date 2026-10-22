/* Motor de promociones (HU-46, HU-49): espejo de App\Services\PromocionService::aplicar().
   Calcula en el navegador lo mismo que el servidor, para mostrar el pedido al
   instante; al cobrar, el servidor recalcula y rechaza si no coincide.
   - Descuento por producto o por categoría: % del subtotal de la línea, o Bs por unidad.
   - Nunca deja una línea en negativo.
   - Si varias tocan la misma línea, gana la de mayor beneficio (a igual beneficio, la más antigua).
   - HU-49: solo valen las que cumplen fechas, día, franja horaria y tope de usos de hoy
     (contexto), y la cantidad mínima y el monto mínimo del pedido.
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

  /* lineas: [{ producto: {id, categoria, precio}, cantidad }]
     Devuelve una entrada por línea: { centavos, promocion } (promocion es null si no hay). */
  function aplicar(lineas, promociones, contexto) {
    var validas = promociones.filter(function (p) {
      return (!contexto || fueraDeVigencia(p, contexto) === null) && cumple(p, lineas);
    });
    return lineas.map(function (l) {
      var bruto = centavos(l.producto.precio) * l.cantidad;
      var mejor = { centavos: 0, promocion: null };
      validas.forEach(function (p) {   // vienen ordenadas por id
        if (!alcanza(p, l.producto)) return;
        var d = descuento(p, bruto, l.cantidad);
        if (d > mejor.centavos) mejor = { centavos: d, promocion: p };
      });
      return mejor;
    });
  }

  /* Agrupa por promoción para mostrarlas como líneas de descuento: [{ promocion, centavos }] */
  function agrupar(aplicadas) {
    var porId = {};
    var orden = [];
    aplicadas.forEach(function (a) {
      if (!a.promocion) return;
      if (!porId[a.promocion.id]) { porId[a.promocion.id] = { promocion: a.promocion, centavos: 0 }; orden.push(a.promocion.id); }
      porId[a.promocion.id].centavos += a.centavos;
    });
    return orden.map(function (id) { return porId[id]; });
  }

  window.PahuichiPromociones = { aplicar: aplicar, agrupar: agrupar, fueraDeVigencia: fueraDeVigencia };
})();
