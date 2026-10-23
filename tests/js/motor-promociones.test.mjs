// Casos de tests/promociones/casos.json contra el motor del navegador (motor-promociones.js).
// Uso: node tests/js/motor-promociones.test.mjs   (sin dependencias; Node 18 o más nuevo)
// El de PHP corre los mismos casos en tests/Unit/MotorCasosCompartidosTest.php.
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { deepStrictEqual } from 'node:assert';

const raiz = new URL('../../', import.meta.url);
const contexto = { window: {} };
runInNewContext(readFileSync(new URL('public/assets/js/motor-promociones.js', raiz), 'utf8'), contexto);
const Promos = contexto.window.PahuichiPromociones;
const { casos } = JSON.parse(readFileSync(new URL('tests/promociones/casos.json', raiz), 'utf8'));

// Mismo formato que arma app/Views/pos/index.php para el navegador
const centesimos = (d) => (d === null || d === undefined ? null : Math.round(parseFloat(d) * 100));
const promocion = (p) => ({
  nombre: 'Promo ' + p.id, tipo: 'descuento', valor_tipo: null, producto_id: null, categoria_id: null, cantidad_compra: null,
  regalo_producto_id: null, fecha_desde: null, fecha_hasta: null, dias: 127, hora_desde: null, hora_hasta: null, cantidad_minima: null,
  tope_usos_dia: null, acumulable: false, prioridad: 10, usos: 0,
  ...p, valor: centesimos(p.valor), monto_minimo: centesimos(p.monto_minimo),
});

let fallas = 0;
for (const caso of casos) {
  const lineas = caso.lineas.map((l) => ({ producto: { id: l.producto_id, categoria: l.categoria_id, precio: l.precio / 100 }, cantidad: l.cantidad }));
  const catalogo = {};
  for (const [id, precio] of Object.entries(caso.regalables || {})) catalogo[id] = { id: Number(id), precio: precio / 100, agotado: false };
  // Los arreglos del contexto aislado tienen otro prototipo: se comparan como datos
  const r = JSON.parse(JSON.stringify(Promos.evaluar(lineas, caso.promociones.map(promocion), catalogo, caso.contexto || null)));
  try {
    deepStrictEqual(r.lineas.map((l) => l.partes.map((x) => [x.promocion.id, x.centavos])), caso.esperado.lineas);
    deepStrictEqual(r.regalos.map((g) => [g.promocion.id, g.producto_id, g.cantidad, g.centavos]), caso.esperado.regalos);
    deepStrictEqual(r.beneficio, caso.esperado.beneficio);
    console.log('ok   ' + caso.nombre);
  } catch (e) {
    fallas++;
    console.log('FALLA ' + caso.nombre + '\n      ' + e.message.split('\n').join('\n      '));
  }
}
console.log(`\n${casos.length - fallas} de ${casos.length} casos`);
process.exit(fallas ? 1 : 0);
