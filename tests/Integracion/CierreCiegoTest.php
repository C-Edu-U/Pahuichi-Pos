<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Arqueo;
use App\Models\Bitacora;
use Tests\CasoPrueba;

/** HU-23: cierre ciego por denominación. */
final class CierreCiegoTest extends CasoPrueba
{
    private int $mediodia;
    private int $cajaId;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-15 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->mediodia = (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'");
        $this->post('/caja/abrir', ['turno_id' => $this->mediodia, 'fondo' => '300']);
        $this->cajaId = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
    }

    /**
     * Escenario: Formulario ciego
     *   Dado una caja abierta con ventas en efectivo
     *   Cuando entro al cierre
     *   Entonces veo el formulario de conteo por denominación y no veo el efectivo esperado
     */
    public function testElFormularioEsCiego(): void
    {
        $r = $this->get('/caja/cierre');

        $this->assertSame(200, $r->estado);
        $this->assertStringContainsString('Conteo por denominación', $r->cuerpo);
        $this->assertStringContainsString('name="conteo[100]"', $r->cuerpo);
        // El efectivo esperado no aparece: ni el dato ni su etiqueta
        $this->assertStringNotContainsString('Efectivo esperado', $r->cuerpo);
        $this->assertStringNotContainsString('300,00', $r->cuerpo);
    }

    /**
     * Escenario: Total del conteo
     *   Dado ingreso 3 billetes de 100 y 2 de 50
     *   Cuando reviso el total contado
     *   Entonces muestra 400 Bs
     */
    public function testElTotalDelConteo(): void
    {
        $r = $this->post('/caja/conteo', ['conteo' => [
            '200' => '0', '100' => '3', '50' => '2', '20' => '0', '10' => '0',
            '5' => '0', '2' => '0', '1' => '0', '0.50' => '0', '0.20' => '0', '0.10' => '0',
        ]]);

        $this->assertRedirige('/caja/cierre', $r);
        $a = DB::fila('SELECT * FROM arqueos WHERE caja_id = ?', [$this->cajaId]);
        $this->assertSame('400.00', $a['total_contado']);
        $this->assertCount(1, Bitacora::ultimos('caja.conteo'));

        $pantalla = $this->get('/caja/cierre')->cuerpo;
        $this->assertStringContainsString('400,00', $pantalla);
    }

    /**
     * Escenario: Denominaciones
     *   Dado abro el formulario
     *   Cuando reviso las denominaciones
     *   Entonces hay billetes de 200, 100, 50, 20 y 10 y monedas de 5, 2, 1, 0,50, 0,20 y 0,10 Bs
     */
    public function testLasDenominaciones(): void
    {
        $pantalla = $this->get('/caja/cierre')->cuerpo;

        $esperadas = [
            'Billete de Bs 200', 'Billete de Bs 100', 'Billete de Bs 50',
            'Billete de Bs 20', 'Billete de Bs 10',
            'Moneda de Bs 5', 'Moneda de Bs 2', 'Moneda de Bs 1',
            'Moneda de Bs 0,50', 'Moneda de Bs 0,20', 'Moneda de Bs 0,10',
        ];
        foreach ($esperadas as $denominacion) {
            $this->assertStringContainsString($denominacion, $pantalla);
        }
        $this->assertCount(11, Arqueo::DENOMINACIONES);
    }

    public function testCantidadNoEnteraEsRechazada(): void
    {
        $r = $this->post('/caja/conteo', ['conteo' => ['100' => '2,5']]);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('números enteros', $r->cuerpo);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM arqueos'));
    }

    public function testElConteoSePuedeVolverAHacer(): void
    {
        $this->post('/caja/conteo', ['conteo' => ['100' => '3', '50' => '2']]);
        $this->post('/caja/conteo', ['conteo' => ['100' => '1']]);

        $a = DB::fila('SELECT * FROM arqueos WHERE caja_id = ?', [$this->cajaId]);
        $this->assertSame('100.00', $a['total_contado']);
        $this->assertSame(1, (int) DB::valor('SELECT COUNT(*) FROM arqueos'));
    }

    /**
     * Escenario: No se cierra sin contar
     *   Dado una caja abierta
     *   Cuando alguien intenta cerrarla sin el conteo y el arqueo (por la dirección directa)
     *   Entonces la caja sigue abierta: cerrar es contar y arquear en un solo paso (RN-02)
     */
    public function testNoSeCierraSinContarNiArquear(): void
    {
        $r = $this->post('/caja/cerrar');

        $this->assertContains($r->estado, [404, 405]);
        $this->assertSame('abierta', DB::valor("SELECT estado FROM cajas ORDER BY id DESC LIMIT 1"));
        $this->assertRedirige('/caja/cierre', $this->post('/caja/arqueo', ['observacion' => '']));   // sin conteo no hay arqueo
        $this->assertSame('abierta', DB::valor("SELECT estado FROM cajas ORDER BY id DESC LIMIT 1"));
    }

    public function testUnaCantidadAbsurdaSeRechaza(): void
    {
        $r = $this->post('/caja/conteo', ['conteo' => ['200' => '10000']]);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('Revisa las cantidades: cada una va de 0 a 9999.', $r->cuerpo);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM arqueos'));
    }

    public function testSinCajaAbiertaNoHayConteo(): void
    {
        DB::consulta("UPDATE cajas SET estado = 'cerrada'");

        $this->assertRedirige('/caja', $this->get('/caja/cierre'));
    }
}
