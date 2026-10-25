/* HU-38: vencimiento real y demostración de 5 segundos, con la hora del servidor. */
(function () {
 'use strict';
 var datos = null, limite = 0, ultimo = 0, consultando = false, enviando = false, revision = 0;
 var $ = function(id){return document.getElementById(id);};
 function mostrar(q) {
  datos = q;
  limite = performance.now() + Math.max(0, q.vence - q.ahora) * 1000;
  actualizar();
 }
 function avisoVencido() {
  return datos && datos.simulado ? 'QR vencido por simulación. Solicita al administrador el QR de hoy.' : 'No hay QR vigente. Solicita al administrador el QR de hoy.';
 }
 function actualizar() {
  if (!datos) return;
  var valido = datos.vigente && performance.now() < limite;
  var aviso = $('qr-dia-aviso');
  if (aviso) { aviso.textContent = valido ? datos.aviso : avisoVencido(); aviso.hidden = valido; }
  var b = $('tab-qr');
  if (b) b.disabled = !valido;
  if (!valido && $('qr-cobro-imagen')) $('qr-cobro-imagen').hidden = true;
  document.querySelectorAll('[data-qr-dia-estado]').forEach(function(x){x.textContent = valido ? datos.aviso+' '+(Number(datos.monto)===0 ? 'Importe libre' : 'Monto fijo: Bs '+datos.monto)+' · '+datos.fecha : avisoVencido();});
  document.querySelectorAll('[data-qr-dia-imagen]').forEach(function(x){x.hidden=!valido;if(valido){var url='/qr/'+datos.id+'/imagen';if(x.getAttribute('src')!==url)x.src=url;}});
  if ($('qr-demo-cuenta')) {
   $('qr-demo-cuenta').textContent = datos.simulado ? (valido ? 'Demostración: el QR vence en '+Math.max(0, Math.ceil((limite-performance.now())/1000))+' segundos.' : 'Demostración terminada: QR vencido. El cobro por QR está deshabilitado en caja.') : (valido ? 'La vigencia habitual termina hoy a las 23:59:59.' : 'Carga un QR vigente de hoy para iniciar la demostración.');
   $('qr-demo-vencer').disabled = enviando || !valido || datos.simulado;
   $('qr-demo-restablecer').disabled = enviando || !datos.simulado || !datos.id;
  }
  if (window.PahuichiQr) window.PahuichiQr.actualizar(valido ? datos : null);
 }
 async function cargar() {
  if (consultando || enviando) return;
  consultando = true; ultimo = performance.now(); var lectura = revision;
  try {
   var r = await fetch('/qr/estado', {headers:{Accept:'application/json'}, cache:'no-store'});
   if (!r.ok) throw new Error('QR no disponible');
   var q = await r.json();
   // An action may have started while the GET was in flight. Never overwrite its result.
   if (!enviando && lectura === revision) mostrar(q);
  } catch (e) { if (!enviando && lectura === revision) mostrar({vigente:false,vence:0,ahora:0}); }
  finally { consultando = false; }
 }
 async function simular(accion) {
  if (enviando || !datos || !datos.id) return;
  enviando = true; revision++; actualizar();
  $('qr-demo-error').hidden = true;
  try {
   var r = await fetch('/qr/simular', {method:'POST', cache:'no-store', headers:{Accept:'application/json','X-CSRF-Token':document.body.getAttribute('data-csrf')||''}, body:new URLSearchParams({accion:accion,qr_id:String(datos.id)})});
   var q = await r.json();
   if (!r.ok) throw new Error(q.error || 'No se pudo realizar la demostración.');
   mostrar(q);
  } catch (e) {
   $('qr-demo-error').textContent = e.message;
   $('qr-demo-error').hidden = false;
  } finally { enviando = false; actualizar(); ultimo = 0; }
 }
 if ($('qr-demo-vencer')) {
  $('qr-demo-vencer').addEventListener('click',function(){simular('vencer');});
  $('qr-demo-restablecer').addEventListener('click',function(){simular('restablecer');});
 }
 // Preview is local: choosing or removing a file never changes the saved QR.
 var archivo = $('qr-imagen'), previaRevision = 0, imagenLista = false;
 if (archivo && $('qr-previa')) {
  function limpiarPrevia() {
   $('qr-previa').hidden = true;
   $('qr-previa-imagen').removeAttribute('src');
   $('qr-previa-nombre').textContent = '';
   $('qr-previa-detalle').textContent = '';
   $('qr-previa-error').hidden = true;
   $('qr-previa-estado').hidden = true;
   archivo.setCustomValidity('');
   $('qr-guardar').disabled = false;
   imagenLista = false;
  }
  function errorPrevia(mensaje) {
   $('qr-previa-error').textContent = mensaje;
   $('qr-previa-error').hidden = false;
   $('qr-previa-estado').hidden = true;
   archivo.setCustomValidity(mensaje);
   $('qr-guardar').disabled = true;
   imagenLista = false;
  }
  archivo.addEventListener('change', function () {
   var version = ++previaRevision, f = archivo.files[0];
   limpiarPrevia();
   if (!f) return;
   if (f.size === 0 || f.size > 2097152) {errorPrevia('Selecciona una imagen de hasta 2 MB.');return;}
   if (f.type && ['image/png','image/jpeg','image/webp'].indexOf(f.type) === -1) {errorPrevia('Selecciona una imagen PNG, JPG o WebP.');return;}
   $('qr-guardar').disabled = true;
   $('qr-previa-estado').textContent = 'Preparando vista previa…';
   $('qr-previa-estado').hidden = false;
   var lector = new FileReader();
   lector.onerror = function(){if(version===previaRevision)errorPrevia('No se pudo leer la imagen. Vuelve a seleccionarla.');};
   lector.onload = function(){
    if(version!==previaRevision)return;
    var temporal = new Image();
    temporal.onerror = function(){if(version===previaRevision)errorPrevia('El archivo no se puede mostrar como imagen. Selecciona otra imagen válida.');};
    temporal.onload = function(){
     if(version!==previaRevision)return;
     if(temporal.naturalWidth*temporal.naturalHeight>24000000){errorPrevia('La imagen supera los 24 millones de píxeles permitidos.');return;}
     $('qr-previa-imagen').src = String(lector.result);
     $('qr-previa-nombre').textContent = f.name;
     $('qr-previa-detalle').textContent = Math.ceil(f.size/1024)+' KB · '+temporal.naturalWidth+' × '+temporal.naturalHeight+' píxeles';
     $('qr-previa').hidden = false;
     $('qr-previa-estado').textContent = 'Imagen lista para revisar. Todavía no se guardó.';
     imagenLista = true;
     $('qr-guardar').disabled = false;
    };
    temporal.src = String(lector.result);
   };
   lector.readAsDataURL(f);
  });
  $('qr-previa-quitar').addEventListener('click',function(){++previaRevision;archivo.value='';limpiarPrevia();archivo.focus();});
  var subiendoQr = false;
  function resumenError(mensaje) {
   var bloque = $('qr-error-general');
   if (!bloque) {
    bloque = document.createElement('div');bloque.id='qr-error-general';bloque.className='alerta alerta--peligro';bloque.setAttribute('role','alert');bloque.tabIndex=-1;
    var titulo = document.createElement('p');titulo.className='alerta__titulo';titulo.textContent='No se guardó el QR';bloque.appendChild(titulo);
    var texto = document.createElement('p');texto.className='alerta__texto';bloque.appendChild(texto);
    $('qr-diario').before(bloque);
   }
   bloque.querySelector('.alerta__texto').textContent=mensaje;bloque.hidden=false;
  }
  function limpiarErrorCampo(id) {
   var campo=$(id),mensaje=$(id+'-error');
   if(mensaje){mensaje.hidden=true;mensaje.textContent='';}
   if(campo)campo.removeAttribute('aria-invalid');
   if($('qr-error-general'))$('qr-error-general').hidden=true;
  }
  ['qr-fecha','qr-monto','qr-imagen'].forEach(function(id){$(id).addEventListener('input',function(){limpiarErrorCampo(id);});});
  archivo.form.addEventListener('submit',async function(e){
   e.preventDefault();
   if(subiendoQr)return;
   if(!imagenLista){archivo.reportValidity();return;}
   var envio=new FormData(archivo.form),focoError=null;
   subiendoQr=true;
   ['qr-fecha','qr-monto','qr-imagen'].forEach(function(id){limpiarErrorCampo(id);$(id).disabled=true;});
   $('qr-guardar').disabled=true;$('qr-previa-quitar').disabled=true;archivo.form.setAttribute('aria-busy','true');
   try {
    var respuesta=await fetch('/qr/subir',{method:'POST',headers:{Accept:'application/json','X-CSRF-Token':document.body.getAttribute('data-csrf')||''},body:envio});
    if(respuesta.redirected)throw new Error('La sesión cambió. Inicia sesión nuevamente antes de guardar.');
    var resultado=await respuesta.json();
    if(!respuesta.ok){
     var errores=resultado.errores||{},campos=Object.keys(errores);
     campos.forEach(function(id){var campo=$(id),mensaje=$(id+'-error');if(campo){campo.setAttribute('aria-invalid','true');if(!focoError)focoError=campo;}if(mensaje){mensaje.textContent=errores[id];mensaje.hidden=false;}});
     resumenError(campos.length?errores[campos[0]]:(resultado.error||'No se pudo guardar el QR. La imagen sigue seleccionada.'));
     if(!focoError)focoError=$('qr-error-general');
     return;
    }
    if(!resultado.guardado)throw new Error('No se pudo confirmar el guardado. La imagen sigue seleccionada.');
    window.location.assign('/qr');
   } catch(error) {
    resumenError('No se pudo completar el guardado. La imagen sigue seleccionada; revisa la conexión o la sesión e inténtalo nuevamente.');focoError=$('qr-error-general');
   } finally {
    subiendoQr=false;['qr-fecha','qr-monto','qr-imagen'].forEach(function(id){$(id).disabled=false;});
    $('qr-guardar').disabled=!imagenLista;$('qr-previa-quitar').disabled=false;archivo.form.removeAttribute('aria-busy');if(focoError)focoError.focus();
   }
  });
 }
 window.PahuichiQrDia = {cargar:cargar, estado:function () { return datos && datos.vigente && performance.now() < limite ? datos : null; }};
 cargar();
 setInterval(function () { actualizar(); if (!document.hidden && performance.now()-ultimo > 1000) cargar(); }, 250);
 document.addEventListener('visibilitychange', function () { if (!document.hidden) cargar(); });
})();
