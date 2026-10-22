<?php

declare(strict_types=1);
namespace App\Models;

use App\Core\DB;

/** HU-12: perfiles persistentes para los PDF de caja y cocina. */
final class ConfiguracionTickets
{
    public const DESTINOS = ['caja' => 'Caja', 'cocina' => 'Cocina'];
    public const TIPOS = ['comanda' => 'Comanda', 'copia' => 'Copia de caja', 'factura' => 'Factura'];

    public static function todas(): array
    {
        $perfiles = [];
        foreach (DB::filas('SELECT * FROM configuracion_tickets ORDER BY destino') as $fila) {
            foreach (['ancho', 'columnas', 'copias', 'factura', 'copia', 'comanda'] as $campo) $fila[$campo] = (int) $fila[$campo];
            $perfiles[$fila['destino']] = $fila;
        }
        return $perfiles;
    }

    public static function guardar(string $destino, array $d): void
    {
        DB::consulta('UPDATE configuracion_tickets SET ancho=?, columnas=?, copias=?, factura=?, copia=?, comanda=? WHERE destino=?',
            [$d['ancho'], $d['columnas'], $d['copias'], $d['factura'], $d['copia'], $d['comanda'], $destino]);
    }
}
