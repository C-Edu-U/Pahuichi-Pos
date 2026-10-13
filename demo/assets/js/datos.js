/* Datos de demostración (equivalen a database/seeds.sql). En la versión con
   servidor llegan de la base; aquí alimentan todas las pantallas.
   Reloj de la demo: jueves 8 de octubre de 2026, 12:41, turno de medio día. */
window.PahuichiDatos = {
  ahora: { fecha: '2026-10-08', hora: '12:41', diaSemana: 4, texto: 'Jueves 08/10/2026' },

  categorias: [
    { id: 'platos', nombre: 'Platos', orden: 1, color: '#8a5300' },
    { id: 'refrescos', nombre: 'Refrescos', orden: 2, color: '#1062a8' },
    { id: 'sodas', nombre: 'Sodas', orden: 3, color: '#8e2c7c' },
    { id: 'vinos', nombre: 'Vinos', orden: 4, color: '#6e2a1e' },
    { id: 'postres', nombre: 'Postres', orden: 5, color: '#3f6f1f' },
    { id: 'prueba', nombre: 'Prueba', orden: 6, color: '#4f5a54' }
  ],

  // codigo, nombre, corto, categoría, precio, unidad, favorito, va a cocina, agotado
  productos: [
    { id: 'PL-01', nombre: 'Majadito de charque', corto: 'Majadito', categoria: 'platos', precio: 40, unidad: 'Plato', favorito: true, cocina: true },
    { id: 'PL-02', nombre: 'Locro carretero', corto: 'Locro', categoria: 'platos', precio: 35, unidad: 'Plato', favorito: true, cocina: true },
    { id: 'PL-03', nombre: 'Picante de pollo', corto: 'Picante', categoria: 'platos', precio: 45, unidad: 'Plato', favorito: true, cocina: true },
    { id: 'PL-04', nombre: 'Keperí al horno', corto: 'Keperí', categoria: 'platos', precio: 55, unidad: 'Plato', favorito: true, cocina: true },
    { id: 'PL-05', nombre: 'Pacumutu de res', corto: 'Pacumutu', categoria: 'platos', precio: 60, unidad: 'Plato', favorito: false, cocina: true },
    { id: 'PL-06', nombre: 'Arroz con queso', corto: 'Arroz c/ queso', categoria: 'platos', precio: 25, unidad: 'Plato', favorito: false, cocina: true },
    { id: 'PL-07', nombre: 'Fricasé de cerdo', corto: 'Fricasé', categoria: 'platos', precio: 40, unidad: 'Plato', favorito: false, cocina: true, agotado: true },
    { id: 'PL-08', nombre: 'Sopa de maní', corto: 'Sopa de maní', categoria: 'platos', precio: 20, unidad: 'Plato', favorito: false, cocina: true },
    { id: 'PL-09', nombre: 'Sonso de yuca', corto: 'Sonso', categoria: 'platos', precio: 15, unidad: 'Porción', favorito: true, cocina: true },
    { id: 'RE-01', nombre: 'Mocochinchi', corto: 'Mocochinchi', categoria: 'refrescos', precio: 10, unidad: 'Vaso', favorito: true, cocina: false },
    { id: 'RE-02', nombre: 'Somó', corto: 'Somó', categoria: 'refrescos', precio: 10, unidad: 'Vaso', favorito: false, cocina: false },
    { id: 'RE-03', nombre: 'Chicha cruceña', corto: 'Chicha', categoria: 'refrescos', precio: 10, unidad: 'Vaso', favorito: true, cocina: false },
    { id: 'RE-04', nombre: 'Limonada', corto: 'Limonada', categoria: 'refrescos', precio: 10, unidad: 'Vaso', favorito: false, cocina: false },
    { id: 'RE-05', nombre: 'Jarra de mocochinchi', corto: 'Jarra moco.', categoria: 'refrescos', precio: 25, unidad: 'Jarra', favorito: false, cocina: false },
    { id: 'SO-01', nombre: 'Soda cola 500 ml', corto: 'Cola 500', categoria: 'sodas', precio: 8, unidad: 'Botella', favorito: true, cocina: false },
    { id: 'SO-02', nombre: 'Soda naranja 500 ml', corto: 'Naranja 500', categoria: 'sodas', precio: 8, unidad: 'Botella', favorito: false, cocina: false },
    { id: 'SO-03', nombre: 'Soda cola 2 L', corto: 'Cola 2 L', categoria: 'sodas', precio: 18, unidad: 'Botella', favorito: false, cocina: false },
    { id: 'SO-04', nombre: 'Agua sin gas 500 ml', corto: 'Agua', categoria: 'sodas', precio: 6, unidad: 'Botella', favorito: false, cocina: false },
    { id: 'VI-01', nombre: 'Vino tinto, copa', corto: 'Copa tinto', categoria: 'vinos', precio: 20, unidad: 'Copa', favorito: false, cocina: false },
    { id: 'VI-02', nombre: 'Vino tinto de Tarija, botella', corto: 'Tinto bot.', categoria: 'vinos', precio: 120, unidad: 'Botella', favorito: false, cocina: false },
    { id: 'VI-03', nombre: 'Vino blanco, copa', corto: 'Copa blanco', categoria: 'vinos', precio: 20, unidad: 'Copa', favorito: false, cocina: false },
    { id: 'PO-01', nombre: 'Arroz con leche', corto: 'Arroz c/ leche', categoria: 'postres', precio: 12, unidad: 'Porción', favorito: false, cocina: false },
    { id: 'PO-02', nombre: 'Leche asada', corto: 'Leche asada', categoria: 'postres', precio: 12, unidad: 'Porción', favorito: false, cocina: false },
    { id: 'PO-03', nombre: 'Helado de canela', corto: 'Helado', categoria: 'postres', precio: 10, unidad: 'Porción', favorito: false, cocina: false },
    { id: 'PR-01', nombre: 'Producto de prueba QR', corto: 'Prueba QR', categoria: 'prueba', precio: 0.5, unidad: 'Unidad', favorito: false, cocina: false }
  ],

  // Promociones (sección 7 del plan): descuento, combo, regalo y cumpleaños
  promociones: [
    { id: 1, nombre: 'Combo majadito + mocochinchi', tipo: 'combo', componentes: ['PL-01', 'RE-01'], precioCombo: 45, acumulable: false, prioridad: 1, dias: [1, 2, 3, 4, 5, 6, 0], desde: '11:00', hasta: '16:00', topeDia: 0, activo: true },
    { id: 2, nombre: '2 picantes, mocochinchi de regalo', tipo: 'regalo', condicion: 'PL-03', cantidadMinima: 2, regalo: 'RE-01', acumulable: true, prioridad: 2, dias: [1, 2, 3, 4, 5, 6, 0], desde: '11:00', hasta: '16:00', topeDia: 20, activo: true },
    { id: 3, nombre: 'Postres 10 % de lunes a viernes', tipo: 'descuento', categoria: 'postres', valorTipo: 'porcentaje', valor: 10, acumulable: true, prioridad: 3, dias: [1, 2, 3, 4, 5], desde: '11:00', hasta: '24:00', topeDia: 0, activo: true },
    { id: 4, nombre: 'Viernes y sábado de vino: Bs 20 menos por botella', tipo: 'descuento', producto: 'VI-02', valorTipo: 'monto', valor: 20, acumulable: true, prioridad: 4, dias: [5, 6], desde: '18:00', hasta: '24:00', topeDia: 0, activo: true },
    { id: 5, nombre: 'Postre gratis en tu cumpleaños', tipo: 'cumpleanos', categoria: 'postres', acumulable: true, prioridad: 5, dias: [1, 2, 3, 4, 5, 6, 0], desde: '11:00', hasta: '24:00', topeDia: 0, activo: true }
  ],

  clientes: [
    { doc: '4567891', tipo: 'CI', complemento: '', nombre: 'Rodríguez Peña', nacimiento: '1988-03-14' },
    { doc: '1023987026', tipo: 'NIT', complemento: '', nombre: 'Constructora Piraí S.R.L.', nacimiento: '' },
    { doc: '6234510', tipo: 'CI', complemento: '1F', nombre: 'Gutiérrez Añez', nacimiento: '1995-10-08' },
    { doc: '3398217', tipo: 'CI', complemento: '', nombre: 'Saucedo Roca', nacimiento: '1979-06-21' },
    { doc: '1987654013', tipo: 'NIT', complemento: '', nombre: 'Distribuidora El Trompillo', nacimiento: '' }
  ],

  usuarios: [
    { usuario: 'admin', nombre: 'Ana Lucía Vaca Saucedo', rol: 'administrador' },
    { usuario: 'mariela', nombre: 'Mariela Suárez Añez', rol: 'cajero', pin: '4821' },
    { usuario: 'jorge', nombre: 'Jorge Rivero Chávez', rol: 'cajero', pin: '7350' },
    { usuario: 'luis', nombre: 'Luis Fernando Roca', rol: 'mesero' },
    { usuario: 'teresa', nombre: 'Teresa Paz Justiniano', rol: 'cocina' }
  ],

  turnos: [
    { id: 'mediodia', nombre: 'Medio día', inicio: '11:00', fin: '16:00' },
    { id: 'noche', nombre: 'Noche', inicio: '18:00', fin: '24:00' }
  ],

  qr: { monto: 0.5, vigenteHasta: '23:59:59', banco: 'BNB' },
  cliente: { nombre: 'S/N', doc: '0' },
  siguientePedido: 43,
  siguienteFactura: 129,
  umbralObservacion: 10
};


/* HU-14: persistencia LOCAL para la demo, compartida por catálogo y POS.
   No sustituye MariaDB: cada navegador tiene sus propios productos. */
(function () {
  'use strict';
  var D = window.PahuichiDatos;
  var CLAVE = 'pahuichi.catalogo.productos.v1';

  try {
    var copia = JSON.parse(window.localStorage.getItem(CLAVE) || 'null');
    if (Array.isArray(copia) && copia.length && copia.every(function (p) {
      return p && typeof p.id === 'string' && typeof p.nombre === 'string' &&
        typeof p.categoria === 'string' && Number.isFinite(p.precio) && p.precio > 0;
    }) && new Set(copia.map(function (p) { return p.id; })).size === copia.length) {
      D.productos = copia;
    }
  } catch (e) { /* Se mantienen los datos iniciales si hay datos corruptos. */ }

  D.guardarProducto = function (producto, idOriginal) {
    var lista = D.productos.slice();
    var indice = idOriginal ? lista.findIndex(function (p) { return p.id === idOriginal; }) : -1;
    if (idOriginal && indice === -1) return { ok: false, error: 'El producto que intentas editar ya no existe.' };
    if (lista.some(function (p, i) { return i !== indice && p.id === producto.id; })) {
      return { ok: false, error: 'Ese código ya lo usa otro producto.' };
    }
    if (indice < 0) lista.push(producto);
    else lista[indice] = producto;
    try {
      window.localStorage.setItem(CLAVE, JSON.stringify(lista));
    } catch (e) {
      return { ok: false, error: 'No se pudo guardar en este navegador. Revisa el almacenamiento disponible.' };
    }
    // Reutilizar el array para no romper otras referencias a los datos.
    D.productos.splice.apply(D.productos, [0, D.productos.length].concat(lista));
    return { ok: true };
  };
})();
