<?php

declare(strict_types=1);
namespace Tests\Integracion;
use App\Core\{Auth,DB,Reloj};
use App\Models\{Caja,Factura,Venta};
use App\Services\{ConciliacionQrService,SaldoQrService,ArqueoService};
use Tests\CasoPrueba;
final class CobroSaldoQrTest extends CasoPrueba
{
    private array $f;
    private int $caja;
    private int $qr;
    protected function setUp(): void
    {
        parent::setUp();Reloj::fijar('2026-10-22 12:00:00');$this->entrarComo('mariela','pin');
        $this->post('/caja/abrir',['turno_id'=>(int)DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"),'fondo'=>'200']);$this->caja=(int)Caja::abierta()['id'];
        DB::consulta('UPDATE promociones SET activo=0');$pid=(int)DB::valor("SELECT id FROM productos WHERE codigo='PL-01'");DB::consulta('UPDATE productos SET precio=38 WHERE id=?',[$pid]);
        $qr=DB::insertar("INSERT INTO qr_diarios (fecha,monto,imagen,imagen_tipo,usuario_id,creado_en) VALUES ('2026-10-22',0,'imagen','image/png',1,'2026-10-22 10:00:00')");
        $d=['uuid'=>'12345678-1234-4123-a123-'.bin2hex(random_bytes(6)),'caja_id'=>(string)$this->caja,'tipo'=>'llevar','mesa'=>'','total_esperado'=>'38','metodo'=>'mixto','qr_id'=>(string)$qr,'qr_monto'=>'0.50','efectivo_monto'=>'37.50','recibido'=>'37.50','comprobante'=>'REC-'.bin2hex(random_bytes(6)),'lineas'=>json_encode([['id'=>$pid,'cantidad'=>1,'nota'=>'']])];
        $r=$this->post('/pos/cobrar',$d);$this->assertSame(200,$r->estado,$r->cuerpo);$this->f=Factura::buscar(json_decode($r->cuerpo,true)['id']);$this->qr=(int)DB::valor("SELECT id FROM pagos WHERE venta_id=? AND metodo='qr'",[$this->f['venta_id']]);
        $this->entrarComo('admin');ConciliacionQrService::resolver($this->qr,'rechazado');$this->entrarComo('mariela','pin');
    }
    private function ruta(): string {return '/facturas/'.$this->f['id'].'/cobrar-saldo';}
    public function testCobroConCambioSinNuevaVentaFacturaNiDobleConteo(): void
    {
        $antes=ArqueoService::esperado(Caja::buscar($this->caja));$snapshot=$this->f['snapshot_json'];
        $r=$this->post($this->ruta(),['importe'=>'0.50','recibido'=>'1']);$this->assertSame(200,$r->estado,$r->cuerpo);$d=json_decode($r->cuerpo,true);$this->assertSame('0.50',$d['cambio']);
        $this->assertSame(0.0,(float)Factura::buscar((int)$this->f['id'])['saldo_por_cobrar']);$this->assertSame($antes+0.5,ArqueoService::esperado(Caja::buscar($this->caja)));
        $res=Venta::resumen($this->caja);$this->assertSame('38.00',$res['total']);$this->assertSame('38.00',$res['efectivo']);$this->assertSame('0.00',$res['qr_rechazado']);$this->assertSame('0.00',$res['qr_confirmado']);
        $this->assertSame(1,(int)DB::valor('SELECT COUNT(*) FROM ventas'));$this->assertSame(1,(int)DB::valor('SELECT COUNT(*) FROM facturas'));$this->assertSame(3,(int)DB::valor('SELECT COUNT(*) FROM pagos'));
        $this->assertSame($snapshot,Factura::buscar((int)$this->f['id'])['snapshot_json']);$this->assertSame('rechazado',DB::valor('SELECT estado FROM pagos WHERE id=?',[$this->qr]));
        $html=$this->get('/facturas/'.$this->f['id'])->cuerpo;$this->assertStringContainsString('Saldo cobrado en efectivo',$html);$this->assertStringNotContainsString('id="form-cobro-saldo"',$html);$this->assertCount(0,ConciliacionQrService::avisos());
        $this->assertSame(200,$this->post($this->ruta(),['importe'=>'0.50','recibido'=>'1'])->estado);$this->assertSame(3,(int)DB::valor('SELECT COUNT(*) FROM pagos'));$this->assertSame(1,(int)DB::valor("SELECT COUNT(*) FROM bitacora WHERE accion='venta.cobro_saldo_qr'"));
        $this->assertSame(422,$this->post($this->ruta(),['importe'=>'0.50','recibido'=>'2'])->estado);
    }
    public function testImportesPermisosYCajaCerradaNoGeneranCobros(): void
    {
        foreach([['importe'=>'0.50','recibido'=>'0.49'],['importe'=>'1','recibido'=>'1'],['importe'=>'0','recibido'=>'1'],['importe'=>'0.50','recibido'=>'x']] as $d)$this->assertSame(422,$this->post($this->ruta(),$d)->estado);
        $this->assertSame(403,$this->post($this->ruta(),['_csrf'=>'incorrecto','importe'=>'0.50','recibido'=>'1'])->estado);
        $this->entrarComo('jorge','pin');$this->assertSame(403,$this->post($this->ruta(),['importe'=>'0.50','recibido'=>'1'])->estado);
        $this->entrarComo('mariela','pin');Caja::cerrar($this->caja,Reloj::sql());$this->assertSame(422,$this->post($this->ruta(),['importe'=>'0.50','recibido'=>'1'])->estado);$this->assertSame(0,(int)DB::valor('SELECT COUNT(*) FROM qr_recuperaciones'));
    }
    public function testNoCobraVentaAnuladaNiQrQueNoFueRechazado(): void
    {
        DB::consulta("UPDATE pagos SET estado='pendiente' WHERE id=?",[$this->qr]);$this->assertSame(422,$this->post($this->ruta(),['importe'=>'0.50','recibido'=>'1'])->estado);
        DB::consulta("UPDATE pagos SET estado='rechazado' WHERE id=?",[$this->qr]);DB::consulta("UPDATE ventas SET estado='anulada' WHERE id=?",[$this->f['venta_id']]);$this->assertSame(422,$this->post($this->ruta(),['importe'=>'0.50','recibido'=>'1'])->estado);
    }
}
