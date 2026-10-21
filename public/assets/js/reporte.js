/* Reporte de cierre (HU-25): el botón imprime o deja guardar la página como PDF.
   El PDF sale del propio navegador con el mismo contenido, sin librerías externas. */
(function () {
  'use strict';

  var boton = document.getElementById('imprimir-reporte');
  if (!boton) return;

  boton.addEventListener('click', function () {
    window.print();
  });
})();
