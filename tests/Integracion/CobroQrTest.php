<?php

declare(strict_types=1);
namespace Tests\Integracion;
use App\Core\DB;
use App\Core\Reloj;
use App\Models\Factura;
use App\Models\Venta;
use Tests\CasoPrueba;
final class CobroQrTest extends CasoPrueba
{
    private array $pedido;
    private int $caja;
    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-22 12:00:00');
        $this->entrarComo('mariela','pin');
        $this->post('/caja/abrir',['turno_id'=>(int) DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"),'fondo'=>'200']);
        $this->caja=(int)DB::valor("SELECT id FROM cajas WHERE estado='abierta'");
        DB::consulta("UPDATE promociones SET activo=0");
        $pid=(int)DB::valor("SELECT id FROM productos WHERE codigo='PL-01'");
        DB::consulta('UPDATE productos SET precio=0.50 WHERE id=?',[$pid]);
        $qr=DB::insertar("INSERT INTO qr_diarios (fecha,monto,imagen,imagen_tipo,usuario_id,creado_en) VALUES ('2026-10-22',0.50,'imagen','image/png',1,'2026-10-22 10:00:00')");
        $this->pedido=['uuid'=>'12345678-1234-4123-a123-'.bin2hex(random_bytes(6)),'caja_id'=>(string)$this->caja,'tipo'=>'llevar','mesa'=>'','exacto'=>'0','recibido'=>'','total_esperado'=>'0.50','lineas'=>json_encode([['id'=>$pid,'cantidad'=>1,'nota'=>'']]),'metodo'=>'qr','qr_id'=>(string)$qr,'qr_monto'=>'0.50','comprobante'=>'0055123','pagador'=>'Cliente de prueba'];
    }
    public function testQrPendienteGeneraTicketsSinSumarEfectivoYReintentoNoDuplica(): void
    {
        $r=$this->post('/pos/cobrar',$this->pedido);
        $this->assertSame(200,$r->estado,$r->cuerpo);
        $id=json_decode($r->cuerpo,true)['id'];$f=Factura::buscar($id);
        $p=DB::fila('SELECT * FROM pagos WHERE venta_id=?',[$f['venta_id']]);
        $this->assertSame('qr',$p['metodo']);$this->assertSame('pendiente',$p['estado']);
        $this->assertSame('0.50',$p['monto']);$this->assertSame(0.0,Venta::efectivoCobrado($this->caja));
        $this->assertSame('0.50',Venta::resumen($this->caja)['qr_pendiente']);
        $this->assertSame('0055123',DB::valor('SELECT comprobante FROM qr_pagos WHERE pago_id=?',[$p['id']]));
        $html=$this->get('/facturas/'.$id)->cuerpo;
        $this->assertStringContainsString('QR · PENDIENTE',$html);
        $this->assertStringContainsString('ticket-comanda',$html);
        $this->assertStringContainsString('ticket-copia',$html);
        $this->assertStringContainsString('ticket-factura',$html);
        Reloj::fijar('2026-10-23 00:00:00');
        $otra=$this->post('/pos/cobrar',$this->pedido);
        $this->assertSame(200,$otra->estado,$otra->cuerpo);
        $this->assertSame($id,json_decode($otra->cuerpo,true)['id']);
        $this->assertSame(1,(int)DB::valor('SELECT COUNT(*) FROM pagos WHERE venta_id=?',[$f['venta_id']]));
    }
    public function testRechazaCobroConQrVencidoSinCrearVenta(): void
    {
        Reloj::fijar('2026-10-23 00:00:00');
        $n=DB::valor('SELECT COUNT(*) FROM ventas');
        $r=$this->post('/pos/cobrar',$this->pedido);
        $this->assertSame(422,$r->estado,$r->cuerpo);
        $this->assertSame($n,DB::valor('SELECT COUNT(*) FROM ventas'));
    }
    public function testRechazaMontoYComprobanteAusente(): void
    {
        foreach([['comprobante'=>''],['qr_monto'=>'0.40']] as $cambio){
            $r=$this->post('/pos/cobrar',$cambio+$this->pedido);
            $this->assertSame(422,$r->estado,$r->cuerpo);
        }
        $this->assertSame(0,(int) DB::valor('SELECT COUNT(*) FROM qr_pagos'));
    }
    public function testNoPermiteCambiarComprobanteDelMismoPedido(): void
    {
        $this->assertSame(200,$this->post('/pos/cobrar',$this->pedido)->estado);
        $this->assertSame(422,$this->post('/pos/cobrar',['comprobante'=>'otro']+$this->pedido)->estado);
    }
    public function testGuardaFotoOpcionalYRechazaFotoInvalida(): void
    {
        $r=$this->post('/pos/cobrar',['foto_qr'=>base64_encode('no es imagen')]+$this->pedido);
        $this->assertSame(422,$r->estado);
        $png='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=';
        $r=$this->post('/pos/cobrar',['foto_qr'=>$png]+$this->pedido);
        $this->assertSame(200,$r->estado,$r->cuerpo);
        $this->assertSame('image/png',DB::valor('SELECT foto_tipo FROM qr_pagos LIMIT 1'));
        $this->assertSame(base64_decode($png),DB::valor('SELECT foto FROM qr_pagos LIMIT 1'));
    }

    public function testQrVencidoPorSimulacionNoPermiteCobrar(): void
    {
        $this->entrarComo('admin');
        \App\Services\QrDiarioService::simular('vencer', (int)$this->pedido['qr_id']);
        Reloj::fijar('2026-10-22 12:00:05');
        $this->entrarComo('mariela', 'pin');
        $antes = DB::valor('SELECT COUNT(*) FROM ventas');
        $r = $this->post('/pos/cobrar', $this->pedido);
        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('QR venció', $r->cuerpo);
        $this->assertSame($antes, DB::valor('SELECT COUNT(*) FROM ventas'));
    }
    public function testImporteLibreCobraTotalRealYReintentoNoDuplica(): void
    {
        DB::consulta('UPDATE qr_diarios SET monto=0 WHERE id=?', [$this->pedido['qr_id']]);
        $pid=(int)DB::valor("SELECT id FROM productos WHERE codigo='PL-01'");
        DB::consulta('UPDATE productos SET precio=90 WHERE id=?', [$pid]);
        $pedido=['qr_monto'=>'90.00', 'total_esperado'=>'90.00']+$this->pedido;
        $r=$this->post('/pos/cobrar', $pedido);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        $id=json_decode($r->cuerpo,true)['id'];
        $f=Factura::buscar($id);
        $p=DB::fila('SELECT * FROM pagos WHERE venta_id=?', [$f['venta_id']]);
        $this->assertSame('90.00', $p['monto']);
        $this->assertSame('pendiente', $p['estado']);
        $this->assertSame(0.0, Venta::efectivoCobrado($this->caja));
        $this->assertSame('90.00', $f['snapshot']['pago_qr']['monto']);
        Reloj::fijar('2026-10-23 00:00:00');
        $r=$this->post('/pos/cobrar', $pedido);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertSame($id, json_decode($r->cuerpo,true)['id']);
        $this->assertSame(1, (int)DB::valor('SELECT COUNT(*) FROM pagos WHERE venta_id=?', [$f['venta_id']]));
    }
    public function testImporteLibreNoPermiteRegistrarCeroNiPagoIncompleto(): void
    {
        DB::consulta('UPDATE qr_diarios SET monto=0 WHERE id=?', [$this->pedido['qr_id']]);
        $antes=DB::valor('SELECT COUNT(*) FROM ventas');
        foreach (['0', '0.40', '0.60'] as $monto) {
            $this->assertSame(422, $this->post('/pos/cobrar', ['qr_monto'=>$monto]+$this->pedido)->estado);
        }
        $this->assertSame($antes, DB::valor('SELECT COUNT(*) FROM ventas'));
    }
    public function testImporteFijoSigueBloqueandoOtroTotalAunqueSeAltereLaPeticion(): void
    {
        $pid=(int)DB::valor("SELECT id FROM productos WHERE codigo='PL-01'");
        DB::consulta('UPDATE productos SET precio=90 WHERE id=?', [$pid]);
        $r=$this->post('/pos/cobrar', ['qr_monto'=>'90', 'total_esperado'=>'90']+$this->pedido);
        $this->assertSame(422, $r->estado);
    }
}
