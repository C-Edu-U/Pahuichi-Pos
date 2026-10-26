<?php
declare(strict_types=1);
if (getenv('HU66_AISLADO') !== '1' || getenv('DB_NOMBRE') !== 'pahuichi_qa' || getenv('DB_HOST') !== 'db') {
    fwrite(STDERR, "Esta preparación solo acepta la base y el host del Compose de HU-66.\n"); exit(2);
}
require '/var/www/pahuichi/vendor/autoload.php';
use App\Core\{App,Auth,DB,Migrador,Reloj,Sesion};
use App\Models\{Caja,Factura,Usuario};
use App\Services\VentaService;
App::preparar();Sesion::enMemoria();
// Es la base DESECHABLE de QA; nunca se conecta a la habitual.
$m=new Migrador(DB::conexion(),App::raiz().'/database');$m->vaciar();$m->migrar();$m->sembrarSiVacia();
Auth::iniciar(Usuario::buscarPorUsuario('mariela'),'clave');
$turno=(int)DB::valor("SELECT id FROM turnos WHERE clave='mediodia'");
$caja=Caja::crear($turno,Reloj::ahora()->format('Y-m-d'),Auth::id(),Auth::id(),200,Reloj::sql());
$producto=(int)DB::valor("SELECT id FROM productos WHERE codigo='PL-01'");
DB::consulta('UPDATE productos SET precio=38,activo=1,agotado=0 WHERE id=?',[$producto]);
DB::consulta('UPDATE promociones SET activo=0');
$f=VentaService::cobrar(['uuid'=>'12345678-1234-4123-a123-'.bin2hex(random_bytes(6)),'caja_id'=>(string)$caja,'tipo'=>'llevar','mesa'=>'','total_esperado'=>'38','exacto'=>'1','lineas'=>[['id'=>$producto,'cantidad'=>1,'nota'=>'']]]);
$f=Factura::buscar((int)$f['id']);
$url='/verificar?'.http_build_query(['nit'=>$f['snapshot']['negocio']['nit'],'cuf'=>$f['cuf'],'numero'=>$f['numero'],'monto'=>$f['monto_total']]);
file_put_contents('/evidencias/objetivo.json',json_encode(['producto'=>$producto,'caja'=>$caja,'precio'=>'38.00','factura'=>$f['id'],'verificar'=>$url],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "Datos propios de HU-66 preparados: una caja y una venta de referencia. No equivalen a los datos de la HU-65.\n";
