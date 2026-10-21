<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CasoPrueba;

/**
 * HU-30: tipo de pedido, mesa con su número o para llevar. El servidor valida la
 * mesa (1 a 99) y la guarda en la venta; la comanda la muestra a cocina.
 */
final class TipoPedidoTest extends CasoPrueba
{
    private int $caja;
    private int $plato;

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-21 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '300']);
        $this->caja = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
        $this->plato = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-01'");
    }

    private function cobrar(string $tipo, string $mesa): \App\Core\Respuesta
    {
        $precio = (string) DB::valor('SELECT precio FROM productos WHERE id = ?', [$this->plato]);
        return $this->post('/pos/cobrar', [
            'uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) $this->caja, 'tipo' => $tipo, 'mesa' => $mesa, 'exacto' => '1',
            'total_esperado' => $precio, 'lineas' => json_encode([['id' => $this->plato, 'cantidad' => 1, 'nota' => '']]),
        ]);
    }

    /**
     * Escenario: Pedido en mesa
     *   Dado elijo Mesa y escribo el número 4
     *   Cuando cobro el pedido
     *   Entonces la venta queda en la mesa 4 y la comanda dice «MESA 4»
     */
    public function testElPedidoEnMesaLlegaALaComanda(): void
    {
        $r = $this->cobrar('mesa', '4');

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $id = (int) json_decode($r->cuerpo, true)['id'];
        $venta = DB::fila('SELECT v.tipo_pedido, v.mesa FROM ventas v JOIN facturas f ON f.venta_id = v.id WHERE f.id = ?', [$id]);
        $this->assertSame(['tipo_pedido' => 'mesa', 'mesa' => '4'], $venta);
        $tickets = $this->get('/facturas/' . $id)->cuerpo;
        $this->assertStringContainsString('<p class="comanda__destino" data-campo="destino">MESA 4</p>', $tickets);
        $this->assertStringContainsString('<dd data-campo="destino-corto">Mesa 4</dd>', $tickets);
    }

    /**
     * Escenario: Para llevar
     *   Dado elijo Llevar
     *   Cuando cobro el pedido
     *   Entonces la venta no tiene mesa y la comanda dice «PARA LLEVAR»
     */
    public function testParaLlevarNoGuardaMesa(): void
    {
        $r = $this->cobrar('llevar', '7');

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $id = (int) json_decode($r->cuerpo, true)['id'];
        $venta = DB::fila('SELECT v.tipo_pedido, v.mesa FROM ventas v JOIN facturas f ON f.venta_id = v.id WHERE f.id = ?', [$id]);
        $this->assertSame(['tipo_pedido' => 'llevar', 'mesa' => null], $venta);
        $this->assertStringContainsString('data-campo="destino">PARA LLEVAR</p>', $this->get('/facturas/' . $id)->cuerpo);
    }

    public static function mesasInvalidas(): array
    {
        return [
            'sin número' => ['mesa', ''],
            'cero' => ['mesa', '0'],
            'mayor que 99' => ['mesa', '100'],
            'cero a la izquierda' => ['mesa', '04'],
            'letras' => ['mesa', 'a1'],
            'tipo desconocido' => ['delivery', '3'],
        ];
    }

    /**
     * Escenario: Mesa obligatoria
     *   Dado elijo Mesa y no escribo un número de 1 a 99
     *   Cuando intento cobrar
     *   Entonces el cobro se rechaza con el motivo y no se guarda venta ni factura
     */
    #[DataProvider('mesasInvalidas')]
    public function testSinMesaValidaNoSeCobra(string $tipo, string $mesa): void
    {
        $r = $this->cobrar($tipo, $mesa);

        $this->assertSame(422, $r->estado);
        $this->assertSame('Elige mesa con número de 1 a 99, o para llevar.', json_decode($r->cuerpo, true)['error']);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM ventas'));
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM facturas'));
    }

    /**
     * Escenario: Sin mesa por defecto
     *   Dado empiezo un pedido nuevo
     *   Cuando miro el tipo de pedido
     *   Entonces Mesa está elegida y el número está vacío, para escribirlo en cada pedido
     */
    public function testElNumeroDeMesaEmpiezaVacio(): void
    {
        $html = $this->get('/pos')->cuerpo;

        $this->assertStringContainsString('<button class="tipo-pedido__opcion" type="button" data-tipo="mesa" aria-pressed="true">', $html);
        $this->assertMatchesRegularExpression('/<input [^>]*id="mesa"[^>]* value="" placeholder="N°"/', $html);
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/pos.js');
        $this->assertStringContainsString("Escribe el número de mesa, de 1 a 99, o elige Llevar.", $js);
    }
}
