/* Usuarios (prototipo): alta, activar/desactivar, desbloquear y resetear clave. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var ETIQUETAS = { cajero: 'Cajero', analista: 'Analista', sysadmin: 'Administrador' };
  var PALABRAS = ['cunape', 'majadito', 'somo', 'locro', 'keperi', 'masaco', 'sonso', 'tari'];

  function claveTemporal() {
    var p = PALABRAS[Math.floor(Math.random() * PALABRAS.length)];
    return p + '-' + String(Math.floor(1000 + Math.random() * 9000));
  }

  // Activar / desactivar y desbloquear
  $('filas-usuarios').addEventListener('click', function (e) {
    var b = e.target.closest('[data-activo], [data-desbloquear]');
    if (!b) return;
    var tr = b.closest('tr');
    var nombre = tr.getAttribute('data-nombre');
    var celda = tr.querySelector('.celda-estado');
    if (b.hasAttribute('data-desbloquear')) {
      celda.innerHTML = '<span class="insignia insignia--ok">Activo</span>';
      b.setAttribute('data-activo', 'true');
      b.removeAttribute('data-desbloquear');
      b.textContent = 'Desactivar';
      P.aviso(nombre + ' puede volver a ingresar.');
      return;
    }
    var activo = b.getAttribute('data-activo') === 'true';
    celda.innerHTML = activo ? '<span class="insignia insignia--neutra">Inactivo</span>' : '<span class="insignia insignia--ok">Activo</span>';
    b.setAttribute('data-activo', String(!activo));
    b.textContent = activo ? 'Activar' : 'Desactivar';
    P.aviso(nombre + (activo ? ' ya no puede ingresar.' : ' puede ingresar de nuevo.'));
  });

  // Resetear clave
  var modal = $('modal-clave');
  var nombreActual = '';
  modal.addEventListener('pahuichi:abrir', function (e) {
    nombreActual = e.detail.origen.closest('tr').getAttribute('data-nombre');
    $('clave-titulo').textContent = '¿Resetear la clave de ' + nombreActual + '?';
    ['clave-paso', 'clave-acciones'].forEach(function (id) { $(id).hidden = false; });
    ['clave-lista', 'clave-cerrar'].forEach(function (id) { $(id).hidden = true; });
  });
  $('generar-clave').addEventListener('click', function () {
    $('clave-temporal').textContent = claveTemporal();
    ['clave-paso', 'clave-acciones'].forEach(function (id) { $(id).hidden = true; });
    ['clave-lista', 'clave-cerrar'].forEach(function (id) { $(id).hidden = false; });
    $('clave-cerrar').querySelector('button').focus();
  });

  // Alta
  $('form-usuario').addEventListener('submit', function (e) {
    e.preventDefault();
    var nombre = $('u-nombre').value.trim();
    var usuario = $('u-usuario').value.trim();
    var existe = Array.prototype.some.call(document.querySelectorAll('#filas-usuarios .codigo'), function (c) { return c.textContent === usuario; });
    var errores = {
      'u-nombre': nombre ? '' : 'Escribe el nombre completo.',
      'u-usuario': !usuario ? 'Escribe el usuario.' : !/^[a-z0-9]{3,20}$/.test(usuario) ? 'Usa de 3 a 20 minúsculas o números, sin espacios.' : existe ? 'Ese usuario ya existe.' : ''
    };
    var primero = null;
    Object.keys(errores).forEach(function (id) {
      P.marcarCampo(id, errores[id]);
      if (errores[id] && !primero) primero = id;
    });
    if (primero) { $(primero).focus(); return; }

    var rol = $('u-rol').value;
    var tr = document.createElement('tr');
    tr.className = 'fila-nueva';
    tr.setAttribute('data-rol', rol);
    tr.setAttribute('data-nombre', nombre);
    tr.innerHTML = '<td><span class="tabla__principal"></span><span class="tabla__secundario codigo"></span></td>' +
      '<td><span class="insignia insignia--neutra"></span></td>' +
      '<td class="celda-estado"><span class="insignia insignia--ok">Activo</span></td><td>Nunca</td>' +
      '<td class="col-accion"><div class="acciones-fila"><button class="boton" type="button" data-abrir-modal="modal-clave">Resetear clave</button><button class="boton" type="button" data-activo="true">Desactivar</button></div></td>';
    tr.querySelector('.tabla__principal').textContent = nombre;
    tr.querySelector('.codigo').textContent = usuario;
    tr.querySelector('.insignia').textContent = ETIQUETAS[rol];
    $('filas-usuarios').appendChild(tr);
    this.reset();
    this.hidden = true;
    document.querySelector('[data-alternar="form-usuario"]').setAttribute('aria-expanded', 'false');
    P.filtrarTabla('filas-usuarios');
    // La clave temporal se muestra una sola vez, en el modal, hasta que se cierre
    $('clave-temporal').textContent = claveTemporal();
    ['clave-paso', 'clave-acciones'].forEach(function (id) { $(id).hidden = true; });
    ['clave-lista', 'clave-cerrar'].forEach(function (id) { $(id).hidden = false; });
    modal.showModal();
    $('clave-cerrar').querySelector('button').focus();
    P.aviso('Usuario ' + usuario + ' creado.');
  });
})();
