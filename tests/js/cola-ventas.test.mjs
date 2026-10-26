// Cola de ventas sin red (HU-36): public/assets/js/cola-ventas.js con un almacenamiento
// y un envío simulados. Uso: node tests/js/cola-ventas.test.mjs   (Node 18 o más nuevo)
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { strictEqual, deepStrictEqual } from 'node:assert';

const raiz = new URL('../../', import.meta.url);
const codigo = readFileSync(new URL('public/assets/js/cola-ventas.js', raiz), 'utf8');

function cargar(fetchFalso) {
  const contexto = { window: {}, URLSearchParams, fetch: fetchFalso || (() => Promise.reject(new Error('sin red'))) };
  runInNewContext(codigo, contexto);
  return contexto.window.PahuichiColaVentas;
}

// localStorage de mentira: lo que sobrevive a «recargar la página»
function almacen() {
  const datos = new Map();
  return { getItem: (k) => (datos.has(k) ? datos.get(k) : null), setItem: (k, v) => datos.set(k, String(v)), datos };
}

// Servidor de mentira: sin red hasta que se conecta; cuenta cuántas veces recibe cada uuid
function servidor() {
  const s = { conectado: false, recibidas: {}, responder: null };
  s.enviar = async (datos) => {
    if (!s.conectado) return { red: true };
    s.recibidas[datos.uuid] = (s.recibidas[datos.uuid] || 0) + 1;
    if (s.responder) return s.responder(datos);
    return { ok: true, datos: { numero: 40 + Object.keys(s.recibidas).length, pedido: '007', cambio: '0.00', tickets: '/facturas/1' } };
  };
  return s;
}

// Lo creado en el contexto aislado tiene otro prototipo: se compara como datos
const datos = (x) => JSON.parse(JSON.stringify(x));

const venta = (n, extra = {}) => ({ uuid: `uuid-${n}`, momento: '2026-10-22T12:5' + n + ':00', lineas: '[]', ...extra });
const resumen = { total: '120.00', items: 3, hora: '12:55' };

const pruebas = [];
const prueba = (nombre, fn) => pruebas.push([nombre, fn]);

prueba('Escenario 1: sin red la venta queda guardada y sobrevive a recargar la página', async () => {
  const Cola = cargar();
  const alm = almacen();
  const srv = servidor();
  const cola = Cola.crear({ almacen: alm, usuario: '3', enviar: srv.enviar });
  cola.agregar(venta(1), resumen);
  const r = await cola.procesar();
  strictEqual(r.detenido, 'red');
  strictEqual(cola.pendientes(), 1);

  // «Recargar»: otra instancia sobre el mismo almacenamiento
  const otra = Cola.crear({ almacen: alm, usuario: '3', enviar: srv.enviar });
  deepStrictEqual(datos(otra.ventas().map((v) => [v.uuid, v.estado, v.provisional, v.total, v.items])), [['uuid-1', 'pendiente', 'S-1', '120.00', 3]]);
});

prueba('Escenario 1: al reconectar la venta se envía una sola vez', async () => {
  const Cola = cargar();
  const srv = servidor();
  const cola = Cola.crear({ almacen: almacen(), usuario: '3', enviar: srv.enviar });
  cola.agregar(venta(1), resumen);
  await cola.procesar();
  srv.conectado = true;
  const r = await cola.procesar();
  await cola.procesar();   // otro disparo (evento «online», intervalo de 30 s)
  strictEqual(srv.recibidas['uuid-1'], 1);
  strictEqual(r.enviadas.length, 1);
  strictEqual(cola.ventas()[0].estado, 'enviada');
  strictEqual(cola.ventas()[0].factura.numero, 41);
});

prueba('Dos disparos a la vez comparten el mismo envío: la venta no sale dos veces', async () => {
  const Cola = cargar();
  const srv = servidor();
  srv.conectado = true;
  let soltar;
  const espera = new Promise((r) => { soltar = r; });
  srv.responder = async () => { await espera; return { ok: true, datos: { numero: 1 } }; };
  const cola = Cola.crear({ almacen: almacen(), usuario: '3', enviar: srv.enviar });
  cola.agregar(venta(1), resumen);
  const a = cola.procesar();
  const b = cola.procesar();
  strictEqual(a, b);
  soltar();
  await a;
  strictEqual(srv.recibidas['uuid-1'], 1);
});

prueba('Se envían en el orden en que se vendieron y se detiene al cortarse la red', async () => {
  const Cola = cargar();
  const srv = servidor();
  srv.conectado = true;
  const orden = [];
  srv.responder = (d) => {
    orden.push(d.uuid);
    if (d.uuid === 'uuid-2') { srv.conectado = false; return { red: true }; }
    return { ok: true, datos: { numero: orden.length } };
  };
  const cola = Cola.crear({ almacen: almacen(), usuario: '3', enviar: srv.enviar });
  [1, 2, 3].forEach((n) => cola.agregar(venta(n), resumen));
  const r = await cola.procesar();
  deepStrictEqual(orden, ['uuid-1', 'uuid-2']);
  strictEqual(r.detenido, 'red');
  deepStrictEqual(datos(cola.ventas().map((v) => v.estado)), ['enviada', 'pendiente', 'pendiente']);
});

prueba('Una venta rechazada queda con su motivo y las siguientes se envían igual', async () => {
  const Cola = cargar();
  const srv = servidor();
  srv.conectado = true;
  srv.responder = (d) => (d.uuid === 'uuid-1' ? { rechazo: 'La caja del pedido ya no está abierta.' } : { ok: true, datos: { numero: 9 } });
  const cola = Cola.crear({ almacen: almacen(), usuario: '3', enviar: srv.enviar });
  cola.agregar(venta(1), resumen);
  cola.agregar(venta(2), resumen);
  const r = await cola.procesar();
  strictEqual(r.rechazadas[0].motivo, 'La caja del pedido ya no está abierta.');
  deepStrictEqual(datos(cola.ventas().map((v) => v.estado)), ['rechazada', 'enviada']);
  strictEqual(cola.pendientes(), 0);
});

prueba('Con la sesión vencida se detiene y la venta espera a que el cajero vuelva a entrar', async () => {
  const Cola = cargar();
  const srv = servidor();
  srv.conectado = true;
  srv.responder = () => ({ sesion: true });
  const cola = Cola.crear({ almacen: almacen(), usuario: '3', enviar: srv.enviar });
  cola.agregar(venta(1), resumen);
  strictEqual((await cola.procesar()).detenido, 'sesion');
  strictEqual(cola.pendientes(), 1);
});

prueba('Las ventas de otro cajero no se envían con esta sesión', async () => {
  const Cola = cargar();
  const alm = almacen();
  const srv = servidor();
  srv.conectado = true;
  Cola.crear({ almacen: alm, usuario: '7', enviar: srv.enviar }).agregar(venta(1), resumen);
  const cola = Cola.crear({ almacen: alm, usuario: '3', enviar: srv.enviar });
  await cola.procesar();
  deepStrictEqual(srv.recibidas, {});
  strictEqual(cola.ventas().length, 0);
  strictEqual(cola.deOtros(), 1);
});

prueba('Una pendiente no se puede quitar; una rechazada o enviada sí', async () => {
  const Cola = cargar();
  const srv = servidor();
  const cola = Cola.crear({ almacen: almacen(), usuario: '3', enviar: srv.enviar });
  cola.agregar(venta(1), resumen);
  strictEqual(cola.quitar('uuid-1'), null);
  srv.conectado = true;
  srv.responder = () => ({ rechazo: 'Cambió una promoción.' });
  await cola.procesar();
  strictEqual(cola.quitar('uuid-1').estado, 'rechazada');
  strictEqual(cola.ventas().length, 0);
});

prueba('Número provisional por día y la misma venta no entra dos veces', async () => {
  const Cola = cargar();
  const cola = Cola.crear({ almacen: almacen(), usuario: '3', enviar: servidor().enviar });
  cola.agregar(venta(1), resumen);
  cola.agregar(venta(1), resumen);
  cola.agregar(venta(2), resumen);
  cola.agregar({ uuid: 'manana', momento: '2026-10-23T09:00:00' }, resumen);
  deepStrictEqual(datos(cola.ventas().map((v) => v.provisional)), ['S-1', 'S-2', 'S-1']);
});

prueba('HU-35: la cola del mesero tiene su clave y su prefijo, y no se mezcla con la del POS', async () => {
  const Cola = cargar();
  const alm = almacen();
  const pos = Cola.crear({ almacen: alm, usuario: '3', enviar: servidor().enviar });
  const mesero = Cola.crear({ almacen: alm, usuario: '3', enviar: servidor().enviar, clave: 'pahuichi.hu35.cola', prefijo: 'M-' });
  pos.agregar(venta(1), resumen);
  mesero.agregar(venta(2), resumen);
  mesero.agregar(venta(3), resumen);
  deepStrictEqual(datos(pos.ventas().map((v) => v.provisional)), ['S-1']);
  deepStrictEqual(datos(mesero.ventas().map((v) => v.provisional)), ['M-1', 'M-2']);
  strictEqual(JSON.parse(alm.datos.get('pahuichi.hu35.cola')).length, 2);
});

prueba('HU-35: el envío real va a la dirección que se le indica', async () => {
  let pedida = null;
  const Cola = cargar(async (url) => { pedida = url; return { status: 200, ok: true, type: 'basic', json: async () => ({ numero: 1 }) }; });
  await Cola.enviarAlServidor({ uuid: 'x' }, () => 't', '/cuentas/cerrar');
  strictEqual(pedida, '/cuentas/cerrar');
  await Cola.enviarAlServidor({ uuid: 'x' }, () => 't');
  strictEqual(pedida, '/pos/cobrar');
});

prueba('Una venta rechazada por un descuento vencido conserva la marca', async () => {
  const Cola = cargar();
  const srv = servidor();
  srv.conectado = true;
  srv.responder = () => ({ rechazo: 'El permiso venció.', descuento: 'invalido' });
  const cola = Cola.crear({ almacen: almacen(), usuario: '3', enviar: srv.enviar });
  cola.agregar(venta(1), resumen);
  strictEqual((await cola.procesar()).rechazadas[0].descuento, 'invalido');
});

prueba('El envío real interpreta la respuesta del servidor', async () => {
  const respuesta = (status, cuerpo, tipo = 'basic') => async () => ({
    status, ok: status >= 200 && status < 300, type: tipo,
    json: async () => { if (cuerpo === undefined) throw new Error('sin JSON'); return cuerpo; },
  });
  const casos = [
    [respuesta(200, { numero: 5 }), { ok: true, datos: { numero: 5 } }],
    [respuesta(422, { error: 'Cambió una promoción.', descuento: 'invalido' }), { rechazo: 'Cambió una promoción.', descuento: 'invalido', respuesta: { error: 'Cambió una promoción.', descuento: 'invalido' } }],
    [respuesta(409, { error: 'No hay una caja abierta.' }), { rechazo: 'No hay una caja abierta.', descuento: undefined, respuesta: { error: 'No hay una caja abierta.' } }],
    [respuesta(401, { error: 'Tu sesión terminó.' }), { sesion: true }],
    [respuesta(403, { error: 'La página venció.', recargar: true }), { sesion: true }],
    [respuesta(0, undefined, 'opaqueredirect'), { sesion: true }],
    [respuesta(502, undefined), { red: true }],
    [() => Promise.reject(new TypeError('Failed to fetch')), { red: true }],
  ];
  for (const [fetchFalso, esperado] of casos) {
    const Cola = cargar(fetchFalso);
    deepStrictEqual(datos(await Cola.enviarAlServidor({ uuid: 'x' }, () => 'token')), datos(esperado));
  }
});

let fallas = 0;
for (const [nombre, fn] of pruebas) {
  try {
    await fn();
    console.log('ok   ' + nombre);
  } catch (e) {
    fallas++;
    console.log('FALLA ' + nombre + '\n      ' + String(e.message).split('\n').join('\n      '));
  }
}
console.log(`\n${pruebas.length - fallas} de ${pruebas.length} pruebas`);
if (fallas) process.exitCode = 1;
