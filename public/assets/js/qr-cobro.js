(function () {
 'use strict';
 var $ = function(id){return document.getElementById(id);};
 var metodo = 'efectivo', qr = null, total = 0, foto = '', leyendo = false, fallo = '', bloqueado = false, lectura = 0;
 function seleccionar(m) {
  if (bloqueado) return;
  metodo = m;
  ['efectivo','qr'].forEach(function(x){ $('tab-'+x).setAttribute('aria-selected',String(x===m)); $('cobro-'+x).hidden=x!==m; });
  pintar();
 }
 function pintar() {
  $('tab-efectivo').disabled = bloqueado;
  $('tab-qr').disabled = bloqueado || !qr;
  $('cobro-qr').inert = bloqueado;
  if (qr) {
   $('qr-cobro-imagen').src = '/qr/'+qr.id+'/imagen';
   $('qr-cobro-imagen').hidden = false;
  } else $('qr-cobro-imagen').hidden = true;
  var libre = qr && Number(qr.monto)===0;
  $('qr-cobro-monto').value = qr ? (libre ? total.toFixed(2) : qr.monto) : '';
  $('qr-cobro-aviso').textContent = !qr ? 'El QR no está vigente. Pide al administrador el QR de hoy.' : (libre ? 'QR de importe libre · el cliente debe pagar Bs '+total.toFixed(2)+'.' : 'QR de hoy por Bs '+qr.monto+'.')+' Vence a las 23:59:59.' + (!libre && Math.round(Number(qr.monto)*100)!==Math.round(total*100) ? ' Su monto no coincide con este pedido.' : '');
 }
 $('tab-efectivo').addEventListener('click',function(){seleccionar('efectivo');});
 $('tab-qr').addEventListener('click',function(){if(qr) seleccionar('qr');});
 $('qr-foto').addEventListener('change',function(){
  var id = ++lectura, f=this.files[0];foto='';fallo='';leyendo=false;
  if(!f)return;
  if(f.size>1048576){fallo='La foto admite hasta 1 MB.';this.value='';$('qr-cobro-aviso').textContent=fallo;return;}
  leyendo=true;var r=new FileReader();
  r.onload=function(){if(id!==lectura)return;foto=String(r.result).split(',')[1]||'';leyendo=false;};
  r.onerror=function(){if(id!==lectura)return;leyendo=false;fallo='No se pudo leer la foto. Selecciónala otra vez.';};r.readAsDataURL(f);
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
 $('qr-comprobante').addEventListener('input',function(){duplicado(null);});
 window.PahuichiQr = {
  duplicado:duplicado,
  actualizar:function(q){qr=q;pintar();},
  abrir:function(t){total=t;seleccionar('efectivo');window.PahuichiQrDia.cargar();},
  bloquear:function(v){bloqueado=v;pintar();},
  datos:function(){
   if(metodo!=='qr')return {};
   qr=window.PahuichiQrDia.estado();
   if(!qr)throw new Error('El QR ya no está vigente. Solicita el QR de hoy.');
   if(Number(qr.monto)!==0 && Math.round(Number(qr.monto)*100)!==Math.round(total*100))throw new Error('El monto del QR no coincide con el pedido. Solicita un QR por ese importe.');
   if(!$('qr-comprobante').value.trim())throw new Error('Escribe el número del comprobante QR.');
   if(leyendo)throw new Error('Espera a que termine de cargar la foto.');
   if(fallo)throw new Error(fallo);
   return {metodo:'qr',qr_id:String(qr.id),qr_monto:Number(qr.monto)===0 ? total.toFixed(2) : qr.monto,comprobante:$('qr-comprobante').value.trim(),pagador:$('qr-pagador').value.trim(),foto_qr:foto};
  },
  reset:function(){duplicado(null);lectura++;foto='';fallo='';leyendo=false;$('qr-comprobante').value='';$('qr-pagador').value='';$('qr-foto').value='';bloqueado=false;seleccionar('efectivo');}
 };
})();
