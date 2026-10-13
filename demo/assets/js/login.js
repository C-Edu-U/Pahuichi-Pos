/* Inicio de sesión (demo). Con servidor: password_verify / PIN con bcrypt,
   session_regenerate_id, intentos en la tabla usuarios y bloqueado_hasta =
   ahora + 15 min, con registro en la bitácora. Aquí el contador vive en
   sessionStorage solo para mostrar los estados. */
(function () {
  'use strict';

  var MAX_INTENTOS = 5;
  var PINES = { '4821': 'cajero', '7350': 'cajero' };
  var USUARIOS = { admin: 'administrador', luis: 'mesero', teresa: 'cocina' };

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };

  function leerS(k) { try { return window.sessionStorage.getItem(k); } catch (e) { return null; } }
  function guardarS(k, v) { try { window.sessionStorage.setItem(k, v); } catch (e) { /* sin almacenamiento */ } }
  function borrarS(k) { try { window.sessionStorage.removeItem(k); } catch (e) { /* sin almacenamiento */ } }

  var modo = 'pin';

  function hora(ms) {
    var d = new Date(ms);
    return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
  }

  function mostrar(cual) {
    ['error', 'bloqueo', 'cocina', 'salida'].forEach(function (n) { $('aviso-' + n).hidden = n !== cual; });
  }

  function claveBloqueo() { return 'pahuichi.bloqueo.' + modo; }
  function claveIntentos() { return 'pahuichi.intentos.' + modo; }

  function bloqueado() {
    var hasta = parseInt(leerS(claveBloqueo()), 10);
    if (hasta && hasta > Date.now()) return hasta;
    if (hasta) { borrarS(claveBloqueo()); borrarS(claveIntentos()); }
    return 0;
  }

  function aplicarBloqueo(hasta) {
    $('hora-desbloqueo').textContent = hora(hasta);
    mostrar('bloqueo');
    $('pin-entrar').disabled = true;
    $('ingresar').disabled = true;
    document.querySelectorAll('.pin__tecla').forEach(function (b) { b.disabled = true; });
  }

  function quitarBloqueo() {
    $('ingresar').disabled = false;
    document.querySelectorAll('.pin__tecla').forEach(function (b) { b.disabled = false; });
    pintarPin();
  }

  // Fallo: cuenta el intento; al quinto bloquea 15 minutos
  function fallo(titulo) {
    var intentos = (parseInt(leerS(claveIntentos()), 10) || 0) + 1;
    guardarS(claveIntentos(), String(intentos));
    if (intentos >= MAX_INTENTOS) {
      var fin = Date.now() + 15 * 60 * 1000;
      guardarS(claveBloqueo(), String(fin));
      aplicarBloqueo(fin);
      return;
    }
    var quedan = MAX_INTENTOS - intentos;
    $('aviso-error-titulo').textContent = titulo;
    $('aviso-error-texto').textContent = quedan === 1
      ? 'Te queda 1 intento. Al siguiente, la cuenta se bloquea 15 minutos.'
      : 'Te quedan ' + quedan + ' intentos. Al quinto fallo, la cuenta se bloquea 15 minutos.';
    mostrar('error');
  }

  function entrar(rol) {
    borrarS(claveIntentos());
    P.guardar(P.CLAVE_ROL, rol);
    window.location.href = 'panel/' + P.ROLES[rol].inicio;
  }

  /* Cambio de modo */
  document.querySelectorAll('[data-modo]').forEach(function (b) {
    b.addEventListener('click', function () {
      modo = b.getAttribute('data-modo');
      document.querySelectorAll('[data-modo]').forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
      $('form-pin').hidden = modo !== 'pin';
      $('form-clave').hidden = modo !== 'clave';
      document.querySelector('.acceso__demo-pin').hidden = modo !== 'pin';
      document.querySelector('.acceso__demo-clave').hidden = modo !== 'clave';
      mostrar('');
      quitarBloqueo();
      var hasta = bloqueado();
      if (hasta) aplicarBloqueo(hasta);
      (modo === 'pin' ? $('pin-entrada') : $('usuario')).focus();
    });
  });

  /* PIN: teclado en pantalla y teclado físico sobre el mismo campo */
  var entrada = $('pin-entrada');

  function pintarPin() {
    var n = entrada.value.length;
    document.querySelectorAll('.pin__punto').forEach(function (p, i) { p.classList.toggle('esta-lleno', i < n); });
    $('pin-cuenta').textContent = n === 1 ? '1 dígito' : n + ' dígitos';
    $('pin-entrar').disabled = n < 4 || !!bloqueado();
  }

  document.querySelectorAll('[data-tecla]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = b.getAttribute('data-tecla');
      if (t === 'borrar') entrada.value = entrada.value.slice(0, -1);
      else if (entrada.value.length < 6) entrada.value += t;
      pintarPin();
    });
  });

  entrada.addEventListener('input', function () {
    entrada.value = entrada.value.replace(/\D/g, '').slice(0, 6);
    pintarPin();
  });

  // En la PC de caja el foco vuelve siempre al PIN para teclear directo
  document.addEventListener('keydown', function (e) {
    if (modo !== 'pin' || document.activeElement === entrada) return;
    if (/^\d$/.test(e.key) && !e.target.closest('input, select, textarea')) {
      e.preventDefault();
      if (entrada.value.length < 6) entrada.value += e.key;
      pintarPin();
      entrada.focus();
    }
  });

  $('form-pin').addEventListener('submit', function (e) {
    e.preventDefault();
    if (bloqueado() || entrada.value.length < 4) return;
    var rol = PINES[entrada.value];
    entrada.value = '';
    pintarPin();
    if (rol) { entrar(rol); return; }
    fallo('PIN incorrecto');
    $('form-pin').classList.remove('pin--error');
    void $('form-pin').offsetWidth;
    $('form-pin').classList.add('pin--error');
    entrada.focus();
  });

  /* Usuario y contraseña */
  var ver = $('ver-clave');
  var clave = $('clave');
  ver.addEventListener('click', function () {
    var visible = clave.type === 'password';
    clave.type = visible ? 'text' : 'password';
    ver.setAttribute('aria-pressed', String(visible));
    ver.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
    clave.focus();
  });

  ['keydown', 'keyup'].forEach(function (t) {
    clave.addEventListener(t, function (e) {
      if (e.getModifierState) $('clave-mayus').hidden = !e.getModifierState('CapsLock');
    });
  });

  $('form-clave').addEventListener('submit', function (e) {
    e.preventDefault();
    if (bloqueado()) return;
    var u = $('usuario').value.trim().toLowerCase();
    P.marcarCampo('usuario', u ? '' : 'Escribe tu usuario.');
    P.marcarCampo('clave', clave.value ? '' : 'Escribe tu contraseña.');
    if (!u || !clave.value) { (!u ? $('usuario') : clave).focus(); return; }
    var rol = USUARIOS[u];
    if (rol === 'cocina') { mostrar('cocina'); return; }
    if (rol) { entrar(rol); return; }
    fallo('Usuario o contraseña incorrectos');
    clave.value = '';
    clave.focus();
  });

  /* Estados que llegan por la URL */
  var estado = P.parametro('estado');
  if (estado === 'salida') mostrar('salida');
  if (estado === 'inactividad') {
    $('aviso-salida-titulo').textContent = 'Sesión cerrada por inactividad';
    $('aviso-salida-texto').textContent = 'Pasaron 15 minutos sin actividad. Ingresa de nuevo para continuar.';
    mostrar('salida');
  }

  var hasta = bloqueado();
  if (hasta) aplicarBloqueo(hasta);
  pintarPin();
  entrada.focus();
})();
