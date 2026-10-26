(function(){
 'use strict';
 var form=document.getElementById('form-cobro-saldo');if(!form)return;
 var input=document.getElementById('saldo-recibido'),boton=document.getElementById('saldo-confirmar'),error=document.getElementById('saldo-error'),resumen=document.getElementById('saldo-cambio'),enviando=false;
 function centavos(v){if(!/^\d{1,8}(?:[.,]\d{1,2})?$/.test(v))return null;var p=v.replace(',','.').split('.');return Number(p[0])*100+Number(((p[1]||'')+'00').slice(0,2));}
 var saldo=centavos(form.getAttribute('data-saldo'));
 function revisar(){var rec=centavos(input.value);boton.disabled=enviando||rec===null||rec<saldo;resumen.textContent=rec===null?'Escribe cuánto efectivo entrega el cliente.':rec<saldo?'Faltan Bs '+((saldo-rec)/100).toFixed(2)+'.':'Cambio: Bs '+((rec-saldo)/100).toFixed(2)+'.';}
 input.addEventListener('input',function(){error.hidden=true;revisar();});
 form.addEventListener('submit',async function(e){
  e.preventDefault();if(enviando)return;revisar();if(boton.disabled)return;
  enviando=true;boton.disabled=true;input.readOnly=true;error.hidden=true;
  try{
   var r=await fetch(form.action,{method:'POST',headers:{Accept:'application/json','X-CSRF-Token':document.body.getAttribute('data-csrf')||''},body:new URLSearchParams({importe:form.getAttribute('data-saldo'),recibido:input.value})});
   var d=await r.json();if(!r.ok)throw new Error(d.error||'No se pudo cobrar el saldo.');window.location.reload();
  }catch(e){error.textContent=e instanceof SyntaxError?'No se confirmó la respuesta. Reintenta con los mismos datos; el cobro no se duplicará.':e.message;error.hidden=false;}
  finally{enviando=false;input.readOnly=false;revisar();}
 });
 revisar();
})();
