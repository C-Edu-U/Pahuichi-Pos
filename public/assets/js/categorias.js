/* Categorías: vista previa del botón y confirmación al desactivar.
   Todo se guarda y valida en el servidor. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var form = $('form-categoria');

  if (form) {
    var vista = $('vista-boton');
    var pintar = function () {
      var elegido = form.querySelector('[name="color"]:checked');
      vista.className = 'categoria-pos categoria-pos--vista ' + (elegido ? elegido.getAttribute('data-clase') : '');
      vista.textContent = $('cat-nombre').value.trim() || 'Nueva categoría';
    };
    $('colores').addEventListener('change', pintar);
    $('cat-nombre').addEventListener('input', pintar);

    form.addEventListener('submit', function (e) {
      var nombre = $('cat-nombre').value.trim();
      window.Pahuichi.marcarCampo('cat-nombre', nombre ? '' : 'Escribe el nombre de la categoría.');
      if (!nombre) { e.preventDefault(); $('cat-nombre').focus(); }
    });
  }

  var modal = $('modal-desactivar');
  if (modal) {
    modal.addEventListener('pahuichi:abrir', function (e) {
      var b = e.detail.origen;
      var n = parseInt(b.getAttribute('data-productos'), 10);
      $('form-desactivar').action = '/catalogo/categorias/' + b.getAttribute('data-id') + '/estado';
      $('modal-desactivar-titulo').textContent = '¿Desactivar «' + b.getAttribute('data-nombre') + '»?';
      $('modal-desactivar-texto').textContent = (n === 0 ? 'No tiene productos.' : n === 1 ? 'Su producto dejará' : 'Sus ' + n + ' productos dejarán') +
        (n === 0 ? ' Dejará de verse en el punto de venta.' : ' de verse en el punto de venta.') + ' Puedes volver a activarla cuando quieras.';
    });
  }
})();
