<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\Config;
use App\Core\DB;
use App\Core\Reloj;
use Tests\CasoPrueba;

/** Con APP_ENTORNO=produccion no hay datos de prueba en el login ni botones de demostración. */
final class DemostracionesProduccionTest extends CasoPrueba
{
    private mixed $entorno;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entorno = Config::get('app.entorno');
        Reloj::fijar('2026-10-22 12:00:00');
    }

    protected function tearDown(): void
    {
        Config::fijar('app.entorno', $this->entorno);
        parent::tearDown();
    }

    public function testFueraDeProduccionSeVenLasDemostraciones(): void
    {
        Config::fijar('app.entorno', 'desarrollo');
        $this->assertStringContainsString('Datos de prueba', $this->get('/login')->cuerpo);
        $this->entrarComo('admin');
        $this->qrDeHoy();
        $qr = $this->get('/qr')->cuerpo;
        $this->assertStringContainsString('id="qr-demo-vencer"', $qr);
        $this->assertStringContainsString('id="qr-demo-comprobante"', $qr);
        $this->assertStringContainsString('data-simular', $this->get('/config')->cuerpo);
    }

    public function testEnProduccionNoHayDatosDePruebaNiDemostraciones(): void
    {
        Config::fijar('app.entorno', 'produccion');
        $this->assertStringNotContainsString('Datos de prueba', $this->get('/login')->cuerpo);
        $this->entrarComo('admin');
        $id = $this->qrDeHoy();

        $qr = $this->get('/qr')->cuerpo;
        $this->assertStringNotContainsString('id="qr-demo-vencer"', $qr);
        $this->assertStringNotContainsString('id="qr-demo-comprobante"', $qr);
        $this->assertStringNotContainsString('qr-comprobantes-demo.js', $qr);
        $this->assertSame(404, $this->post('/qr/simular', ['accion' => 'vencer', 'qr_id' => (string) $id])->estado);
        $this->assertSame(404, $this->post('/qr/comprobantes/demostrar', ['accion' => 'mismo_dia', 'comprobante' => '000555'])->estado);
        $this->assertNull(DB::valor('SELECT vencimiento_demo FROM qr_diarios WHERE id = ?', [$id]));

        $config = $this->get('/config')->cuerpo;
        $this->assertStringNotContainsString('data-simular', $config);
        $this->assertMatchesRegularExpression('/id="respaldo-simular" disabled hidden/', $config);
        foreach (['copiar', 'vencer'] as $accion) {
            $this->assertSame(422, $this->post('/config/respaldos/simular', ['accion' => $accion, 'archivo' => ''])->estado);
        }
    }

    private function qrDeHoy(): int
    {
        return DB::insertar("INSERT INTO qr_diarios (fecha, monto, imagen, imagen_tipo, usuario_id, creado_en) VALUES ('2026-10-22', 0.50, 'imagen', 'image/png', 1, '2026-10-22 10:00:00')");
    }
}
