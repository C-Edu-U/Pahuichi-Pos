(function(){
 'use strict';
 var lista=document.getElementById('conciliacion-lista'),aviso=document.getElementById('conciliacion-resultado');if(!lista)return;
 lista.addEventListener('submit',async function(e){
  var form=e.target.closest('[data-resolver-qr]');if(!form)return;e.preventDefault();if(form.dataset.ocupado)return;
  var boton=e.submitter;if(!boton)return;var estado=boton.value,datos=new FormData(form);datos.set('estado',estado);
  form.dataset.ocupado='1';form.querySelectorAll('button').forEach(function(b){b.disabled=true;});
  try{
   var r=await fetch(form.action,{method:'POST',headers:{Accept:'application/json','X-CSRF-Token':document.body.getAttribute('data-csrf')||''},body:datos});var d=await r.json();
   if(!r.ok)throw new Error(d.error||'No se pudo actualizar el pago.');
   var card=form.closest('[data-pago]'),etiqueta=card.querySelector('[data-estado-texto]');card.dataset.estado=d.estado;
   etiqueta.textContent=d.estado==='confirmado'?'Confirmado':'Rechazado';etiqueta.className='insignia '+(d.estado==='confirmado'?'insignia--ok':'insignia--peligro');form.remove();
   aviso.textContent='Pago '+(d.estado==='confirmado'?'confirmado.':'rechazado. La caja correspondiente recibirá el aviso.');aviso.className='alerta alerta--ok';aviso.hidden=false;
   if(lista.dataset.filtroEstado && lista.dataset.filtroEstado!==d.estado){card.remove();var total=document.getElementById('conciliacion-total'),n=Number(total.dataset.total||total.textContent.match(/^\d+/)[0]);total.dataset.total=String(Math.max(0,n-1));total.textContent=Math.max(0,n-1)+' pagos encontrados.';}
   document.getElementById('conciliacion-vacia').hidden=!!lista.querySelector('[data-pago]');
  }catch(error){aviso.textContent=error.message;aviso.className='alerta alerta--peligro';aviso.hidden=false;delete form.dataset.ocupado;form.querySelectorAll('button').forEach(function(b){b.disabled=false;});}
 });
})();
