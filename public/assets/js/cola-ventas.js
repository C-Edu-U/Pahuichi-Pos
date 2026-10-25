/* Cola de ventas sin red (HU-36). Una venta que no se pudo confirmar con el servidor
   queda guardada en el equipo con su identificador único (uuid) y se envía sola al
   volver la red: una a la vez, en el orden en que se hicieron. Sale de la cola recién
   cuando el servidor la confirma; si la respuesta se pierde, el reintento lleva el
   mismo uuid y el servidor devuelve la venta original en vez de crear otra (HU-57).

   Estados de cada venta:
   - «pendiente»: espera la red.
   - «rechazada»: el servidor no la aceptó (por ejemplo, la caja ya se cerró). Queda
     a la vista con su motivo hasta que el cajero la descarte o la vuelva al pedido.
   - «enviada»: confirmada, con su factura; queda hasta que el cajero la marque lista.

   No toca la pantalla: el POS le pasa dónde guardar (localStorage), cómo enviar y qué
   hacer cuando algo cambia. Así se prueba en Node (tests/js/cola-ventas.test.mjs).
   HU-35: el mesero usa otra cola (su clave y su prefijo) para sus pedidos y cobros. */
(function () {
  'use strict';

  var CLAVE = 'pahuichi.hu36.cola';

  /**
   * @param {{almacen: Storage, usuario: string, enviar: function(object): Promise<object>, alCambiar?: function(Array): void,
   *          clave?: string, prefijo?: string}} op  clave: dónde se guarda; prefijo: del número provisional (S-)
   * enviar(datos) resuelve {ok: true, datos} | {red: true} | {sesion: true} | {rechazo: 'motivo'}
   */
  function crear(op) {
    var procesando = null;
    var clave = op.clave || CLAVE;
    var prefijo = op.prefijo || 'S-';

    function leer() {
      try { return JSON.parse(op.almacen.getItem(clave) || '[]'); } catch (e) { return []; }
    }
    function escribir(lista) {
      op.almacen.setItem(clave, JSON.stringify(lista));
      if (op.alCambiar) op.alCambiar(propias(lista));
    }
    function propias(lista) {
      return lista.filter(function (v) { return v.usuario === op.usuario; });
    }
    function cambiar(uuid, cambios) {
      var lista = leer();
      lista.forEach(function (v) { if (v.uuid === uuid) Object.assign(v, cambios); });
      escribir(lista);
    }

    /** Número provisional del día para anotar el pedido mientras no hay factura (S-1, S-2…). */
    function numeroProvisional(lista, fecha) {
      var delDia = lista.filter(function (v) { return v.fecha === fecha; });
      return prefijo + (delDia.reduce(function (m, v) { return Math.max(m, Number(String(v.provisional).slice(prefijo.length)) || 0); }, 0) + 1);
    }

    /** Guarda la venta en la cola. Lanza si el equipo no deja guardar: entonces no se cobra. */
    function agregar(datos, resumen) {
      var lista = leer();
      if (lista.some(function (v) { return v.uuid === datos.uuid; })) return lista.filter(function (v) { return v.uuid === datos.uuid; })[0];
      var fecha = String(datos.momento || '').slice(0, 10);
      var venta = {uuid: datos.uuid, usuario: op.usuario, datos: datos, estado: 'pendiente', fecha: fecha,
        provisional: numeroProvisional(lista, fecha), total: resumen.total, items: resumen.items, hora: resumen.hora, intentos: 0};
      lista.push(venta);
      escribir(lista);
      return venta;
    }

    /**
     * Envía las pendientes de este usuario, en orden. Se detiene sin red o si la sesión
     * terminó (las demás esperan). Si ya está enviando, devuelve ese mismo envío: una
     * venta nunca sale dos veces a la vez.
     */
    function procesar() {
      if (procesando) return procesando;
      procesando = (async function () {
        var resultado = {enviadas: [], rechazadas: [], detenido: null};
        try {
          for (;;) {
            var siguiente = propias(leer()).filter(function (v) { return v.estado === 'pendiente'; })[0];
            if (!siguiente) break;
            cambiar(siguiente.uuid, {intentos: siguiente.intentos + 1});
            var r;
            try { r = await op.enviar(siguiente.datos); } catch (e) { r = {red: true}; }
            if (r.ok) {
              cambiar(siguiente.uuid, {estado: 'enviada', factura: r.datos});
              resultado.enviadas.push(Object.assign({}, siguiente, {factura: r.datos}));
            } else if (r.rechazo !== undefined) {
              cambiar(siguiente.uuid, {estado: 'rechazada', motivo: r.rechazo, descuento: r.descuento || null});
              resultado.rechazadas.push(Object.assign({}, siguiente, {motivo: r.rechazo, descuento: r.descuento || null}));
            } else {
              resultado.detenido = r.sesion ? 'sesion' : 'red';
              break;
            }
          }
        } finally {
          procesando = null;
        }
        return resultado;
      })();
      return procesando;
    }

    /** Quita una venta rechazada o enviada. Las pendientes no: todavía pueden llegar. */
    function quitar(uuid) {
      var lista = leer();
      var v = lista.filter(function (x) { return x.uuid === uuid; })[0];
      if (!v || v.estado === 'pendiente') return null;
      escribir(lista.filter(function (x) { return x.uuid !== uuid; }));
      return v;
    }

    return {
      agregar: agregar,
      procesar: procesar,
      quitar: quitar,
      ventas: function () { return propias(leer()); },
      pendientes: function () { return propias(leer()).filter(function (v) { return v.estado === 'pendiente'; }).length; },
      deOtros: function () { return leer().filter(function (v) { return v.usuario !== op.usuario && v.estado === 'pendiente'; }).length; }
    };
  }

  /** Envío real al servidor: interpreta la respuesta del cobro. */
  /** url: '/pos/cobrar' (por defecto) o, para el mesero (HU-35), '/cuentas/rondas' o '/cuentas/cerrar'. */
  async function enviarAlServidor(datos, csrf, url) {
    var r;
    try {
      r = await fetch(url || '/pos/cobrar', {method: 'POST', credentials: 'same-origin', redirect: 'manual',
        headers: {'X-CSRF-Token': csrf(), 'Accept': 'application/json'},
        body: new URLSearchParams(datos)});
    } catch (e) {
      return {red: true};
    }
    // Sesión vencida (401 o redirección al login) o página vencida (token CSRF): se envía después de entrar
    if (r.status === 401 || r.type === 'opaqueredirect') return {sesion: true};
    var cuerpo = null;
    try { cuerpo = await r.json(); } catch (e) { cuerpo = null; }
    if (cuerpo && cuerpo.recargar) return {sesion: true};
    if (r.ok && cuerpo) return {ok: true, datos: cuerpo};
    // 422 (pedido inválido), 409 (sin caja), 403 (sin permiso): no se arregla reintentando
    if (r.status >= 400 && r.status < 500 && cuerpo && cuerpo.error) return {rechazo: cuerpo.error, descuento: cuerpo.descuento};
    // 5xx, respuesta cortada o un proxy sin red: se reintenta después
    return {red: true};
  }

  window.PahuichiColaVentas = {crear: crear, enviarAlServidor: enviarAlServidor, CLAVE: CLAVE};
})();
