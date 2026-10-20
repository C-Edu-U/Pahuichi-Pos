/* Configuración: validación previa de los formularios (el servidor valida igual). */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };

  function validar(form, reglas, e) {
    var primero = null;
    Object.keys(reglas).forEach(function (id) {
      var mensaje = reglas[id]($(id).value.trim());
      P.marcarCampo(id, mensaje);
      if (mensaje && !primero) primero = id;
    });
    if (primero) { e.preventDefault(); $(primero).focus(); }
  }

  var negocio = $('negocio');
  if (negocio) {
    negocio.addEventListener('submit', function (e) {
      validar(negocio, {
        'n-razon': function (v) { return v ? '' : 'Escribe la razón social.'; },
        'n-comercial': function (v) { return v ? '' : 'Escribe el nombre comercial.'; },
        'n-nit': function (v) { return /^\d{7,13}$/.test(v.replace(/\s/g, '')) ? '' : 'El NIT tiene solo números, entre 7 y 13 dígitos.'; },
        'n-direccion': function (v) { return v ? '' : 'Escribe la dirección.'; },
        'n-municipio': function (v) { return v ? '' : 'Escribe el municipio.'; }
      }, e);
      var logo = $('n-logo').files[0];
      if (!e.defaultPrevented && logo && logo.size > 1048576) {
        P.marcarCampo('n-logo', 'La imagen pesa más de 1 MB. Elige una más liviana.');
        e.preventDefault();
        $('n-logo').focus();
      }
    });
  }

  var fiscal = $('fiscal');
  if (fiscal) {
    ['f-cuis', 'f-control'].forEach(function (id) {
      $(id).addEventListener('input', function () { this.value = this.value.toUpperCase(); });
    });
    fiscal.addEventListener('submit', function (e) {
      validar(fiscal, {
        'f-sucursal': function (v) { return /^\d{1,4}$/.test(v) ? '' : 'Escribe un número de 0 a 9999.'; },
        'f-pv': function (v) { return /^\d{1,4}$/.test(v) ? '' : 'Escribe un número de 0 a 9999.'; },
        'f-cuis': function (v) { return /^[A-Z0-9]{4,20}$/.test(v) ? '' : 'El CUIS tiene de 4 a 20 letras o números.'; },
        'f-control': function (v) { return /^[A-F0-9]{8,40}$/.test(v) ? '' : 'El código de control tiene de 8 a 40 caracteres hexadecimales (0-9 y A-F).'; },
        'f-vigencia': function (v) { return v ? '' : 'Elige la fecha y la hora de vigencia.'; },
        'f-inicial': function (v) { return /^[1-9]\d{0,8}$/.test(v) ? '' : 'El número inicial es un entero desde 1.'; }
      }, e);
    });
  }

  /* ------------------------------------------------------------------
     Turnos: tarjetas con nombre, horario, resumen en vivo y avisos claros.
     - El nombre y las horas se revisan al escribir: si algo no cierra, el
       error sale bajo el campo y como aviso flotante.
     - El resumen de arriba (cuántos turnos y de qué hora a qué hora) se
       rehace solo.
     - Si el borrado no tiene sentido por reglas del sistema, se explica
       con un aviso en vez de dejar el botón sin efecto.
     ------------------------------------------------------------------ */
  var seccionTurnos = document.querySelector('[data-turnos]');

  /** «HH:MM» a minutos. 23:59 en el fin es hasta medianoche (1440). */
  function aMinutos(hora, esFin) {
    if (!/^\d{2}:\d{2}$/.test(hora)) return null;
    var h = parseInt(hora.slice(0, 2), 10);
    var m = parseInt(hora.slice(3, 5), 10);
    var total = h * 60 + m;
    if (esFin && h === 23 && m === 59) total = 1440;
    return total;
  }

  var formularioTurnos = $('form-turnos');
  var resumenTurnos = seccionTurnos ? seccionTurnos.querySelector('[data-resumen-turnos]') : null;

  /** Las fichas de turno de la pantalla, con lo que el administrador escribió. */
  function tarjetas() {
    if (!seccionTurnos) return [];
    return Array.prototype.map.call(seccionTurnos.querySelectorAll('[data-turno]'), function (ficha) {
      var id = ficha.getAttribute('data-turno');
      var inicio = $('t' + id + '-ini');
      var fin = $('t' + id + '-fin');
      var nombre = $('t' + id + '-nombre');
      return {
        id: id,
        nombre: nombre ? nombre.value.trim() : (ficha.getAttribute('data-nombre') || ''),
        activo: ficha.getAttribute('data-activo') === '1',
        inicio: inicio ? inicio.value : '',
        fin: fin ? fin.value : '',
        ficha: ficha
      };
    });
  }

  /** Muestra el horario de cada turno y el resumen del día. */
  function pintar() {
    var lista = tarjetas();
    var desde = null;
    var hasta = null;

    lista.forEach(function (t) {
      var ini = aMinutos(t.inicio, false);
      var fin = aMinutos(t.fin, true);
      var horario = t.ficha.querySelector('[data-horario]');
      var valido = ini !== null && fin !== null && fin > ini;

      // El horario se escribe tal como está marcado en los campos
      if (horario) horario.textContent = valido ? t.inicio + ' a ' + t.fin : '—';
      if (valido) {
        if (desde === null || ini < desde.min) desde = { min: ini, texto: t.inicio };
        if (hasta === null || fin > hasta.min) hasta = { min: fin, texto: t.fin };
      }
      // El nombre escrito es el que se usa al confirmar el borrado
      t.ficha.setAttribute('data-nombre', t.nombre);
      var boton = t.ficha.querySelector('[data-eliminar-turno]');
      if (boton) boton.setAttribute('data-nombre-turno', t.nombre);
    });

    if (resumenTurnos) {
      resumenTurnos.textContent = lista.length === 0
        ? 'Todavía no hay turnos: agrega el primero para poder abrir caja.'
        : lista.length + (lista.length === 1 ? ' turno' : ' turnos')
          + ' · de ' + (desde ? desde.texto : '—') + ' a ' + (hasta ? hasta.texto : '—');
    }
  }

  /** Revisa los horarios escritos y devuelve los problemas con su campo. */
  function revisar() {
    var lista = tarjetas();
    var problemas = [];

    lista.forEach(function (t) {
      var ini = aMinutos(t.inicio, false);
      var fin = aMinutos(t.fin, true);
      var campoIni = 't' + t.id + '-ini';
      var campoFin = 't' + t.id + '-fin';
      var errorIni = '';
      var errorFin = '';

      if (t.inicio === '' || ini === null) {
        errorIni = 'Elige la hora de inicio.';
      }
      if (t.fin === '' || fin === null) {
        errorFin = 'Elige la hora de fin.';
      } else if (ini !== null && fin <= ini) {
        errorFin = 'El turno ' + t.nombre + ' debe terminar después de empezar.';
      }

      P.marcarCampo(campoIni, errorIni);
      P.marcarCampo(campoFin, errorFin);
      if (errorIni) problemas.push({ campo: campoIni, mensaje: errorIni });
      if (errorFin) problemas.push({ campo: campoFin, mensaje: errorFin });
    });

    // El nombre del turno se escribe: no puede quedar vacío ni repetirse
    var vistos = {};
    lista.forEach(function (t) {
      var campo = 't' + t.id + '-nombre';
      var error = '';
      if (t.nombre === '') {
        error = 'Escribe el nombre del turno.';
      } else if (t.nombre.length > 30) {
        error = 'El nombre tiene de 1 a 30 caracteres.';
      } else {
        var clave = t.nombre.toLowerCase();
        if (vistos[clave] !== undefined) {
          error = 'Ese nombre de turno ya existe.';
        } else {
          vistos[clave] = t.id;
        }
      }
      P.marcarCampo(campo, error);
      if (error) problemas.push({ campo: campo, mensaje: error });
    });

    // Los turnos no se cruzan: cada uno empieza cuando el anterior terminó
    var orden = lista.slice().sort(function (a, b) {
      return (aMinutos(a.inicio, false) || 0) - (aMinutos(b.inicio, false) || 0);
    });
    for (var i = 1; i < orden.length; i++) {
      var anterior = orden[i - 1];
      var actual = orden[i];
      var finAnterior = aMinutos(anterior.fin, true);
      var inicioActual = aMinutos(actual.inicio, false);
      if (finAnterior === null || inicioActual === null) continue;
      if (inicioActual < finAnterior) {
        var mensaje = 'Se cruza con el turno de ' + anterior.nombre + ', que termina a las ' + anterior.fin + '.';
        P.marcarCampo('t' + actual.id + '-ini', mensaje);
        problemas.push({ campo: 't' + actual.id + '-ini', mensaje: mensaje });
      }
    }

    return problemas;
  }

  if (seccionTurnos) {
    seccionTurnos.querySelectorAll('input[type="time"], input[data-nombre-campo]').forEach(function (campo) {
      campo.addEventListener('input', function () {
        pintar();
        revisar();
      });
      // Al salir del campo, si el horario no cierra, se avisa una vez
      campo.addEventListener('change', function () {
        var problemas = revisar();
        var mio = problemas.filter(function (p) { return p.campo === campo.id; });
        if (mio.length) P.aviso(mio[0].mensaje, 'error');
      });
    });

    if (formularioTurnos) {
      formularioTurnos.addEventListener('submit', function (e) {
        var problemas = revisar();
        if (!problemas.length) return;
        e.preventDefault();
        P.aviso(problemas[0].mensaje, 'error');
        var campo = document.getElementById(problemas[0].campo);
        if (campo) campo.focus();
      });
    }

    pintar();
  }

  /* Turnos: el modal de agregar turno */
  var modalTurno = $('modal-turno');
  if (modalTurno) {
    // Si el alta falló, el modal se abre solo para que se vea el error
    if (modalTurno.hasAttribute('data-modal-al-cargar') && modalTurno.showModal) {
      modalTurno.showModal();
    }
    // Al abrirlo, el foco va al nombre para escribir de una
    modalTurno.addEventListener('pahuichi:abrir', function () {
      var nombre = $('nuevo_nombre');
      if (nombre) { nombre.focus(); nombre.select(); }
    });

    var nuevoInicio = $('nuevo_inicio');
    var nuevoFin = $('nuevo_fin');

    function revisarNuevo() {
      if (!nuevoInicio || !nuevoFin) return '';
      var ini = aMinutos(nuevoInicio.value, false);
      var fin = aMinutos(nuevoFin.value, true);
      var error = '';
      if (nuevoFin.value !== '' && ini !== null && fin !== null && fin <= ini) {
        error = 'El turno debe terminar después de empezar.';
      }
      P.marcarCampo('nuevo_fin', error);
      return error;
    }

    [nuevoInicio, nuevoFin].forEach(function (campo) {
      if (campo) campo.addEventListener('input', revisarNuevo);
    });

    var formNuevo = modalTurno.querySelector('form');
    if (formNuevo) {
      formNuevo.addEventListener('submit', function (e) {
        var error = revisarNuevo();
        if (!error) return;
        e.preventDefault();
        P.aviso(error, 'error');
        if (nuevoFin) nuevoFin.focus();
      });
    }
  }

  /* Turnos: eliminar un turno (con confirmación y con el aviso cuando no se puede) */
  var modalEliminar = $('modal-eliminar-turno');
  if (modalEliminar) {
    document.querySelectorAll('[data-eliminar-turno]').forEach(function (boton) {
      boton.addEventListener('click', function () {
        var id = boton.getAttribute('data-eliminar-turno');
        var nombre = boton.getAttribute('data-nombre-turno');
        var lista = seccionTurnos ? tarjetas() : [];
        var turno = lista.filter(function (t) { return t.id === id; })[0];
        var activos = lista.filter(function (t) { return t.activo; });
        var motivo = '';

        if (turno && activos.length <= 1) {
          motivo = 'No se puede eliminar el único turno que queda.';
        }
        if (motivo) {
          P.marcarCampo('e' + id, motivo);
          P.aviso(motivo, 'error');
          return;
        }

        var campoId = $('turno-eliminar-id');
        var campoNombre = $('turno-eliminar-nombre');
        if (campoId) campoId.value = id;
        if (campoNombre) campoNombre.textContent = nombre;

        if (modalEliminar.showModal) {
          modalEliminar.showModal();
          return;
        }
        // Sin soporte de <dialog>: se borra derecho, con el mismo formulario
        var form = document.createElement('form');
        form.method = 'post';
        form.action = '/config/turnos';
        form.innerHTML = '<input type="hidden" name="_csrf" value="'
          + (document.body.getAttribute('data-csrf') || '') + '">'
          + '<input type="hidden" name="eliminar" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
      });
    });
  }

  window.PahuichiConfig = { validar: validar };
})();
