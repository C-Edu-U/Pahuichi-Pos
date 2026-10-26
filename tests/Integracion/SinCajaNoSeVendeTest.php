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

    /**
     * Escenario: Al cerrar, el POS vuelve a pedir abrir caja
     *   Dado que la caja del turno se cerró con su conteo y arqueo
     *   Cuando el cajero entra al punto de venta
     *   Entonces ve directamente el botón "Abrir caja"
     */
    public function testAlCerrarElPosVuelveAPedirAbrirCaja(): void
    {
        Reloj::fijar('2026-10-24 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '300']);
        $this->post('/caja/conteo', ['conteo' => ['200' => '1', '100' => '1']]);
        $this->assertRedirige('/caja/arqueo', $this->post('/caja/arqueo', ['observacion' => '']));

        $r = $this->get('/pos');

        $this->assertStringContainsString('No hay una caja abierta', $r->cuerpo);
        $this->assertStringContainsString('Abrir caja', $r->cuerpo);
    }

    /**
     * Escenario: La caja abierta es de otro cajero (HU-20)
     *   Dado que Mariela tiene la caja abierta
     *   Cuando Jorge entra al punto de venta
     *   Entonces ve de quién es la caja y no puede armar ni cobrar pedidos; el administrador sí vende
     */
    public function testEnLaCajaDeOtroCajeroNoSeVende(): void
    {
        Reloj::fijar('2026-10-24 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '300']);

        $this->entrarComo('jorge', 'pin');
        $r = $this->get('/pos');
        $this->assertStringContainsString('La caja abierta es de Mariela Suárez Añez', $r->cuerpo);
        $this->assertStringNotContainsString('id="grilla"', $r->cuerpo);
        $json = App::crear()->manejar(new Peticion('GET', '/pos', [], [], [], '10.0.0.1', ['accept' => 'application/json']));
        $this->assertSame(409, $json->estado);
        $this->assertStringContainsString('solo esa persona vende en ella', $json->cuerpo);

        $this->entrarComo('admin');
        $this->assertStringContainsString('id="grilla"', $this->get('/pos')->cuerpo);
    }

    public function testElMeseroNoEntraAlPuntoDeVentaAunqueHayaCaja(): void
    {
        $this->entrarComo('luis');

        $this->assertSame(403, $this->get('/pos')->estado);
    }
}
