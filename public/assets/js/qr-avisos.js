(function(){
 'use strict';
 var zona=document.getElementById('qr-avisos-caja');if(!zona)return;
 var ocupado=false,ultima='';
 async function cargar(){
  if(ocupado||document.hidden)return;ocupado=true;
  try{
   var r=await fetch('/conciliacion/avisos',{headers:{Accept:'application/json'},cache:'no-store'});
   if(!r.ok)return;
   var d=await r.json(),firma=JSON.stringify(d.avisos);if(firma===ultima)return;
   ultima=firma;zona.replaceChildren();
   d.avisos.forEach(function(a){
    var panel=document.createElement('div');panel.className='qr-aviso';panel.setAttribute('role','alert');
    var titulo=document.createElement('strong');titulo.textContent='Pago QR rechazado';panel.append(titulo);
    var texto=document.createElement('p');texto.textContent='Pedido '+a.venta_id+' · caja '+a.caja_id+' · comprobante '+a.comprobante+' · Saldo por cobrar: Bs '+a.monto;panel.append(texto);
    var acciones=document.createElement('div');acciones.className='qr-aviso__acciones';
    var ver=document.createElement('a');ver.className='boton';ver.href='/facturas/'+a.factura_id;ver.target='_blank';ver.rel='noopener';ver.textContent='Ver venta';acciones.append(ver);
    var b=document.createElement('button');b.className='boton';b.type='button';b.textContent='Entendido';
    b.addEventListener('click',async function(){
     b.disabled=true;
     try{
      var x=await fetch('/conciliacion/avisos/'+a.id+'/visto',{method:'POST',headers:{Accept:'application/json','X-CSRF-Token':document.body.getAttribute('data-csrf')||''}});
      if(!x.ok)throw new Error('No se pudo marcar el aviso.');
      panel.remove();zona.hidden=!zona.childElementCount;ultima='';
     }catch(e){b.textContent='Reintentar';}finally{b.disabled=false;}
    });
    acciones.append(b);panel.append(acciones);zona.append(panel);
   });
   zona.hidden=!d.avisos.length;
  }catch(e){}finally{ocupado=false;}
 }
 cargar();setInterval(cargar,5000);document.addEventListener('visibilitychange',function(){if(!document.hidden)cargar();});
})();
