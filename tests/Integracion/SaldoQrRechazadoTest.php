<?php

declare(strict_types=1);
namespace Tests\Integracion;
use App\Core\{DB,Reloj};
use App\Models\{Caja,Factura,Venta};
use App\Services\ConciliacionQrService;
use Tests\CasoPrueba;
final class SaldoQrRechazadoTest extends CasoPrueba
{
    private array $pedido;
    private int $caja;
    protected function setUp(): void
    {
        parent::setUp();Reloj::fijar('2026-10-22 12:00:00');$this->entrarComo('mariela','pin');
        $this->post('/caja/abrir',['turno_id'=>(int)DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"),'fondo'=>'200']);
        $this->caja=(int)Caja::abierta()['id'];DB::consulta('UPDATE promociones SET activo=0');
        $pid=(int)DB::valor("SELECT id FROM productos WHERE codigo='PL-01'");DB::consulta('UPDATE productos SET precio=38 WHERE id=?',[$pid]);
        $qr=DB::insertar("INSERT INTO qr_diarios (fecha,monto,imagen,imagen_tipo,usuario_id,creado_en) VALUES ('2026-10-22',0,'imagen','image/png',1,'2026-10-22 10:00:00')");
        $this->pedido=['uuid'=>'12345678-1234-4123-a123-'.bin2hex(random_bytes(6)),'caja_id'=>(string)$this->caja,'tipo'=>'llevar','mesa'=>'','total_esperado'=>'38','metodo'=>'qr','qr_id'=>(string)$qr,'qr_monto'=>'38','comprobante'=>'HU43-'.bin2hex(random_bytes(6)),'lineas'=>json_encode([['id'=>$pid,'cantidad'=>1,'nota'=>'']])];
    }
    public function testRechazoAvisaYLaVentaMuestraSaldoAunqueSeMarqueEntendido(): void
    {
        $r=$this->post('/pos/cobrar',$this->pedido);$this->assertSame(200,$r->estado,$r->cuerpo);
        $id=json_decode($r->cuerpo,true)['id'];$f=Factura::buscar($id);$snapshot=$f['snapshot_json'];
        $pago=(int)DB::valor("SELECT id FROM pagos WHERE venta_id=? AND metodo='qr'",[$f['venta_id']]);
        $this->assertSame(0.0,(float)$f['saldo_por_cobrar']);
        $this->entrarComo('admin');ConciliacionQrService::resolver($pago,'rechazado');ConciliacionQrService::resolver($pago,'rechazado');
        $this->entrarComo('mariela','pin');$avisos=ConciliacionQrService::avisos();$this->assertCount(1,$avisos);$this->assertSame('38.00',$avisos[0]['monto']);
        $this->assertSame(38.0,(float)Factura::buscar($id)['saldo_por_cobrar']);
        $this->assertStringContainsString('Saldo por cobrar: Bs 38,00',$this->get('/facturas/'.$id)->cuerpo);
        $this->assertStringContainsString('Saldo por cobrar: 38,00',$this->get('/ventas')->cuerpo);
        ConciliacionQrService::enterado((int)$avisos[0]['id']);$this->assertCount(0,ConciliacionQrService::avisos());
        $this->assertSame(38.0,(float)Factura::buscar($id)['saldo_por_cobrar']);$this->assertSame($snapshot,Factura::buscar($id)['snapshot_json']);
        $resumen=Venta::resumen($this->caja);$this->assertSame('0.00',$resumen['qr_confirmado']);$this->assertSame('38.00',$resumen['qr_rechazado']);
    }
    public function testMixtoSoloDebeLaParteQrSinDuplicarVentaNiAlterarEfectivo(): void
    {
        $r=$this->post('/pos/cobrar',['metodo'=>'mixto','qr_monto'=>'0.50','efectivo_monto'=>'37.50','recibido'=>'50']+$this->pedido);
        $this->assertSame(200,$r->estado,$r->cuerpo);$id=json_decode($r->cuerpo,true)['id'];$f=Factura::buscar($id);
        $pago=(int)DB::valor("SELECT id FROM pagos WHERE venta_id=? AND metodo='qr'",[$f['venta_id']]);
        $this->entrarComo('admin');ConciliacionQrService::resolver($pago,'rechazado');
        $this->assertSame(0.5,(float)Factura::buscar($id)['saldo_por_cobrar']);$this->assertSame(37.5,Venta::efectivoCobrado($this->caja));
        $this->assertSame(1,(int)DB::valor('SELECT COUNT(*) FROM ventas'));$this->assertSame(2,(int)DB::valor('SELECT COUNT(*) FROM pagos'));
        $this->entrarComo('jorge','pin');$this->assertSame(403,$this->get('/facturas/'.$id)->estado);$this->assertCount(0,Factura::ultimas(\App\Core\Auth::id()));$this->assertCount(0,ConciliacionQrService::avisos());
        DB::consulta("UPDATE ventas SET estado='anulada' WHERE id=?",[$f['venta_id']]);$this->assertSame(0.0,(float)Factura::buscar($id)['saldo_por_cobrar']);
    }
    public function testQrConfirmadoNoGeneraSaldoNiAviso(): void
    {
        $r=$this->post('/pos/cobrar',$this->pedido);$this->assertSame(200,$r->estado,$r->cuerpo);$id=json_decode($r->cuerpo,true)['id'];$f=Factura::buscar($id);
        $pago=(int)DB::valor('SELECT id FROM pagos WHERE venta_id=?',[$f['venta_id']]);$this->entrarComo('admin');ConciliacionQrService::resolver($pago,'confirmado');
        $this->assertSame(0.0,(float)Factura::buscar($id)['saldo_por_cobrar']);$this->assertSame('38.00',Venta::resumen($this->caja)['qr_confirmado']);
        $this->assertCount(0,DB::filas('SELECT * FROM qr_avisos'));
    }
}
