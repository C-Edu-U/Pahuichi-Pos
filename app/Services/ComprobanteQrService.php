<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Auth, DB, Reloj};
use DomainException;
final class ComprobanteQrService
{
    private static function normalizar(string $ref): string
    {
        $ref=trim($ref);
        if (!preg_match('/^[\p{L}\p{N}\-\/_.]{1,80}$/uD', $ref)) throw new DomainException('Escribe un comprobante válido, de hasta 80 caracteres y sin espacios.');
        return $ref;
    }
    /** Consulta todo el historial: un pago no se puede reutilizar otro dia. */
    public static function buscar(string $ref, bool $bloquear = false): ?array
    {
        $ref=self::normalizar($ref);
        $f=DB::fila('SELECT v.id AS venta_id, f.id AS factura_id, f.numero, v.creado_en AS fecha, p.monto, c.cajero_id
            FROM qr_comprobantes q JOIN pagos p ON p.id=q.pago_id JOIN ventas v ON v.id=p.venta_id
            JOIN facturas f ON f.venta_id=v.id JOIN cajas c ON c.id=v.caja_id
            WHERE q.comprobante=?' . ($bloquear ? ' FOR UPDATE' : ''), [$ref]);
        if (!$f) return null;
        $puedeVer=puede('ventas.todas') || (int)$f['cajero_id']===Auth::id();
        return ['venta_id'=>(int)$f['venta_id'], 'numero'=>(int)$f['numero'], 'monto'=>$f['monto'], 'fecha'=>$f['fecha'],
            'tickets'=>$puedeVer ? '/facturas/'.$f['factura_id'] : null];
    }
    /** Se llama dentro de la transaccion del cobro, despues de resolver reintentos. */
    public static function reservar(string $ref, string $fecha): void
    {
        $ref=self::normalizar($ref);
        // La clave primaria serializa dos cajas que intenten usar la misma referencia.
        DB::consulta('INSERT INTO qr_comprobantes (fecha, comprobante) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE comprobante=VALUES(comprobante)', [$fecha, $ref]);
        if ($original=self::buscar($ref,true)) throw new ComprobanteQrDuplicado($original);
    }
    public static function asignar(string $ref, string $fecha, int $pago): void
    {
        $r=DB::consulta('UPDATE qr_comprobantes SET pago_id=? WHERE fecha=? AND comprobante=? AND pago_id IS NULL', [$pago,$fecha,self::normalizar($ref)]);
        if ($r->rowCount() !== 1) throw new \LogicException('No se pudo asignar el comprobante reservado.');
    }
    /** Demostracion de solo lectura: hoy frente al dia siguiente simulado. */
    public static function demostrar(string $ref, string $accion): array
    {
        if (!puede('qr.subir')) throw new DomainException('Solo el administrador puede realizar la demostración.');
        if (!in_array($accion,['mismo_dia','otro_dia'],true)) throw new DomainException('Elige uno de los escenarios.');
        $ref=self::normalizar($ref);
        $original=self::buscar($ref);
        if (!$original) throw new DomainException('Escribe el comprobante de una venta QR ya registrada. La demostración utiliza un pago existente en el sistema.');
        $registro=substr($original['fecha'],0,10);
        $dia=new \DateTimeImmutable($registro, Reloj::ahora()->getTimezone());
        $evaluada=$accion==='otro_dia' ? $dia->modify('+1 day')->format('Y-m-d') : $registro;
        $duplicado=self::buscar($ref);
        return ['comprobante'=>$ref,'fecha_registro'=>$registro,'fecha_evaluada'=>$evaluada,'aceptado'=>$duplicado===null,'original'=>$original];
    }
}
