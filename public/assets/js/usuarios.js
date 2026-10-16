/* Usuarios: confirmación del reseteo, nota según el rol del alta y apertura
   del aviso con las credenciales temporales. Todo se guarda en el servidor. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };

  // El modal de reseteo apunta al usuario del botón que lo abrió; el PIN solo
  // aparece para cajero y administrador
  var modal = $('modal-clave');
  modal.addEventListener('pahuichi:abrir', function (e) {
    var b = e.detail.origen;
    var id = b.getAttribute('data-id');
    $('clave-titulo').textContent = 'Resetear el acceso de ' + b.getAttribute('data-nombre');
    $('resetear-clave').setAttribute('formaction', '/usuarios/' + id + '/clave');
    $('resetear-pin').setAttribute('formaction', '/usuarios/' + id + '/pin');
    $('resetear-pin').hidden = !b.hasAttribute('data-con-pin');
  });

  // Credenciales recién generadas: se muestran una vez al cargar
  var credenciales = $('modal-credenciales');
  if (credenciales && credenciales.showModal) credenciales.showModal();

  // Nota del alta según el rol
  var rol = $('u-rol');
  function nota() {
    $('u-nota').textContent = rol.value === 'cocina'
      ? 'Cocina no entra al panel: el usuario existe solo para la bitácora y las comandas.'
      : 'Se genera una contraseña temporal' + (rol.value === 'cajero' ? ' y un PIN de 4 dígitos' : '') + '. Se muestran una sola vez.';
  }
  rol.addEventListener('change', nota);
  nota();

  // Validación previa (el servidor valida igual)
  $('form-usuario').addEventListener('submit', function (e) {
    var P = window.Pahuichi;
    var nombre = $('u-nombre').value.trim();
    var usuario = $('u-usuario').value.trim().toLowerCase();
    $('u-usuario').value = usuario;
    var errores = {
      'u-nombre': nombre ? '' : 'Escribe el nombre completo.',
      'u-usuario': !usuario ? 'Escribe el usuario.' : !/^[a-z0-9]{3,20}$/.test(usuario) ? 'Usa de 3 a 20 minúsculas o números, sin espacios.' : ''
    };
    var primero = null;
    Object.keys(errores).forEach(function (id) { P.marcarCampo(id, errores[id]); if (errores[id] && !primero) primero = id; });
    if (primero) { e.preventDefault(); $(primero).focus(); }
  });
})();
