/* Inicio de sesión. El servidor valida el PIN y la contraseña, cuenta los
   intentos y bloquea; aquí solo el teclado del PIN, el cambio de modo, mostrar
   la contraseña y el aviso de mayúsculas. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var formPin = $('form-pin');
  var formClave = $('form-clave');
  var entrada = $('pin-entrada');
  var modo = formPin.hidden ? 'clave' : 'pin';

  function bloqueado(form) { return form.hasAttribute('data-bloqueado'); }

  /* Cambio de modo: los avisos de un modo no se muestran en el otro */
  document.querySelectorAll('[data-modo]').forEach(function (b) {
    b.addEventListener('click', function () {
      modo = b.getAttribute('data-modo');
      document.querySelectorAll('[data-modo]').forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
      formPin.hidden = modo !== 'pin';
      formClave.hidden = modo !== 'clave';
      document.querySelectorAll('[data-de-modo]').forEach(function (a) { a.hidden = a.getAttribute('data-de-modo') !== modo; });
      var salida = $('aviso-salida');
      if (salida) salida.hidden = true;
      var demoPin = document.querySelector('.acceso__demo-pin');
      var demoClave = document.querySelector('.acceso__demo-clave');
      if (demoPin) demoPin.hidden = modo !== 'pin';
      if (demoClave) demoClave.hidden = modo !== 'clave';
      (modo === 'pin' ? entrada : $('usuario')).focus();
    });
  });

  /* PIN: teclado en pantalla y teclado físico sobre el mismo campo */
  function pintarPin() {
    var n = entrada.value.length;
    document.querySelectorAll('.pin__punto').forEach(function (p, i) { p.classList.toggle('esta-lleno', i < n); });
    $('pin-cuenta').textContent = n === 1 ? '1 dígito' : n + ' dígitos';
    $('pin-entrar').disabled = n < 4 || bloqueado(formPin);
  }

  if (bloqueado(formPin)) {
    document.querySelectorAll('.pin__tecla').forEach(function (b) { b.disabled = true; });
    entrada.disabled = true;
  }
  if (bloqueado(formClave)) $('ingresar').disabled = true;

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
    if (modo !== 'pin' || bloqueado(formPin) || document.activeElement === entrada) return;
    if (/^\d$/.test(e.key) && !e.target.closest('input, select, textarea')) {
      e.preventDefault();
      if (entrada.value.length < 6) entrada.value += e.key;
      pintarPin();
      entrada.focus();
    }
  });

  formPin.addEventListener('submit', function (e) {
    if (bloqueado(formPin) || entrada.value.length < 4) { e.preventDefault(); return; }
    $('pin-entrar').setAttribute('aria-busy', 'true');
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

  formClave.addEventListener('submit', function (e) {
    var P = window.Pahuichi;
    var u = $('usuario').value.trim();
    P.marcarCampo('usuario', u ? '' : 'Escribe tu usuario.');
    P.marcarCampo('clave', clave.value ? '' : 'Escribe tu contraseña.');
    if (!u || !clave.value || bloqueado(formClave)) {
      e.preventDefault();
      (!u ? $('usuario') : clave).focus();
      return;
    }
    $('ingresar').setAttribute('aria-busy', 'true');
  });

  pintarPin();
  if (modo === 'pin') { if (!entrada.disabled) entrada.focus(); }
  else if ($('usuario').value) clave.focus();
  else $('usuario').focus();
})();
