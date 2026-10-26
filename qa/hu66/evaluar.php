<?php
declare(strict_types=1);
if (getenv('HU66_AISLADO')!=='1' || getenv('DB_NOMBRE')!=='pahuichi_qa') {fwrite(STDERR,"Evaluador exclusivo del entorno HU-66.\n");exit(2);}
date_default_timezone_set('America/La_Paz');
$tipo=$argv[1]??'';$dir='/evidencias';$ok=false;$resultado=['tipo'=>$tipo,'fecha'=>date(DATE_ATOM),'aprobado'=>false];
try {
 if($tipo==='cobertura'){
  $areas=json_decode(file_get_contents('/qa/areas.json'),true,512,JSON_THROW_ON_ERROR);$datos=[];
  if(!is_file($dir.'/clover.xml'))throw new RuntimeException('No se generó Clover. Revisa phpunit.log; no hay cobertura demostrada.');
  $xml=simplexml_load_file($dir.'/clover.xml');if(!$xml)throw new RuntimeException('Clover inválido.');
  foreach($xml->xpath('//file') as $f){$ruta=str_replace('\\','/',(string)$f['name']);$pos=strpos($ruta,'/app/');if($pos===false)continue;$ruta=substr($ruta,$pos+1);$datos[$ruta]=[(int)$f->metrics['statements'],(int)$f->metrics['coveredstatements']];}
  $ok=trim(file_get_contents($dir.'/phpunit-exit.txt'))==='0';$resultado['phpunit_aprobado']=$ok;$resultado['metrica']='Cobertura de líneas ejecutables; mínimo 60% en cada área, incluyendo archivos sin ejecutar.';
  foreach($areas as $nombre=>$archivos){$total=0;$cubierto=0;$faltan=[];$detalle=[];foreach($archivos as $archivo){if(!isset($datos[$archivo])){$faltan[]=$archivo;continue;}[$t,$c]=$datos[$archivo];$total+=$t;$cubierto+=$c;$detalle[$archivo]=['lineas'=>$t,'cubiertas'=>$c];}$porcentaje=$total>0?100*$cubierto/$total:0;$cumple=$total>0&&$cubierto*100>=$total*60&&$faltan===[];$ok=$ok&&$cumple;$resultado['areas'][$nombre]=['porcentaje'=>round($porcentaje,2),'lineas'=>$total,'cubiertas'=>$cubierto,'cumple'=>$cumple,'faltantes'=>$faltan,'archivos'=>$detalle];echo "$nombre: ".number_format($porcentaje,2)."% ".($cumple?'CUMPLE':'NO CUMPLE')."\n";}
 }elseif($tipo==='seguridad'){
  $resultado['detalle']=json_decode(file_get_contents($dir.'/zap.json'),true,512,JSON_THROW_ON_ERROR);$z=$resultado['detalle'];
  if(!isset($z['version_zap'],$z['roles'],$z['urls'],$z['alertas'])||!is_array($z['alertas'])||!is_array($z['urls'])||count($z['urls'])===0)throw new RuntimeException('Falta evidencia del motor ZAP o del alcance recorrido.');
  foreach($z['alertas'] as $a)if(!isset($a['riskcode'])||!in_array($a['riskcode'],[0,1,2,3],true))throw new RuntimeException('Hay una alerta con riesgo desconocido. Requiere revision.');
  $roles=array_column(array_filter($z['roles'],fn($r)=>($r['sesion_inicio_y_fin']??false)===true),'usuario_qa');
  $altas=array_values(array_filter($z['alertas'],fn($a)=>$a['riskcode']>=3));
  $ok=($z['completo']??false)===true&&($z['autenticacion_verificada']??false)===true&&in_array('admin',$roles,true)&&in_array('mariela',$roles,true)&&count($altas)===0;
  $resultado['alertas_altas']=count($altas);echo 'ZAP: '.count($altas).' alertas altas. '.($ok?'CUMPLE':'PENDIENTE / NO CUMPLE')."\n";
 }elseif($tipo==='carga'){
  $k=json_decode(file_get_contents($dir.'/k6.json'),true,512,JSON_THROW_ON_ERROR);$ventas=(int)($k['metrics']['ventas_confirmadas']['values']['count']??0);$ok=trim(file_get_contents($dir.'/k6-exit.txt'))==='0'&&$ventas>0;
  foreach($k['metrics']??[] as $m)foreach($m['thresholds']??[] as $t)$ok=$ok&&($t['ok']??false);
  require '/var/www/pahuichi/vendor/autoload.php';\App\Core\App::preparar();
  $persistidas=(int)\App\Core\DB::valor("SELECT COUNT(*) FROM ventas WHERE estado='cobrada'");$facturas=(int)\App\Core\DB::valor('SELECT COUNT(*) FROM facturas');$numeros=(int)\App\Core\DB::valor('SELECT COUNT(DISTINCT numero) FROM facturas');
  $ok=$ok&&$persistidas===$ventas+1&&$facturas===$ventas+1&&$numeros===$facturas;
  $resultado+=['ventas_confirmadas_k6'=>$ventas,'ventas_base_incluye_referencia'=>$persistidas,'facturas'=>$facturas,'numeros_distintos'=>$numeros,'parametros'=>'Hasta 5 usuarios virtuales, 50 segundos. Perfil técnico: checks=100% y errores HTTP<1% para validar el ensayo. Se registran tiempos y percentiles sin imponer un SLA no especificado por el criterio.','metricas'=>$k];echo "k6: $ventas cobros confirmados; $persistidas ventas con referencia. ".($ok?'EVIDENCIA REGISTRADA Y CONSISTENTE':'NO CUMPLE')."\n";
 }else{throw new RuntimeException('Selecciona cobertura, seguridad o carga.');}
}catch(Throwable $e){$resultado['error']=$e->getMessage();echo $e->getMessage()."\n";$ok=false;}
$resultado['aprobado']=$ok;
file_put_contents($dir.'/resultado-'.$tipo.'.json',json_encode($resultado,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));
$esc=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$titulos=['cobertura'=>'Escenario 1: PHPUnit y cobertura','seguridad'=>'Escenario 2: Seguridad con OWASP ZAP','carga'=>'Escenario 3: Carga con k6'];
$titulo='HU-66 · '.($titulos[$tipo]??$tipo);$estado=$ok?'CUMPLE EN ESTA EJECUCIÓN':'PENDIENTE / NO CUMPLE';
$resumen='';
if($tipo==='cobertura'){
 $resumen='<table><thead><tr><th>Área</th><th>Cobertura de líneas</th><th>Mínimo</th><th>Resultado</th></tr></thead><tbody>';
 foreach($resultado['areas']??[] as $n=>$a)$resumen.='<tr><td>'.$esc($n).'</td><td>'.$esc($a['porcentaje']).' %</td><td>60 %</td><td>'.($a['cumple']?'Cumple':'No cumple').'</td></tr>';
 $resumen.='</tbody></table><p>PHPUnit: '.(($resultado['phpunit_aprobado']??false)?'ejecución correcta':'hay errores o no se completó').'.</p><p><a href="cobertura/index.html">Ver cobertura por archivo</a> · <a href="phpunit.log">Ver salida completa de PHPUnit</a></p>';
}elseif($tipo==='seguridad'){
 $resumen='<p>Alertas altas: <strong>'.$esc($resultado['alertas_altas']??'Sin resultado').'</strong>. Solo cumple con un escaneo completo, autenticación comprobada y ninguna alerta alta pendiente.</p><p><a href="zap-original.html">Informe original de ZAP</a> · <a href="zap-motor.log">Registro del motor</a></p>';
}elseif($tipo==='carga'){
 $k=$resultado['metricas']['metrics']??[];$val=static fn(string $m,string $v):mixed=>$k[$m]['values'][$v]??'Sin resultado';
 $resumen='<table><tbody><tr><th>Cobros confirmados por k6</th><td>'.$esc($resultado['ventas_confirmadas_k6']??'Sin resultado').'</td></tr><tr><th>Ventas en base, incluida referencia</th><td>'.$esc($resultado['ventas_base_incluye_referencia']??'Sin resultado').'</td></tr><tr><th>Tiempo medio de cobro (ms)</th><td>'.$esc($val('cobro_ms','avg')).'</td></tr><tr><th>Percentil 95 de cobro (ms)</th><td>'.$esc($val('cobro_ms','p(95)')).'</td></tr><tr><th>Proporción de solicitudes HTTP fallidas</th><td>'.$esc($val('http_req_failed','rate')).'</td></tr></tbody></table><p>Perfil: hasta 5 usuarios virtuales, 50 segundos. Se registran los tiempos; el criterio recibido no fija un tiempo máximo.</p><p><a href="k6.json">Ver métricas originales de k6</a></p>';
}
$error=isset($resultado['error'])?'<p class="error">'.$esc($resultado['error']).'</p>':'';
$json=$esc(json_encode($resultado,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));
$html='<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$esc($titulo).'</title><style>body{font:16px system-ui;line-height:1.5;max-width:1000px;margin:32px auto;padding:0 20px;color:#173328;background:#fafcfb}h1{font-size:26px}h2{padding:16px;background:'.($ok?'#dff4e8':'#fff0d0').';border-radius:8px;font-size:21px}table{width:100%;border-collapse:collapse;background:white}td,th{padding:12px;border:1px solid #cbd8d0;text-align:left}a{color:#146841}pre{white-space:pre-wrap;overflow-wrap:anywhere;font-size:13px;padding:16px;background:#edf3ef}.error{color:#a12b21}summary{cursor:pointer;font-weight:bold;margin:20px 0}</style></head><body><h1>'.$esc($titulo).'</h1><p>'.$esc($resultado['fecha']).' · Hora de Bolivia</p><h2>'.$estado.'</h2>'.$error.$resumen.'<p>Corresponde a la versión identificada por <a href="instantanea.sha256">instantanea.sha256</a>. Conserva la carpeta completa para la revisión de Eduardo.</p><details><summary>Detalle técnico y evidencia completa</summary><pre>'.$json.'</pre></details></body></html>';
file_put_contents($dir.'/resultado-'.$tipo.'.html',$html);
exit($ok?0:1);
