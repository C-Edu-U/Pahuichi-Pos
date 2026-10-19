<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use Tests\CasoPrueba;

/**
 * HU-29: carrito de venta. La lógica corre en el navegador (carrito.js); aquí se
 * prueba lo que el servidor le entrega. El recorrido en el navegador está en la
 * guía de QA del README.
 */
final class CarritoPosTest extends CasoPrueba
{
    private string $html;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-19 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '300']);
        $this->html = $this->get('/pos')->cuerpo;
    }

    public function testCargaLaLogicaDelCarritoAntesQueLaPantalla(): void
    {
        $carrito = strpos($this->html, '<script src="/assets/js/carrito.js"></script>');
        $pos = strpos($this->html, '<script src="/assets/js/pos.js"></script>');

        $this->assertNotFalse($carrito);
        $this->assertNotFalse($pos);
        $this->assertLessThan($pos, $carrito);
    }

    public function testElPedidoEmpiezaVacioConSuTotalEnCero(): void
    {
        $this->assertStringContainsString('Toca un producto para agregarlo. Otro toque suma una unidad.', $this->html);
        $this->assertStringContainsString('<span id="t-total">0,00</span>', $this->html);
        $this->assertStringContainsString('<span class="pos__resumen-cuenta" id="resumen-cuenta">Pedido vacío</span>', $this->html);
    }

    public function testCancelarElPedidoPideConfirmacion(): void
    {
        $this->assertMatchesRegularExpression('/id="vaciar" data-abrir-modal="modal-vaciar" disabled>/', $this->html);
        $this->assertStringContainsString('<dialog class="modal" id="modal-vaciar"', $this->html);
        $this->assertStringContainsString('<button class="boton boton--peligro" type="submit" value="vaciar">Cancelar pedido</button>', $this->html);
    }

    public function testElCobroTodaviaNoEstaDisponible(): void
    {
        $this->assertMatchesRegularExpression('/id="cobrar" disabled/', $this->html);
        $this->assertStringContainsString('El cobro llega en el Sprint 3 (HU-37).', $this->html);
    }

    public function testCadaProductoTraeSuPrecioYSiEstaAgotado(): void
    {
        preg_match('/data-productos="([^"]+)"/', $this->html, $m);
        $datos = json_decode(html_entity_decode($m[1]), true);

        $prueba = array_values(array_filter($datos, fn (array $p): bool => $p['codigo'] === 'PR-01'))[0];
        $fricase = array_values(array_filter($datos, fn (array $p): bool => $p['codigo'] === 'PL-07'))[0];
        $this->assertSame(0.5, $prueba['precio']);
        $this->assertTrue($fricase['agotado']);
    }
}
