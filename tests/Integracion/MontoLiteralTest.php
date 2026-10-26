<?php

declare(strict_types=1);
namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Factura;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CasoPrueba;

/** HU-58: ruta de cobro -> snapshot en MariaDB -> consulta/reimpresión de factura. */
final class MontoLiteralTest extends CasoPrueba
{
    protected function setUp(): void
    {
        parent::setUp();
        Reloj::fijar('2026-10-21 12:00:00');
        $this->entrarComo('mariela', 'pin');
        $r = $this->post('/caja/abrir', [
            'turno_id' => (int) DB::valor("SELECT id FROM turnos WHERE clave = 'mediodia'"), 'fondo' => '300',
        ]);
        $this->assertRedirige('/pos', $r);
    }

    public static function escenarios(): array
    {
        return [
            ['38.50', 'TREINTA Y OCHO 50/100 BOLIVIANOS'],
            ['1.00', 'UN 00/100 BOLIVIANOS'],
            ['1250.75', 'UN MIL DOSCIENTOS CINCUENTA 75/100 BOLIVIANOS'],
        ];
    }

    #[DataProvider('escenarios')]
    public function testEmiteGuardaYConservaElLiteral(string $precio, string $literal): void
    {
        $producto = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PR-01'");
        DB::consulta('UPDATE productos SET precio = ?, activo = 1, agotado = 0 WHERE id = ?', [$precio, $producto]);
        $caja = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
        $pedido = [
            'uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)),
            'caja_id' => (string) $caja, 'tipo' => 'llevar', 'mesa' => '', 'exacto' => '1',
            'total_esperado' => $precio, 'lineas' => json_encode([['id' => $producto, 'cantidad' => 1, 'nota' => '']]),
        ];
        $r = $this->post('/pos/cobrar', $pedido);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        $id = (int) json_decode($r->cuerpo, true, 512, JSON_THROW_ON_ERROR)['id'];
        $f = Factura::buscar($id);
        $this->assertSame($precio, (string) $f['monto_total']);
        $this->assertSame($literal, $f['snapshot']['literal']);
        $this->assertMatchesRegularExpression('/^[A-F0-9]{28}$/D', $f['cuf']);
        $this->assertStringContainsString('Son: ' . $literal, $this->get('/facturas/' . $id)->cuerpo);

        // Cambiar el catálogo no cambia una factura emitida, ni su literal al recargar.
        DB::consulta('UPDATE productos SET precio = 99 WHERE id = ?', [$producto]);
        $this->assertSame($f['snapshot_json'], Factura::buscar($id)['snapshot_json']);
        $this->assertStringContainsString('Son: ' . $literal, $this->get('/facturas/' . $id)->cuerpo);
        $this->assertSame(200, $this->post('/facturas/' . $id . '/reimpresion')->estado);
        $this->assertSame($literal, Factura::buscar($id)['snapshot']['literal']);

        // El reintento de cobro devuelve la misma factura con su literal guardado.
        $r = $this->post('/pos/cobrar', $pedido);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        $this->assertSame($id, (int) json_decode($r->cuerpo, true)['id']);
        $this->assertSame($f['snapshot_json'], Factura::buscar($id)['snapshot_json']);
    }
}
