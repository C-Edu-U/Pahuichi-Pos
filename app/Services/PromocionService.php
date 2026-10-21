<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Motor de promociones (HU-46, RF-31, RF-48, RN-08). El navegador corre el mismo
 * cálculo en public/assets/js/motor-promociones.js para mostrar el pedido; el que
 * vale es este, al cobrar.
 *
 * Reglas de esta historia:
 * - Descuento por producto o por categoría, en porcentaje del subtotal de la línea
 *   o en un monto fijo por unidad.
 * - Se aplica solo, al agregar el producto: el cajero no elige nada.
 * - Ningún descuento deja una línea en negativo.
 * - Si varias promociones tocan la misma línea, se aplica la de mayor beneficio (a
 *   igual beneficio, la más antigua). Acumularlas por prioridad llega con HU-50.
 *
 * Todo en centavos enteros, para que el navegador y el servidor den lo mismo.
 */
final class PromocionService
{
    /**
     * @param list<array{producto_id: int, categoria_id: int, cantidad: int, bruto: int}> $lineas bruto en centavos
     * @param list<array> $promociones filas de Promocion::activas()
     * @return list<array{descuento: int, promocion: ?array}> una entrada por línea, en el mismo orden
     */
    public static function aplicar(array $lineas, array $promociones): array
    {
        $resultado = [];
        foreach ($lineas as $l) {
            $mejor = ['descuento' => 0, 'promocion' => null];
            foreach ($promociones as $p) {
                if (!self::alcanza($p, $l)) {
                    continue;
                }
                $d = self::descuento($p, $l);
                // Gana la de mayor beneficio; las promociones vienen ordenadas por id
                if ($d > $mejor['descuento']) {
                    $mejor = ['descuento' => $d, 'promocion' => $p];
                }
            }
            $resultado[] = $mejor;
        }
        return $resultado;
    }

    /** ¿La promoción toca esta línea? */
    private static function alcanza(array $p, array $l): bool
    {
        return $p['producto_id'] !== null
            ? (int) $p['producto_id'] === $l['producto_id']
            : (int) $p['categoria_id'] === $l['categoria_id'];
    }

    /** Descuento en centavos, nunca mayor que la línea. */
    public static function descuento(array $p, array $l): int
    {
        $valor = self::centesimos((string) $p['valor']);
        $d = $p['valor_tipo'] === 'porcentaje'
            ? intdiv($l['bruto'] * $valor + 5000, 10000)   // % con dos decimales, redondeo al centavo
            : $valor * $l['cantidad'];                       // Bs por unidad
        return min($d, $l['bruto']);
    }

    /** «12.50» → 1250 */
    private static function centesimos(string $decimal): int
    {
        $p = explode('.', $decimal);
        return (int) $p[0] * 100 + (int) str_pad(substr($p[1] ?? '', 0, 2), 2, '0');
    }

    /** Texto de la regla para la lista y el formulario: «10 % menos» o «Bs 5,00 menos por unidad». */
    public static function regla(array $p): string
    {
        return $p['valor_tipo'] === 'porcentaje'
            ? rtrim(rtrim(number_format((float) $p['valor'], 2, ',', ''), '0'), ',') . ' % menos'
            : 'Bs ' . bs($p['valor']) . ' menos por unidad';
    }
}
