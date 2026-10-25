/* HU-44: anular la venta con PIN propio del administrador y motivo obligatorio. */
(function () {
 'use strict';
 var $=function(id){return document.getElementById(id);};
 if($('venta-anulada')){document.querySelectorAll('.tickets-herramientas a[href*="/pdf"],.tickets-herramientas a[href*="reimpresion"],.tickets-menu,[data-abrir-modal="modal-anular"]').forEach(function(e){e.hidden=true;});return;}
 var dialogo=$('modal-anular');if(!dialogo)return;
 var enviando=false,form=$('form-anular');
 function error(texto){$('anular-error').textContent=texto;$('anular-error').hidden=!texto;}
 dialogo.addEventListener('pahuichi:abrir',function(){if(enviando)return;$('anular-pin').value='';$('anular-motivo').value='';error('');setTimeout(function(){$('anular-pin').focus();},0);});
 dialogo.addEventListener('cancel',function(e){if(enviando)e.preventDefault();});
 form.addEventListener('submit',async function(e){
  e.preventDefault();if(enviando)return;error('');
  var pin=$('anular-pin').value,motivo=$('anular-motivo').value.trim();
  if(!/^\d{4,6}$/.test(pin)){error('Escribe tu PIN de administrador, de 4 a 6 dígitos.');$('anular-pin').focus();return;}
  if(motivo.length<3||motivo.length>120){error('Escribe el motivo de la anulación (de 3 a 120 caracteres).');$('anular-motivo').focus();return;}
  enviando=true;form.setAttribute('aria-busy','true');form.querySelectorAll('button').forEach(function(b){b.disabled=true;});
  try{
   var r=await fetch(form.action,{method:'POST',headers:{'X-CSRF-Token':document.body.getAttribute('data-csrf')||'',Accept:'application/json'},body:new URLSearchParams({pin:pin,motivo:motivo})});
   var d=await r.json();$('anular-pin').value='';
   if(!r.ok){error(d.error||'No se pudo anular la venta.');return;}
   window.location.reload();
  }catch(err){$('anular-pin').value='';error('No se confirmó la respuesta. Revisa la venta o reintenta; una anulación guardada no se duplica.');}
  finally{enviando=false;form.removeAttribute('aria-busy');form.querySelectorAll('button').forEach(function(b){b.disabled=false;});}
 });
})();
