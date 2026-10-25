(function () {
 'use strict';
 var $ = function(id){return document.getElementById(id);};
 var metodo='efectivo', qr=null, total=0, foto='', leyendo=false, fallo='', bloqueado=false, lectura=0, inicializado=null;
 function centavos(valor) {
  if(!/^\d{1,8}(?:[.,]\d{1,2})?$/.test(valor))return null;
  var p=valor.replace(',','.').split('.');return Number(p[0])*100+Number(((p[1]||'')+'00').slice(0,2));
 }
 function decimal(c){return (c/100).toFixed(2);}
 function error(texto){$('qr-cobro-error').textContent=texto;$('qr-cobro-error').hidden=!texto;}
 function calcularEfectivo() {
  var parteQr=centavos($('qr-cobro-monto').value), t=Math.round(total*100);
  $('mixto-efectivo').value=parteQr!==null && parteQr>0 && parteQr<t ? decimal(t-parteQr) : '';
 }
 function prepararMixto() {
  if(!qr || inicializado===qr.id)return;
  var fijo=centavos(String(qr.monto));
  $('qr-cobro-monto').value=fijo>0 ? decimal(fijo) : '';
  $('mixto-recibido').value='';
  inicializado=qr.id;
 }
 function revisarMixto() {
  var parteQr=centavos($('qr-cobro-monto').value), recibido=centavos($('mixto-recibido').value), t=Math.round(total*100);
  if(parteQr===null || parteQr<=0)return 'Escribe un monto QR mayor que cero, con hasta dos decimales.';
  if(parteQr>=t)return 'En Mixto, el monto QR debe ser menor que el total. Para pagar todo por QR, elige Cobrar con QR.';
  if(recibido===null)return 'Escribe cuánto efectivo te entrega el cliente.';
  var efectivo=t-parteQr;
  if(recibido<efectivo)return 'Faltan Bs '+decimal(efectivo-recibido)+' en efectivo. Debes recibir al menos Bs '+decimal(efectivo)+'.';
  return '';
 }
 function seleccionar(m) {
  if(bloqueado)return;
  metodo=m;
  ['efectivo','qr','mixto'].forEach(function(x){$('tab-'+x).setAttribute('aria-selected',String(x===m));});
  $('cobro-efectivo').hidden=m!=='efectivo';$('cobro-qr').hidden=m==='efectivo';$('cobro-mixto').hidden=m!=='mixto';
  $('cobro-qr').setAttribute('aria-labelledby','tab-'+(m==='mixto'?'mixto':'qr'));
  inicializado=null;error('');pintar();
 }
 function pintar() {
  $('tab-efectivo').disabled=bloqueado;
  ['qr','mixto'].forEach(function(m){$('tab-'+m).disabled=bloqueado || !qr;});
  $('cobro-qr').inert=bloqueado;
  if(qr){$('qr-cobro-imagen').src='/qr/'+qr.id+'/imagen';$('qr-cobro-imagen').hidden=false;}
  else {$('qr-cobro-imagen').hidden=true;inicializado=null;}
  var libre=qr && Number(qr.monto)===0, mixto=metodo==='mixto';
  $('qr-cobro-monto').readOnly=!mixto || !libre;
  if(mixto){prepararMixto();calcularEfectivo();}
  else $('qr-cobro-monto').value=qr?(libre?total.toFixed(2):qr.monto):'';
  $('qr-cobro-aviso').textContent=!qr?'El QR no está vigente. Pide al administrador el QR de hoy.':(libre?'QR de importe libre. El cliente debe pagar el monto indicado en QR.':'QR de hoy por Bs '+qr.monto+'.')+' Vence a las 23:59:59.'+(!mixto && !libre && Math.round(Number(qr.monto)*100)!==Math.round(total*100)?' Su monto no coincide con este pedido.':'');
  var invalido=mixto?revisarMixto():'';
  if(mixto)$('mixto-resumen').textContent=invalido || 'Efectivo + QR = Bs '+total.toFixed(2)+' · Cambio: Bs '+decimal(centavos($('mixto-recibido').value)-centavos($('mixto-efectivo').value))+'. Solo la parte en efectivo suma al arqueo.';
  // POS administra el bloqueo durante envío/reintento. No impedir recuperar un cobro guardado.
  if(!bloqueado)$('confirmar-cobro').disabled=metodo!=='efectivo' && (!qr || !!invalido || leyendo || !!fallo);
 }
 ['efectivo','qr','mixto'].forEach(function(m){$('tab-'+m).addEventListener('click',function(){if(m==='efectivo'||qr)seleccionar(m);});});
 ['qr-cobro-monto','mixto-recibido'].forEach(function(id){$(id).addEventListener('input',function(){error('');pintar();});});
 $('qr-foto').addEventListener('change',function(){
  var id=++lectura,f=this.files[0];foto='';fallo='';leyendo=false;
  if(!f){pintar();return;}
  if(f.size>1048576){fallo='La foto admite hasta 1 MB.';this.value='';error(fallo);pintar();return;}
  leyendo=true;pintar();var r=new FileReader();
  r.onload=function(){if(id!==lectura)return;foto=String(r.result).split(',')[1]||'';leyendo=false;pintar();};
  r.onerror=function(){if(id!==lectura)return;leyendo=false;fallo='No se pudo leer la foto. Selecciónala otra vez.';error(fallo);pintar();};r.readAsDataURL(f);
 });
 var alerta=document.createElement('div');alerta.className='alerta alerta--peligro';alerta.id='qr-comprobante-duplicado';alerta.setAttribute('role','alert');alerta.tabIndex=-1;alerta.hidden=true;
 $('qr-comprobante').closest('.campo').after(alerta);
 function duplicado(original) {
  alerta.replaceChildren();alerta.hidden=!original;
  if(!original)return;
  var mensaje=document.createElement('p');mensaje.textContent='Comprobante ya utilizado. Venta original: pedido '+original.venta_id+' · factura N° '+original.numero+' · Bs '+original.monto+' · '+original.fecha;alerta.append(mensaje);
  if(original.tickets){var enlace=document.createElement('a');enlace.className='boton';enlace.href=original.tickets;enlace.target='_blank';enlace.rel='noopener';enlace.textContent='Ver venta original';alerta.append(enlace);}
  alerta.scrollIntoView({block:'nearest'});alerta.focus();
 }
 $('qr-comprobante').addEventListener('input',function(){duplicado(null);error('');});
 window.PahuichiQr={
  duplicado:duplicado,error:error,
  actualizar:function(q){qr=q;pintar();},
  abrir:function(t){total=t;seleccionar('efectivo');window.PahuichiQrDia.cargar();},
  bloquear:function(v){bloqueado=v;pintar();},
  datos:function(){
   if(metodo==='efectivo')return {};
   qr=window.PahuichiQrDia.estado();
   if(!qr)throw new Error('El QR ya no está vigente. Solicita el QR de hoy.');
   var parte=metodo==='mixto'?centavos($('qr-cobro-monto').value):Math.round(total*100),fijo=centavos(String(qr.monto));
   if(metodo==='mixto'){calcularEfectivo();var problema=revisarMixto();if(problema)throw new Error(problema);}
   if(fijo>0 && fijo!==parte)throw new Error('El monto pagado por QR no coincide con el importe fijo del QR.');
   if(!$('qr-comprobante').value.trim())throw new Error('Escribe el número del comprobante QR.');
   if(leyendo)throw new Error('Espera a que termine de cargar la foto.');
   if(fallo)throw new Error(fallo);
   var d={metodo:metodo,qr_id:String(qr.id),qr_monto:decimal(parte),comprobante:$('qr-comprobante').value.trim(),pagador:$('qr-pagador').value.trim(),foto_qr:foto};
   if(metodo==='mixto'){d.efectivo_monto=decimal(centavos($('mixto-efectivo').value));d.recibido=decimal(centavos($('mixto-recibido').value));}
   return d;
  },
  reset:function(){duplicado(null);lectura++;foto='';fallo='';leyendo=false;$('qr-comprobante').value='';$('qr-pagador').value='';$('qr-foto').value='';bloqueado=false;seleccionar('efectivo');}
 };
})();
