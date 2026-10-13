<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\App;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Reloj;
use Tests\CasoPrueba;

/** HU-21: sin caja abierta no se vende. */
final class SinCajaNoSeVendeTest extends CasoPrueba
{
    /**
     * Escenario: Sin caja no se vende
     *   Dado que no hay caja abierta
     *   Cuando el cajero entra al punto de venta
     *   Entonces ve directamente el botón "Abrir caja"
     */
    public function testSinCajaElPosMuestraAbrirCaja(): void
    {
        $this->post('/login', ['modo' => 'pin', 'pin' => '4821']);

        $r = $this->get('/pos');

        $this->assertSame(200, $r->estado);
        $this->assertStringContainsString('No hay una caja abierta', $r->cuerpo);
        $this->assertMatchesRegularExpression('#<a class="boton boton--primario boton--grande" href="/caja"[^>]*>\s*<svg[^>]*>.*?</svg>\s*Abrir caja#s', $r->cuerpo);
    }

    public function testConCajaAbiertaElPosYaNoPideAbrirla(): void
    {
        Reloj::fijar('2026-10-15 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $turno = (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'");
        $this->post('/caja/abrir', ['turno_id' => $turno, 'fondo' => '300']);

        $r = $this->get('/pos');

        $this->assertStringNotContainsString('No hay una caja abierta', $r->cuerpo);
        $this->assertStringContainsString('Caja abierta · medio día desde las 12:00', $r->cuerpo);
    }

    public function testUnPedidoDeVentaSinCajaRecibe409(): void
    {
        $this->entrarComo('mariela', 'pin');

        $r = App::crear()->manejar(new Peticion('GET', '/pos', [], [], [], '10.0.0.1', ['accept' => 'application/json']));

        $this->assertSame(409, $r->estado);
        $this->assertStringContainsString('No hay una caja abierta', $r->cuerpo);
    }

    public function testElMeseroNoEntraAlPuntoDeVentaAunqueHayaCaja(): void
    {
        $this->entrarComo('luis');

        $this->assertSame(403, $this->get('/pos')->estado);
    }
}
