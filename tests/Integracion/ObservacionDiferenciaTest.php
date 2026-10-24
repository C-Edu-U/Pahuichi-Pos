<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Bitacora;
use App\Models\Configuracion;
use Tests\CasoPrueba;

/** HU-26: observación obligatoria cuando la diferencia llega al umbral. */
final class ObservacionDiferenciaTest extends CasoPrueba
{
    private int $mediodia;
    private int $cajaId;
    private int $mariela;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-15 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->mediodia = (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'");
        $this->mariela = (int) DB::valor("SELECT id FROM usuarios WHERE usuario = 'mariela'");
        $this->post('/caja/abrir', ['turno_id' => $this->mediodia, 'fondo' => '200']);
        $this->cajaId = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
        // Venta en efectivo de Bs 1.000: el efectivo esperado queda en 1.200
        $this->ventaDirecta($this->cajaId, $this->mariela, 'efectivo', 'confirmado', 1000.00);
    }

    private function contar(int $b200, int $b100, int $b50, int $b10, int $m5): void
    {
        $this->post('/caja/conteo', ['conteo' => [
            '200' => (string) $b200, '100' => (string) $b100, '50' => (string) $b50, '20' => '0', '10' => (string) $b10,
            '5' => (string) $m5, '2' => '0', '1' => '0', '0.50' => '0', '0.20' => '0', '0.10' => '0',
        ]]);
    }

    private function umbralPorDefecto(): float
    {
        return Configuracion::umbralObservacion();
    }

    /**
     * Escenario: Diferencia en el umbral
     *   Dado el umbral es 10 Bs y la diferencia es 10 Bs
     *   Cuando intento confirmar el cierre sin observación
     *   Entonces el sistema exige la observación
     */
    public function testLaDiferenciaEnElUmbralExigeObservacion(): void
    {
        $this->assertSame(10.0, $this->umbralPorDefecto());
        $this->contar(5, 1, 1, 4, 0); // 1.190 -> diferencia de 10

        $r = $this->post('/caja/arqueo', ['observacion' => '']);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Escribe una observación', $r->cuerpo);
        $this->assertStringContainsString('10,00', $r->cuerpo);
        $this->assertSame('abierta', DB::valor('SELECT estado FROM cajas WHERE id = ?', [$this->cajaId]));
    }

    public function testConObservacionLaCajaCierra(): void
    {
        $this->contar(5, 1, 1, 4, 0);

        $r = $this->post('/caja/arqueo', ['observacion' => 'Se rompió un billete de 100']);

        $this->assertRedirige('/caja/arqueo', $r);
        $this->assertSame('cerrada', DB::valor('SELECT estado FROM cajas WHERE id = ?', [$this->cajaId]));
        $a = DB::fila('SELECT * FROM arqueos WHERE caja_id = ?', [$this->cajaId]);
        $this->assertSame('Se rompió un billete de 100', $a['observacion']);
        $this->assertSame('-10.00', $a['diferencia']);
    }

    /**
     * Escenario: Diferencia menor
     *   Dado la diferencia es de 5 Bs
     *   Cuando confirmo el cierre
     *   Entonces la observación es opcional
     */
    public function testLaDiferenciaMenorNoExigeObservacion(): void
    {
        $this->contar(5, 1, 1, 4, 1); // 1.195 -> diferencia de 5

        $r = $this->post('/caja/arqueo', ['observacion' => '']);

        $this->assertRedirige('/caja/arqueo', $r);
        $this->assertSame('cerrada', DB::valor('SELECT estado FROM cajas WHERE id = ?', [$this->cajaId]));
        $a = DB::fila('SELECT * FROM arqueos WHERE caja_id = ?', [$this->cajaId]);
        $this->assertSame('-5.00', $a['diferencia']);
        $this->assertNull($a['observacion']);
    }

    /**
     * Escenario: Umbral configurable
     *   Dado cambio el umbral a 20 Bs
     *   Cuando cierro con una diferencia de 15 Bs
     *   Entonces no se exige observación
     */
    public function testElUmbralEsConfigurable(): void
    {
        $this->entrarComo('admin');
        $this->assertRedirige('/config#caja-cobro', $this->post('/config/caja-cobro', ['umbral_observacion' => '20,00']));
        $this->assertSame(20.0, $this->umbralPorDefecto());

        $this->entrarComo('mariela', 'pin');
        $this->contar(5, 1, 1, 3, 1); // 1.185 -> diferencia de 15

        $r = $this->post('/caja/arqueo', ['observacion' => '']);

        $this->assertRedirige('/caja/arqueo', $r);
        $this->assertSame('cerrada', DB::valor('SELECT estado FROM cajas WHERE id = ?', [$this->cajaId]));
    }

    public function testElCampoSeMarcaObligatorioCuandoHaceFalta(): void
    {
        $this->contar(5, 1, 1, 4, 0); // diferencia de 10

        $html = $this->get('/caja/arqueo')->cuerpo;

        $this->assertStringContainsString('Observación (obligatoria)</label>', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*id="observacion"[^>]* required/', $html);
    }

    public function testLaObservacionEnBlancoNoCuenta(): void
    {
        $this->contar(5, 1, 1, 4, 0);

        $this->assertSame(422, $this->post('/caja/arqueo', ['observacion' => "   \t "])->estado);
        $this->assertSame('abierta', DB::valor('SELECT estado FROM cajas WHERE id = ?', [$this->cajaId]));
    }

    public function testUnaObservacionDemasiadoLargaSeRechaza(): void
    {
        $this->contar(5, 1, 1, 4, 0);

        $r = $this->post('/caja/arqueo', ['observacion' => str_repeat('ñ', 256)]);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('La observación admite hasta 255 caracteres.', $r->cuerpo);
        $this->assertSame('abierta', DB::valor('SELECT estado FROM cajas WHERE id = ?', [$this->cajaId]));
        $this->assertRedirige('/caja/arqueo', $this->post('/caja/arqueo', ['observacion' => str_repeat('ñ', 255)]));
    }

    /**
     * Escenario: Umbral en cero
     *   Dado el administrador puso el umbral en 0 Bs
     *   Cuando la caja cuadra al centavo
     *   Entonces no se pide observación; con 10 centavos de diferencia, sí
     */
    public function testConUmbralCeroSoloLaCajaQueCuadraCierraSinObservacion(): void
    {
        Configuracion::guardar(Configuracion::UMBRAL_OBSERVACION, '0.00');
        $this->post('/caja/conteo', ['conteo' => ['200' => '6', '0.10' => '1']]); // 1.200,10

        $this->assertSame(422, $this->post('/caja/arqueo', ['observacion' => ''])->estado);

        $this->contar(6, 0, 0, 0, 0); // 1.200: cuadra
        $this->assertRedirige('/caja/arqueo', $this->post('/caja/arqueo', ['observacion' => '']));
        $this->assertSame('cerrada', DB::valor('SELECT estado FROM cajas WHERE id = ?', [$this->cajaId]));
    }

    public function testLaObservacionQuedaEnLaBitacora(): void
    {
        $this->contar(5, 1, 1, 4, 0);
        $this->post('/caja/arqueo', ['observacion' => 'Vuelto mal dado']);

        $this->assertStringContainsString('"observacion":"Vuelto mal dado"', (string) Bitacora::ultimos('caja.arqueo')[0]['detalle']);
    }

    public function testElCajeroNoCambiaElUmbral(): void
    {
        $this->assertSame(403, $this->post('/config/caja-cobro', ['umbral_observacion' => '50'])->estado);
    }

    public function testElUmbralSeMuestraEnLaConfiguracion(): void
    {
        $this->entrarComo('admin');

        $r = $this->get('/config');

        $this->assertStringContainsString('Umbral de la observación obligatoria', $r->cuerpo);
        $this->assertStringContainsString('10,00', $r->cuerpo);
    }
}
