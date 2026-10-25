<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Bitacora;
use Tests\CasoPrueba;

/**
 * HU-20: cada cajero opera y ve solo su caja; el administrador, todas. Y el ciclo completo:
 * la caja de medio día se cierra con su conteo y arqueo, y la de noche abre después.
 */
final class CajaPropiaTest extends CasoPrueba
{
    private int $caja;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-24 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '300']);
        $this->caja = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
    }

    private function cerrarComo(string $usuario, string $modo = 'pin'): void
    {
        $this->entrarComo($usuario, $modo);
        $this->post('/caja/conteo', ['conteo' => ['200' => '1', '100' => '1']]);
        $this->assertRedirige('/caja/arqueo', $this->post('/caja/arqueo', ['observacion' => 'Cierre de prueba']));
    }

    /**
     * Escenario: La caja de otro cajero
     *   Dado Mariela tiene la caja abierta
     *   Cuando Jorge entra a Caja
     *   Entonces ve de quién es la caja, pero no su fondo, sus movimientos ni los botones para operarla
     */
    public function testOtroCajeroSoloVeDeQuienEsLaCaja(): void
    {
        $this->entrarComo('jorge', 'pin');

        $html = $this->get('/caja')->cuerpo;

        $this->assertStringContainsString('La caja abierta es de Mariela Suárez Añez', $html);
        $this->assertStringNotContainsString('Fondo inicial', $html);
        $this->assertStringNotContainsString('id="form-movimiento"', $html);
        $this->assertStringNotContainsString('href="/caja/cierre"', $html);
    }

    /**
     * Escenario: Por la dirección directa tampoco
     *   Dado Mariela tiene la caja abierta
     *   Cuando Jorge intenta registrar un egreso, contar o arquear su caja
     *   Entonces el sistema lo impide y queda en la bitácora
     */
    public function testOtroCajeroNoOperaLaCajaAjena(): void
    {
        $this->entrarComo('jorge', 'pin');

        $this->assertSame(403, $this->post('/caja/movimientos', ['tipo' => 'egreso', 'monto' => '50', 'motivo' => 'hielo'])->estado);
        $this->assertSame(403, $this->get('/caja/cierre')->estado);
        $this->assertSame(403, $this->post('/caja/conteo', ['conteo' => ['200' => '1']])->estado);
        $this->assertSame(403, $this->get('/caja/arqueo')->estado);
        $this->assertSame(403, $this->post('/caja/arqueo', ['observacion' => ''])->estado);

        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM movimientos_caja'));
        $this->assertSame('abierta', DB::valor('SELECT estado FROM cajas WHERE id = ?', [$this->caja]));
        $this->assertCount(5, Bitacora::ultimos('acceso.denegado'));
    }

    public function testElAdministradorOperaCualquierCaja(): void
    {
        $this->entrarComo('admin');

        $this->assertStringContainsString('Fondo inicial', $this->get('/caja')->cuerpo);
        $this->assertRedirige('/caja', $this->post('/caja/movimientos', ['tipo' => 'ingreso', 'monto' => '20', 'motivo' => 'cambio']));
        $this->cerrarComo('admin', 'clave');
        $this->assertSame('cerrada', DB::valor('SELECT estado FROM cajas WHERE id = ?', [$this->caja]));
    }

    /**
     * Escenario: Reporte e historial
     *   Dado la caja de Mariela está cerrada
     *   Cuando Jorge busca su reporte de cierre o mira las cajas recientes
     *   Entonces no ve el reporte ni la caja en su historial; Mariela y el administrador sí
     */
    public function testReporteEHistorialSoloDelDuenoYDelAdministrador(): void
    {
        $this->cerrarComo('mariela');

        $this->entrarComo('jorge', 'pin');
        $this->assertSame(403, $this->get('/caja/reporte/' . $this->caja)->estado);
        $this->assertStringNotContainsString('id="seccion-historial"', $this->get('/caja')->cuerpo);
        $this->assertSame('/caja', $this->get('/caja/arqueo')->cabeceras['Location'] ?? null);   // no tiene cajas cerradas

        $this->entrarComo('mariela', 'pin');
        $this->assertSame(200, $this->get('/caja/reporte/' . $this->caja)->estado);
        $this->assertStringContainsString('id="seccion-historial"', $this->get('/caja')->cuerpo);

        $this->entrarComo('admin');
        $this->assertSame(200, $this->get('/caja/reporte/' . $this->caja)->estado);
    }

    /**
     * Escenario: El turno siguiente abre después del cierre
     *   Dado la caja de medio día se cerró con su conteo y arqueo
     *   Cuando otro cajero abre la caja de noche
     *   Entonces la caja de noche queda abierta a su nombre
     */
    public function testElTurnoSiguienteAbreDespuesDelCierre(): void
    {
        $this->cerrarComo('mariela');

        Reloj::fijar('2026-10-24 18:05:00');
        $this->entrarComo('jorge', 'pin');
        $r = $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'noche'"), 'fondo' => '300']);

        $this->assertRedirige('/pos', $r);
        $this->assertSame('Jorge Rivero Chávez', DB::valor("SELECT u.nombre FROM cajas c JOIN usuarios u ON u.id = c.cajero_id WHERE c.estado = 'abierta'"));
    }
}
