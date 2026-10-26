/* Cuentas por mesa (HU-35): lo que comparten la pantalla del mesero y la caja.
   - listar(): las cuentas abiertas y las cobradas de hoy, con sus pedidos.
   - pintar(cuenta, opciones): la ficha de una mesa (pedidos, total y acciones).
   - cobrar(cuenta, alCobrar, enviar): diálogo para cerrar y cobrar en efectivo, por QR o
     mixto (HU-39, HU-42), con las reglas del POS: el QR tiene que estar vigente y, si es de
     monto fijo, ese es el monto por QR. enviar(datos, cuenta) es opcional: el mesero cobra a
     través de su cola sin red.
   - cancelar(ronda, alCancelar): diálogo para cancelar un pedido con su motivo.
   Un cobro que no se confirmó conserva su uuid: reintentar no cobra dos veces. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  function csrf() { return document.body.getAttribute('data-csrf') || ''; }
  function centavos(texto) { return Math.round(Number(String(texto).replace(',', '.')) * 100); }

  async function pedir(url, datos) {
    var op = {method: datos ? 'POST' : 'GET', credentials: 'same-origin', headers: {'Accept': 'application/json'}};
    if (datos) { op.headers['X-CSRF-Token'] = csrf(); op.body = new URLSearchParams(datos); }
    var r = await fetch(url, op);
    var cuerpo = null;
    try { cuerpo = await r.json(); } catch (e) { cuerpo = null; }
    if (!r.ok) {
      var error = new Error((cuerpo && cuerpo.error) || 'No se pudo completar. Revisa la conexión e intenta otra vez.');
      error.datos = cuerpo || {};
      throw error;
    }
    return cuerpo;
  }

  function listar() { return pedir('/cuentas'); }

  function el(etiqueta, clase, texto) {
    var x = document.createElement(etiqueta);
    if (clase) x.className = clase;
    if (texto !== undefined) x.textContent = texto;
    return x;
  }

  /**
   * Ficha de una mesa. opciones: {caja: bool (comandas y avisos de nuevos),
   * alCobrar(cuenta), alCancelar(ronda, cuenta), alAgregar(cuenta)}
   */
  function pintar(c, op) {
    var art = el('article', 'cuenta cuenta--' + c.estado);
    art.setAttribute('data-cuenta', c.id);
    var cabeza = el('div', 'cuenta__cabeza');
    var titulo = el('h3', 'cuenta__mesa', 'Mesa ' + c.mesa);
    if (op.caja && c.nuevas) titulo.appendChild(el('span', 'insignia insignia--aviso', c.nuevas === 1 ? '1 pedido nuevo' : c.nuevas + ' pedidos nuevos'));
    cabeza.appendChild(titulo);
    cabeza.appendChild(el('p', 'cuenta__datos', c.estado === 'abierta'
      ? 'Abierta a las ' + c.abierta + ' · ' + c.abierta_por
      : (c.estado === 'cerrada' ? 'Cobrada a las ' + c.cerrada + ' por ' + c.cerrada_por + ' · factura N° ' + c.factura : 'Cancelada a las ' + c.cerrada)));
    var total = el('p', 'cuenta__total num');
    total.appendChild(el('span', 'totales__moneda', 'Bs'));
    total.appendChild(document.createTextNode(P.bs(Number(c.total))));
    cabeza.appendChild(total);
    art.appendChild(cabeza);

    if (c.estado === 'abierta') {
      var ul = el('ol', 'cuenta__rondas');
      c.rondas.forEach(function (r) {
        var li = el('li', 'ronda ronda--' + r.estado + (op.caja && r.estado === 'enviada' && !r.comanda_vista ? ' ronda--nueva' : ''));
        var cab = el('p', 'ronda__cabeza');
        cab.appendChild(el('span', 'ronda__titulo', 'Pedido ' + r.numero + ' · ' + r.hora + ' · ' + r.usuario));
        if (op.caja && r.estado === 'enviada' && !r.comanda_vista) cab.firstChild.appendChild(el('span', 'insignia insignia--aviso', 'Nuevo'));
        cab.appendChild(el('span', 'ronda__subtotal num', r.estado === 'cancelada' ? 'Cancelado' : 'Bs ' + P.bs(Number(r.subtotal))));
        li.appendChild(cab);
        var items = el('ul', 'ronda__lineas');
        r.lineas.forEach(function (l) {
          var item = el('li', l.regalo ? 'ronda__regalo' : '', l.cantidad + ' × ' + l.nombre + (l.regalo ? ' · regalo' : ''));
          if (l.nota) item.appendChild(el('span', 'ronda__nota', ' » ' + l.nota));
          if (!l.regalo && l.promociones.length) item.appendChild(el('span', 'ronda__promo', ' · ' + l.promociones.join(', ')));
          items.appendChild(item);
        });
        li.appendChild(items);
        if (r.estado === 'cancelada' && r.motivo) li.appendChild(el('p', 'ronda__motivo', 'Motivo: ' + r.motivo));
        var acciones = el('div', 'ronda__acciones');
        if (op.caja && r.estado === 'enviada') {
          var comanda = el('a', 'boton boton--fantasma', r.comanda_vista ? 'Comanda' : 'Imprimir comanda');
          comanda.href = r.comanda; comanda.target = '_blank'; comanda.rel = 'noopener';
          comanda.setAttribute('data-ronda-comanda', r.id);
          acciones.appendChild(comanda);
        }
        if (r.cancelable) {
          var cancelar = el('button', 'boton boton--fantasma', 'Cancelar');
          cancelar.type = 'button';
          cancelar.addEventListener('click', function () { op.alCancelar(r, c); });
          acciones.appendChild(cancelar);
        }
        if (acciones.children.length) li.appendChild(acciones);
        ul.appendChild(li);
      });
      art.appendChild(ul);
      var pie = el('div', 'cuenta__acciones');
      if (op.alAgregar) {
        var agregar = el('button', 'boton', 'Agregar pedido');
        agregar.type = 'button';
        agregar.addEventListener('click', function () { op.alAgregar(c); });
        pie.appendChild(agregar);
      }
      var cobrar = el('button', 'boton boton--primario', 'Cerrar y cobrar');
      cobrar.type = 'button';
      // Sin caja abierta no se cobra; con un cobro guardado sin red, tampoco otra vez
      cobrar.disabled = !op.hayCaja || !!c.cobroPendiente;
      if (c.cobroPendiente) cobrar.textContent = 'Cobro por enviar';
      else if (!op.hayCaja) cobrar.title = 'No hay una caja abierta';
      cobrar.addEventListener('click', function () { op.alCobrar(c); });
      pie.appendChild(cobrar);
      art.appendChild(pie);
    } else if (c.tickets) {
      var ver = el('a', 'boton boton--fantasma', 'Factura y copia (PDF)');
      ver.href = c.tickets; ver.target = '_blank'; ver.rel = 'noopener';
      art.appendChild(ver);
    }
    return art;
  }

  /* ------------------------------------------------------------------ cobro */
  // {cuenta, uuid, descuento: {centavos, motivo, token}, alCobrar, enviar, metodo, qr, foto, leyendo}
  var cobro = null;

  function errorCobro(texto) { $('cuenta-error').textContent = texto; $('cuenta-error').hidden = !texto; }
  function aCobrar() { return (centavos(cobro.cuenta.total) - (cobro.descuento ? cobro.descuento.centavos : 0)) / 100; }
  function libre() { return !!cobro.qr && Number(cobro.qr.monto) === 0; }
  // Lo que se paga por QR, en centavos: todo (QR) o una parte (mixto). Un QR de monto fijo manda.
  function parteQr() {
    if (cobro.metodo === 'efectivo' || !cobro.qr) return 0;
    if (!libre()) return centavos(cobro.qr.monto);
    return cobro.metodo === 'qr' ? centavos(aCobrar()) : centavos($('cuenta-qr-monto').value || '0');
  }
  function parteEfectivo() { return cobro.metodo === 'qr' ? 0 : centavos(aCobrar()) - parteQr(); }
  function problemaPago() {
    if (cobro.metodo === 'efectivo') return '';
    if (!cobro.qr) return 'El QR del día no está vigente. Pide al administrador el QR de hoy.';
    var total = centavos(aCobrar());
    if (cobro.metodo === 'qr' && parteQr() !== total) return 'El QR de hoy es de Bs ' + P.bs(parteQr() / 100) + ' y no cubre el total: cobra con Mixto.';
    if (cobro.metodo === 'mixto') {
      if (!(parteQr() > 0)) return 'Escribe el monto pagado por QR.';
      if (parteQr() >= total) return 'En Mixto, el QR debe ser menor que el total. Para pagar todo por QR, elige QR.';
    }
    return '';
  }

  function pintarCobro() {
    $('cuenta-total').textContent = P.bs(aCobrar());
    $('cuenta-descuento-fila').hidden = !cobro.descuento;
    if (cobro.descuento) {
      $('cuenta-descuento').textContent = '−' + P.bs(cobro.descuento.centavos / 100);
      $('cuenta-descuento-motivo').textContent = '(' + cobro.descuento.motivo + ')';
    }
    document.querySelectorAll('[data-cuenta-metodo]').forEach(function (b) {
      var m = b.getAttribute('data-cuenta-metodo');
      b.setAttribute('aria-pressed', String(m === cobro.metodo));
      b.disabled = m !== 'efectivo' && !cobro.qr;
    });
    var conQr = cobro.metodo !== 'efectivo';
    $('cuenta-qr').hidden = !conQr;
    $('cuenta-efectivo').hidden = cobro.metodo === 'qr';
    $('cuenta-recibido-etiqueta').textContent = cobro.metodo === 'mixto' ? 'Efectivo recibido' : 'Monto recibido';
    $('cuenta-qr-imagen').hidden = !(conQr && cobro.qr);
    if (conQr && cobro.qr && $('cuenta-qr-imagen').getAttribute('src') !== '/qr/' + cobro.qr.id + '/imagen') $('cuenta-qr-imagen').src = '/qr/' + cobro.qr.id + '/imagen';
    $('cuenta-qr-aviso').textContent = !cobro.qr ? 'El QR del día no está vigente: solo se puede cobrar en efectivo.'
      : (libre() ? 'QR de importe libre: el cliente paga por QR el monto indicado.' : 'QR de hoy por Bs ' + P.bs(Number(cobro.qr.monto)) + '.') + ' Vence a las 23:59:59.';
    // En el mixto con QR de importe libre, el monto por QR lo escribe quien cobra
    var editable = cobro.metodo === 'mixto' && libre();
    $('cuenta-qr-monto').readOnly = !editable;
    if (!editable) $('cuenta-qr-monto').value = conQr && cobro.qr ? (parteQr() / 100).toFixed(2) : '';
    $('cuenta-mixto-parte').hidden = cobro.metodo !== 'mixto';
    $('cuenta-mixto-parte').textContent = 'Parte en efectivo: Bs ' + P.bs(Math.max(0, parteEfectivo()) / 100) + '. Solo esta parte suma al arqueo.';
    cambio();
  }
  function cambio() {
    var recibido = Number(($('cuenta-recibido').value || '0').replace(',', '.'));
    $('cuenta-cambio').textContent = 'Bs ' + P.bs(Math.max(0, recibido - parteEfectivo() / 100));
  }
  function resumenCliente() {
    var doc = $('cuenta-cliente-doc').value.trim();
    $('cuenta-cliente-resumen').textContent = doc && doc !== '0'
      ? ($('cuenta-cliente-nombre').value.trim() || 'Cliente') + ' · ' + $('cuenta-cliente-tipo').value + ' ' + doc : 'S/N · NIT 0';
  }

  // El QR vigente del día (HU-38): sin él, solo efectivo
  async function cargarQr() {
    var q = null;
    try { q = await pedir('/qr/estado'); } catch (e) { q = null; }
    if (!cobro) return;
    cobro.qr = q && q.vigente ? q : null;
    if (!cobro.qr && cobro.metodo !== 'efectivo') cobro.metodo = 'efectivo';
    pintarCobro();
  }

  function elegirMetodo(m) {
    if (!cobro || m === cobro.metodo) return;
    cobro.metodo = m;
    cobro.uuid = crypto.randomUUID();   // otro medio de pago es otro cobro
    if (m === 'mixto' && libre()) $('cuenta-qr-monto').value = '';
    $('cuenta-recibido').value = m === 'qr' ? '' : (parteEfectivo() / 100).toFixed(2);
    errorCobro(''); pintarCobro();
  }

  function cobrar(cuenta, alCobrar, enviar) {
    // El mismo cobro (misma mesa y total) conserva su uuid si no se confirmó
    // Una cuenta abierta sin red todavía no tiene número: se reconoce por la mesa
    if (!cobro || cobro.cuenta.mesa !== cuenta.mesa || cobro.cuenta.total !== cuenta.total) {
      cobro = {cuenta: cuenta, uuid: crypto.randomUUID(), descuento: null, metodo: 'efectivo', qr: null, foto: '', leyendo: false};
      $('cuenta-cliente-doc').value = ''; $('cuenta-cliente-nombre').value = ''; $('cuenta-cliente-tipo').value = 'NIT';
      $('cuenta-qr-comprobante').value = ''; $('cuenta-qr-pagador').value = ''; $('cuenta-qr-foto').value = ''; $('cuenta-qr-monto').value = '';
    }
    cobro.alCobrar = alCobrar;
    cobro.enviar = enviar || null;
    $('cuenta-titulo').textContent = 'Cobrar la mesa ' + cuenta.mesa;
    var activas = cuenta.rondas.filter(function (r) { return r.estado === 'enviada'; }).length;
    $('cuenta-detalle').textContent = (activas === 1 ? '1 pedido' : activas + ' pedidos') + ' · abierta a las ' + cuenta.abierta + ' · ' + cuenta.abierta_por;
    $('cuenta-recibido').value = (parteEfectivo() / 100).toFixed(2);
    errorCobro(''); resumenCliente(); pintarCobro();
    $('modal-cuenta').showModal();
    $('cuenta-recibido').focus(); $('cuenta-recibido').select();
    cargarQr();
  }

  function datosCliente() {
    var doc = $('cuenta-cliente-doc').value.trim().toUpperCase();
    var nombre = $('cuenta-cliente-nombre').value.trim();
    if (!doc || doc === '0') return {cliente_doc: '0', cliente_nombre: '', cliente_tipo: 'NIT'};
    return {cliente_doc: doc, cliente_nombre: nombre, cliente_tipo: $('cuenta-cliente-tipo').value};
  }

  // Lo que se envía según el medio de pago (el servidor valida igual: HU-39, HU-40, HU-42)
  function datosPago() {
    var problema = problemaPago();
    if (problema) throw new Error(problema);
    if (cobro.metodo === 'efectivo') {
      var exacto = centavos($('cuenta-recibido').value || '0') === centavos(aCobrar());
      return {exacto: exacto ? '1' : '0', recibido: $('cuenta-recibido').value};
    }
    var comprobante = $('cuenta-qr-comprobante').value.trim();
    if (!comprobante) throw new Error('Escribe el número de comprobante QR.');
    if (cobro.leyendo) throw new Error('Espera a que termine de cargar la foto del comprobante.');
    var d = {metodo: cobro.metodo, qr_id: String(cobro.qr.id), qr_monto: (parteQr() / 100).toFixed(2), comprobante: comprobante,
      pagador: $('cuenta-qr-pagador').value.trim(), foto_qr: cobro.foto, exacto: '0', recibido: ''};
    if (cobro.metodo === 'mixto') {
      var recibido = centavos($('cuenta-recibido').value || '0');
      if (recibido < parteEfectivo()) throw new Error('El efectivo recibido no cubre la parte en efectivo (Bs ' + P.bs(parteEfectivo() / 100) + ').');
      d.efectivo_monto = (parteEfectivo() / 100).toFixed(2);
      d.recibido = $('cuenta-recibido').value;
    }
    return d;
  }

  async function confirmarCobro() {
    if (!cobro) return;
    var boton = $('cuenta-confirmar');
    errorCobro('');
    var pago;
    try { pago = datosPago(); } catch (e) { errorCobro(e.message); return; }
    boton.disabled = true;
    var datos = Object.assign({uuid: cobro.uuid, mesa: cobro.cuenta.mesa, total_esperado: cobro.cuenta.total,
      descuento: cobro.descuento ? (cobro.descuento.centavos / 100).toFixed(2) : '', autorizacion: cobro.descuento ? cobro.descuento.token : ''}, pago, datosCliente());
    try {
      // Caja: directo a la cuenta. Mesero: por su cola; sin red queda guardado ({guardado: true})
      var r = cobro.enviar ? await cobro.enviar(datos, cobro.cuenta) : await pedir('/cuentas/' + cobro.cuenta.id + '/cerrar', datos);
      var hecho = cobro;
      cobro = null;
      $('modal-cuenta').close();
      hecho.alCobrar(r, hecho.cuenta);
    } catch (e) {
      if (e.datos && e.datos.descuento === 'invalido') { cobro.descuento = null; pintarCobro(); }
      // Un rechazo del servidor (422) es otro cobro; un corte de red reintenta con el mismo uuid
      if (e.datos && e.datos.error) cobro.uuid = crypto.randomUUID();
      errorCobro(e.message);
    } finally {
      boton.disabled = false;
    }
  }

  /* ------------------------------------------------------------------ cancelar */
  var cancelando = null;
  function cancelar(ronda, cuenta, alCancelar) {
    cancelando = {ronda: ronda, alCancelar: alCancelar};
    $('cancelar-ronda-titulo').textContent = 'Cancelar el pedido ' + ronda.numero + ' de la mesa ' + cuenta.mesa;
    $('cancelar-ronda-motivo').value = '';
    $('cancelar-ronda-error').hidden = true;
    $('modal-cancelar-ronda').showModal();
    $('cancelar-ronda-motivo').focus();
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!$('modal-cuenta')) return;
    $('cuenta-recibido').addEventListener('input', cambio);
    document.querySelectorAll('[data-cuenta-billete]').forEach(function (b) {
      b.addEventListener('click', function () {
        $('cuenta-recibido').value = b.getAttribute('data-cuenta-billete') === 'exacto' ? (parteEfectivo() / 100).toFixed(2) : b.getAttribute('data-cuenta-billete');
        cambio();
      });
    });
    document.querySelectorAll('[data-cuenta-metodo]').forEach(function (b) {
      b.addEventListener('click', function () { elegirMetodo(b.getAttribute('data-cuenta-metodo')); });
    });
    $('cuenta-qr-monto').addEventListener('input', function () { errorCobro(''); pintarCobro(); });
    $('cuenta-qr-comprobante').addEventListener('input', function () { errorCobro(''); });
    // Foto opcional del comprobante, hasta 1 MB, en base64 como la del POS
    $('cuenta-qr-foto').addEventListener('change', function () {
      var archivo = this.files[0];
      cobro.foto = '';
      if (!archivo) return;
      if (archivo.size > 1048576) { this.value = ''; errorCobro('La foto admite hasta 1 MB.'); return; }
      cobro.leyendo = true;
      var lector = new FileReader();
      var este = cobro;
      lector.onload = function () { este.foto = String(lector.result).split(',')[1] || ''; este.leyendo = false; };
      lector.onerror = function () { este.leyendo = false; errorCobro('No se pudo leer la foto. Selecciónala otra vez.'); };
      lector.readAsDataURL(archivo);
    });
    ['cuenta-cliente-doc', 'cuenta-cliente-nombre', 'cuenta-cliente-tipo'].forEach(function (id) { $(id).addEventListener('input', resumenCliente); });
    $('cuenta-confirmar').addEventListener('click', confirmarCobro);
    $('cuenta-recibido').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('cuenta-confirmar').click(); } });

    // HU-06 en caja: el administrador autoriza el descuento con su PIN
    if ($('cuenta-autorizar')) {
      $('cuenta-autorizar').addEventListener('click', async function () {
        var texto = $('cuenta-descuento-monto').value.trim();
        var monto = /^\d{1,8}([.,]\d{1,2})?$/.test(texto) ? centavos(texto) : 0;
        if (monto < 1 || monto >= centavos(cobro.cuenta.total)) { errorCobro('El descuento debe ser mayor que cero y menor que el total de la mesa.'); return; }
        if ($('cuenta-descuento-texto').value.trim().length < 3) { errorCobro('Escribe el motivo del descuento.'); return; }
        try {
          var d = await pedir('/autorizaciones', {accion: 'descuento', monto: (monto / 100).toFixed(2), motivo: $('cuenta-descuento-texto').value.trim(), pin: $('cuenta-descuento-pin').value});
          cobro.descuento = {centavos: centavos(d.monto), motivo: d.motivo, token: d.token};
          cobro.uuid = crypto.randomUUID();
          $('cuenta-descuento-pin').value = '';
          $('cuenta-descuento-panel').open = false;
          errorCobro(''); pintarCobro();
          $('cuenta-recibido').value = (parteEfectivo() / 100).toFixed(2); cambio();
          P.aviso('Descuento autorizado por ' + d.autorizado_por + '.');
        } catch (e) {
          $('cuenta-descuento-pin').value = '';
          errorCobro(e.message);
        }
      });
    }

    $('form-cancelar-ronda').addEventListener('submit', async function (e) {
      e.preventDefault();
      if (!cancelando) return;
      var motivo = $('cancelar-ronda-motivo').value.trim();
      if (motivo.length < 3) { $('cancelar-ronda-error').textContent = 'Escribe el motivo de la cancelación.'; $('cancelar-ronda-error').hidden = false; return; }
      $('cancelar-ronda-confirmar').disabled = true;
      try {
        await pedir('/cuentas/rondas/' + cancelando.ronda.id + '/cancelar', {motivo: motivo});
        $('modal-cancelar-ronda').close();
        P.aviso('Pedido ' + cancelando.ronda.numero + ' cancelado.');
        cancelando.alCancelar();
        cancelando = null;
      } catch (err) {
        $('cancelar-ronda-error').textContent = err.message; $('cancelar-ronda-error').hidden = false;
      } finally {
        $('cancelar-ronda-confirmar').disabled = false;
      }
    });
  });

  window.PahuichiCuentas = {pedir: pedir, listar: listar, pintar: pintar, cobrar: cobrar, cancelar: cancelar};
})();
