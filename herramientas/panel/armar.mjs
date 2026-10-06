// Arma las páginas del panel: marco común + fragmento de contenido.
// Cada fragmento empieza con <!-- {"titulo","modulo","h1","js":[], ...} -->
// Uso: node armar.mjs <carpeta fragmentos> <carpeta panel>
import { readFileSync, writeFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const [dirFrag, dirPanel] = process.argv.slice(2);

const MENU = [
  ['escritorio', 'escritorio.html', 'escritorio', 'Escritorio'],
  ['pos', 'pos.html', 'pos', 'Punto de venta'],
  ['caja', 'caja.html', 'caja', 'Caja'],
  ['ventas', 'ventas.html', 'ventas', 'Ventas y facturas'],
  ['clientes', 'clientes.html', 'clientes', 'Clientes'],
  '-',
  ['productos', 'productos.html', 'productos', 'Productos y categorías'],
  ['qr', 'qr.html', 'qr', 'Pagos QR', '<span class="menu__aviso"><span class="visualmente-oculto">QR vence en </span>2 h</span>'],
  '-',
  ['reportes', 'reportes.html', 'reportes', 'Reportes'],
  ['bitacora', 'bitacora.html', 'bitacora', 'Bitácora'],
  '-',
  ['usuarios', 'usuarios.html', 'usuarios', 'Usuarios'],
  ['config', 'config.html', 'config', 'Configuración'],
];

function marco(m, main) {
  const items = MENU.map((it) => {
    if (it === '-') return '        <li class="menu__separador" role="separator"></li>';
    const [mod, href, icono, texto, extra = ''] = it;
    const actual = mod === m.modulo && m.pagina !== '403' ? ' aria-current="page"' : '';
    return `        <li class="menu__item" data-modulo="${mod}"><a class="menu__enlace" href="${href}"${actual}><svg class="icono" aria-hidden="true"><use href="#i-${icono}"></use></svg><span class="menu__texto">${texto}</span>${extra}</a></li>`;
  }).join('\n');

  const atributos = [`class="panel${m.claseBody ? ' ' + m.claseBody : ''}"`, `data-modulo="${m.modulo}"`];
  if (m.pagina) atributos.push(`data-pagina="${m.pagina}"`);
  if (m.rielPlegado) atributos.push('data-riel-plegado');
  const scripts = ['iconos.js', 'app.js', ...(m.js || [])].map((f) => `  <script src="../assets/js/${f}"></script>`).join('\n');

  return `<!doctype html>
<html lang="es-BO">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${m.titulo} · Pahuichi POS</title>
  <meta name="theme-color" content="#13603e">
  <link rel="preload" href="../assets/fonts/archivo-variable-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/layout.css">
  <link rel="stylesheet" href="../assets/css/pages.css">
${m.cssExtra ? m.cssExtra.map((c) => `  <link rel="stylesheet" href="../assets/css/${c}">\n`).join('') : ''}  <link rel="stylesheet" href="../assets/css/print.css" media="print">
</head>
<body ${atributos.join(' ')}>
  <a class="saltar-contenido" href="#contenido">Saltar al contenido</a>

  <aside class="riel" id="riel" aria-label="Menú principal">
    <div class="riel__marca">
      <a class="marca" href="escritorio.html">
        <span class="marca__sello" aria-hidden="true"><span></span></span>
        <span class="marca__texto">Pahuichi <small>Punto de venta</small></span>
      </a>
      <button class="boton boton--fantasma boton--icono riel__cerrar" type="button" data-cerrar-riel aria-label="Cerrar menú">
        <svg class="icono" aria-hidden="true"><use href="#i-cerrar"></use></svg>
      </button>
    </div>
    <nav class="menu" aria-label="Módulos">
      <ul class="menu__lista" role="list">
${items}
      </ul>
    </nav>
    <div class="riel__pie">
      <button class="boton boton--fantasma riel__plegar" type="button" data-plegar-riel aria-controls="riel" aria-expanded="true">
        <svg class="icono icono-plegar" aria-hidden="true"><use href="#i-plegar"></use></svg>
        <svg class="icono icono-desplegar" aria-hidden="true"><use href="#i-desplegar"></use></svg>
        <span class="menu__texto">Plegar menú</span>
      </button>
    </div>
  </aside>
  <div class="velo" hidden></div>

  <div class="marco">
    <header class="cabecera">
      <div class="barra">
        <button class="boton boton--fantasma boton--icono barra__menu" type="button" data-abrir-riel aria-controls="riel" aria-expanded="false" aria-label="Abrir menú">
          <svg class="icono" aria-hidden="true"><use href="#i-menu"></use></svg>
        </button>
        <h1 class="barra__titulo"${m.h1id ? ` id="${m.h1id}"` : ''}>${m.h1}</h1>
        <div class="barra__usuario">
          <label class="demo-rol">
            <span class="demo-rol__texto">Prototipo: ver como</span>
            <select class="control" id="demo-rol">
              <option value="cajero">Cajero</option>
              <option value="analista">Analista</option>
              <option value="sysadmin">Administrador</option>
            </select>
          </label>
          <p class="usuario"><span class="usuario__nombre" data-rol-nombre>Ana Lucía Vaca Saucedo</span><span class="usuario__rol" data-rol-etiqueta>Administrador</span></p>
          <button class="boton boton--fantasma boton-salir" type="button" data-salir aria-label="Cerrar sesión">
            <svg class="icono" aria-hidden="true"><use href="#i-salir"></use></svg>
            <span class="boton-salir__texto">Salir</span>
          </button>
        </div>
      </div>
    </header>

${main.trimEnd()}
  </div>

${scripts}
</body>
</html>
`;
}

for (const f of readdirSync(dirFrag).filter((x) => x.endsWith('.html'))) {
  const texto = readFileSync(join(dirFrag, f), 'utf8');
  const m = JSON.parse(/^<!--\s*(\{.*\})\s*-->/.exec(texto)[1]);
  const main = texto.replace(/^<!--.*-->\n/, '');
  writeFileSync(join(dirPanel, f), marco(m, main));
  console.log('ok', f);
}
