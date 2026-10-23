<?php

declare(strict_types=1);
namespace Tests\Unit;

use App\Services\PromocionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Casos de tests/promociones/casos.json: el motor de PHP debe dar lo que dice cada caso.
 * El de JavaScript corre los mismos casos con `node tests/js/motor-promociones.test.mjs`;
 * si los dos pasan, el navegador y el servidor calculan igual y el cobro no se rechaza.
 */
final class MotorCasosCompartidosTest extends TestCase
{
    private const VACIA = ['nombre' => '', 'tipo' => 'descuento', 'valor_tipo' => null, 'valor' => null, 'producto_id' => null, 'categoria_id' => null,
        'cantidad_compra' => null, 'regalo_producto_id' => null, 'precio_combo' => null, 'items' => [], 'fecha_desde' => null, 'fecha_hasta' => null, 'dias' => 127, 'hora_desde' => null,
        'hora_hasta' => null, 'cantidad_minima' => null, 'monto_minimo' => null, 'tope_usos_dia' => null, 'acumulable' => false, 'prioridad' => 10];

    public static function casos(): array
    {
        $datos = json_decode((string) file_get_contents(dirname(__DIR__) . '/promociones/casos.json'), true, 32, JSON_THROW_ON_ERROR);
        $casos = [];
        foreach ($datos['casos'] as $c) {
            $casos[$c['nombre']] = [$c];
        }
        return $casos;
    }

    #[DataProvider('casos')]
    public function testElMotorDaLoEsperado(array $caso): void
    {
        $lineas = array_map(static fn (array $l): array => $l + ['bruto' => $l['precio'] * $l['cantidad']], $caso['lineas']);
        $promociones = array_map(static fn (array $p): array => $p + ['nombre' => 'Promo ' . $p['id']] + self::VACIA, $caso['promociones']);
        $regalables = [];
        foreach ($caso['regalables'] ?? [] as $id => $precio) {
            $regalables[(int) $id] = $precio;
        }

        $r = PromocionService::evaluar($lineas, $promociones, $caso['contexto'] ?? null, $regalables);

        $this->assertSame($caso['esperado']['lineas'], array_map(
            static fn (array $l): array => array_map(static fn (array $x): array => [(int) $x['promocion']['id'], $x['monto']], $l['partes']),
            $r['lineas'],
        ));
        $this->assertSame($caso['esperado']['regalos'], array_map(
            static fn (array $g): array => [(int) $g['promocion']['id'], $g['producto_id'], $g['cantidad'], $g['monto']],
            $r['regalos'],
        ));
        $this->assertSame($caso['esperado']['beneficio'], $r['beneficio']);
        foreach ($r['lineas'] as $l) {
            $this->assertSame(array_sum(array_column($l['partes'], 'monto')), $l['descuento']);
        }
    }
}
