<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Controller,Peticion,Respuesta,ErrorHttp,Reloj};
use App\Models\Turno;
use App\Services\ConciliacionQrService;
use DomainException;
final class ConciliacionController extends Controller
{
    public function index(Peticion $p): Respuesta
    {
        $f=['fecha'=>$p->consulta('fecha',Reloj::ahora()->format('Y-m-d')),'turno'=>$p->consulta('turno'),'estado'=>$p->consulta('estado','pendiente')];
        try{$datos=ConciliacionQrService::listar($f,(int)$p->consulta('pagina','1'));}
        catch(DomainException $e){return $this->vista('conciliacion/index',['titulo'=>'Conciliación QR','modulo'=>'conciliacion','css'=>['modulos.css','conciliacion.css'],'js'=>['conciliacion.js'],'error'=>$e->getMessage(),'f'=>$f,'filas'=>[],'total'=>0,'pagina'=>1,'paginas'=>1,'turnos'=>Turno::todos()],422);}
        return $this->vista('conciliacion/index',$datos+['titulo'=>'Conciliación QR','modulo'=>'conciliacion','css'=>['modulos.css','conciliacion.css'],'js'=>['conciliacion.js'],'turnos'=>Turno::todos()]);
    }
    public function resolver(Peticion $p): Respuesta
    {
        try{
            $id=filter_var($p->parametro('id'),FILTER_VALIDATE_INT);$estado=$p->entrada('estado');
            if(!$id || !is_string($estado))throw new DomainException('Selecciona un pago QR.');
            $r=ConciliacionQrService::resolver($id,$estado);
            if($p->esperaJson())return Respuesta::json($r)->cabecera('Cache-Control','no-store');
            return $this->redirigirConAviso('/conciliacion','Pago QR '.$r['estado'].'.');
        }catch(DomainException $e){return Respuesta::json(['error'=>$e->getMessage()],422)->cabecera('Cache-Control','no-store');}
    }
    public function foto(Peticion $p): Respuesta
    {
        $id=filter_var($p->parametro('id'),FILTER_VALIDATE_INT);
        $f=$id?ConciliacionQrService::foto($id):null;
        if(!$f)throw ErrorHttp::noEncontrado();
        return new Respuesta($f['foto'],200,['Content-Type'=>$f['foto_tipo'],'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
    public function avisos(Peticion $p): Respuesta
    {
        return Respuesta::json(['avisos'=>ConciliacionQrService::avisos()])->cabecera('Cache-Control','private, no-store');
    }
    public function enterado(Peticion $p): Respuesta
    {
        $id=filter_var($p->parametro('id'),FILTER_VALIDATE_INT);
        if(!$id)return Respuesta::json(['error'=>'Aviso inválido.'],422);
        ConciliacionQrService::enterado($id);return Respuesta::json(['ok'=>true])->cabecera('Cache-Control','no-store');
    }
}
