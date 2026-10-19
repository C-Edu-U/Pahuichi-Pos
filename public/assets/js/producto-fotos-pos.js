/* HU-15: presenta las fotos reales; no modifica carrito, cobro ni facturación. */
(function () {
  'use strict';
  var pos = document.getElementById('pos'), grilla = document.getElementById('grilla');
  if (!pos || !grilla || !pos.dataset.productos) return;
  var productos = JSON.parse(pos.dataset.productos);
  function pintar() {
    grilla.querySelectorAll('.producto-pos[data-id]').forEach(function (boton) {
      var p = productos.find(function (x) { return String(x.id) === boton.dataset.id; });
      var zona = boton.querySelector('.producto-pos__foto');
      if (!p || !p.foto || !zona || zona.querySelector('img')) return;
      var img = new Image(); img.alt = ''; img.loading = 'lazy';
      img.src = '/catalogo/productos/' + encodeURIComponent(p.id) + '/foto?v=' + encodeURIComponent(p.foto);
      img.addEventListener('error', function () { img.remove(); });
      img.addEventListener('load', function () { var icono = zona.querySelector('svg'); if (icono) icono.remove(); });
      zona.append(img);
    });
  }
  new MutationObserver(pintar).observe(grilla, {childList: true});
  pintar();
})();
