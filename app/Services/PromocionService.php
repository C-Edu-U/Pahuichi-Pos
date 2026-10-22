<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Promocion;
use DateTimeImmutable;

/**
 * Motor de promociones (HU-46, RF-31, RF-48, RN-08; restricciones de HU-49, RF-51).
 * El navegador corre el mismo cálculo en public/assets/js/motor-promociones.js para
 * mostrar el pedido; el que vale es este, al cobrar.
 *
 * Reglas:
 * - Descuento por producto o por categoría, en porcentaje del subtotal de la línea
 *   o en un monto fijo por unidad.
 * - Se aplica solo, al agregar el producto: el cajero no elige nada.
 * - Ningún descuento deja una línea en negativo.
 * - Si varias promociones tocan la misma línea, se aplica la de mayor beneficio (a
 *   igual beneficio, la más antigua). Acumularlas por prioridad llega con HU-50.
 * - HU-49: restricciones combinables, todas opcionales. Del momento: rango de fechas,
 *   días de la semana, franja horaria y tope de usos por día. Del pedido: cantidad
 *   mínima del producto o la categoría y monto mínimo del pedido.
 *
 * Todo en centavos enteros, para que el navegador y el servidor den lo mismo.
 */
final class PromocionService
{
    public const DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
    public const TODOS_LOS_DIAS = 127;

    /**
     * Momento en que se evalúa y usos de hoy de cada promoción.
     * @return array{fecha: string, dia: int, hora: string, usos: array<int, int>}
     */
    public static function contexto(DateTimeImmutable $ahora): array
    {
        return [
            'fecha' => $ahora->format('Y-m-d'),
            'dia' => (int) $ahora->format('N'),   // 1 = lunes … 7 = domingo
            'hora' => $ahora->format('H:i:s'),
            'usos' => Promocion::usosDelDia($ahora->format('Y-m-d')),
        ];
    }

    /**
     * @param list<array{producto_id: int, categoria_id: int, cantidad: int, bruto: int}> $lineas bruto en centavos
     * @param list<array> $promociones filas de Promocion::activas()
     * @param ?array $contexto de contexto(); sin él no se miran fecha, hora ni tope
     * @return list<array{descuento: int, promocion: ?array}> una entrada por línea, en el mismo orden
     */
    public static function aplicar(array $lineas, array $promociones, ?array $contexto = null): array
    {
        $validas = array_values(array_filter(
            $promociones,
            static fn (array $p): bool => ($contexto === null || self::vigente($p, $contexto)) && self::cumple($p, $lineas),
        ));

        $resultado = [];
        foreach ($lineas as $l) {
            $mejor = ['descuento' => 0, 'promocion' => null];
            foreach ($validas as $p) {
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

    /** ¿Vale en este momento? Fechas, día, franja horaria y tope de usos del día. */
    public static function vigente(array $p, array $c): bool
    {
        return self::motivoFueraDeVigencia($p, $c) === null;
    }

    /** Por qué no vale ahora (programada, vencida, dia, hora, tope) o null si vale. */
    public static function motivoFueraDeVigencia(array $p, array $c): ?string
    {
        if (($p['fecha_desde'] ?? null) !== null && $c['fecha'] < $p['fecha_desde']) {
            return 'programada';
        }
        if (($p['fecha_hasta'] ?? null) !== null && $c['fecha'] > $p['fecha_hasta']) {
            return 'vencida';
        }
        if (!(((int) ($p['dias'] ?? self::TODOS_LOS_DIAS) >> ($c['dia'] - 1)) & 1)) {
            return 'dia';
        }
        if (($p['hora_desde'] ?? null) !== null && ($c['hora'] < $p['hora_desde'] || $c['hora'] >= $p['hora_hasta'])) {
            return 'hora';
        }
        if (($p['tope_usos_dia'] ?? null) !== null && ($c['usos'][(int) $p['id']] ?? 0) >= (int) $p['tope_usos_dia']) {
            return 'tope';
        }
        return null;
    }

    /** ¿El pedido cumple la cantidad mínima del producto o la categoría y el monto mínimo? */
    private static function cumple(array $p, array $lineas): bool
    {
        if (($p['monto_minimo'] ?? null) !== null
            && array_sum(array_column($lineas, 'bruto')) < self::centesimos((string) $p['monto_minimo'])) {
            return false;
        }
        if (($p['cantidad_minima'] ?? null) !== null) {
            $unidades = 0;
            foreach ($lineas as $l) {
                $unidades += self::alcanza($p, $l) ? $l['cantidad'] : 0;
            }
            if ($unidades < (int) $p['cantidad_minima']) {
                return false;
            }
        }
        return true;
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
    public static function centesimos(string $decimal): int
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

    /** «De lunes a viernes, 12:00 a 15:00 · del 01/11 al 30/11» */
    public static function cuando(array $p): string
    {
        $dias = (int) ($p['dias'] ?? self::TODOS_LOS_DIAS);
        $partes = [match (true) {
            $dias === self::TODOS_LOS_DIAS => 'Todos los días',
            $dias === 31 => 'De lunes a viernes',
            $dias === 96 => 'Sábado y domingo',
            default => implode(', ', array_map(
                static fn (int $d): string => mb_strtolower(self::DIAS[$d]),
                array_values(array_filter(array_keys(self::DIAS), static fn (int $d): bool => (bool) (($dias >> ($d - 1)) & 1))),
            )),
        }];
        if (($p['hora_desde'] ?? null) !== null) {
            $partes[0] .= ', ' . substr($p['hora_desde'], 0, 5) . ' a ' . substr($p['hora_hasta'], 0, 5);
        }
        $fecha = static fn (string $f): string => (new DateTimeImmutable($f))->format('d/m/Y');
        $desde = $p['fecha_desde'] ?? null;
        $hasta = $p['fecha_hasta'] ?? null;
        if ($desde !== null && $hasta !== null) {
            $partes[] = 'del ' . $fecha($desde) . ' al ' . $fecha($hasta);
        } elseif ($desde !== null) {
            $partes[] = 'desde el ' . $fecha($desde);
        } elseif ($hasta !== null) {
            $partes[] = 'hasta el ' . $fecha($hasta);
        }
        return implode(' · ', $partes);
    }

    /** «Desde 2 unidades · pedido desde Bs 50,00 · tope 20 por día», o «Sin condiciones». */
    public static function condiciones(array $p): string
    {
        $partes = [];
        if (($p['cantidad_minima'] ?? null) !== null) {
            $partes[] = 'Desde ' . (int) $p['cantidad_minima'] . ' unidades';
        }
        if (($p['monto_minimo'] ?? null) !== null) {
            $partes[] = 'pedido desde Bs ' . bs($p['monto_minimo']);
        }
        if (($p['tope_usos_dia'] ?? null) !== null) {
            $partes[] = 'tope ' . (int) $p['tope_usos_dia'] . ' por día';
        }
        return $partes ? ucfirst(implode(' · ', $partes)) : 'Sin condiciones';
    }
}
