/* ==========================================================================
   Pahuichi POS · Panel (prototipo estático)
   - Permisos por rol: espejo de lo que será config/permisos.php + puede().
     Aquí solo se simula; en PHP el servidor decide y responde 403.
   - Riel lateral plegable, cajón en móvil.
   - Utilidades compartidas: filtros de tabla, formularios desplegables,
     modales, avisos y formato de montos.
   ========================================================================== */
(function () {
  'use strict';

  var CLAVE_ROL = 'pahuichi.rol';
  var CLAVE_PLEGADO = 'pahuichi.rielPlegado';

  var ROLES = {
    cajero: { etiqueta: 'Cajero', usuario: 'Mariela Suárez Añez', inicio: 'pos.html' },
    analista: { etiqueta: 'Analista', usuario: 'Rodrigo Justiniano Paz', inicio: 'reportes.html' },
    sysadmin: { etiqueta: 'Administrador', usuario: 'Ana Lucía Vaca Saucedo', inicio: 'escritorio.html' }
  };

  // Matriz de la sección 4 del HANDOFF, por módulo y por acción
  var TODOS = ['cajero', 'analista', 'sysadmin'];
  var PERMISOS = {
    'modulo.escritorio': TODOS,
    'modulo.pos': ['cajero', 'sysadmin'],
    'modulo.caja': TODOS,
    'modulo.ventas': TODOS,
    'modulo.clientes': TODOS,
    'modulo.productos': TODOS,
    'modulo.qr': TODOS,
    'modulo.reportes': ['analista', 'sysadmin'],
    'modulo.usuarios': ['sysadmin'],
    'modulo.config': ['sysadmin'],
    'modulo.bitacora': ['analista', 'sysadmin'],
    'caja.operar': ['cajero', 'sysadmin'],
    'ventas.todas': ['analista', 'sysadmin'],
    'ventas.anular': ['sysadmin'],
    'clientes.crear': ['cajero', 'sysadmin'],
    'clientes.editar': ['sysadmin'],
    'productos.editar': ['sysadmin'],
    'productos.agotado': ['cajero', 'sysadmin'],
    'qr.subir': ['sysadmin'],
    'qr.historial': ['analista', 'sysadmin'],
    'reportes.exportar': ['analista', 'sysadmin']
  };

  var MODULOS = {
    escritorio: 'Escritorio',
    pos: 'Punto de venta',
    caja: 'Caja',
    ventas: 'Ventas y facturas',
    clientes: 'Clientes',
    productos: 'Productos y categorías',
    qr: 'Pagos QR',
    reportes: 'Reportes',
    usuarios: 'Usuarios',
    config: 'Configuración',
    bitacora: 'Bitácora'
  };

  function leer(clave) {
    try { return window.localStorage.getItem(clave); } catch (e) { return null; }
  }

  function guardar(clave, valor) {
    try { window.localStorage.setItem(clave, valor); } catch (e) { /* almacenamiento bloqueado */ }
  }

  function rolActual() {
    var rol = leer(CLAVE_ROL);
    return ROLES[rol] ? rol : 'sysadmin';
  }

  function puede(permiso, rol) {
    var roles = PERMISOS[permiso];
    return !!roles && roles.indexOf(rol || rolActual()) !== -1;
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

  function leerMonto(texto) {
    var limpio = String(texto).trim().replace(/\s/g, '').replace(/\./g, '').replace(',', '.');
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

  // Error junto al campo
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

  // Solo prototipo: ?rol=cajero fija el rol de demostración (útil para QA)
  if (ROLES[parametro('rol')]) guardar(CLAVE_ROL, parametro('rol'));

  window.Pahuichi = {
    puede: puede, rolActual: rolActual, ROLES: ROLES, MODULOS: MODULOS,
    parametro: parametro, guardar: guardar, CLAVE_ROL: CLAVE_ROL,
    bs: bs, leerMonto: leerMonto, normalizar: normalizar, aviso: aviso, marcarCampo: marcarCampo
  };

  var cuerpo = document.body;
  if (!cuerpo.classList.contains('panel')) return;

  var moduloPagina = cuerpo.getAttribute('data-modulo');
  var es403 = cuerpo.getAttribute('data-pagina') === '403';

  // Guardia de permiso (en PHP: middleware Rol + 403 + bitácora)
  if (!es403 && !puede('modulo.' + moduloPagina)) {
    window.location.replace('403.html?m=' + encodeURIComponent(moduloPagina));
    return;
  }

  /* ------------------------------------------------------------------
     Aplicar rol: menú, acciones y bloques por rol
     ------------------------------------------------------------------ */
  function aplicarRol() {
    var rol = rolActual();
    var datos = ROLES[rol];

    document.querySelectorAll('[data-rol-nombre]').forEach(function (el) { el.textContent = datos.usuario; });
    document.querySelectorAll('[data-rol-etiqueta]').forEach(function (el) { el.textContent = datos.etiqueta; });

    document.querySelectorAll('.menu__item[data-modulo]').forEach(function (li) {
      li.hidden = !puede('modulo.' + li.getAttribute('data-modulo'), rol);
    });

    // Separadores: solo entre grupos con algo visible a ambos lados
    document.querySelectorAll('.menu__separador').forEach(function (sep) {
      var antes = false;
      var despues = false;
      var el = sep.previousElementSibling;
      while (el && !el.classList.contains('menu__separador')) { if (!el.hidden) antes = true; el = el.previousElementSibling; }
      el = sep.nextElementSibling;
      while (el && !el.classList.contains('menu__separador')) { if (!el.hidden) despues = true; el = el.nextElementSibling; }
      sep.hidden = !(antes && despues);
    });

    document.querySelectorAll('[data-requiere]').forEach(function (el) {
      el.hidden = !puede(el.getAttribute('data-requiere'), rol);
    });

    document.querySelectorAll('[data-sin-permiso]').forEach(function (el) {
      el.hidden = puede(el.getAttribute('data-sin-permiso'), rol);
    });

    document.querySelectorAll('[data-solo-rol]').forEach(function (el) {
      el.hidden = el.getAttribute('data-solo-rol').split(' ').indexOf(rol) === -1;
    });

    var selector = document.getElementById('demo-rol');
    if (selector) selector.value = rol;

    document.dispatchEvent(new CustomEvent('pahuichi:rol', { detail: { rol: rol } }));
  }

  aplicarRol();

  /* ------------------------------------------------------------------
     QR vencido (HU-14): el aviso del menú cambia y el administrador
     solo puede subir uno nuevo o cerrar sesión
     ------------------------------------------------------------------ */
  function qrVencido() {
    try { return window.localStorage.getItem('pahuichi.qrVencido') === '1'; } catch (e) { return false; }
  }

  function guardiaQr() {
    var aviso = document.querySelector('.menu__item[data-modulo="qr"] .menu__aviso');
    if (aviso && qrVencido()) {
      aviso.classList.add('menu__aviso--vencido');
      aviso.innerHTML = '<span class="visualmente-oculto">QR </span>Vencido';
    }
    if (qrVencido() && rolActual() === 'sysadmin' && moduloPagina !== 'qr' && !es403) {
      window.location.replace('qr.html');
      return true;
    }
    return false;
  }

  if (guardiaQr()) return;

  // Página 403: nombra el módulo y el rol
  if (es403) {
    var m = parametro('m');
    var rol403 = rolActual();
    var nombre = document.getElementById('denegado-modulo');
    if (nombre && MODULOS[m]) nombre.textContent = MODULOS[m];
    var etiqueta = document.getElementById('denegado-rol');
    if (etiqueta) etiqueta.textContent = ROLES[rol403].etiqueta;
    var volver = document.getElementById('denegado-volver');
    if (volver) volver.href = ROLES[rol403].inicio;
  }

  var selectorRol = document.getElementById('demo-rol');
  if (selectorRol) {
    selectorRol.addEventListener('change', function () {
      guardar(CLAVE_ROL, selectorRol.value);
      if (es403) { window.location.replace(ROLES[selectorRol.value].inicio); return; }
      if (!puede('modulo.' + moduloPagina, selectorRol.value)) {
        window.location.replace('403.html?m=' + encodeURIComponent(moduloPagina));
        return;
      }
      aplicarRol();
      guardiaQr();
    });
  }

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

  // Cerrar sesión (en PHP: POST con CSRF a /salir)
  document.querySelectorAll('[data-salir]').forEach(function (b) {
    b.addEventListener('click', function () { window.location.href = '../login.html?estado=salida'; });
  });
})();
