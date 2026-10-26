/* HU-07: los contadores representan operaciones del contenedor, sin cambiar el reloj. */
(function () {
  'use strict';
  var panel = document.getElementById('respaldos');
  if (!panel) return;
  var lista = document.getElementById('respaldo-lista'), boton = document.getElementById('respaldo-simular');
  var aviso = document.getElementById('respaldo-estado'), reloj = document.getElementById('respaldo-proxima');
  var simulaciones = panel.hasAttribute('data-simular');
  var estado = null, desfase = 0, enviando = false, consultando = false, firma = '';
  var solicitud = '';
  try { solicitud = sessionStorage.getItem('hu07-solicitud') || ''; } catch (e) { /* funciona sin persistencia */ }
  function guardar(id) { solicitud = id; try { if (id) sessionStorage.setItem('hu07-solicitud', id); else sessionStorage.removeItem('hu07-solicitud'); } catch (e) {} }
  function restante(fecha) {
    var s = Math.max(0, Math.ceil(fecha - (Date.now() + desfase) / 1000));
    var d = Math.floor(s / 86400); s %= 86400;
    var h = Math.floor(s / 3600); s %= 3600;
    var m = Math.floor(s / 60); s %= 60;
    return d + ' d ' + String(h).padStart(2, '0') + ' h ' + String(m).padStart(2, '0') + ' min ' + String(s).padStart(2, '0') + ' s';
  }
  function cuenta() {
    if (!estado) return;
    var p = estado.pendiente;
    if (p && p.accion === 'restaurar') p = null;
    document.getElementById('respaldo-proxima-titulo').textContent = p ? 'Próxima copia (simulación)' : 'Próxima copia automática';
    reloj.textContent = !estado.activo ? 'Servicio no disponible' : p && p.programada * 1000 <= Date.now() + desfase ? 'Generando copia…' : estado.activo ? restante(p ? p.programada : estado.proxima) : 'Servicio no disponible';
    lista.querySelectorAll('[data-vence]').forEach(function (n) {
      var fecha = Number(n.dataset.vence), simulada = p && p.accion === 'vencer' && n.dataset.archivo === p.archivo;
      if (simulada) fecha = p.programada;
      n.textContent = fecha * 1000 <= Date.now() + desfase ? (simulada ? 'Generando reemplazo y aplicando vencimiento…' : 'Plazo vencido; pendiente de la próxima limpieza.') : 'Conservación restante: ' + restante(fecha) + (simulada ? ' (simulación)' : '');
    });
  }
  function pintar(datos) {
    estado = datos; desfase = datos.ahora * 1000 - Date.now();
    document.getElementById('respaldos-hora').textContent = datos.hora;
    var ocupado = enviando || !!datos.pendiente || datos.esRestaurada;
    boton.disabled = ocupado || !datos.activo;
    var nuevaFirma = JSON.stringify(datos.copias);
    if (nuevaFirma !== firma) {
      firma = nuevaFirma; lista.replaceChildren();
      if (!datos.copias.length) { var vacio = document.createElement('p'); vacio.textContent = 'Todavía no hay copias disponibles.'; lista.appendChild(vacio); }
      datos.copias.forEach(function (copia) {
        var fila = document.createElement('div'); fila.className = 'respaldo-fila';
        var info = document.createElement('div'); info.className = 'respaldo-info';
        var titulo = document.createElement('strong'); titulo.textContent = copia.fecha + ' · ' + (copia.peso / 1024).toLocaleString('es-BO', {maximumFractionDigits:1}) + ' KB';
        var tiempo = document.createElement('span'); tiempo.className = 'num'; tiempo.dataset.vence = copia.vence; tiempo.dataset.archivo = copia.nombre;
        info.append(titulo, tiempo);
        var b = document.createElement('button'); b.type = 'button'; b.className = 'boton'; b.textContent = 'Simular vencimiento en 5 s'; b.dataset.vencer = copia.nombre;
        var acciones = document.createElement('div'); acciones.className = 'respaldo-acciones';
        var restaurar = document.createElement('button'); restaurar.type = 'button'; restaurar.className = 'boton'; restaurar.textContent = 'Probar restauración'; restaurar.dataset.restaurar = copia.nombre;
        if (simulaciones) acciones.append(b);
        acciones.append(restaurar); fila.append(info, acciones); lista.appendChild(fila);
      });
    }
    lista.querySelectorAll('button').forEach(function (b) { b.disabled = ocupado || !datos.activo; });
    if (datos.esRestaurada) { aviso.textContent = 'Estás en la instancia restaurada de prueba. Realiza las pruebas de copias desde el sistema habitual.'; }
    else if (datos.pendiente) {
      guardar(datos.pendiente.id);
      aviso.textContent = !datos.activo ? 'Simulación pendiente. El servicio de copias no está disponible; revisa Docker.' : datos.pendiente.accion === 'restaurar' ? (datos.pendiente.mensaje || 'Preparando la restauración en otra base vacía…') : datos.pendiente.estado === 'ejecutando' ? 'Generando una copia nueva. Espera el resultado…' : 'Simulación en curso. Observa la cuenta regresiva.';
    } else if (datos.resultado) {
      aviso.textContent = datos.resultado.mensaje || 'Consultando resultado…';
      if (datos.resultado.estado === 'hecho' || datos.resultado.estado === 'error') guardar('');
    } else if (!datos.activo) aviso.textContent = 'El servicio de copias no está disponible. Revisa que Docker esté activo.';
    else if (!aviso.dataset.terminado) aviso.textContent = simulaciones ? 'Servicio disponible. Puedes simular la copia o el vencimiento desde esta pantalla.' : 'Servicio disponible.';
    if (datos.resultado && (datos.resultado.estado === 'hecho' || datos.resultado.estado === 'error')) aviso.dataset.terminado = '1';
    pintarRestauracion(datos);
    cuenta();
  }
  function pintarRestauracion(datos) {
    var caja = document.getElementById('restauracion-resultado'), link = document.getElementById('restauracion-abrir');
    var pendiente = datos.pendiente && datos.pendiente.accion === 'restaurar';
    var ultima = datos.restauracion;
    caja.hidden = !ultima && !pendiente;
    if (caja.hidden) return;
    document.getElementById('restauracion-mensaje').textContent = pendiente ? (datos.pendiente.mensaje || 'Restauración en curso…') : 'Se partió de otra base vacía (0 tablas) y se recuperaron estos datos:';
    var dl = document.getElementById('restauracion-datos'); dl.replaceChildren();
    link.hidden = !!pendiente || !ultima || !ultima.lista;
    if (ultima && !pendiente) {
      var etiquetas = {usuarios:'Usuarios',productos:'Productos',ventas:'Ventas',facturas:'Facturas',pagos:'Pagos',cajas:'Cajas',bitacora:'Bitácora'};
      Object.keys(etiquetas).forEach(function (k) {
        var grupo = document.createElement('div'), dt = document.createElement('dt'), dd = document.createElement('dd');
        dt.textContent = etiquetas[k]; dd.textContent = String(ultima.datos[k]); grupo.append(dt,dd); dl.appendChild(grupo);
      });
      var url = new URL(window.location.href); url.port = String(datos.puertoRestaurado); url.pathname = '/'; url.search = ''; url.hash = ''; link.href = url.href;
    }
  }
  async function consultar() {
    if (consultando) return;
    consultando = true;
    try {
      var r = await fetch('/config/respaldos/estado' + (solicitud ? '?solicitud=' + encodeURIComponent(solicitud) : ''), {cache:'no-store', headers:{Accept:'application/json'}});
      if (!r.ok) throw new Error('No se pudo consultar el estado. Comprueba tu sesión.');
      pintar(await r.json());
    } catch (e) { aviso.textContent = e.message; boton.disabled = true; lista.querySelectorAll('button').forEach(function (b) { b.disabled = true; }); }
    finally { consultando = false; }
  }
  async function simular(accion, archivo) {
    if (enviando || !estado || estado.pendiente || !estado.activo) return;
    enviando = true; boton.disabled = true; lista.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
    delete aviso.dataset.terminado;
    aviso.textContent = accion === 'restaurar' ? 'Preparando la restauración…' : 'Preparando simulación…';
    try {
      var r = await fetch('/config/respaldos/simular', {method:'POST',headers:{'X-CSRF-Token':document.body.getAttribute('data-csrf') || '',Accept:'application/json'},body:new URLSearchParams({accion:accion,archivo:archivo || ''})});
      var datos = await r.json(); if (!r.ok) throw new Error(datos.error || 'No se pudo iniciar la simulación.');
      guardar(datos.id); await consultar();
    } catch (e) { aviso.textContent = e.message; }
    finally { enviando = false; await consultar(); }
  }
  boton.addEventListener('click', function () { simular('copiar', ''); });
  lista.addEventListener('click', function (e) { var b = e.target.closest('[data-vencer], [data-restaurar]'); if (b && !b.disabled) simular(b.dataset.restaurar ? 'restaurar' : 'vencer', b.dataset.restaurar || b.dataset.vencer); });
  consultar(); setInterval(function () { if (!document.hidden) consultar(); }, 1000); setInterval(cuenta, 200);
})();
