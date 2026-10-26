<?php
declare(strict_types=1);
namespace Tests\Integracion;
use App\Core\{DB,Reloj};
use App\Models\Factura;
use App\Services\ComprobanteQrService;
use Tests\CasoPrueba;
final class ComprobanteQrDuplicadoTest extends CasoPrueba
{
    private array $pedido;
    protected function setUp(): void
    {
        parent::setUp();Reloj::fijar('2026-10-22 12:00:00');$this->entrarComo('mariela','pin');
        $this->post('/caja/abrir',['turno_id'=>(int)DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"),'fondo'=>'200']);
        $caja=(int)DB::valor("SELECT id FROM cajas WHERE estado='abierta'");
        DB::consulta('UPDATE promociones SET activo=0');$pid=(int)DB::valor("SELECT id FROM productos WHERE codigo='PL-01'");DB::consulta('UPDATE productos SET precio=90 WHERE id=?',[$pid]);
        $qr=DB::insertar("INSERT INTO qr_diarios (fecha,monto,imagen,imagen_tipo,usuario_id,creado_en) VALUES ('2026-10-22',0,'imagen','image/png',1,'2026-10-22 10:00:00')");
        $this->pedido=['uuid'=>$this->uuid(),'caja_id'=>(string)$caja,'tipo'=>'llevar','mesa'=>'','total_esperado'=>'90.00','exacto'=>'0','lineas'=>json_encode([['id'=>$pid,'cantidad'=>1,'nota'=>'']]),'metodo'=>'qr','qr_id'=>(string)$qr,'qr_monto'=>'90.00','comprobante'=>'0098123'];
    }
    private function uuid(): string { return '12345678-1234-4123-a123-'.bin2hex(random_bytes(6)); }
    public function testDuplicadoMismoDiaMuestraOriginalSinCrearOtraVentaNiPago(): void
    {
        $r=$this->post('/pos/cobrar',$this->pedido);$this->assertSame(200,$r->estado,$r->cuerpo);$original=json_decode($r->cuerpo,true);
        $ventas=DB::valor('SELECT COUNT(*) FROM ventas');$pagos=DB::valor('SELECT COUNT(*) FROM pagos');
        $r=$this->post('/pos/cobrar',['uuid'=>$this->uuid()]+$this->pedido);$this->assertSame(422,$r->estado,$r->cuerpo);$d=json_decode($r->cuerpo,true);
        $this->assertSame($original['numero'],$d['comprobante_duplicado']['numero']);$this->assertSame($original['tickets'],$d['comprobante_duplicado']['tickets']);
        $this->assertSame($ventas,DB::valor('SELECT COUNT(*) FROM ventas'));$this->assertSame($pagos,DB::valor('SELECT COUNT(*) FROM pagos'));
    }
    public function testOtroDiaRechazaElMismoComprobanteConQrVigente(): void
    {
        $r=$this->post('/pos/cobrar',$this->pedido);$this->assertSame(200,$r->estado,$r->cuerpo);$primero=json_decode($r->cuerpo,true)['id'];
        Reloj::fijar('2026-10-23 12:00:00');$qr=DB::insertar("INSERT INTO qr_diarios (fecha,monto,imagen,imagen_tipo,usuario_id,creado_en) VALUES ('2026-10-23',0,'imagen','image/png',1,'2026-10-23 10:00:00')");
        $r=$this->post('/pos/cobrar',['uuid'=>$this->uuid(),'qr_id'=>(string)$qr]+$this->pedido);$this->assertSame(422,$r->estado,$r->cuerpo);$original=json_decode($r->cuerpo,true)['comprobante_duplicado'];$f=Factura::buscar($primero);$this->assertSame((int)$f['numero'],$original['numero']);
        $this->assertSame(1,(int)DB::valor("SELECT COUNT(*) FROM qr_comprobantes WHERE comprobante='0098123'"));
    }
    public function testReintentoNoEsOtraVentaYCerosInicialesSeConservan(): void
    {
        $r=$this->post('/pos/cobrar',$this->pedido);$this->assertSame(200,$r->estado,$r->cuerpo);$id=json_decode($r->cuerpo,true)['id'];
        $this->assertSame($id,json_decode($this->post('/pos/cobrar',$this->pedido)->cuerpo,true)['id']);
        $this->assertNull(ComprobanteQrService::buscar('98123'));
        $this->assertNotNull(ComprobanteQrService::buscar('0098123'));
    }
    public function testDemoSoloAdministradorYNoModificaFechasNiVentas(): void
    {
        $this->assertSame(200,$this->post('/pos/cobrar',$this->pedido)->estado);
        $this->assertSame(403,$this->post('/qr/comprobantes/demostrar',['comprobante'=>'0098123','accion'=>'otro_dia'])->estado);
        $this->entrarComo('admin');$ventas=DB::valor('SELECT COUNT(*) FROM ventas');$pagos=DB::valor('SELECT COUNT(*) FROM pagos');
        foreach(['mismo_dia'=>false,'otro_dia'=>false] as $accion=>$esperado){
            $r=$this->post('/qr/comprobantes/demostrar',['comprobante'=>'0098123','accion'=>$accion]);$this->assertSame(200,$r->estado,$r->cuerpo);$this->assertSame($esperado,json_decode($r->cuerpo,true)['aceptado']);
        }
        $this->assertSame($ventas,DB::valor('SELECT COUNT(*) FROM ventas'));$this->assertSame($pagos,DB::valor('SELECT COUNT(*) FROM pagos'));$this->assertSame('2026-10-22',Reloj::ahora()->format('Y-m-d'));
    }
}
