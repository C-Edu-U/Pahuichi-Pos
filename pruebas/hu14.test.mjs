// Pruebas automáticas de la persistencia de productos de la DEMO (HU-14).
// Ejecutar desde la raíz: node --test pruebas/hu14.test.mjs
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import path from 'node:path';

const dataJs = fs.readFileSync(path.join(process.cwd(), 'public/assets/js/datos.js'), 'utf8');
const memoria = new Map();
function iniciar(almacenamiento = memoria) {
  const storage = {
    getItem: k => almacenamiento.get(k) ?? null,
    setItem: (k, v) => almacenamiento.set(k, v)
  };
  const contexto = { window: { localStorage: storage }, Set, Number };
  vm.runInNewContext(dataJs, contexto, { filename: 'datos.js' });
  return contexto.window.PahuichiDatos;
}
function nuevo(id, activo = true) {
  return {
    id, nombre: 'Picante de lengua', corto: 'Picante', descripcion: 'Plato de prueba',
    categoria: 'platos', precio: 42, unidad: 'Plato', activo,
    favorito: false, cocina: true, agotado: false, modificadores: []
  };
}

test('parte de los 25 productos iniciales', () => {
  memoria.clear();
  assert.equal(iniciar().productos.length, 25);
});

test('registro, persistencia después de recarga y código único', () => {
  memoria.clear();
  const d = iniciar();
  assert.equal(d.guardarProducto(nuevo('PL-100'), null).ok, true);
  assert.equal(d.productos.length, 26);
  assert.equal(iniciar().productos.find(p => p.id === 'PL-100').nombre, 'Picante de lengua');
  const repetido = d.guardarProducto(nuevo('PL-100'), null);
  assert.equal(repetido.ok, false);
  assert.equal(d.productos.length, 26);
  assert.equal(iniciar().productos.length, 26);
});

test('edición del precio, cocina y estado activo sin duplicar registro', () => {
  memoria.clear();
  const d = iniciar();
  const p = nuevo('PL-200', false);
  assert.equal(d.guardarProducto(p, null).ok, true);
  const cambios = { ...p, precio: 55, nombre: 'Picante especial', cocina: false };
  assert.equal(d.guardarProducto(cambios, 'PL-200').ok, true);
  let recargado = iniciar();
  assert.equal(recargado.productos.length, 26);
  let guardado = recargado.productos.find(x => x.id === 'PL-200');
  assert.equal(guardado.precio, 55);
  assert.equal(guardado.activo, false);
  assert.equal(guardado.cocina, false);
  assert.equal(recargado.guardarProducto({ ...guardado, activo: true }, 'PL-200').ok, true);
  guardado = iniciar().productos.find(x => x.id === 'PL-200');
  assert.equal(guardado.activo, true);
});

test('no confirma éxito si no puede escribir en localStorage', () => {
  const almacenamientoBloqueado = new Map();
  almacenamientoBloqueado.set = () => { throw new Error('Sin espacio'); };
  const d = iniciar(almacenamientoBloqueado);
  assert.equal(d.guardarProducto(nuevo('PL-300'), null).ok, false);
  assert.equal(d.productos.length, 25);
});
