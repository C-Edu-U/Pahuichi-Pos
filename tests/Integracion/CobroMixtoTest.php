<?php

declare(strict_types=1);
namespace Tests\Integracion;
use App\Core\{DB,Reloj};
use App\Models\{Caja,Factura,Venta};
use App\Services\{ArqueoService,ConciliacionQrService};
use Tests\CasoPrueba;

final class CobroMixtoTest extends CasoPrueba
{
    private array $pedido;
    private int $caja;
    protected function setUp(): void
    {
        parent::setUp();Reloj::fijar('2026-10-22 12:00:00');$this->entrarComo('mariela','pin');
        $this->post('/caja/abrir',['turno_id'=>(int)DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"),'fondo'=>'200']);
        $this->caja=(int)Caja::abierta()['id'];DB::consulta('UPDATE promociones SET activo=0');
        $pid=(int)DB::valor("SELECT id FROM productos WHERE codigo='PL-01'");DB::consulta('UPDATE productos SET precio=38 WHERE id=?',[$pid]);
        $qr=DB::insertar("INSERT INTO qr_diarios (fecha,monto,imagen,imagen_tipo,usuario_id,creado_en) VALUES ('2026-10-22',0.50,'imagen','image/png',1,'2026-10-22 10:00:00')");
        $this->pedido=['uuid'=>'12345678-1234-4123-a123-'.bin2hex(random_bytes(6)),'caja_id'=>(string)$this->caja,'tipo'=>'llevar','mesa'=>'','total_esperado'=>'38','metodo'=>'mixto','qr_id'=>(string)$qr,'qr_monto'=>'0.50','efectivo_monto'=>'37.50','recibido'=>'37.50','comprobante'=>'HU42-'.bin2hex(random_bytes(6)),'lineas'=>json_encode([['id'=>$pid,'cantidad'=>1,'nota'=>'']])];
    }
    private function cobrar(array $c=[]): array
    {
        $r=$this->post('/pos/cobrar',$c+$this->pedido);$this->assertSame(200,$r->estado,$r->cuerpo);
        return Factura::buscar(json_decode($r->cuerpo,true)['id']);
    }
    public function testUnaVentaConDosPagosYDesgloseEnTickets(): void
    {
        $f=$this->cobrar();$p=DB::filas('SELECT * FROM pagos WHERE venta_id=? ORDER BY id',[$f['venta_id']]);
        $this->assertCount(2,$p);$this->assertSame(['efectivo','qr'],array_column($p,'metodo'));
        $this->assertSame(['37.50','0.50'],array_column($p,'monto'));$this->assertSame(['confirmado','pendiente'],array_column($p,'estado'));
        $html=$this->get('/facturas/'.$f['id'])->cuerpo;$this->assertStringContainsString('Recibió el cajero en efectivo',$html);$this->assertStringContainsString('37,50',$html);$this->assertStringContainsString('Total recibido registrado',$html);$this->assertStringContainsString('Efectivo neto en caja',$html);$this->assertStringContainsString('QR pendiente de verificación',$html);
        $this->assertSame(1,(int)DB::valor('SELECT COUNT(*) FROM facturas WHERE venta_id=?',[$f['venta_id']]));
    }
    public function testImportesInvalidosNoCreanVentaNiPagos(): void
    {
        $ventas=DB::valor('SELECT COUNT(*) FROM ventas');$pagos=DB::valor('SELECT COUNT(*) FROM pagos');
        foreach([['efectivo_monto'=>'29.50'],['efectivo_monto'=>'38'],['efectivo_monto'=>'0'],['qr_monto'=>'0'],['qr_monto'=>'1','efectivo_monto'=>'37'],['recibido'=>'37.49'],['efectivo_monto'=>'29.50','exacto'=>'1']] as $c){
            $r=$this->post('/pos/cobrar',$c+$this->pedido);$this->assertSame(422,$r->estado,$r->cuerpo);
        }
        $this->assertSame($ventas,DB::valor('SELECT COUNT(*) FROM ventas'));$this->assertSame($pagos,DB::valor('SELECT COUNT(*) FROM pagos'));
    }
    public function testArqueoSoloSumaParteEfectivoYDescuentaCambio(): void
    {
        $caja=Caja::buscar($this->caja);$antes=ArqueoService::esperado($caja);$f=$this->cobrar(['recibido'=>'50']);
        $this->assertSame('12.50',$f['snapshot']['cambio']);$this->assertSame(37.5,Venta::efectivoCobrado($this->caja));$this->assertSame($antes+37.5,ArqueoService::esperado($caja));
        $this->assertSame('0.50',Venta::resumen($this->caja)['qr_pendiente']);
        $qr=(int)DB::valor("SELECT id FROM pagos WHERE venta_id=? AND metodo='qr'",[$f['venta_id']]);$this->entrarComo('admin');ConciliacionQrService::resolver($qr,'rechazado');
        $this->assertSame($antes+37.5,ArqueoService::esperado($caja));
    }
    public function testReintentoNoDuplicaYComprobanteSigueUnico(): void
    {
        $f=$this->cobrar();Reloj::fijar('2026-10-23 00:00:00');$this->assertSame($f['id'],$this->cobrar()['id']);
        $this->assertSame(2,(int)DB::valor('SELECT COUNT(*) FROM pagos WHERE venta_id=?',[$f['venta_id']]));Reloj::fijar('2026-10-22 13:00:00');
        $r=$this->post('/pos/cobrar',['uuid'=>'12345678-1234-4123-a123-'.bin2hex(random_bytes(6))]+$this->pedido);$this->assertSame(422,$r->estado);$this->assertArrayHasKey('comprobante_duplicado',json_decode($r->cuerpo,true));
    }
    public function testImporteLibreYQrVencido(): void
    {
        DB::consulta('UPDATE qr_diarios SET monto=0 WHERE id=?',[$this->pedido['qr_id']]);$f=$this->cobrar(['qr_monto'=>'18','efectivo_monto'=>'20','recibido'=>'20']);
        $this->assertSame('18.00',$f['snapshot']['pago_qr']['monto']);$this->assertSame('20.00',$f['snapshot']['pago_efectivo']['monto']);Reloj::fijar('2026-10-23 00:00:00');
        $this->assertSame(422,$this->post('/pos/cobrar',['uuid'=>'12345678-1234-4123-a123-'.bin2hex(random_bytes(6)),'comprobante'=>'OTRO']+$this->pedido)->estado);
    }
}
