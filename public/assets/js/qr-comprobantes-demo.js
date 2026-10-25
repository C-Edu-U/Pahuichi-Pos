(function(){
 'use strict';
 var campo=document.getElementById('qr-demo-comprobante');if(!campo)return;
 var resultado=document.getElementById('qr-demo-comprobante-resultado'),ocupado=false;
 async function probar(accion){
  if(ocupado)return;ocupado=true;resultado.hidden=false;resultado.textContent='Comprobando…';
  var botones=['qr-demo-mismo-dia','qr-demo-otro-dia'].map(function(id){return document.getElementById(id);});botones.forEach(function(b){b.disabled=true;});
  try{
   var r=await fetch('/qr/comprobantes/demostrar',{method:'POST',cache:'no-store',headers:{Accept:'application/json','X-CSRF-Token':document.body.getAttribute('data-csrf')||''},body:new URLSearchParams({accion:accion,comprobante:campo.value})});
   var d=await r.json();if(!r.ok)throw new Error(d.error||'No se pudo completar la demostración.');
   resultado.replaceChildren();
   [d.aceptado ? 'El comprobante no figura como utilizado.' : 'RECHAZADO: el comprobante ya fue utilizado; cambiar de día no permite reutilizarlo.',
    'Comprobante: '+d.comprobante+' · registro original: '+d.fecha_registro+' · fecha evaluada: '+d.fecha_evaluada,
    'Venta original: pedido '+d.original.venta_id+' · factura N° '+d.original.numero+' · Bs '+d.original.monto,
    'No se guardó ninguna venta ni se cambió la fecha del sistema.'].forEach(function(t){var p=document.createElement('p');p.textContent=t;resultado.append(p);});
  }catch(e){resultado.textContent=e.message;}finally{ocupado=false;botones.forEach(function(b){b.disabled=false;});}
 }
 document.getElementById('qr-demo-mismo-dia').addEventListener('click',function(){probar('mismo_dia');});
 document.getElementById('qr-demo-otro-dia').addEventListener('click',function(){probar('otro_dia');});
})();
