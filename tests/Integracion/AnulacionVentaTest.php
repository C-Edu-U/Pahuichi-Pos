<?php

declare(strict_types=1);
namespace Tests\Integracion;
use App\Core\DB;
use App\Core\Reloj;
use App\Models\{Caja,Factura,Venta};
use App\Services\{AnulacionVentaService,ArqueoService};
use Tests\CasoPrueba;
final class AnulacionVentaTest extends CasoPrueba
{
    private array $pedido;
    private array $factura;
    private int $caja;
    protected function setUp(): void
    {
        parent::setUp();Reloj::fijar('2026-10-22 12:00:00');$this->entrarComo('mariela','pin');
        $this->post('/caja/abrir',['turno_id'=>(int)DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"),'fondo'=>'200']);
        $this->caja=(int)Caja::abierta()['id'];DB::consulta('UPDATE promociones SET activo=0');
        $pid=(int)DB::valor("SELECT id FROM productos WHERE codigo='PL-01'");DB::consulta('UPDATE productos SET precio=38 WHERE id=?',[$pid]);
        $this->pedido=['uuid'=>'12345678-1234-4123-a123-'.bin2hex(random_bytes(6)),'caja_id'=>(string)$this->caja,'tipo'=>'llevar','mesa'=>'','total_esperado'=>'38','exacto'=>'1','lineas'=>json_encode([['id'=>$pid,'cantidad'=>1,'nota'=>'']])];
        $r=$this->post('/pos/cobrar',$this->pedido);$this->assertSame(200,$r->estado,$r->cuerpo);$this->factura=Factura::buscar(json_decode($r->cuerpo,true)['id']);
        $this->entrarComo('admin');
    }
    private function ruta(): string {return '/facturas/'.$this->factura['id'].'/anular';}
    public function testAnulaConMotivoSinReutilizarNumeroYRegistraUnaSolaBitacora(): void
    {
        $r=$this->post($this->ruta(),['pin'=>'1234','motivo'=>'Pedido duplicado']);$this->assertSame(200,$r->estado,$r->cuerpo);
        $f=Factura::buscar((int)$this->factura['id']);$this->assertSame('anulada',$f['estado']);$this->assertSame($this->factura['snapshot_json'],$f['snapshot_json']);
        $this->assertSame('anulada',DB::valor('SELECT estado FROM ventas WHERE id=?',[$f['venta_id']]));
        $b=DB::fila("SELECT * FROM bitacora WHERE accion='venta.anulacion'");$d=json_decode($b['detalle'],true);
        $this->assertSame('Pedido duplicado',$d['motivo']);$this->assertSame('1',(string)$b['usuario_id']);$this->assertArrayHasKey('fecha',$d);$this->assertStringNotContainsString('1234',$b['detalle']);
        $this->post($this->ruta(),['pin'=>'1234','motivo'=>'Otra razon']);$this->assertSame(1,(int)DB::valor("SELECT COUNT(*) FROM bitacora WHERE accion='venta.anulacion'"));
        $this->assertSame('Pedido duplicado',AnulacionVentaService::detalle((int)$f['id'])['motivo']);
        $html=$this->get('/facturas/'.$f['id'])->cuerpo;$this->assertStringContainsString('FACTURA ANULADA',$html);$this->assertSame(3,substr_count($html,'>ANULADA</p>'));
        $this->assertSame(422,$this->get('/facturas/'.$f['id'].'/pdf')->estado);
        $this->entrarComo('mariela','pin');$r=$this->post('/pos/cobrar',['uuid'=>'12345678-1234-4123-a123-'.bin2hex(random_bytes(6))]+$this->pedido);$this->assertSame(200,$r->estado,$r->cuerpo);$this->assertSame((int)$f['numero']+1,json_decode($r->cuerpo,true)['numero']);
    }
    public function testCierreExcluyeVentaYEfectivoYLaMuestraEnAnulaciones(): void
    {
        $c=Caja::buscar($this->caja);$this->assertSame(238.0,ArqueoService::esperado($c));
        AnulacionVentaService::anular((int)$this->factura['id'],'1234','Pedido duplicado');
        $this->assertSame(200.0,ArqueoService::esperado($c));$this->assertSame(0.0,Venta::efectivoCobrado($this->caja));
        $r=Venta::resumen($this->caja);$this->assertSame('0.00',$r['total']);$this->assertSame('38.00',$r['anuladas']);$this->assertSame(1,(int)DB::valor('SELECT COUNT(*) FROM pagos'));
    }
    public function testPinMotivoPermisosYCsrfProtegenLaAccion(): void
    {
        foreach([['pin'=>'0000','motivo'=>'Pedido duplicado'],['pin'=>'1234','motivo'=>''],['pin'=>'1234','motivo'=>'ab'],['pin'=>'1234','motivo'=>str_repeat('x',121)]] as $d)$this->assertSame(422,$this->post($this->ruta(),$d)->estado);
        $this->assertSame(403,$this->post($this->ruta(),['_csrf'=>'incorrecto','pin'=>'1234','motivo'=>'Pedido duplicado'])->estado);
        $this->entrarComo('mariela','pin');$this->assertSame(403,$this->post($this->ruta(),['pin'=>'1234','motivo'=>'Pedido duplicado'])->estado);
        $this->assertSame('emitida',Factura::buscar((int)$this->factura['id'])['estado']);$this->assertSame(0,(int)DB::valor('SELECT COUNT(*) FROM venta_anulaciones'));
    }
    public function testCajaCerradaNoCambiaHastaReabrir(): void
    {
        Caja::cerrar($this->caja,Reloj::sql());$this->assertSame(422,$this->post($this->ruta(),['pin'=>'1234','motivo'=>'Pedido duplicado'])->estado);$this->assertSame('emitida',Factura::buscar((int)$this->factura['id'])['estado']);
    }
}
