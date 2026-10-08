// Descarga íconos de Tabler (MIT) y arma el sprite SVG + un loader JS.
// Uso: node iconos.mjs <carpeta assets>
import { writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const destino = process.argv[2];
const VERSION = '3.24.0';
const nombres = {
  escritorio: 'layout-dashboard',
  pos: 'cash-register',
  caja: 'cash',
  ventas: 'receipt',
  clientes: 'address-book',
  productos: 'tools-kitchen-2',
  qr: 'qrcode',
  reportes: 'chart-bar',
  usuarios: 'user-cog',
  config: 'settings',
  bitacora: 'history',
  menu: 'menu-2',
  cerrar: 'x',
  buscar: 'search',
  mas: 'plus',
  editar: 'pencil',
  salir: 'logout',
  plegar: 'layout-sidebar-left-collapse',
  desplegar: 'layout-sidebar-left-expand',
  alerta: 'alert-triangle',
  info: 'info-circle',
  ok: 'circle-check',
  error: 'alert-circle',
  foto: 'photo',
  subir: 'upload',
  candado: 'lock',
  volver: 'arrow-left',
  ver: 'eye',
  ocultar: 'eye-off',
  reloj: 'clock',
  lugar: 'map-pin',
  telefono: 'phone',
  agotado: 'circle-off',
  basura: 'trash',
  orden: 'arrows-sort',
  escudo: 'shield-lock',
  obra: 'barrier-block',
  imprimir: 'printer',
  descargar: 'download',
  menos: 'minus',
  check: 'check',
  calendario: 'calendar',
  anular: 'receipt-off',
  llave: 'key',
  usuarioMas: 'user-plus',
  empresa: 'building-store',
  billete: 'cash-banknote',
  qrOff: 'qrcode-off',
  abrir: 'lock-open',
  filtro: 'filter',
  carrito: 'shopping-cart',
  persona: 'user',
  documento: 'file-text',
  subir2: 'cloud-upload',
  sinRed: 'wifi-off',
  promocion: 'discount',
  pausa: 'player-pause',
  nota: 'note',
  mesa: 'armchair',
  llevar: 'paper-bag',
  camara: 'camera',
  recortar: 'crop',
  estrella: 'star',
  cocina: 'chef-hat',
  regalo: 'gift',
  pastel: 'cake',
  reintentar: 'refresh',
  movil: 'device-mobile',
  borrarTecla: 'backspace',
  ingreso: 'arrow-bar-to-down',
  egreso: 'arrow-bar-up',
  pdf: 'file-type-pdf',
  excel: 'file-spreadsheet',
  verificado: 'shield-check',
  pantalla: 'device-desktop',
  combo: 'tools-kitchen-3',
  cuadra: 'scale',
  enviar: 'send',
  teclado: 'keyboard',
  vaso: 'cup',
  botella: 'bottle',
  copa: 'glass-full',
  helado: 'ice-cream',
};

let simbolos = '';
for (const [id, nombre] of Object.entries(nombres)) {
  const url = `https://cdn.jsdelivr.net/npm/@tabler/icons@${VERSION}/icons/outline/${nombre}.svg`;
  const res = await fetch(url);
  if (!res.ok) throw new Error(`${nombre}: ${res.status}`);
  const svg = await res.text();
  // Solo se conservan los elementos de dibujo; nada de scripts ni atributos ajenos.
  const cuerpo = (svg.match(/<(path|circle|rect|line|polyline|polygon|ellipse)\b[^>]*\/>/g) || [])
    .filter((el) => !/stroke="none"/.test(el))
    .filter((el) => !/\son\w+=/i.test(el))
    .join('');
  if (!cuerpo) throw new Error(`${nombre}: sin trazos`);
  simbolos += `<symbol id="i-${id}" viewBox="0 0 24 24">${cuerpo}</symbol>`;
}

const sprite = `<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">${simbolos}</svg>`;
mkdirSync(join(destino, 'img'), { recursive: true });
mkdirSync(join(destino, 'js'), { recursive: true });
writeFileSync(join(destino, 'img', 'iconos.svg'), `<!-- Tabler Icons ${VERSION} (MIT, tabler.io/icons) -->\n${sprite}\n`);
writeFileSync(
  join(destino, 'js', 'iconos.js'),
  `/* Sprite de íconos: Tabler Icons ${VERSION} (MIT, tabler.io/icons). Generado; no editar a mano.\n   En PHP, el layout incluirá assets/img/iconos.svg en línea y este archivo dejará de hacer falta. */\n` +
    `(function () {\n  var sprite = ${JSON.stringify(sprite)};\n  var div = document.createElement('div');\n  div.className = 'sprite-iconos';\n  div.innerHTML = sprite;\n  document.body.insertBefore(div, document.body.firstChild);\n})();\n`
);
console.log(`${Object.keys(nombres).length} íconos`);
