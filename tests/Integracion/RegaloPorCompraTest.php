<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Core\Respuesta;
use App\Models\Factura;
use Tests\CasoPrueba;

/**
 * HU-48: regalo por compra, «compra X y recibe Y gratis» (RF-50). El motor ya lo calcula
 * desde HU-46; aquí se prueban los escenarios de la historia, incluida la comanda.
 */
final class RegaloPorCompraTest extends CasoPrueba
{
    private int $caja;
    private int $majadito;   // Bs 40, plato
    private int $locro;      // Bs 35, plato
    private int $soda;       // Bs 8, no va a cocina

    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-23 12:00:00');
        $this->majadito = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-01'");
        $this->locro = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-02'");
        $this->soda = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'SO-01'");
        // «Compra 2 platos, 1 soda gratis»
        DB::insertar("INSERT INTO promociones (nombre, tipo, categoria_id, cantidad_compra, regalo_producto_id) VALUES ('2 platos, 1 soda', 'regalo', ?, 2, ?)",
            [(int) DB::valor("SELECT id FROM categorias WHERE nombre = 'Platos'"), $this->soda]);
        $this->entrarComo('mariela', 'pin');
        $this->post('/caja/abrir', ['turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '200']);
        $this->caja = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
    }

    /** @param array<int, int> $cantidades producto => cantidad */
    private function cobrar(array $cantidades, string $bruto, string $regalos): Respuesta
    {
        $lineas = [];
        foreach ($cantidades as $id => $n) {
            $lineas[] = ['id' => $id, 'cantidad' => $n, 'nota' => ''];
        }
        return $this->post('/pos/cobrar', [
            'uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) $this->caja, 'tipo' => 'mesa', 'mesa' => '6', 'exacto' => '1',
            'total_esperado' => $bruto, 'promociones' => '0.00', 'regalos' => $regalos, 'lineas' => json_encode($lineas),
        ]);
    }

    /**
     * Escenario 1: Regalo aplicado
     *   Dado la promoción «compra 2 platos, 1 soda gratis»
     *   Cuando el carrito tiene 2 platos
     *   Entonces se agrega 1 soda a 0 Bs
     */
    public function testDosPlatosAgreganUnaSodaABsCero(): void
    {
        $r = $this->cobrar([$this->majadito => 1, $this->locro => 1], '75.00', '8.00');

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $f = Factura::buscar((int) json_decode($r->cuerpo, true)['id']);
        $soda = DB::fila('SELECT cantidad, descuento, subtotal FROM detalle_venta WHERE venta_id = ? AND producto_id = ?', [$f['venta_id'], $this->soda]);
        $this->assertSame([1, '8.00', '0.00'], [(int) $soda['cantidad'], $soda['descuento'], $soda['subtotal']]);
        $this->assertSame('75.00', (string) $f['monto_total']);
    }

    /**
     * Escenario 2: No alcanza
     *   Dado el carrito tiene 1 plato
     *   Cuando reviso el carrito
     *   Entonces no se agrega el regalo
     */
    public function testUnPlatoNoAlcanza(): void
    {
        $this->assertSame(422, $this->cobrar([$this->majadito => 1], '40.00', '8.00')->estado);
        $r = $this->cobrar([$this->majadito => 1], '40.00', '0.00');

        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertSame(0, (int) DB::valor('SELECT COUNT(*) FROM detalle_venta WHERE producto_id = ?', [$this->soda]));
    }

    /**
     * Escenario 3: Comanda
     *   Dado un regalo aplicado
     *   Cuando se imprime la comanda
     *   Entonces el regalo aparece en la comanda, aunque la soda no vaya a cocina
     */
    public function testElRegaloApareceEnLaComanda(): void
    {
        $r = $this->cobrar([$this->majadito => 2], '80.00', '8.00');
        $id = (int) json_decode($r->cuerpo, true)['id'];

        $this->assertSame(1, preg_match('/<article class="ticket ticket--comanda"[^>]*>(.*?)<\/article>/s', $this->get('/facturas/' . $id)->cuerpo, $m));
        $this->assertStringContainsString('<span class="comanda__nombre">Majadito de charque</span>', $m[1]);
        $this->assertStringContainsString('<span class="comanda__nombre">Soda cola 500 ml</span><span class="comanda__nota comanda__regalo">» REGALO · 2 PLATOS, 1 SODA</span>', $m[1]);
        $this->assertStringNotContainsString('8,00', $m[1]);

        // El PDF de cocina usa la misma comanda
        $pdf = $this->get('/facturas/' . $id . '/pdf', ['destino' => 'cocina']);
        $this->assertSame(200, $pdf->estado);
        $this->assertStringStartsWith('%PDF-', $pdf->cuerpo);
    }
}
