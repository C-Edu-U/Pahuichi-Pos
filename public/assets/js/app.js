/* ==========================================================================
   Pahuichi POS · Panel
   - Los permisos los decide el servidor; el body trae los del usuario en
     data-permisos solo para mostrar u ocultar botones.
   - Riel lateral plegable, cajón en móvil, estado de la conexión, PWA.
   - Utilidades compartidas: filtros de tabla, formularios desplegables,
     tablas en fichas, modales, avisos y formato de montos.
   ========================================================================== */
(function () {
  'use strict';

  var CLAVE_PLEGADO = 'pahuichi.rielPlegado';
  var cuerpo = document.body;

  function leer(clave) {
    try { return window.localStorage.getItem(clave); } catch (e) { return null; }
  }

  function guardar(clave, valor) {
    try { window.localStorage.setItem(clave, valor); } catch (e) { /* almacenamiento bloqueado */ }
  }

  var permisos = (cuerpo.getAttribute('data-permisos') || '').split(' ');
  function puede(permiso) {
    return permisos.indexOf(permiso) !== -1;
  }

  function parametro(nombre) {
    var m = new RegExp('[?&]' + nombre + '=([^&]*)').exec(window.location.search);
    return m ? decodeURIComponent(m[1]) : null;
  }

  // Montos en bolivianos: 1.286,50
  function bs(n) {
    var fijo = (Math.round(n * 100) / 100).toFixed(2).split('.');
    return fijo[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + fijo[1];
  }

  // Acepta 1.286,50 · 1286,50 · 1286.50 · 45 (igual que App\Support\Monto en el servidor)
  function leerMonto(texto) {
    var limpio = String(texto).trim().replace(/\s/g, '');
    if (limpio.indexOf(',') !== -1) limpio = limpio.replace(/\./g, '').replace(',', '.');
    else if (!/^\d+\.\d{1,2}$/.test(limpio)) limpio = limpio.replace(/\./g, '');
    if (!/^\d+(\.\d{1,2})?$/.test(limpio)) return null;
    return Math.round(parseFloat(limpio) * 100) / 100;
  }

  function normalizar(texto) {
    return String(texto).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  // Aviso flotante breve (confirma una acción sin interrumpir)
  var zonaAvisos = null;
  function aviso(texto, tipo) {
    if (!zonaAvisos) {
      zonaAvisos = document.createElement('div');
      zonaAvisos.className = 'avisos';
      zonaAvisos.setAttribute('role', 'status');
      zonaAvisos.setAttribute('aria-live', 'polite');
      document.body.appendChild(zonaAvisos);
    }
    var el = document.createElement('p');
    el.className = 'aviso-flotante' + (tipo ? ' aviso-flotante--' + tipo : '');
    el.textContent = texto;
    zonaAvisos.appendChild(el);
    window.setTimeout(function () { el.classList.add('se-va'); }, 3200);
    window.setTimeout(function () { el.remove(); }, 3600);
  }

  // Error junto al campo (el servidor valida igual; esto solo adelanta el aviso)
  function marcarCampo(id, mensaje) {
    var control = document.getElementById(id);
    var error = document.getElementById(id + '-error');
    if (!error) return;
    if (mensaje) {
      error.innerHTML = '<svg class="icono icono--sm" aria-hidden="true"><use href="#i-error"></use></svg><span></span>';
      error.querySelector('span').textContent = mensaje;
      error.hidden = false;
      if (control) control.setAttribute('aria-invalid', 'true');
    } else {
      error.hidden = true;
      error.textContent = '';
      if (control) control.removeAttribute('aria-invalid');
    }
  }

  /* HU-35: mesa del pedido. Los botones 1 a 12 llenan el campo «Otra»; el campo es el valor
     y marca su botón. mesa(campo) vuelve a marcar después de cambiar el valor por código. */
  function mesa(campo) {
    var grupo = campo && campo.closest('.mesas--pedido');
    if (!grupo) return;
    grupo.querySelectorAll('.mesa-opcion').forEach(function (b) {
      b.setAttribute('aria-pressed', String(b.getAttribute('data-mesa') === campo.value));
    });
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('.mesas--pedido .mesa-opcion');
    if (!b) return;
    var campo = b.closest('.mesas--pedido').querySelector('.mesas__otra');
    campo.value = b.getAttribute('data-mesa');
    campo.dispatchEvent(new Event('input', {bubbles: true}));
  });
  document.addEventListener('input', function (e) {
    if (e.target.classList && e.target.classList.contains('mesas__otra')) mesa(e.target);
  });

  window.Pahuichi = {
    puede: puede, parametro: parametro, leer: leer, guardar: guardar,
    bs: bs, leerMonto: leerMonto, normalizar: normalizar, aviso: aviso, marcarCampo: marcarCampo, mesa: mesa
  };

  // PWA: service worker en la raíz del sitio
  if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
    navigator.serviceWorker.register('/sw.js').catch(function () { /* sin PWA, el sistema sigue funcionando */ });
  }

  if (!cuerpo.classList.contains('panel')) return;

  // Aviso que deja el servidor tras guardar (patrón POST, redirección, GET)
  if (cuerpo.hasAttribute('data-aviso')) aviso(cuerpo.getAttribute('data-aviso'));

  /* ------------------------------------------------------------------
     Estado de la conexión con el servidor
     ------------------------------------------------------------------ */
  var estadoRed = document.getElementById('estado-red');
  // HU-36: también sin conexión si la página salió de la copia guardada del service worker
  var copiaSinRed = document.documentElement.hasAttribute('data-sin-red');
  function pintarRed() {
    if (estadoRed) estadoRed.hidden = navigator.onLine && !copiaSinRed;
  }
  window.addEventListener('online', function () { copiaSinRed = false; pintarRed(); aviso('Conexión recuperada.'); });
  window.addEventListener('offline', pintarRed);
  pintarRed();

  /* ------------------------------------------------------------------
     Cierre por inactividad (el servidor lo aplica igual): aviso un minuto
     antes. data-inactividad trae los minutos; el cajero no lo tiene.
     ------------------------------------------------------------------ */
  var minutosInactividad = parseFloat(cuerpo.getAttribute('data-inactividad') || '0');
  var temporizadores = [];
  var dialogoInactividad = null;

  function cerrarPorInactividad() {
    // Sin red, salir dejaría al equipo sin pantalla (HU-36): se espera a que vuelva
    if (!navigator.onLine || copiaSinRed) {
      window.addEventListener('online', cerrarPorInactividad, {once: true});
      return;
    }
    var form = document.getElementById('form-salir');
    if (!form) { window.location.href = '/login?estado=inactividad'; return; }
    var motivo = form.querySelector('[name="motivo"]');
    if (motivo) motivo.value = 'inactividad';
    form.submit();
  }

  function avisarInactividad() {
    if (!dialogoInactividad) {
      dialogoInactividad = document.createElement('dialog');
      dialogoInactividad.className = 'modal';
      dialogoInactividad.setAttribute('aria-labelledby', 'inactividad-titulo');
      dialogoInactividad.innerHTML =
        '<div class="modal__cuerpo"><h2 class="modal__titulo" id="inactividad-titulo">¿Sigues ahí?</h2>' +
        '<p class="modal__texto">Por seguridad, tu sesión se cierra en un minuto por inactividad.</p></div>' +
        '<div class="modal__acciones"><button class="boton" type="button" data-salir-ahora>Cerrar sesión</button>' +
        '<button class="boton boton--primario" type="button" data-cerrar-modal autofocus>Seguir trabajando</button></div>';
      document.body.appendChild(dialogoInactividad);
      dialogoInactividad.querySelector('[data-salir-ahora]').addEventListener('click', function () {
        var form = document.getElementById('form-salir');
        if (form) form.submit();
      });
      dialogoInactividad.addEventListener('close', function () {
        // Seguir trabajando: un pedido al servidor renueva la sesión
        fetch('/sesion/seguir', {
          method: 'POST',
          headers: { 'X-CSRF-Token': cuerpo.getAttribute('data-csrf') || '', 'Accept': 'application/json' }
        }).catch(function () { /* si no hay red, el servidor decidirá en el próximo pedido */ });
        iniciarInactividad();
      });
    }
    if (!dialogoInactividad.open) dialogoInactividad.showModal();
  }

  function iniciarInactividad() {
    temporizadores.forEach(window.clearTimeout);
    temporizadores = [];
    if (!minutosInactividad) return;
    var minutos = parametro('inactividad') ? 0.05 : minutosInactividad - 1; // ?inactividad=1 lo muestra enseguida
    temporizadores.push(window.setTimeout(avisarInactividad, minutos * 60000));
    temporizadores.push(window.setTimeout(cerrarPorInactividad, (minutos + 1) * 60000));
  }

  ['pointerdown', 'keydown'].forEach(function (t) {
    document.addEventListener(t, function () {
      if (!dialogoInactividad || !dialogoInactividad.open) iniciarInactividad();
    }, { passive: true });
  });
  iniciarInactividad();

  /* ------------------------------------------------------------------
     Riel: expandido en escritorio salvo que el usuario lo pliegue
     ------------------------------------------------------------------ */
  var escritorio = window.matchMedia('(min-width: 1200px)');
  var tableta = window.matchMedia('(min-width: 768px)');
  var botonPlegar = document.querySelector('[data-plegar-riel]');
  var forzarPlegado = cuerpo.hasAttribute('data-riel-plegado');

  function actualizarRiel() {
    var plegado = forzarPlegado ? leer(CLAVE_PLEGADO) !== '0' : leer(CLAVE_PLEGADO) === '1';
    cuerpo.classList.toggle('riel-plegado', plegado);
    cuerpo.classList.toggle('riel-expandido', !tableta.matches || (escritorio.matches && !plegado));
    if (botonPlegar) {
      botonPlegar.setAttribute('aria-expanded', String(!plegado));
      var texto = botonPlegar.querySelector('.menu__texto');
      if (texto) texto.textContent = plegado ? 'Desplegar menú' : 'Plegar menú';
    }
  }

  actualizarRiel();
  escritorio.addEventListener('change', actualizarRiel);
  tableta.addEventListener('change', function () { actualizarRiel(); cerrarCajon(); });

  if (botonPlegar) {
    botonPlegar.addEventListener('click', function () {
      var plegado = cuerpo.classList.contains('riel-plegado');
      guardar(CLAVE_PLEGADO, plegado ? '0' : '1');
      actualizarRiel();
    });
  }

  /* ------------------------------------------------------------------
     Cajón en móvil
     ------------------------------------------------------------------ */
  var riel = document.getElementById('riel');
  var velo = document.querySelector('.velo');
  var botonMenu = document.querySelector('[data-abrir-riel]');
  var botonCerrar = document.querySelector('[data-cerrar-riel]');

  function abrirCajon() {
    if (tableta.matches) return;
    velo.hidden = false;
    riel.classList.add('esta-abierto');
    botonMenu.setAttribute('aria-expanded', 'true');
    requestAnimationFrame(function () { velo.classList.add('esta-visible'); });
    var actual = riel.querySelector('[aria-current="page"]') || riel.querySelector('.menu__enlace');
    if (actual) actual.focus();
  }

  function cerrarCajon() {
    if (!riel.classList.contains('esta-abierto')) return;
    riel.classList.remove('esta-abierto');
    velo.classList.remove('esta-visible');
    botonMenu.setAttribute('aria-expanded', 'false');
    window.setTimeout(function () { velo.hidden = true; }, 240);
    botonMenu.focus();
  }

  if (botonMenu) botonMenu.addEventListener('click', abrirCajon);
  if (botonCerrar) botonCerrar.addEventListener('click', cerrarCajon);
  if (velo) velo.addEventListener('click', cerrarCajon);

  document.addEventListener('keydown', function (e) {
    if (!riel.classList.contains('esta-abierto')) return;
    if (e.key === 'Escape') cerrarCajon();
    if (e.key === 'Tab') {
      var focos = Array.prototype.filter.call(
        riel.querySelectorAll('a[href], button:not([disabled])'),
        function (el) { return el.offsetParent !== null; }
      );
      if (!focos.length) return;
      var primero = focos[0];
      var ultimo = focos[focos.length - 1];
      if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
      else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
    }
  });

  /* ------------------------------------------------------------------
     Tablas que en móvil pasan a fichas: cada celda lleva la etiqueta de
     su columna (también las filas que se agregan después)
     ------------------------------------------------------------------ */
  function etiquetarFichas(tabla) {
    if (!tabla.tHead) return;
    var cabeza = Array.prototype.map.call(tabla.tHead.rows[0].cells, function (th) { return th.textContent.trim(); });
    Array.prototype.forEach.call(tabla.tBodies[0].rows, function (tr) {
      Array.prototype.forEach.call(tr.cells, function (td, i) {
        if (!td.hasAttribute('data-etiqueta')) td.setAttribute('data-etiqueta', cabeza[i] || '');
      });
    });
  }

  document.querySelectorAll('.tabla--fichas').forEach(function (tabla) {
    etiquetarFichas(tabla);
    new MutationObserver(function () { etiquetarFichas(tabla); }).observe(tabla.tBodies[0], { childList: true });
  });

  /* ------------------------------------------------------------------
     Filtros de tabla por atributos:
       <input data-filtra="filas-x">            busca en el texto de la fila
       <select data-filtra="filas-x" data-campo="estado">  compara tr[data-estado]
       <span data-cuenta="filas-x" data-singular="venta" data-plural="ventas">
       <div data-vacio-de="filas-x" hidden>
     ------------------------------------------------------------------ */
  function filtrarTabla(id) {
    var tbody = document.getElementById(id);
    if (!tbody) return;
    var controles = document.querySelectorAll('[data-filtra="' + id + '"]');
    var visibles = 0;
    Array.prototype.forEach.call(tbody.rows, function (tr) {
      if (tr.hasAttribute('data-oculta-rol')) return;
      var ok = true;
      controles.forEach(function (c) {
        var v = c.value;
        if (!v) return;
        var campo = c.getAttribute('data-campo');
        if (campo) {
          if ((tr.getAttribute('data-' + campo) || '') !== v) ok = false;
        } else if (normalizar(tr.textContent).indexOf(normalizar(v.trim())) === -1) {
          ok = false;
        }
      });
      tr.hidden = !ok;
      if (ok) visibles++;
    });
    document.querySelectorAll('[data-cuenta="' + id + '"]').forEach(function (el) {
      el.textContent = visibles + ' ' + (visibles === 1 ? el.getAttribute('data-singular') : el.getAttribute('data-plural'));
    });
    var tabla = tbody.closest('table');
    var vacio = document.querySelector('[data-vacio-de="' + id + '"]');
    if (vacio) {
      vacio.hidden = visibles !== 0;
      if (tabla) tabla.hidden = visibles === 0;
    }
    document.dispatchEvent(new CustomEvent('pahuichi:filtrado', { detail: { id: id, visibles: visibles } }));
  }
  window.Pahuichi.filtrarTabla = filtrarTabla;

  var tablasFiltrables = {};
  document.querySelectorAll('[data-filtra]').forEach(function (c) {
    var id = c.getAttribute('data-filtra');
    tablasFiltrables[id] = true;
    c.addEventListener(c.tagName === 'SELECT' ? 'change' : 'input', function () { filtrarTabla(id); });
  });
  document.querySelectorAll('[data-limpiar-filtros]').forEach(function (b) {
    b.addEventListener('click', function () {
      var id = b.getAttribute('data-limpiar-filtros');
      document.querySelectorAll('[data-filtra="' + id + '"]').forEach(function (c) { c.value = ''; });
      filtrarTabla(id);
    });
  });
  Object.keys(tablasFiltrables).forEach(filtrarTabla);

  /* ------------------------------------------------------------------
     Formularios desplegables: <button data-alternar="id"> muestra/oculta
     ------------------------------------------------------------------ */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-alternar]');
    if (!b) return;
    var destino = document.getElementById(b.getAttribute('data-alternar'));
    if (!destino) return;
    var abrir = destino.hidden;
    destino.hidden = !abrir;
    document.querySelectorAll('[data-alternar="' + destino.id + '"]').forEach(function (x) {
      x.setAttribute('aria-expanded', String(abrir));
    });
    if (abrir) {
      var primero = destino.querySelector('input:not([type=hidden]), select, textarea');
      if (primero) primero.focus();
    } else if (b.closest('#' + destino.id)) {
      var origen = document.querySelector('[data-alternar="' + destino.id + '"]:not(#' + destino.id + ' *)');
      if (origen) origen.focus();
    }
  });

  /* ------------------------------------------------------------------
     Modales (<dialog>): abrir y cerrar por atributo
     ------------------------------------------------------------------ */
  document.addEventListener('click', function (e) {
    var abrir = e.target.closest('[data-abrir-modal]');
    if (abrir) {
      var dialogo = document.getElementById(abrir.getAttribute('data-abrir-modal'));
      if (dialogo && dialogo.showModal) {
        dialogo.dispatchEvent(new CustomEvent('pahuichi:abrir', { detail: { origen: abrir } }));
        dialogo.showModal();
      }
      return;
    }
    var cerrar = e.target.closest('[data-cerrar-modal]');
    if (cerrar) {
      var d = cerrar.closest('dialog');
      if (d) d.close(cerrar.value || '');
    }
  });

  // Envíos que piden confirmación: <form data-confirmar="¿Seguro?"> o en el botón que envía
  document.addEventListener('submit', function (e) {
    var boton = e.submitter;
    var texto = (boton && boton.getAttribute('data-confirmar')) || (e.target.getAttribute && e.target.getAttribute('data-confirmar'));
    if (texto && !window.confirm(texto)) e.preventDefault();
  });

  // Selects que envían su formulario al cambiar (por ejemplo, el rol en la tabla de usuarios)
  document.addEventListener('change', function (e) {
    var sel = e.target.closest('[data-enviar-al-cambiar]');
    if (!sel || !sel.form) return;
    if (sel.form.requestSubmit) sel.form.requestSubmit(); else sel.form.submit();
  });
})();
