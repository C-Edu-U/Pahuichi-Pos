import http from 'k6/http';
import {check, sleep, fail} from 'k6';
import {Counter, Trend} from 'k6/metrics';
const base=__ENV.BASE_URL || 'https://app';
if(base!=='https://app')throw new Error('Solo se permite el servicio app de la red privada de HU-66.');
const caso=JSON.parse(open('/evidencias/objetivo.json'));
const ventas=new Counter('ventas_confirmadas'),tiempo=new Trend('cobro_ms',true);
export const options={insecureSkipTLSVerify:true,noCookiesReset:true,stages:[{duration:'10s',target:1},{duration:'20s',target:5},{duration:'10s',target:5},{duration:'10s',target:0}],gracefulRampDown:'10s',thresholds:{checks:['rate==1'],http_req_failed:['rate<0.01']},summaryTrendStats:['avg','med','p(90)','p(95)','max']};
let csrf='';
function token(r){return r.html().find('input[name="_csrf"]').first().attr('value')||r.html().find('body').attr('data-csrf')||'';}
export default function(){
 if(!csrf){
  let r=http.get(base+'/login?modo=clave');const t=token(r);if(!check(r,{'login disponible':x=>x.status===200&&!!t}))fail('No se obtuvo CSRF.');
  r=http.post(base+'/login',{_csrf:t,modo:'clave',usuario:'mariela',clave:'Pahuichi2026'});
  if(!check(r,{'sesion cajero':x=>x.status===200&&x.url.endsWith('/pos')}))fail('No se inició sesión como cajero.');
  csrf=token(r);if(!csrf)fail('No se obtuvo el CSRF de caja.');
 }
 let r=http.get(base+'/pos');check(r,{'POS autenticado':x=>x.status===200&&x.body.includes('id="pos"')});
 const uuid='xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,c=>{const v=Math.floor(Math.random()*16);return(c==='x'?v:(v&3)|8).toString(16);});
 r=http.post(base+'/pos/cobrar',{uuid,caja_id:String(caso.caja),tipo:'llevar',mesa:'',total_esperado:caso.precio,exacto:'1',lineas:JSON.stringify([{id:caso.producto,cantidad:1,nota:''}])},{headers:{Accept:'application/json','X-CSRF-Token':csrf}});
 tiempo.add(r.timings.duration);let d={};try{d=r.json();}catch(e){}
 const ok=check(r,{'venta persistida':x=>x.status===200&&Number.isInteger(d.id)&&Number.isInteger(d.numero)});if(ok){ventas.add(1);r=http.get(base+'/facturas/'+d.id);check(r,{'tickets disponibles':x=>x.status===200&&x.body.includes('ticket-factura')});}
 r=http.get(base+caso.verificar);check(r,{'verificacion publica':x=>x.status===200&&x.body.includes(caso.verificar.split('cuf=')[1].split('&')[0])});sleep(1);
}
export function handleSummary(data){return {'/evidencias/k6.json':JSON.stringify(data,null,2),stdout:JSON.stringify({ventas:data.metrics.ventas_confirmadas?.values.count||0,cobro_ms:data.metrics.cobro_ms?.values,checks:data.metrics.checks?.values},null,2)+'\n'};}
