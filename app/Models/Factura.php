<?php

declare(strict_types=1);
namespace App\Models;

use App\Core\DB;

final class Factura
{
    private const SELECT = 'SELECT f.*, v.usuario_id, v.caja_id, c.cajero_id FROM facturas f JOIN ventas v ON v.id = f.venta_id JOIN cajas c ON c.id = v.caja_id';

    public static function buscar(int $id): ?array
    {
        $f = DB::fila(self::SELECT . ' WHERE f.id = ?', [$id]);
        if ($f) $f['snapshot'] = json_decode($f['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
        return $f;
    }

    public static function deVenta(int $id): ?array
    {
        return DB::fila('SELECT * FROM facturas WHERE venta_id = ?', [$id]);
    }

    /** Debe ejecutarse dentro de la misma transacción que inserta la factura. */
    public static function siguientes(int $sucursal, int $pv, int $inicial): int
    {
        if (!DB::conexion()->inTransaction()) throw new \LogicException('El correlativo exige una transacción.');
        DB::consulta('INSERT INTO correlativos (sucursal, punto_venta, ultimo_numero) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE punto_venta = VALUES(punto_venta)', [$sucursal, $pv, $inicial - 1]);
        $ultimo = (int) DB::valor('SELECT ultimo_numero FROM correlativos WHERE sucursal = ? AND punto_venta = ? FOR UPDATE', [$sucursal, $pv]);
        if ($ultimo >= 9999999999) throw new \DomainException('Se alcanzó el límite de numeración del punto de venta.');
        $numero = $ultimo + 1;
        DB::consulta('UPDATE correlativos SET ultimo_numero = ? WHERE sucursal = ? AND punto_venta = ?', [$numero, $sucursal, $pv]);
        return $numero;
    }

    public static function crear(int $venta, array $s, int $leyendaId): int
    {
        return DB::insertar('INSERT INTO facturas (venta_id, sucursal, punto_venta, numero, cuf, cufd, fecha_emision, leyenda_id, leyenda, monto_total, importe_base_cf, snapshot_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$venta, $s['fiscal']['codigo_sucursal'], $s['fiscal']['codigo_punto_venta'], $s['factura'], $s['cuf'], $s['fiscal']['cufd'], $s['fecha_emision'], $leyendaId, $s['leyenda'], $s['total'], $s['total'], json_encode($s, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    }

    public static function ultimas(?int $cajero): array
    {
        return DB::filas(self::SELECT . ($cajero !== null ? ' WHERE c.cajero_id = ?' : '') . ' ORDER BY f.id DESC LIMIT 100', $cajero !== null ? [$cajero] : []);
    }

    public static function verificar(string $cuf, string $nit, string $numero, string $monto): ?array
    {
        $f = DB::fila('SELECT id FROM facturas WHERE cuf = ? AND numero = ? AND monto_total = ?', [$cuf, $numero, $monto]);
        if (!$f) return null;
        $f = self::buscar((int) $f['id']);
        return hash_equals((string) $f['snapshot']['negocio']['nit'], $nit) ? $f : null;
    }
}
