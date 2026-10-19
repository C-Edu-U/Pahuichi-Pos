/* Carrito del punto de venta (HU-29): solo la lógica, sin tocar la pantalla.
   Los montos se llevan en centavos para que las sumas no acumulen errores de
   redondeo. Vive en la memoria del navegador; guardarlo ante un corte de red es
   HU-36, y el precio que vale al cobrar lo fija el servidor (Sprint 3). */
(function () {
  'use strict';

  function centavos(monto) { return Math.round(Number(monto) * 100); }

  function Carrito(productos) {
    this.catalogo = {};
    var self = this;
    (productos || []).forEach(function (p) { self.catalogo[String(p.id)] = p; });
    this.lineas = [];   // [{ producto, cantidad }] en el orden en que se agregaron
  }

  Carrito.prototype.linea = function (id) {
    id = String(id);
    for (var i = 0; i < this.lineas.length; i++) {
      if (String(this.lineas[i].producto.id) === id) return this.lineas[i];
    }
    return null;
  };

  Carrito.prototype.cantidadDe = function (id) {
    var l = this.linea(id);
    return l ? l.cantidad : 0;
  };

  /* Un toque agrega el producto; otro toque suma una unidad.
     Devuelve la línea, o null si el producto no existe o está agotado. */
  Carrito.prototype.agregar = function (id) {
    var p = this.catalogo[String(id)];
    if (!p || p.agotado) return null;
    var l = this.linea(id);
    if (!l) {
      l = { producto: p, cantidad: 0 };
      this.lineas.push(l);
    }
    l.cantidad += 1;
    return l;
  };

  /* + y −: con −1 en una unidad, la línea se quita. Devuelve la cantidad que queda. */
  Carrito.prototype.cambiar = function (id, delta) {
    var l = this.linea(id);
    if (!l) return 0;
    if (delta > 0 && l.producto.agotado) return l.cantidad;
    l.cantidad += delta;
    if (l.cantidad <= 0) this.quitar(id);
    return Math.max(l.cantidad, 0);
  };

  Carrito.prototype.quitar = function (id) {
    id = String(id);
    this.lineas = this.lineas.filter(function (l) { return String(l.producto.id) !== id; });
  };

  Carrito.prototype.vaciar = function () { this.lineas = []; };

  Carrito.prototype.subtotalDe = function (l) { return centavos(l.producto.precio) * l.cantidad / 100; };

  Carrito.prototype.total = function () {
    return this.lineas.reduce(function (s, l) { return s + centavos(l.producto.precio) * l.cantidad; }, 0) / 100;
  };

  Carrito.prototype.unidades = function () {
    return this.lineas.reduce(function (s, l) { return s + l.cantidad; }, 0);
  };

  Carrito.prototype.vacio = function () { return this.lineas.length === 0; };

  window.PahuichiCarrito = Carrito;
})();
