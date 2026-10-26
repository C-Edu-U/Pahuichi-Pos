<?php

declare(strict_types=1);
namespace App\Services;
use App\Core\{Auth,DB,ErrorHttp,Reloj};
use App\Models\Bitacora;
use DomainException;

final class AnulacionVentaService
{
    public static function detalle(int $factura): ?array
    {
        return DB::fila('SELECT a.motivo,a.creado_en,u.nombre AS administrador FROM venta_anulaciones a JOIN usuarios u ON u.id=a.usuario_id WHERE a.factura_id=?',[$factura]);
    }
    public static function anular(int $factura, string $pin, string $motivo): array
    {
        if (!puede('ventas.anular')) throw new ErrorHttp(403);
        $motivo=trim($motivo);
        if (mb_strlen($motivo)<3 || mb_strlen($motivo)>120) throw new DomainException('Escribe el motivo de la anulación (de 3 a 120 caracteres).');
        // Fuera de la transacción: los intentos de PIN y su bloqueo se conservan si falla.
        $admin=AutorizacionService::verificarPin('anulacion',$pin);
        if ((int)$admin['id']!==Auth::id()) throw new DomainException('Usa el PIN de tu propia cuenta de administrador.');
        $origen=DB::fila('SELECT v.caja_id FROM facturas f JOIN ventas v ON v.id=f.venta_id WHERE f.id=?',[$factura]);
        if (!$origen) throw ErrorHttp::noEncontrado('Esa factura no existe.');
        return DB::transaccion(static function()use($factura,$motivo,$origen):array{
            // Mismo orden que cobro/cierre: primero la caja, luego la venta.
            $caja=DB::fila('SELECT id,estado FROM cajas WHERE id=? FOR UPDATE',[$origen['caja_id']]);
            $f=DB::fila('SELECT f.id,f.venta_id,f.numero,f.estado,v.estado AS venta_estado,v.total FROM facturas f JOIN ventas v ON v.id=f.venta_id WHERE f.id=? FOR UPDATE',[$factura]);
            if (!$f) throw ErrorHttp::noEncontrado();
            if ($f['estado']==='anulada' && $f['venta_estado']==='anulada') return ['estado'=>'anulada','cambiado'=>false,'numero'=>(int)$f['numero']];
            if (!$caja || $caja['estado']!=='abierta') throw new DomainException('La caja de esta venta está cerrada. Reábrela con motivo antes de anular.');
            if ($f['estado']!=='emitida' || $f['venta_estado']!=='cobrada') throw new DomainException('Solo se puede anular una venta cobrada con factura emitida.');
            DB::consulta("UPDATE ventas SET estado='anulada' WHERE id=?",[$f['venta_id']]);
            DB::consulta("UPDATE facturas SET estado='anulada' WHERE id=?",[$factura]);
            $ahora=Reloj::sql();
            DB::insertar('INSERT INTO venta_anulaciones (venta_id,factura_id,usuario_id,motivo,creado_en) VALUES (?,?,?,?,?)',[$f['venta_id'],$factura,Auth::id(),$motivo,$ahora]);
            Bitacora::registrar('venta.anulacion','ventas',$f['venta_id'],['factura_id'=>$factura,'factura_numero'=>(int)$f['numero'],'caja_id'=>(int)$caja['id'],'total'=>$f['total'],'motivo'=>$motivo,'fecha'=>$ahora,'antes'=>'cobrada','despues'=>'anulada']);
            return ['estado'=>'anulada','cambiado'=>true,'numero'=>(int)$f['numero']];
        });
    }
}
