/* Páginas genéricas del panel: 403 y «próximamente». En PHP las pintará el
   controlador con el nombre del módulo; aquí se leen de ?m= */
(function () {
  'use strict';

  var P = window.Pahuichi;
  var m = P.parametro('m');
  var modulo = P.MODULOS[m];
  var pagina = document.body.getAttribute('data-pagina');
  var titulo = document.getElementById('titulo-pagina');

  if (pagina === '403') {
    var rol = P.rolActual();
    if (modulo) document.getElementById('denegado-modulo').textContent = modulo.nombre;
    document.getElementById('denegado-rol').textContent = P.ROLES[rol].etiqueta;
    document.getElementById('denegado-volver').href = P.ROLES[rol].inicio;
    return;
  }

  if (pagina === 'proximamente' && modulo) {
    titulo.textContent = modulo.nombre;
    document.title = modulo.nombre + ' · Pahuichi POS';
    document.getElementById('proximo-modulo').textContent = modulo.nombre;
    document.getElementById('proximo-sprint').textContent = modulo.sprint;
    document.getElementById('proximo-texto').textContent = modulo.texto;
    document.getElementById('proximo-hu').textContent = modulo.hu;
  }
})();
