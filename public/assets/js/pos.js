/* Punto de venta.
   HU-28: pestañas de categoría y búsqueda instantánea sobre la cuadrícula que
   dibuja el servidor. Favoritos es la primera pantalla; la búsqueda recorre
   todos los productos sin importar la pestaña.
   HU-29: carrito. Un toque agrega, otro suma una unidad; + y − por línea,
   quitar, total siempre visible y cancelar el pedido. La lógica está en carrito.js. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var grilla = $('grilla');
  if (!grilla) return;

  var buscador = $('pos-buscar');
  var barra = $('categorias-pos');
  var items = Array.prototype.slice.call(grilla.children);
  var categoria = grilla.getAttribute('data-inicial') || 'todo';
  var carrito = new window.PahuichiCarrito(JSON.parse($('pos').getAttribute('data-productos') || '[]'));

  function anunciar(texto) { $('anuncio').textContent = texto; }

  /* ------------------------------------------------------------------
     Cuadrícula: pestañas y búsqueda (HU-28)
     ------------------------------------------------------------------ */
  function visible(li, q) {
    if (q) return P.normalizar(li.getAttribute('data-busqueda')).indexOf(q) !== -1;
    if (categoria === 'favoritos') return li.getAttribute('data-favorito') === '1';
    if (categoria === 'todo') return true;
    return li.getAttribute('data-categoria') === categoria;
  }

  function filtrar() {
    var q = P.normalizar(buscador.value.trim());
    var n = 0;
    items.forEach(function (li) {
      var ok = visible(li, q);
      li.hidden = !ok;
      if (ok) n++;
    });
    $('pos-vacio').hidden = n !== 0;
    return n;
  }

  barra.addEventListener('click', function (e) {
    var b = e.target.closest('[data-categoria]');
    if (!b) return;
    categoria = b.getAttribute('data-categoria');
    barra.querySelectorAll('[data-categoria]').forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
    buscador.value = '';
    filtrar();
  });

  buscador.addEventListener('input', function () {
    var n = filtrar();
    anunciar(buscador.value.trim() ? (n === 1 ? '1 producto' : n + ' productos') : '');
  });

  // Enter agrega el primer producto disponible de la búsqueda
  buscador.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    var primero = grilla.querySelector('li:not([hidden]) .producto-pos:not([disabled])');
    if (primero) {
      agregar(primero.getAttribute('data-id'));
      buscador.select();
    }
  });

  /* ------------------------------------------------------------------
     Pedido (HU-29)
     ------------------------------------------------------------------ */
  function agregar(id) {
    var l = carrito.agregar(id);
    if (!l) return;
    anunciar(l.producto.nombre + ': ' + l.cantidad + ' en el pedido.');
    pintar();
  }

  grilla.addEventListener('click', function (e) {
    var b = e.target.closest('.producto-pos');
    if (!b || b.disabled) return;
    agregar(b.getAttribute('data-id'));
  });

  function boton(clase, icono, accion, etiqueta) {
    var b = document.createElement('button');
    b.className = 'boton boton--icono ' + clase;
    b.type = 'button';
    b.setAttribute(accion[0], accion[1]);
    b.setAttribute('aria-label', etiqueta);
    b.innerHTML = '<svg class="icono" aria-hidden="true"><use href="#i-' + icono + '"></use></svg>';
    return b;
  }

  function lineaHtml(l) {
    var p = l.producto;
    var li = document.createElement('li');
    li.className = 'linea';
    li.setAttribute('data-id', p.id);
    li.innerHTML =
      '<div class="linea__texto"><span class="linea__nombre"></span><span class="linea__unitario num"></span></div>' +
      '<span class="linea__subtotal num"></span>' +
      '<div class="linea__controles"><div class="paso" role="group"><span class="paso__valor num"></span></div></div>';
    li.querySelector('.linea__nombre').textContent = p.nombre;
    li.querySelector('.linea__unitario').textContent = 'Bs ' + P.bs(p.precio) + ' c/u';
    li.querySelector('.linea__subtotal').textContent = P.bs(carrito.subtotalDe(l));
    var paso = li.querySelector('.paso');
    paso.setAttribute('aria-label', 'Cantidad de ' + p.nombre);
    paso.querySelector('.paso__valor').textContent = l.cantidad;
    paso.insertBefore(boton('paso__boton', 'menos', ['data-paso', '-1'], l.cantidad === 1 ? 'Quitar ' + p.nombre : 'Una menos de ' + p.nombre), paso.firstChild);
    paso.appendChild(boton('paso__boton', 'mas', ['data-paso', '1'], 'Una más de ' + p.nombre));
    // HU-31: nota libre para cocina, solo en lo que sale en la comanda. Se guarda al escribir.
    if (p.cocina) {
      var notaBoton = boton('boton--fantasma', 'nota', ['data-nota', ''], 'Nota para cocina de ' + p.nombre);
      notaBoton.setAttribute('aria-expanded', 'false');
      li.querySelector('.linea__controles').appendChild(notaBoton);
      var notaCampo = document.createElement('label');
      notaCampo.className = 'linea__nota'; notaCampo.hidden = true;
      notaCampo.innerHTML = '<span class="visualmente-oculto">Nota para cocina de </span><input class="control" type="text" maxlength="60" enterkeyhint="done" placeholder="Nota para cocina, p. ej. sin cebolla">';
      notaCampo.querySelector('.visualmente-oculto').textContent += p.nombre;
      notaCampo.querySelector('input').value = l.nota || '';
      li.appendChild(notaCampo);
      var notaTexto = document.createElement('p'); notaTexto.className = 'linea__nota-texto';
      notaTexto.textContent = l.nota || ''; notaTexto.hidden = !l.nota; li.appendChild(notaTexto);
    }
    li.querySelector('.linea__controles').appendChild(boton('boton--fantasma', 'basura', ['data-quitar', ''], 'Quitar ' + p.nombre + ' del pedido'));
    return li;
  }

  /* HU-06: descuento manual autorizado con el PIN del administrador.
     El servidor entrega un permiso de un solo uso; el cobro lo envía con el monto. */
  var descuento = null; // {centavos, motivo, token, autorizo}
  function centavos(bs) { return Math.round(bs * 100); }

  /* HU-46: promociones activas. Se aplican solas al agregar y se ven como línea de
     descuento; el servidor recalcula al cobrar con el mismo motor. */
  var promociones = JSON.parse($('pos').getAttribute('data-promociones') || '[]');
  var Promos = window.PahuichiPromociones;
  var promoPorId = {};
  var usos = {};   // HU-49: ventas de hoy con cada promoción, para el tope
  promociones.forEach(function (p) { promoPorId[p.id] = p; usos[p.id] = p.usos; });

  /* HU-49: el momento es la hora del servidor al cargar más lo transcurrido; así vale
     la hora de La Paz aunque el reloj del equipo esté mal. La fecha y hora se leen
     tratándolas como UTC, sin conversión de zona. */
  var ahoraServidor = Date.parse($('pos').getAttribute('data-ahora') + 'Z');
  var cargado = Date.now();
  function contexto() {
    var d = new Date(ahoraServidor + (Date.now() - cargado));
    var iso = d.toISOString();
    return { fecha: iso.slice(0, 10), dia: d.getUTCDay() || 7, hora: iso.slice(11, 19), usos: usos };
  }
  function aplicadas() { return Promos.aplicar(carrito.lineas, promociones, contexto()); }
  function firma(aps) { return aps.map(function (a) { return a.promocion ? a.promocion.id + ':' + a.centavos : '-'; }).join(','); }
  var firmaPintada = '';
  function promoCentavos() {
    return aplicadas().reduce(function (s, a) { return s + a.centavos; }, 0);
  }

  // La marca «Promo» de cada producto se ve solo si alguna de sus promociones vale ahora
  function marcarProductos() {
    var c = contexto();
    grilla.querySelectorAll('.producto-pos').forEach(function (b) {
      var marca = b.querySelector('.insignia--promo');
      if (marca) {
        marca.hidden = !(b.getAttribute('data-promos') || '').split(',').some(function (id) {
          return promoPorId[id] && Promos.fueraDeVigencia(promoPorId[id], c) === null;
        });
      }
      var n = carrito.cantidadDe(b.getAttribute('data-id'));
      var base = b.getAttribute('data-etiqueta') || b.getAttribute('aria-label');
      b.setAttribute('data-etiqueta', base);
      b.setAttribute('aria-label', base + (marca && !marca.hidden ? ', con promoción' : '') + (n ? ', ' + n + ' en el pedido' : ''));
    });
  }
  // Subtotal con las promociones ya descontadas: el descuento manual debe ser menor
  function neto() { return (centavos(carrito.total()) - promoCentavos()) / 100; }
  function aCobrar() { return (centavos(neto()) - (descuento ? descuento.centavos : 0)) / 100; }

  function lineaPromo(g) {
    var li = document.createElement('li');
    li.className = 'linea linea--promo';
    li.innerHTML = '<svg class="icono icono--sm" aria-hidden="true"><use href="#i-promocion"></use></svg><span class="linea__nombre"></span><span class="linea__subtotal num"></span>';
    li.querySelector('.linea__nombre').textContent = g.promocion.nombre;
    li.querySelector('.linea__subtotal').textContent = '−' + P.bs(g.centavos / 100);
    return li;
  }

  function pintar() {
    var ul = $('lineas');
    ul.innerHTML = '';
    carrito.lineas.forEach(function (l) { ul.appendChild(lineaHtml(l)); });
    var aps = aplicadas();
    firmaPintada = firma(aps);
    var grupos = Promos.agrupar(aps);
    grupos.forEach(function (g) { ul.appendChild(lineaPromo(g)); });
    var promo = grupos.reduce(function (s, g) { return s + g.centavos; }, 0);

    // HU-06: el descuento se cae si el pedido se vacía o deja de ser menor que el subtotal
    if (descuento && (carrito.vacio() || descuento.centavos >= centavos(neto()))) {
      if (!carrito.vacio()) P.aviso('Se quitó el descuento: ya no es menor que el subtotal.');
      descuento = null;
    }
    $('desglose').hidden = !descuento && !promo;
    $('t-subtotal').textContent = P.bs(carrito.total());
    $('fila-promociones').hidden = !promo;
    $('t-promociones').textContent = '−' + P.bs(promo / 100);
    $('fila-descuento').hidden = !descuento;
    if (descuento) {
      $('t-descuento').textContent = '−' + P.bs(descuento.centavos / 100);
      $('t-descuento-motivo').textContent = '· ' + descuento.motivo;
    }
    $('descuento').textContent = descuento ? 'Quitar descuento' : 'Descuento';
    $('descuento').disabled = carrito.vacio();

    var total = P.bs(aCobrar());
    var unidades = carrito.unidades();
    $('lineas-vacio').hidden = !carrito.vacio();
    $('t-total').textContent = total;
    $('resumen-total').textContent = total;
    $('resumen-cuenta').textContent = unidades ? (unidades === 1 ? '1 ítem' : unidades + ' ítems') : 'Pedido vacío';
    $('vaciar').disabled = carrito.vacio();
    $('cobrar').disabled = carrito.vacio();
    $('efectivo-exacto').disabled = carrito.vacio();

    // Cuántas unidades de cada producto hay en el pedido, sobre su botón
    grilla.querySelectorAll('.producto-pos').forEach(function (b) {
      var n = carrito.cantidadDe(b.getAttribute('data-id'));
      var cuenta = b.querySelector('.producto-pos__cuenta');
      if (n && !cuenta) {
        cuenta = document.createElement('span');
        cuenta.className = 'producto-pos__cuenta';
        cuenta.setAttribute('aria-hidden', 'true');
        b.appendChild(cuenta);
      }
      if (cuenta) {
        if (n) cuenta.textContent = n; else cuenta.remove();
      }
    });
    marcarProductos();
  }

  $('lineas').addEventListener('click', function (e) {
    var li = e.target.closest('.linea');
    if (!li) return;
    var id = li.getAttribute('data-id');
    var nombre = li.querySelector('.linea__nombre').textContent;
    if (e.target.closest('[data-nota]')) {
      if (li.querySelector('.linea__nota').hidden) abrirNota(li); else cerrarNota(li);
      return;
    }
    var paso = e.target.closest('[data-paso]');
    if (paso) {
      var delta = parseInt(paso.getAttribute('data-paso'), 10);
      var queda = carrito.cambiar(id, delta);
      anunciar(queda ? nombre + ': ' + queda + ' en el pedido.' : nombre + ' quitado del pedido.');
      pintar();
      // El foco se queda en el mismo botón si la línea sigue; si no, vuelve a la búsqueda
      var mismo = $('lineas').querySelector('[data-id="' + id + '"] [data-paso="' + delta + '"]');
      (mismo || buscador).focus();
      return;
    }
    if (e.target.closest('[data-quitar]')) {
      carrito.quitar(id);
      anunciar(nombre + ' quitado del pedido.');
      pintar();
      buscador.focus();
    }
  });

  // Mientras se escribe, la nota se ve solo en el campo; al cerrarlo queda debajo del plato
  function abrirNota(li) {
    li.querySelector('.linea__nota').hidden = false;
    li.querySelector('.linea__nota-texto').hidden = true;
    li.querySelector('[data-nota]').setAttribute('aria-expanded', 'true');
    li.querySelector('.linea__nota input').focus();
  }
  function cerrarNota(li) {
    var l = carrito.linea(li.dataset.id);
    li.querySelector('.linea__nota').hidden = true;
    li.querySelector('.linea__nota-texto').hidden = !(l && l.nota);
    var abre = li.querySelector('[data-nota]');
    abre.setAttribute('aria-expanded', 'false');
    abre.focus();
  }

  $('lineas').addEventListener('input', function (e) {
    if (!e.target.matches('.linea__nota input') || pendiente) return;
    var li = e.target.closest('.linea');
    var l = carrito.linea(li.dataset.id);
    l.nota = e.target.value.trim();
    li.querySelector('.linea__nota-texto').textContent = l.nota;
  });

  // Enter guarda la nota y la cierra (ya quedó guardada al escribir)
  $('lineas').addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' || !e.target.matches('.linea__nota input')) return;
    e.preventDefault();
    var li = e.target.closest('.linea');
    var l = carrito.linea(li.dataset.id);
    cerrarNota(li);
    anunciar(l.nota ? 'Nota guardada: ' + l.nota + '.' : 'Sin nota.');
  });

  // Cancelar el pedido: pide confirmación porque se pierde lo cargado
  var modal = $('modal-vaciar');
  modal.addEventListener('pahuichi:abrir', function () {
    var n = carrito.unidades();
    $('modal-vaciar-texto').textContent = 'Se quita' + (n === 1 ? ' 1 ítem' : 'n los ' + n + ' ítems') + ' del pedido.';
  });
  modal.addEventListener('close', function () {
    if (modal.returnValue !== 'vaciar') return;
    carrito.vaciar();
    pintar();
    anunciar('Pedido cancelado.');
    P.aviso('Pedido cancelado.');
    buscador.focus();
  });

  // Celular: el pedido es una barra inferior que se despliega
  $('ver-pedido').addEventListener('click', function () {
    var abierto = $('pedido').classList.toggle('esta-abierto');
    this.setAttribute('aria-expanded', String(abierto));
  });

  pintar();
  // HU-57: un identificador por intento. Reintentar conserva el mismo pedido.
  var pendiente = null;
  var enviando = false;
  var claveCobro = 'pahuichi.hu57.cobro.' + $('pos').getAttribute('data-caja');
  var dialogoCobro = $('modal-cobro');
  var tipoPedido = 'mesa';
  function errorCobro(texto) {
    $('cobro-error').textContent = texto;
    $('cobro-error').hidden = !texto;
    $('recibido-error').textContent = texto;
    $('recibido-error').hidden = !texto;
  }
  function bloquearPedido(valor) {
    document.querySelector('.pos__catalogo').inert = valor;
    $('lineas').inert = valor;
    document.querySelector('.tipo-pedido').inert = valor;
    $('vaciar').disabled = valor || carrito.vacio();
    $('descuento').disabled = valor || carrito.vacio();
    $('cobrar').disabled = enviando || (!pendiente && carrito.vacio());
    $('efectivo-exacto').disabled = valor || carrito.vacio();
    $('confirmar-cobro').disabled = enviando;
    $('recibido').disabled = valor;
    document.querySelector('.billetes').inert = valor;
    $('cobrar-texto').textContent = pendiente ? 'Reintentar cobro' : 'Cobrar';
  }
  /* HU-30: mesa (con su número) o para llevar. El número no trae valor por
     defecto: se escribe en cada pedido para no cobrar en la mesa equivocada. */
  function elegirTipo(tipo) {
    tipoPedido = tipo;
    document.querySelectorAll('[data-tipo]').forEach(function (x) { x.setAttribute('aria-pressed', String(x.getAttribute('data-tipo') === tipo)); });
    $('mesa').hidden = tipo !== 'mesa';
    $('mesa').removeAttribute('aria-invalid');
    errorCobro('');
  }
  function exigirMesa() {
    if (tipoPedido !== 'mesa' || /^[1-9]\d?$/.test($('mesa').value)) return true;
    errorCobro('Escribe el número de mesa, de 1 a 99, o elige Llevar.');
    $('mesa').setAttribute('aria-invalid', 'true');
    $('mesa').focus();
    return false;
  }
  document.querySelectorAll('[data-tipo]').forEach(function (b) {
    b.addEventListener('click', function () {
      elegirTipo(b.getAttribute('data-tipo'));
      if (tipoPedido === 'mesa') $('mesa').focus();
    });
  });
  // Solo cifras y sin ceros a la izquierda: «04» queda en «4»
  $('mesa').addEventListener('input', function () {
    var limpio = this.value.replace(/\D/g, '').replace(/^0+/, '');
    if (limpio !== this.value) this.value = limpio;
    if (this.hasAttribute('aria-invalid')) { this.removeAttribute('aria-invalid'); errorCobro(''); }
  });
  function cambio() {
    var recibido = Number($('recibido').value.replace(',', '.'));
    $('cambio').textContent = 'Bs ' + P.bs(Math.max(0, recibido - aCobrar()));
  }
  $('recibido').addEventListener('input', cambio);
  document.querySelectorAll('[data-billete]').forEach(function (b) {
    b.addEventListener('click', function () {
      $('recibido').value = b.dataset.billete === 'exacto' ? aCobrar().toFixed(2) : b.dataset.billete;
      cambio();
    });
  });
  $('cobrar').addEventListener('click', function () {
    if (pendiente) { cobrar(false); return; }
    if (!exigirMesa() || !promocionesAlDia()) return;
    $('cobro-total').textContent = P.bs(aCobrar());
    $('recibido').value = aCobrar().toFixed(2);
    errorCobro(''); cambio(); dialogoCobro.showModal(); $('recibido').focus();
  });
  /* HU-49: una promoción puede empezar o terminar mientras se arma el pedido (horario,
     tope). Si lo que vale ahora no es lo que se ve, se repinta y no se cobra a ciegas. */
  var usadasPendiente = [];
  function promocionesAlDia() {
    if (firma(aplicadas()) === firmaPintada) return true;
    if (dialogoCobro.open) dialogoCobro.close();
    pintar();
    errorCobro('Cambió una promoción por su horario o su tope. Revisa el total y vuelve a cobrar.');
    anunciar('Cambió una promoción. Revisa el total.');
    return false;
  }
  // Cada 30 segundos: marcas «Promo» al día y, si cambió una promoción del pedido, se repinta
  setInterval(function () {
    if (enviando || pendiente || document.querySelector('dialog[open]')) return;
    var escribiendo = $('lineas').contains(document.activeElement) && document.activeElement.matches('input');
    if (firma(aplicadas()) !== firmaPintada && !escribiendo) pintar(); else marcarProductos();
  }, 30000);

  async function cobrar(exacto) {
    if (enviando || (!pendiente && carrito.vacio())) return;
    if (!pendiente && (!exigirMesa() || !promocionesAlDia())) return;
    errorCobro('');
    if (!pendiente) {
      usadasPendiente = Promos.agrupar(aplicadas()).map(function (g) { return g.promocion.id; });
      pendiente = {uuid: crypto.randomUUID(), caja_id: $('pos').getAttribute('data-caja'),
        tipo: tipoPedido, mesa: $('mesa').value, exacto: exacto ? '1' : '0',
        recibido: $('recibido').value, total_esperado: carrito.total().toFixed(2),
        promociones: (promoCentavos() / 100).toFixed(2),
        descuento: descuento ? (descuento.centavos / 100).toFixed(2) : '', autorizacion: descuento ? descuento.token : '',
        a_cobrar: aCobrar().toFixed(2),
        lineas: JSON.stringify(carrito.lineas.map(function (l) { return {id: Number(l.producto.id), cantidad: l.cantidad, nota: l.nota || ''}; }))};
      try { sessionStorage.setItem(claveCobro, JSON.stringify(pendiente)); }
      catch (e) { pendiente = null; errorCobro('Permite el almacenamiento de esta pestaña para proteger el reintento del cobro.'); return; }
    }
    enviando = true; bloquearPedido(true);
    try {
      var respuesta = await fetch('/pos/cobrar', {method: 'POST',
        headers: {'X-CSRF-Token': document.body.getAttribute('data-csrf') || '', 'Accept': 'application/json'},
        body: new URLSearchParams(pendiente)});
      var datos = await respuesta.json();
      if (!respuesta.ok) {
        if (respuesta.status === 422) {
          sessionStorage.removeItem(claveCobro); pendiente = null;
          // Permiso vencido o ya usado: se quita el descuento para pedir el PIN otra vez
          if (datos.descuento === 'invalido') { descuento = null; pintar(); }
        }
        throw new Error(datos.error || 'No se pudo confirmar la respuesta. Reintenta el mismo cobro.');
      }
      sessionStorage.removeItem(claveCobro);
      pendiente = null;
      enviando = false;
      descuento = null;
      // HU-49: esta venta cuenta para el tope de hoy de sus promociones
      usadasPendiente.forEach(function (id) { usos[id] = (usos[id] || 0) + 1; });
      usadasPendiente = [];
      carrito.vaciar();
      if (dialogoCobro.open) dialogoCobro.close();
      // El siguiente pedido vuelve a elegir su mesa
      $('mesa').value = ''; elegirTipo('mesa');
      pintar(); bloquearPedido(false);
      $('venta-lista-titulo').textContent = 'Pedido ' + datos.pedido + ' cobrado';
      $('venta-lista-detalle').textContent = 'Factura N° ' + datos.numero + ' · cambio Bs ' + P.bs(datos.cambio) + ' · lista para imprimir';
      $('venta-lista-ver').href = datos.tickets;
      $('venta-lista').hidden = false;
      $('pedido').classList.add('esta-abierto');
      $('ver-pedido').setAttribute('aria-expanded', 'true');
      anunciar('Venta guardada. Abre Ver tickets para consultar comanda, copia de caja y factura.');
      $('venta-lista-ver').focus();
    } catch (e) {
      errorCobro(pendiente ? 'No se confirmó el resultado. Pulsa Reintentar cobro; se comprobará el mismo pedido sin duplicarlo.' : e.message);
      enviando = false; bloquearPedido(!!pendiente);
    }
  }
  $('efectivo-exacto').addEventListener('click', function () { cobrar(true); });
  $('confirmar-cobro').addEventListener('click', function () { cobrar(false); });
  try { pendiente = JSON.parse(sessionStorage.getItem(claveCobro) || 'null'); } catch (e) { pendiente = null; }
  if (pendiente) {
    bloquearPedido(true);
    errorCobro('Hay un cobro pendiente de comprobar por Bs ' + (pendiente.a_cobrar || pendiente.total_esperado) + '. Pulsa Reintentar cobro para recuperar su factura.');
  }

  /* HU-06: pedir el descuento. El administrador escribe su PIN en el mismo equipo;
     el servidor valida el PIN, el bloqueo por intentos y entrega el permiso. */
  var dialogoDescuento = $('modal-descuento');
  function errorDescuento(texto, campo) {
    $('descuento-error').textContent = texto;
    $('descuento-error').hidden = !texto;
    ['descuento-monto', 'descuento-motivo', 'descuento-pin'].forEach(function (id) {
      if (id === campo) $(id).setAttribute('aria-invalid', 'true'); else $(id).removeAttribute('aria-invalid');
    });
    if (campo) $(campo).focus();
  }
  $('descuento').addEventListener('click', function () {
    if (descuento) {
      descuento = null; pintar();
      anunciar('Descuento quitado.'); P.aviso('Descuento quitado.');
      return;
    }
    $('descuento-monto').value = ''; $('descuento-motivo').value = ''; $('descuento-pin').value = '';
    $('descuento-subtotal').textContent = 'Menor que el subtotal de Bs ' + P.bs(neto()) + '.';
    errorDescuento('');
    dialogoDescuento.showModal();
    $('descuento-monto').focus();
  });
  $('form-descuento').addEventListener('submit', async function (e) {
    e.preventDefault();
    var texto = $('descuento-monto').value.trim();
    var monto = /^\d{1,8}([.,]\d{1,2})?$/.test(texto) ? centavos(Number(texto.replace(',', '.'))) : 0;
    var motivo = $('descuento-motivo').value.trim();
    var pin = $('descuento-pin').value;
    if (monto < 1) return errorDescuento('Escribe el monto del descuento, con hasta dos decimales.', 'descuento-monto');
    if (monto >= centavos(neto())) return errorDescuento('El descuento debe ser menor que el subtotal de Bs ' + P.bs(neto()) + '.', 'descuento-monto');
    if (motivo.length < 3) return errorDescuento('Escribe el motivo del descuento.', 'descuento-motivo');
    if (!/^\d{4,6}$/.test(pin)) return errorDescuento('Escribe el PIN del administrador, de 4 a 6 dígitos.', 'descuento-pin');
    $('autorizar-descuento').disabled = true;
    try {
      var respuesta = await fetch('/autorizaciones', {method: 'POST',
        headers: {'X-CSRF-Token': document.body.getAttribute('data-csrf') || '', 'Accept': 'application/json'},
        body: new URLSearchParams({accion: 'descuento', monto: (monto / 100).toFixed(2), motivo: motivo, pin: pin})});
      var datos = await respuesta.json();
      $('descuento-pin').value = '';
      if (!respuesta.ok) return errorDescuento(datos.error || 'No se pudo autorizar. Intenta otra vez.', 'descuento-pin');
      descuento = {centavos: centavos(Number(datos.monto)), motivo: datos.motivo, token: datos.token, autorizo: datos.autorizado_por};
      dialogoDescuento.close();
      pintar();
      var aviso = 'Descuento de Bs ' + P.bs(descuento.centavos / 100) + ' autorizado por ' + datos.autorizado_por + '.';
      anunciar(aviso); P.aviso(aviso);
      $('cobrar').focus();
    } catch (err) {
      errorDescuento('No hay conexión con el servidor. Intenta otra vez.', 'descuento-pin');
    } finally {
      $('autorizar-descuento').disabled = false;
    }
  });

  /* HU-33: atajos. F2 busca, F4 cobra, F8 cobra en efectivo exacto y Esc cancela.
     Con un modal abierto no actúan: Esc lo cierra (lo hace el propio dialog).
     Esc va por pasos, del más cercano al pedido: cierra la nota que se escribe,
     luego borra la búsqueda y recién después pide confirmar la cancelación. */
  document.addEventListener('keydown', function (e) {
    if (['F2', 'F4', 'F8', 'Escape'].indexOf(e.key) === -1) return;
    if (document.querySelector('dialog[open]')) return;
    if (e.key === 'F2') {
      e.preventDefault();
      buscador.focus(); buscador.select();
      return;
    }
    if (e.key === 'Escape') {
      var nota = e.target.closest && e.target.closest('.linea__nota');
      if (nota) {
        e.preventDefault();
        cerrarNota(nota.closest('.linea'));
        return;
      }
      if (e.target === buscador && buscador.value) {
        e.preventDefault();
        buscador.value = ''; filtrar(); anunciar('');
        return;
      }
      if (!$('vaciar').disabled) { e.preventDefault(); $('vaciar').click(); }
      return;
    }
    e.preventDefault();
    var b = $(e.key === 'F8' ? 'efectivo-exacto' : 'cobrar');
    if (!b.disabled) b.click();
  });
  dialogoCobro.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target === $('recibido')) { e.preventDefault(); cobrar(false); }
  });
  window.PahuichiPos = { filtrar: filtrar, carrito: carrito };
})();
