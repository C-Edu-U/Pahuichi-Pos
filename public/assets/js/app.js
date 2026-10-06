/* ==========================================================================
   Pahuichi POS · Panel (prototipo estático)
   - Permisos por rol: espejo de lo que será config/permisos.php + puede().
     Aquí solo se simula; en PHP el servidor decide y responde 403.
   - Riel lateral: plegado, cajón en móvil y la cinta del módulo activo.
   ========================================================================== */
(function () {
  'use strict';

  var CLAVE_ROL = 'pahuichi.rol';
  var CLAVE_PLEGADO = 'pahuichi.rielPlegado';
  var CLAVE_CINTA = 'pahuichi.cintaY';

  var ROLES = {
    cajero: { etiqueta: 'Cajero', usuario: 'Mariela Suárez Añez', inicio: 'proximamente.html?m=pos' },
    analista: { etiqueta: 'Analista', usuario: 'Rodrigo Justiniano Paz', inicio: 'proximamente.html?m=reportes' },
    sysadmin: { etiqueta: 'Administrador', usuario: 'Ana Lucía Vaca Saucedo', inicio: 'escritorio.html' }
  };

  // Matriz de la sección 4 del HANDOFF
  var PERMISOS = {
    'modulo.escritorio': ['cajero', 'analista', 'sysadmin'],
    'modulo.pos': ['cajero', 'sysadmin'],
    'modulo.caja': ['cajero', 'analista', 'sysadmin'],
    'modulo.ventas': ['cajero', 'analista', 'sysadmin'],
    'modulo.clientes': ['cajero', 'analista', 'sysadmin'],
    'modulo.productos': ['cajero', 'analista', 'sysadmin'],
    'modulo.qr': ['cajero', 'analista', 'sysadmin'],
    'modulo.reportes': ['analista', 'sysadmin'],
    'modulo.usuarios': ['sysadmin'],
    'modulo.config': ['sysadmin'],
    'modulo.bitacora': ['analista', 'sysadmin'],
    'productos.editar': ['sysadmin'],
    'productos.agotado': ['cajero', 'sysadmin']
  };

  // Módulos que aún no existen: qué historia y qué sprint los trae
  var MODULOS = {
    escritorio: { nombre: 'Escritorio' },
    pos: { nombre: 'Punto de venta', hu: 'HU-09', sprint: '2', texto: 'Grilla de productos por categoría, búsqueda y carrito con el total siempre visible.' },
    caja: { nombre: 'Caja', hu: 'HU-22', sprint: '3', texto: 'Apertura y cierre del turno con arqueo. Sin turno abierto no se podrá vender.' },
    ventas: { nombre: 'Ventas y facturas', hu: 'HU-17 y HU-24', sprint: '2 y 3', texto: 'Listado de ventas, reimpresión de facturas y anulación con motivo.' },
    clientes: { nombre: 'Clientes', hu: 'HU-16', sprint: '2', texto: 'Búsqueda y registro de clientes por NIT o CI, con complemento y casos especiales.' },
    productos: { nombre: 'Productos y categorías' },
    qr: { nombre: 'Pagos QR', hu: 'HU-13 y HU-14', sprint: '2', texto: 'Subida del QR de cobro con vigencia de 24 horas, vencimiento automático e historial.' },
    reportes: { nombre: 'Reportes', hu: 'HU-19 y HU-23', sprint: '3', texto: 'Ventas del día, platos más vendidos, ventas por hora y exportación a CSV.' },
    usuarios: { nombre: 'Usuarios', hu: 'HU-11', sprint: '2', texto: 'Alta de usuarios, asignación de rol, activación y reseteo de clave.' },
    config: { nombre: 'Configuración', hu: 'HU-18', sprint: '2', texto: 'Datos fiscales de la empresa, leyendas y catálogos del SIN.' },
    bitacora: { nombre: 'Bitácora', hu: 'HU-20', sprint: '3', texto: 'Registro de quién hizo qué y cuándo: ventas, anulaciones, QR, usuarios y configuración.' }
  };

  function leer(clave) {
    try { return window.localStorage.getItem(clave); } catch (e) { return null; }
  }

  function guardar(clave, valor) {
    try { window.localStorage.setItem(clave, valor); } catch (e) { /* almacenamiento bloqueado: se ignora */ }
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

  // Solo prototipo: ?rol=cajero fija el rol de demostración (útil para QA)
  if (ROLES[parametro('rol')]) guardar(CLAVE_ROL, parametro('rol'));

  // Exponer lo mínimo para las otras pantallas del prototipo
  window.Pahuichi = { puede: puede, rolActual: rolActual, ROLES: ROLES, MODULOS: MODULOS, parametro: parametro, guardar: guardar, CLAVE_ROL: CLAVE_ROL };

  var cuerpo = document.body;
  if (!cuerpo.classList.contains('panel')) return;

  /* ------------------------------------------------------------------
     Módulo de la página: el de body[data-modulo] o el de ?m= en las
     páginas genéricas (próximamente, 403)
     ------------------------------------------------------------------ */
  var moduloPagina = cuerpo.getAttribute('data-modulo');
  if (moduloPagina === 'generico') {
    moduloPagina = MODULOS[parametro('m')] ? parametro('m') : 'escritorio';
  }

  // Guardia de permiso (en PHP: middleware Rol + 403 + bitácora)
  if (cuerpo.getAttribute('data-pagina') !== '403' && !puede('modulo.' + moduloPagina)) {
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

    // Ítems del menú
    var items = document.querySelectorAll('.menu__item[data-modulo]');
    items.forEach(function (li) {
      li.hidden = !puede('modulo.' + li.getAttribute('data-modulo'), rol);
    });

    // Separadores: solo entre grupos que tengan algo visible a ambos lados
    document.querySelectorAll('.menu__separador').forEach(function (sep) {
      var antes = false;
      var despues = false;
      var el = sep.previousElementSibling;
      while (el && !el.classList.contains('menu__separador')) { if (!el.hidden) antes = true; el = el.previousElementSibling; }
      el = sep.nextElementSibling;
      while (el && !el.classList.contains('menu__separador')) { if (!el.hidden) despues = true; el = el.nextElementSibling; }
      sep.hidden = !(antes && despues);
    });

    // Acciones que requieren un permiso
    document.querySelectorAll('[data-requiere]').forEach(function (el) {
      el.hidden = !puede(el.getAttribute('data-requiere'), rol);
    });

    // Bloques que solo ve un rol
    document.querySelectorAll('[data-solo-rol]').forEach(function (el) {
      el.hidden = el.getAttribute('data-solo-rol').split(' ').indexOf(rol) === -1;
    });

    var selector = document.getElementById('demo-rol');
    if (selector) selector.value = rol;

    document.dispatchEvent(new CustomEvent('pahuichi:rol', { detail: { rol: rol } }));
  }

  // Marcar el módulo activo en el menú
  document.querySelectorAll('.menu__enlace').forEach(function (a) {
    var li = a.closest('.menu__item');
    if (li && li.getAttribute('data-modulo') === moduloPagina && cuerpo.getAttribute('data-pagina') !== '403') {
      a.setAttribute('aria-current', 'page');
    } else {
      a.removeAttribute('aria-current');
    }
  });

  aplicarRol();

  var selectorRol = document.getElementById('demo-rol');
  if (selectorRol) {
    selectorRol.addEventListener('change', function () {
      guardar(CLAVE_ROL, selectorRol.value);
      if (!puede('modulo.' + moduloPagina, selectorRol.value)) {
        window.location.replace('403.html?m=' + encodeURIComponent(moduloPagina));
        return;
      }
      if (cuerpo.getAttribute('data-pagina') === '403') {
        window.location.replace(ROLES[selectorRol.value].inicio);
        return;
      }
      aplicarRol();
      colocarCinta(false);
    });
  }

  /* ------------------------------------------------------------------
     Riel: expandido en escritorio salvo que el usuario lo pliegue
     ------------------------------------------------------------------ */
  var escritorio = window.matchMedia('(min-width: 1200px)');
  var tableta = window.matchMedia('(min-width: 768px)');
  var botonPlegar = document.querySelector('[data-plegar-riel]');

  function actualizarRiel() {
    var plegado = leer(CLAVE_PLEGADO) === '1';
    cuerpo.classList.toggle('riel-plegado', plegado);
    cuerpo.classList.toggle('riel-expandido', !tableta.matches || (escritorio.matches && !plegado));
    if (botonPlegar) {
      botonPlegar.setAttribute('aria-expanded', String(!plegado));
      var texto = botonPlegar.querySelector('.menu__texto');
      if (texto) texto.textContent = plegado ? 'Desplegar menú' : 'Plegar menú';
    }
  }

  actualizarRiel();
  escritorio.addEventListener('change', function () { actualizarRiel(); colocarCinta(false); });
  tableta.addEventListener('change', function () { actualizarRiel(); cerrarCajon(); colocarCinta(false); });

  if (botonPlegar) {
    botonPlegar.addEventListener('click', function () {
      guardar(CLAVE_PLEGADO, leer(CLAVE_PLEGADO) === '1' ? '0' : '1');
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
    colocarCinta(false);
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
    if (e.key === 'Escape' && riel.classList.contains('esta-abierto')) cerrarCajon();
    // Mantener el foco dentro del cajón abierto
    if (e.key === 'Tab' && riel.classList.contains('esta-abierto')) {
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
     La cinta: una sola pieza blanca que viaja al módulo activo.
     Parte de donde estaba en la página anterior y se desliza.
     ------------------------------------------------------------------ */
  var lista = document.querySelector('.menu__lista');
  var cinta = null;

  function colocarCinta(animar) {
    if (!cinta) return;
    var activo = lista.querySelector('.menu__enlace[aria-current="page"]');
    var item = activo && activo.closest('.menu__item');
    if (!item || item.hidden) { cinta.hidden = true; return; }
    cinta.hidden = false;
    var y = item.offsetTop;
    if (!animar) cinta.classList.add('sin-transicion');
    cinta.style.setProperty('--cinta-y', y + 'px');
    if (!animar) {
      void cinta.offsetWidth;
      cinta.classList.remove('sin-transicion');
    }
    try { window.sessionStorage.setItem(CLAVE_CINTA, String(y)); } catch (e) { /* sin almacenamiento */ }
  }

  if (lista) {
    cinta = document.createElement('span');
    cinta.className = 'menu__cinta';
    cinta.setAttribute('aria-hidden', 'true');
    lista.insertBefore(cinta, lista.firstChild);
    lista.closest('.menu').classList.add('menu--cinta');

    var anterior = null;
    try { anterior = window.sessionStorage.getItem(CLAVE_CINTA); } catch (e) { anterior = null; }
    if (anterior !== null) {
      cinta.classList.add('sin-transicion');
      cinta.style.setProperty('--cinta-y', anterior + 'px');
      void cinta.offsetWidth;
      cinta.classList.remove('sin-transicion');
      requestAnimationFrame(function () { colocarCinta(true); });
    } else {
      colocarCinta(false);
    }
  }

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
