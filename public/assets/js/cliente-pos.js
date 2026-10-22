/* HU-32: buscador de la demo conectado al catálogo persistente. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  if (!$('cliente-buscador')) return;
  var doc = $('doc-cliente'), nombre = $('nombre-cliente'), tipo = $('tipo-cliente');
  var lista = $('cliente-sugerencias'), resultados = [], indice = -1;
  var secuencia = 0, temporizador = null, peticion = null;
  function error(texto) { $('cliente-error').textContent = texto; $('cliente-error').hidden = !texto; }
  function invalidar() {
    secuencia += 1; clearTimeout(temporizador);
    if (peticion) peticion.abort();
    lista.hidden = true; doc.setAttribute('aria-expanded', 'false'); doc.removeAttribute('aria-activedescendant');
    resultados = []; indice = -1;
  }
  function resumen() {
    $('cliente-dato').textContent = doc.value && doc.value !== '0'
      ? (nombre.value || 'Cliente por completar') + ' · ' + tipo.value + ' ' + doc.value
      : 'S/N · NIT 0';
  }
  function cerrar() {
    $('cliente-buscador').hidden = true; $('cliente-cambiar').setAttribute('aria-expanded', 'false');
    lista.hidden = true; doc.setAttribute('aria-expanded', 'false'); doc.removeAttribute('aria-activedescendant');
  }
  function aplicar(c, terminar) {
    invalidar(); doc.value = c.doc; nombre.value = c.nombre; tipo.value = c.tipo;
    nombre.readOnly = true; tipo.disabled = true;
    $('cliente-estado').textContent = 'Cliente registrado. El nombre se completó automáticamente.';
    $('cliente-usar').textContent = 'Usar cliente'; error(''); resumen();
    if (terminar) { cerrar(); $('cliente-cambiar').focus(); }
  }
  function reset() {
    invalidar(); doc.value = ''; nombre.value = ''; tipo.value = 'NIT'; nombre.readOnly = false; tipo.disabled = false;
    $('cliente-usar').textContent = 'Guardar y usar cliente';
    $('cliente-estado').textContent = 'Escribe el NIT o CI. Los clientes nuevos también se guardan al confirmar el cobro.';
    error(''); resumen(); cerrar();
  }
  function datos() {
    var d = doc.value.trim().toUpperCase(), n = nombre.value.trim();
    if ((!d || d === '0') && n && n !== 'S/N') throw new Error('Escribe el NIT o CI del cliente, o selecciona S/N.');
    if (!d || d === '0') return {cliente_doc:'0', cliente_nombre:'', cliente_tipo:'NIT'};
    var patron = tipo.value === 'NIT' ? /^[0-9]{1,20}$/ : /^[0-9]{1,20}(?:-[A-Z0-9]{1,5})?$/;
    if (!patron.test(d) || /^0+$/.test(d)) throw new Error('Revisa el NIT o CI del cliente.');
    return {cliente_doc:d, cliente_nombre:n, cliente_tipo:tipo.value};
  }
  function enfocarOpcion(i) {
    indice = (i + resultados.length) % resultados.length;
    Array.from(lista.children).forEach(function (li, j) { li.setAttribute('aria-selected', String(j === indice)); });
    doc.setAttribute('aria-activedescendant', 'cliente-opcion-' + indice);
  }
  async function buscar(s, q) {
    peticion = new AbortController();
    try {
      var r = await fetch('/clientes/buscar?q=' + encodeURIComponent(q), {headers:{'Accept':'application/json'},signal:peticion.signal});
      if (!r.ok) throw new Error('No se pudo buscar el cliente. Reintenta o escribe su documento y nombre.');
      var d = await r.json();
      if (s !== secuencia) return;
      resultados = d.clientes; lista.replaceChildren();
      resultados.forEach(function (c, i) {
        var li = document.createElement('li'); li.className = 'cliente-pos__sugerencia';
        li.id = 'cliente-opcion-' + i; li.setAttribute('role','option'); li.setAttribute('aria-selected','false');
        var numero = document.createElement('span'), texto = document.createElement('span');
        numero.textContent = c.tipo + ' ' + c.doc; texto.textContent = c.nombre; li.append(numero,texto);
        li.addEventListener('mousedown', function (e) { e.preventDefault(); });
        li.addEventListener('click', function () { aplicar(c,true); }); lista.appendChild(li);
      });
      var exacto = resultados.find(function (c) { return c.doc.toUpperCase() === q; });
      if (exacto) { aplicar(exacto,false); return; }
      lista.hidden = !resultados.length; doc.setAttribute('aria-expanded', String(!lista.hidden));
      $('cliente-estado').textContent = resultados.length ? 'Selecciona el cliente o completa el documento.' : 'Cliente nuevo: escribe su nombre. Se guardará al usarlo o confirmar el cobro.';
    } catch (e) { if (e.name !== 'AbortError' && s === secuencia) error(e.message); }
  }
  $('cliente-cambiar').addEventListener('click', function () {
    var abrir = $('cliente-buscador').hidden; $('cliente-buscador').hidden = !abrir;
    this.setAttribute('aria-expanded',String(abrir)); if (abrir) doc.focus(); else cerrar();
  });
  doc.addEventListener('input', function () {
    invalidar(); doc.value = doc.value.toUpperCase(); nombre.value = ''; nombre.readOnly = false; tipo.disabled = false;
    $('cliente-usar').textContent = 'Guardar y usar cliente'; error(''); resumen();
    var q = doc.value.trim();
    if (q && q !== '0') {
      $('cliente-estado').textContent = 'Buscando cliente…';
      var s = secuencia; temporizador = setTimeout(function () { buscar(s,q); },180);
    } else $('cliente-estado').textContent = 'Sin datos: se utilizará S/N con NIT 0.';
  });
  doc.addEventListener('keydown', function (e) {
    if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && resultados.length && !lista.hidden) {
      e.preventDefault(); enfocarOpcion(indice + (e.key === 'ArrowDown' ? 1 : -1));
    } else if (e.key === 'Enter') {
      e.preventDefault(); if (indice >= 0 && !lista.hidden) aplicar(resultados[indice],true); else nombre.focus();
    } else if (e.key === 'Escape') { cerrar(); $('cliente-cambiar').focus(); }
  });
  nombre.addEventListener('input', resumen); tipo.addEventListener('change', resumen);
  $('sin-datos').addEventListener('click', function () { reset(); $('cliente-cambiar').focus(); });
  $('cliente-usar').addEventListener('click', async function () {
    error(''); var boton = this, version = secuencia;
    try {
      var d = datos(); if (d.cliente_doc === '0') { reset(); return; }
      boton.disabled = true;
      var r = await fetch('/clientes', {method:'POST',headers:{'Accept':'application/json','X-CSRF-Token':document.body.getAttribute('data-csrf') || ''},
        body:new URLSearchParams({doc:d.cliente_doc,nombre:d.cliente_nombre,tipo:d.cliente_tipo})});
      var json = await r.json(); if (!r.ok) throw new Error(json.error || 'No se pudo guardar el cliente.');
      if (version === secuencia) aplicar(json.cliente,true);
    } catch (e) { if (version === secuencia) error(e.message); }
    finally { boton.disabled = false; }
  });
  window.PahuichiCliente = {datos:datos,reset:reset,restaurar:function (d) {
    invalidar(); doc.value = d.cliente_doc || ''; nombre.value = d.cliente_nombre || ''; tipo.value = d.cliente_tipo || 'NIT'; resumen(); cerrar();
  }};
})();
