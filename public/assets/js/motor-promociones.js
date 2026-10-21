/* Motor de promociones (HU-46): espejo de App\Services\PromocionService::aplicar().
   Calcula en el navegador lo mismo que el servidor, para mostrar el pedido al
   instante; al cobrar, el servidor recalcula y rechaza si no coincide.
   - Descuento por producto o por categoría: % del subtotal de la línea, o Bs por unidad.
   - Nunca deja una línea en negativo.
   - Si varias tocan la misma línea, gana la de mayor beneficio (a igual beneficio, la más antigua).
   Todo en centavos enteros. Las promociones llegan con su valor en centésimos (12,50 → 1250). */
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

  /* lineas: [{ producto: {id, categoria, precio}, cantidad }]
     Devuelve una entrada por línea: { centavos, promocion } (promocion es null si no hay). */
  function aplicar(lineas, promociones) {
    return lineas.map(function (l) {
      var bruto = centavos(l.producto.precio) * l.cantidad;
      var mejor = { centavos: 0, promocion: null };
      promociones.forEach(function (p) {   // vienen ordenadas por id
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

  window.PahuichiPromociones = { aplicar: aplicar, agrupar: agrupar };
})();
