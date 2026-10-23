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
 * - HU-50: una promoción no acumulable no se combina con ninguna otra del pedido; si
 *   compiten, gana la de mayor beneficio. Las acumulables se aplican juntas por
 *   prioridad (menor primero), cada una sobre lo que dejó la anterior. Ver evaluar().
 * - HU-49: restricciones combinables, todas opcionales. Del momento: rango de fechas,
 *   días de la semana, franja horaria y tope de usos por día. Del pedido: cantidad
 *   mínima del producto o la categoría y monto mínimo del pedido.
 *
 * - Regalo por compra: por cada N unidades del producto o la categoría, se agrega solo
 *   1 del producto regalo a Bs 0, con el nombre de la promoción. Si el regalo está
 *   agotado o inactivo, no se agrega. Respeta las mismas restricciones de HU-49.
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
     * Evalúa el pedido y devuelve lo que se aplica (HU-50, RF-53, RN-09):
     * - Cada promoción no acumulable compite sola contra todas las demás opciones.
     * - Las acumulables se aplican juntas, una tras otra, por prioridad (menor primero).
     * - Gana la opción de mayor beneficio total para el cliente. A igual beneficio, las
     *   acumulables; entre no acumulables, la más antigua.
     *
     * @param list<array{producto_id: int, categoria_id: int, cantidad: int, bruto: int}> $lineas bruto en centavos
     * @param list<array> $promociones filas de Promocion::activas()
     * @param ?array $contexto de contexto(); sin él no se miran fecha, hora ni tope
     * @param array<int, int> $regalables precio en centavos de cada producto que se puede regalar (disponible)
     * @return array{lineas: list<array{descuento: int, partes: list<array{promocion: array, monto: int}>}>,
     *               regalos: list<array{promocion: array, producto_id: int, cantidad: int, monto: int}>, beneficio: int}
     */
    public static function evaluar(array $lineas, array $promociones, ?array $contexto = null, array $regalables = []): array
    {
        $validas = array_values(array_filter(
            $promociones,
            static fn (array $p): bool => ($contexto === null || self::vigente($p, $contexto)) && self::cumple($p, $lineas),
        ));

        $mejor = self::simular($lineas, [], $regalables);
        foreach ($validas as $p) {
            if (!(int) ($p['acumulable'] ?? 0)) {
                $opcion = self::simular($lineas, [$p], $regalables);
                if ($opcion['beneficio'] > $mejor['beneficio']) {
                    $mejor = $opcion;
                }
            }
        }
        $acumulables = array_values(array_filter($validas, static fn (array $p): bool => (bool) (int) ($p['acumulable'] ?? 0)));
        if ($acumulables) {
            usort($acumulables, static fn (array $a, array $b): int => [(int) ($a['prioridad'] ?? 10), (int) $a['id']] <=> [(int) ($b['prioridad'] ?? 10), (int) $b['id']]);
            $opcion = self::simular($lineas, $acumulables, $regalables);
            if ($opcion['beneficio'] > 0 && $opcion['beneficio'] >= $mejor['beneficio']) {
                $mejor = $opcion;
            }
        }
        return $mejor;
    }

    /** Aplica las promociones en orden: cada una descuenta sobre lo que dejó la anterior. */
    private static function simular(array $lineas, array $promociones, array $regalables): array
    {
        $restante = array_column($lineas, 'bruto');
        $resultado = array_map(static fn (): array => ['descuento' => 0, 'partes' => []], $lineas);
        $regalos = [];
        $beneficio = 0;
        foreach ($promociones as $p) {
            $tipo = $p['tipo'] ?? 'descuento';
            if ($tipo === 'descuento') {
                foreach ($lineas as $i => $l) {
                    if (!self::alcanza($p, $l)) {
                        continue;
                    }
                    $d = min(self::descuento($p, $restante[$i], $l['cantidad']), $restante[$i]);
                    if ($d > 0) {
                        $restante[$i] -= $d;
                        $resultado[$i]['descuento'] += $d;
                        $resultado[$i]['partes'][] = ['promocion' => $p, 'monto' => $d];
                        $beneficio += $d;
                    }
                }
            } elseif ($tipo === 'regalo') {
                $unidades = 0;
                foreach ($lineas as $l) {
                    $unidades += self::alcanza($p, $l) ? $l['cantidad'] : 0;
                }
                $cantidad = intdiv($unidades, max(1, (int) $p['cantidad_compra']));
                $precio = $regalables[(int) $p['regalo_producto_id']] ?? null;
                if ($cantidad > 0 && $precio !== null) {
                    $regalos[] = ['promocion' => $p, 'producto_id' => (int) $p['regalo_producto_id'], 'cantidad' => $cantidad, 'monto' => $precio * $cantidad];
                    $beneficio += $precio * $cantidad;
                }
            }
        }
        return ['lineas' => $resultado, 'regalos' => $regalos, 'beneficio' => $beneficio];
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

    /** Descuento en centavos sobre lo que queda de la línea, nunca mayor que eso. */
    public static function descuento(array $p, int $base, int $cantidad): int
    {
        $valor = self::centesimos((string) $p['valor']);
        $d = $p['valor_tipo'] === 'porcentaje'
            ? intdiv($base * $valor + 5000, 10000)   // % con dos decimales, redondeo al centavo
            : $valor * $cantidad;                    // Bs por unidad
        return min($d, $base);
    }

    /** «12.50» → 1250 */
    public static function centesimos(string $decimal): int
    {
        $p = explode('.', $decimal);
        return (int) $p[0] * 100 + (int) str_pad(substr($p[1] ?? '', 0, 2), 2, '0');
    }

    /**
     * Texto de la regla para la lista y el formulario: «10 % menos», «Bs 5,00 menos por unidad»
     * o «1 Soda cola 500 ml gratis por cada uno» (el regalo con su nombre en $p['regalo']).
     */
    public static function regla(array $p): string
    {
        if (($p['tipo'] ?? 'descuento') === 'regalo') {
            $n = (int) $p['cantidad_compra'];
            return '1 ' . ($p['regalo'] ?? 'regalo') . ' gratis por cada ' . ($n === 1 ? 'uno' : $n);
        }
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

    /** «Acumulable · prioridad 2» o «No acumulable». */
    public static function combinacion(array $p): string
    {
        return (int) ($p['acumulable'] ?? 0) ? 'Acumulable · prioridad ' . (int) ($p['prioridad'] ?? 10) : 'No acumulable';
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
