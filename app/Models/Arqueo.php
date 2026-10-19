<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/**
 * Arqueo de caja (HU-23): conteo por denominación. El cajero cuenta a ciegas:
 * el total contado se guarda y el efectivo esperado se calcula después (HU-24).
 */
final class Arqueo
{
    /**
     * Denominaciones de Bolivia: billetes de 200 a 10 y monedas de 5 a 0,10.
     * La clave es lo que se guarda en el JSON del conteo.
     */
    public const DENOMINACIONES = [
        ['clave' => '200', 'valor' => 200.00, 'etiqueta' => 'Billete de Bs 200', 'tipo' => 'billete'],
        ['clave' => '100', 'valor' => 100.00, 'etiqueta' => 'Billete de Bs 100', 'tipo' => 'billete'],
        ['clave' => '50', 'valor' => 50.00, 'etiqueta' => 'Billete de Bs 50', 'tipo' => 'billete'],
        ['clave' => '20', 'valor' => 20.00, 'etiqueta' => 'Billete de Bs 20', 'tipo' => 'billete'],
        ['clave' => '10', 'valor' => 10.00, 'etiqueta' => 'Billete de Bs 10', 'tipo' => 'billete'],
        ['clave' => '5', 'valor' => 5.00, 'etiqueta' => 'Moneda de Bs 5', 'tipo' => 'moneda'],
        ['clave' => '2', 'valor' => 2.00, 'etiqueta' => 'Moneda de Bs 2', 'tipo' => 'moneda'],
        ['clave' => '1', 'valor' => 1.00, 'etiqueta' => 'Moneda de Bs 1', 'tipo' => 'moneda'],
        ['clave' => '0.50', 'valor' => 0.50, 'etiqueta' => 'Moneda de Bs 0,50', 'tipo' => 'moneda'],
        ['clave' => '0.20', 'valor' => 0.20, 'etiqueta' => 'Moneda de Bs 0,20', 'tipo' => 'moneda'],
        ['clave' => '0.10', 'valor' => 0.10, 'etiqueta' => 'Moneda de Bs 0,10', 'tipo' => 'moneda'],
    ];

    public static function porCaja(int $cajaId): ?array
    {
        $fila = DB::fila('SELECT * FROM arqueos WHERE caja_id = ?', [$cajaId]);
        if ($fila === null) {
            return null;
        }
        $fila['denominaciones'] = json_decode((string) $fila['denominaciones'], true) ?: [];
        $fila['total_contado'] = (float) $fila['total_contado'];
        return $fila;
    }

    public static function guardar(int $cajaId, array $conteo, float $total, string $creadoEn): void
    {
        DB::consulta(
            'INSERT INTO arqueos (caja_id, denominaciones, total_contado, creado_en) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE denominaciones = VALUES(denominaciones), total_contado = VALUES(total_contado), creado_en = VALUES(creado_en)',
            [$cajaId, json_encode($conteo, JSON_UNESCAPED_UNICODE), $total, $creadoEn],
        );
    }

    /** Total del conteo: suma de cada denominación por su cantidad. */
    public static function totalContado(array $conteo): float
    {
        $total = 0.0;
        foreach (self::DENOMINACIONES as $d) {
            $cantidad = (int) ($conteo[$d['clave']] ?? 0);
            $total += $d['valor'] * $cantidad;
        }
        return round($total, 2);
    }

    /** Subtotal de una denominación: valor por la cantidad contada. */
    public static function subtotal(string $clave, int $cantidad): float
    {
        foreach (self::DENOMINACIONES as $d) {
            if ($d['clave'] === $clave) {
                return round($d['valor'] * $cantidad, 2);
            }
        }
        return 0.0;
    }
}
