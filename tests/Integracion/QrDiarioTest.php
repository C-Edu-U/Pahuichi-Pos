<?php

declare(strict_types=1);
namespace Tests\Integracion;
use App\Core\DB;
use App\Core\Reloj;
use App\Services\QrDiarioService;
use DomainException;
use Tests\CasoPrueba;
final class QrDiarioTest extends CasoPrueba
{
    public function testElQrVenceExactamenteAMedianocheBoliviana(): void
    {
        Reloj::fijar('2026-10-22 10:00:00');
        $id = DB::insertar("INSERT INTO qr_diarios (fecha,monto,imagen,imagen_tipo,usuario_id,creado_en) VALUES ('2026-10-22',0.50,'imagen','image/png',1,'2026-10-22 10:00:00')");
        Reloj::fijar('2026-10-22 23:59:59');
        $this->assertSame($id,QrDiarioService::estado()['id']);
        $this->entrarComo('mariela','pin');
        $this->assertSame(200,$this->get('/qr/'.$id.'/imagen')->estado);
        Reloj::fijar('2026-10-23 00:00:00');
        $this->assertFalse(QrDiarioService::estado()['vigente']);
        $this->assertSame(404,$this->get('/qr/'.$id.'/imagen')->estado);
    }
    public function testNoSeAceptaFechaDeAyer(): void
    {
        Reloj::fijar('2026-10-22 12:00:00');
        $this->expectException(DomainException::class);
        QrDiarioService::validar('2026-10-21','0.50');
    }
    public function testMontoConfigurableYFechaDeHoy(): void
    {
        Reloj::fijar('2026-10-22 12:00:00');
        $this->assertSame('38.50',QrDiarioService::validar('2026-10-22','38,50'));
    }
    public function testNoAceptaArchivoQueNoEsImagen(): void
    {
        $this->expectException(DomainException::class);
        QrDiarioService::imagen('<?php echo "malo";');
    }
    public function testCajeroPuedeConsultarPeroNoSubir(): void
    {
        $this->entrarComo('mariela','pin');
        $this->assertSame(200,$this->get('/qr/estado')->estado);
        $this->assertSame(403,$this->post('/qr/subir',[])->estado);
    }
    public function testAbrirCajaSinQrAvisaAlCajero(): void
    {
        Reloj::fijar('2026-10-22 12:00:00');
        $this->entrarComo('mariela','pin');
        $r=$this->post('/caja/abrir',['turno_id'=>(int) DB::valor("SELECT id FROM turnos WHERE clave='mediodia'"),'fondo'=>'200']);
        $this->assertRedirige('/pos',$r);
        $this->assertStringContainsString('No hay QR vigente',$this->get('/pos')->cuerpo);
    }

    public function testFechaIncorrectaConservaEntradaYMuestraErrorSinGuardar(): void
    {
        Reloj::fijar('2026-10-22 12:00:00');
        $this->entrarComo('admin');
        $antes = (int) DB::valor('SELECT COUNT(*) FROM qr_diarios');
        $r = $this->post('/qr/subir', ['fecha' => '2026-10-21', 'monto' => '0.50']);
        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('No se guardó el QR', $r->cuerpo);
        $this->assertStringContainsString('La fecha del QR debe ser la de hoy', $r->cuerpo);
        $this->assertStringContainsString('value="2026-10-21" aria-describedby="qr-fecha-error" aria-invalid="true"', $r->cuerpo);
        $this->assertSame($antes, (int) DB::valor('SELECT COUNT(*) FROM qr_diarios'));
    }
    public function testConfiguracionNoDuplicaElModuloQr(): void
    {
        $this->entrarComo('admin');
        $html = $this->get('/config')->cuerpo;
        $this->assertStringNotContainsString('id="qr-diario"', $html);
        $this->assertStringNotContainsString('href="#qr-diario"', $html);
        $this->assertStringContainsString('id="qr-diario"', $this->get('/qr')->cuerpo);
    }
    public function testCeroPermiteImporteLibrePeroNegativosNo(): void
    {
        Reloj::fijar('2026-10-22 12:00:00');
        $this->assertSame('0.00', QrDiarioService::validar('2026-10-22', '0'));
        $this->assertSame('0.00', QrDiarioService::validar('2026-10-22', '0,00'));
        $this->expectException(DomainException::class);
        QrDiarioService::validar('2026-10-22', '-1');
    }
}
