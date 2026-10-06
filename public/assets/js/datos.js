/* Datos de demostración: semillas del HANDOFF (sección 5). En PHP llegan del
   servidor; aquí alimentan el POS, el formulario de producto y los clientes. */
window.PahuichiDatos = {
  productos: [
    { id: 'PL-01', nombre: 'Majadito de charque', categoria: 'platos', precio: 30, sin: '63111', actividad: '561011', unidad: '58' },
    { id: 'PL-02', nombre: 'Locro carretero', categoria: 'platos', precio: 28, sin: '63111', actividad: '561011', unidad: '58' },
    { id: 'PL-03', nombre: 'Keperí al horno', categoria: 'platos', precio: 45, sin: '63111', actividad: '561011', unidad: '58' },
    { id: 'PL-04', nombre: 'Pacumutu', categoria: 'platos', precio: 50, sin: '63111', actividad: '561011', unidad: '58' },
    { id: 'PL-05', nombre: 'Arroz con queso', categoria: 'platos', precio: 20, sin: '63111', actividad: '561011', unidad: '58' },
    { id: 'HO-01', nombre: 'Cuñapé', categoria: 'horneados', precio: 3, sin: '10712', actividad: '107100', unidad: '57' },
    { id: 'HO-02', nombre: 'Pan de arroz', categoria: 'horneados', precio: 4, sin: '10712', actividad: '107100', unidad: '57' },
    { id: 'HO-03', nombre: 'Empanada de arroz', categoria: 'horneados', precio: 5, sin: '10712', actividad: '107100', unidad: '57' },
    { id: 'HO-04', nombre: 'Humintas al horno', categoria: 'horneados', precio: 6, sin: '10712', actividad: '107100', unidad: '57' },
    { id: 'HO-05', nombre: 'Sonso de yuca', categoria: 'horneados', precio: 10, sin: '', actividad: '107100', unidad: '57' },
    { id: 'HO-06', nombre: 'Masaco de plátano', categoria: 'horneados', precio: 15, sin: '10712', actividad: '107100', unidad: '57', agotado: true },
    { id: 'BE-01', nombre: 'Somó', categoria: 'bebidas', precio: 8, sin: '11041', actividad: '110400', unidad: '57' },
    { id: 'BE-02', nombre: 'Chicha cruceña', categoria: 'bebidas', precio: 8, sin: '11041', actividad: '110400', unidad: '57' },
    { id: 'BE-03', nombre: 'Mocochinchi', categoria: 'bebidas', precio: 7, sin: '11041', actividad: '110400', unidad: '57' },
    { id: 'PR-01', nombre: 'Producto de prueba QR', categoria: 'prueba', precio: 0.5, sin: '99100', actividad: '561011', unidad: '58' }
  ],
  categorias: { platos: 1, horneados: 2, bebidas: 3, postres: 4, prueba: 5 },
  clientes: [
    { doc: '4567891', tipo: 'CI', complemento: '', nombre: 'Rodríguez Peña' },
    { doc: '1023987026', tipo: 'NIT', complemento: '', nombre: 'Constructora Piraí S.R.L.' },
    { doc: '6234510', tipo: 'CI', complemento: '1F', nombre: 'Gutiérrez Añez' },
    { doc: '3398217', tipo: 'CI', complemento: '', nombre: 'Saucedo Roca' },
    { doc: '1987654013', tipo: 'NIT', complemento: '', nombre: 'Distribuidora El Trompillo' }
  ],
  qr: { monto: 0.5, vence: '16:40', banco: 'BNB' },
  sinDatos: '99002',
  siguienteFactura: 129
};
