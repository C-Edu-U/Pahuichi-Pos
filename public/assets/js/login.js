/* Login (prototipo). En PHP: password_verify, session_regenerate_id,
   contador de intentos en la tabla usuarios y bloqueado_hasta = ahora + 15 min.
   Aquí el contador vive en sessionStorage solo para mostrar los estados. */
(function () {
  'use strict';

  var MAX_INTENTOS = 5;
  var CLAVE_INTENTOS = 'pahuichi.intentos';
  var CLAVE_BLOQUEO = 'pahuichi.bloqueoHasta';
  var USUARIOS = { cajero: 'cajero', analista: 'analista', admin: 'sysadmin' };

  var P = window.Pahuichi;
  var form = document.getElementById('form-acceso');
  var usuario = document.getElementById('usuario');
  var clave = document.getElementById('clave');
  var avisoError = document.getElementById('aviso-error');
  var avisoBloqueo = document.getElementById('aviso-bloqueo');
  var avisoSalida = document.getElementById('aviso-salida');
  var ingresar = document.getElementById('ingresar');

  function leer(k) { try { return window.sessionStorage.getItem(k); } catch (e) { return null; } }
  function guardar(k, v) { try { window.sessionStorage.setItem(k, v); } catch (e) { /* sin almacenamiento */ } }
  function borrar(k) { try { window.sessionStorage.removeItem(k); } catch (e) { /* sin almacenamiento */ } }

  function hora(ms) {
    var d = new Date(ms);
    return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
  }

  function mostrar(cual) {
    avisoError.hidden = cual !== 'error';
    avisoBloqueo.hidden = cual !== 'bloqueo';
    avisoSalida.hidden = cual !== 'salida';
  }

  function bloqueado() {
    var hasta = parseInt(leer(CLAVE_BLOQUEO), 10);
    if (hasta && hasta > Date.now()) return hasta;
    if (hasta) { borrar(CLAVE_BLOQUEO); borrar(CLAVE_INTENTOS); }
    return 0;
  }

  function aplicarBloqueo(hasta) {
    document.getElementById('hora-desbloqueo').textContent = hora(hasta);
    mostrar('bloqueo');
    ingresar.disabled = true;
    ingresar.textContent = 'Bloqueado hasta las ' + hora(hasta);
  }

  function marcar(campo, mensaje) {
    var control = document.getElementById(campo);
    var error = document.getElementById(campo + '-error');
    if (mensaje) {
      error.innerHTML = '<svg class="icono icono--sm" aria-hidden="true"><use href="#i-error"></use></svg><span></span>';
      error.querySelector('span').textContent = mensaje;
      error.hidden = false;
      control.setAttribute('aria-invalid', 'true');
    } else {
      error.hidden = true;
      control.removeAttribute('aria-invalid');
    }
  }

  // Estados que llegan por la URL (?estado=salida | error | bloqueado)
  var estado = P.parametro('estado');
  if (estado === 'salida') mostrar('salida');
  if (estado === 'error') mostrar('error');
  if (estado === 'bloqueado') aplicarBloqueo(Date.now() + 15 * 60 * 1000);

  var hasta = bloqueado();
  if (hasta) aplicarBloqueo(hasta);

  // Mostrar u ocultar la contraseña
  var ver = document.getElementById('ver-clave');
  ver.addEventListener('click', function () {
    var visible = clave.type === 'password';
    clave.type = visible ? 'text' : 'password';
    ver.setAttribute('aria-pressed', String(visible));
    ver.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
    clave.focus();
  });

  // Aviso de mayúsculas activadas
  var mayus = document.getElementById('clave-mayus');
  ['keydown', 'keyup'].forEach(function (t) {
    clave.addEventListener(t, function (e) {
      if (e.getModifierState) mayus.hidden = !e.getModifierState('CapsLock');
    });
  });
  clave.addEventListener('blur', function () { mayus.hidden = true; });

  usuario.addEventListener('input', function () { if (usuario.value.trim()) marcar('usuario', ''); });
  clave.addEventListener('input', function () { if (clave.value) marcar('clave', ''); });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (bloqueado()) return;

    var u = usuario.value.trim().toLowerCase();
    var faltaU = !u;
    var faltaC = !clave.value;
    marcar('usuario', faltaU ? 'Escribe tu usuario.' : '');
    marcar('clave', faltaC ? 'Escribe tu contraseña.' : '');
    if (faltaU || faltaC) {
      (faltaU ? usuario : clave).focus();
      return;
    }

    ingresar.setAttribute('aria-busy', 'true');
    ingresar.disabled = true;

    window.setTimeout(function () {
      ingresar.removeAttribute('aria-busy');
      ingresar.disabled = false;

      var rol = USUARIOS[u];
      if (rol) {
        borrar(CLAVE_INTENTOS);
        P.guardar(P.CLAVE_ROL, rol);
        window.location.href = 'panel/' + P.ROLES[rol].inicio;
        return;
      }

      var intentos = (parseInt(leer(CLAVE_INTENTOS), 10) || 0) + 1;
      guardar(CLAVE_INTENTOS, String(intentos));
      if (intentos >= MAX_INTENTOS) {
        var fin = Date.now() + 15 * 60 * 1000;
        guardar(CLAVE_BLOQUEO, String(fin));
        aplicarBloqueo(fin);
        return;
      }
      var quedan = MAX_INTENTOS - intentos;
      document.getElementById('aviso-error-texto').textContent = quedan === 1
        ? 'Te queda 1 intento. Al siguiente, la cuenta se bloquea 15 minutos.'
        : 'Te quedan ' + quedan + ' intentos. Al quinto fallo, la cuenta se bloquea 15 minutos.';
      mostrar('error');
      clave.value = '';
      clave.focus();
    }, 400);
  });
})();
