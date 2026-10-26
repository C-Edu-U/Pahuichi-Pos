<?php

declare(strict_types=1);
namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Services\QrDiarioService;
use Tests\CasoPrueba;

final class QrSimulacionTest extends CasoPrueba
{
    private int $qr;
    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-22 12:00:00');
        $this->entrarComo('admin');
        $this->qr = DB::insertar("INSERT INTO qr_diarios (fecha,monto,imagen,imagen_tipo,usuario_id,creado_en) VALUES ('2026-10-22',0.50,'imagen','image/png',1,'2026-10-22 10:00:00')");
    }
    public function testVenceEnCincoSegundosSinCambiarLaFecha(): void
    {
        $s = QrDiarioService::simular('vencer', $this->qr);
        $this->assertTrue($s['vigente']);
        $this->assertTrue($s['simulado']);
        $this->assertSame(5, $s['vence'] - $s['ahora']);
        Reloj::fijar('2026-10-22 12:00:04');
        $this->assertTrue(QrDiarioService::estado()['vigente']);
        Reloj::fijar('2026-10-22 12:00:05');
        $this->assertFalse(QrDiarioService::estado()['vigente']);
        $this->assertSame('2026-10-22', DB::valor('SELECT fecha FROM qr_diarios WHERE id=?', [$this->qr]));
        $this->assertSame(404, $this->get('/qr/'.$this->qr.'/imagen')->estado);
    }
    public function testClicsRepetidosNoReinicianElTiempoNiLlenanBitacora(): void
    {
        $uno = QrDiarioService::simular('vencer', $this->qr);
        Reloj::fijar('2026-10-22 12:00:02');
        $dos = QrDiarioService::simular('vencer', $this->qr);
        $this->assertSame($uno['vence'], $dos['vence']);
        $this->assertSame(1, (int) DB::valor("SELECT COUNT(*) FROM bitacora WHERE accion='qr.simular_vencimiento' AND entidad_id=?", [(string)$this->qr]));
    }
    public function testRestableceVigenciaNormalSinCrearOtroQr(): void
    {
        QrDiarioService::simular('vencer', $this->qr);
        Reloj::fijar('2026-10-22 12:00:05');
        $s = QrDiarioService::simular('restablecer', $this->qr);
        $this->assertTrue($s['vigente']);
        $this->assertFalse($s['simulado']);
        $this->assertSame($this->qr, $s['id']);
        $this->assertNull(DB::valor('SELECT vencimiento_demo FROM qr_diarios WHERE id=?', [$this->qr]));
        Reloj::fijar('2026-10-23 00:00:00');
        $this->assertFalse(QrDiarioService::estado()['vigente']);
    }
    public function testCajeroNoPuedeSimularNiRestablecer(): void
    {
        $this->entrarComo('mariela', 'pin');
        foreach (['vencer', 'restablecer'] as $accion) {
            $this->assertSame(403, $this->post('/qr/simular', ['accion'=>$accion,'qr_id'=>(string)$this->qr])->estado);
        }
        $this->assertStringNotContainsString('id="qr-demo-vencer"', $this->get('/qr')->cuerpo);
    }
    public function testNoRestableceQrDeAyerNiAfectaUnoNuevo(): void
    {
        QrDiarioService::simular('vencer', $this->qr);
        $nuevo = DB::insertar("INSERT INTO qr_diarios (fecha,monto,imagen,imagen_tipo,usuario_id,creado_en) VALUES ('2026-10-22',0.50,'nuevo','image/png',1,'2026-10-22 12:00:01')");
        $this->assertSame(422, $this->post('/qr/simular', ['accion'=>'restablecer','qr_id'=>(string)$this->qr])->estado);
        $this->assertSame($nuevo, QrDiarioService::estado()['id']);
        Reloj::fijar('2026-10-23 00:00:00');
        // Medianoche: la sesión del administrador ya venció por inactividad; se vuelve a entrar
        $this->entrarComo('admin');
        $this->assertSame(422, $this->post('/qr/simular', ['accion'=>'restablecer','qr_id'=>(string)$nuevo])->estado);
    }
}
