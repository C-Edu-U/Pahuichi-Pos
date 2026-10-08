/* Usuarios (demo): alta con contraseña y PIN temporales, cambio de rol,
   activar/desactivar, desbloquear y reseteo de contraseña o PIN. */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var $ = function (id) { return document.getElementById(id); };
  var PALABRAS = ['cunape', 'majadito', 'somo', 'locro', 'keperi', 'masaco', 'sonso', 'tari'];
  var ROLES = { administrador: 'Administrador', cajero: 'Cajero', mesero: 'Mesero', cocina: 'Cocina' };
  var ACCESO = { administrador: 'Contraseña y PIN de autorización', cajero: 'PIN', mesero: 'Contraseña', cocina: 'Sin acceso al panel' };
  var modal = $('modal-clave');

  function claveTemporal() {
    return PALABRAS[Math.floor(Math.random() * PALABRAS.length)] + '-' + String(Math.floor(1000 + Math.random() * 9000));
  }
  function pinTemporal() { return String(Math.floor(1000 + Math.random() * 9000)); }

  function mostrarClaves(nombre, claves) {
    $('clave-lista-titulo').textContent = 'Acceso temporal de ' + nombre;
    var dl = $('claves-temporales');
    dl.innerHTML = '';
    Object.keys(claves).forEach(function (k) {
      var div = document.createElement('div');
      div.className = 'clave-temporal';
      div.innerHTML = '<dt></dt><dd></dd>';
      div.children[0].textContent = k;
      div.children[1].textContent = claves[k];
      dl.appendChild(div);
    });
    ['clave-paso', 'clave-acciones'].forEach(function (id) { $(id).hidden = true; });
    ['clave-lista', 'clave-cerrar'].forEach(function (id) { $(id).hidden = false; });
    if (!modal.open) modal.showModal();
    $('clave-cerrar').querySelector('button').focus();
  }

  // Cambio de rol en la fila
  $('filas-usuarios').addEventListener('change', function (e) {
    var sel = e.target.closest('select');
    if (!sel) return;
    var tr = sel.closest('tr');
    tr.setAttribute('data-rol', sel.value);
    tr.cells[2].textContent = ACCESO[sel.value];
    P.aviso(tr.getAttribute('data-nombre') + ' ahora es ' + ROLES[sel.value].toLowerCase() + '.');
  });

  // Activar / desactivar y desbloquear
  $('filas-usuarios').addEventListener('click', function (e) {
    var b = e.target.closest('[data-activo], [data-desbloquear]');
    if (!b) return;
    var tr = b.closest('tr');
    var nombre = tr.getAttribute('data-nombre');
    var celda = tr.querySelector('.celda-estado');
    if (b.hasAttribute('data-desbloquear')) {
      celda.innerHTML = '<span class="insignia insignia--ok">Activo</span>';
      b.removeAttribute('data-desbloquear');
      b.setAttribute('data-activo', 'true');
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

  // Resetear contraseña o PIN
  var actual = null;
  modal.addEventListener('pahuichi:abrir', function (e) {
    actual = { nombre: e.detail.origen.closest('tr').getAttribute('data-nombre'), tipo: e.detail.origen.getAttribute('data-tipo') };
    $('clave-titulo').textContent = '¿Resetear el ' + actual.tipo + ' de ' + actual.nombre + '?';
    $('clave-texto').textContent = 'El ' + actual.tipo + ' actual deja de funcionar y se genera uno temporal.';
    ['clave-paso', 'clave-acciones'].forEach(function (id) { $(id).hidden = false; });
    ['clave-lista', 'clave-cerrar'].forEach(function (id) { $(id).hidden = true; });
  });
  $('generar-clave').addEventListener('click', function () {
    var claves = {};
    if (actual.tipo === 'PIN') claves['PIN'] = pinTemporal(); else claves['Contraseña'] = claveTemporal();
    mostrarClaves(actual.nombre, claves);
  });

  // Alta
  $('u-rol').addEventListener('change', function () {
    $('u-nota').textContent = this.value === 'cocina'
      ? 'Cocina no entra al panel: el usuario existe solo para la bitácora y las comandas.'
      : 'Se genera una contraseña temporal' + (this.value === 'cajero' ? ' y un PIN de 4 dígitos' : '') + '. Se muestran una sola vez.';
  });

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
    Object.keys(errores).forEach(function (id) { P.marcarCampo(id, errores[id]); if (errores[id] && !primero) primero = id; });
    if (primero) { $(primero).focus(); return; }

    var rol = $('u-rol').value;
    var tr = document.createElement('tr');
    tr.className = 'fila-nueva';
    tr.setAttribute('data-rol', rol);
    tr.setAttribute('data-nombre', nombre);
    tr.innerHTML = '<td><span class="tabla__principal"></span><span class="tabla__secundario codigo"></span></td>' +
      '<td><span class="insignia insignia--neutra"></span></td><td></td>' +
      '<td class="celda-estado"><span class="insignia insignia--ok">Activo</span></td><td class="celda-vacia">Nunca</td>' +
      '<td class="col-accion"><div class="acciones-fila"><button class="boton" type="button" data-activo="true">Desactivar</button></div></td>';
    tr.querySelector('.tabla__principal').textContent = nombre;
    tr.querySelector('.codigo').textContent = usuario;
    tr.querySelector('.insignia').textContent = ROLES[rol];
    tr.cells[2].textContent = ACCESO[rol];
    $('filas-usuarios').appendChild(tr);
    this.reset();
    this.hidden = true;
    document.querySelector('[data-alternar="form-usuario"]').setAttribute('aria-expanded', 'false');
    P.filtrarTabla('filas-usuarios');
    P.aviso('Usuario ' + usuario + ' creado.');
    if (rol !== 'cocina') {
      var claves = { 'Contraseña': claveTemporal() };
      if (rol === 'cajero') claves['PIN'] = pinTemporal();
      mostrarClaves(nombre, claves);
    }
  });
})();
