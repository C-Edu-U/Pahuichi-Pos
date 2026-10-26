<?php

declare(strict_types=1);
namespace App\Services;
use App\Core\{Auth,DB,ErrorHttp,Reloj};
use App\Models\Bitacora;
use DomainException;

/** Recupera el QR rechazado en la venta original; conserva el documento emitido. */
final class SaldoQrService
{
    private static function centavos(string $monto): int
    {
        if (!preg_match('/^\d{1,8}(?:[.,]\d{1,2})?$/D',$monto)) throw new DomainException('Escribe el efectivo con hasta dos decimales.');
        $p=explode('.',str_replace(',','.',$monto));
        return (int)$p[0]*100+(int)str_pad($p[1]??'',2,'0');
    }
    /** La consulta de la factura autoriza el acceso antes de cargar este dato. */
    public static function detalle(int $factura): ?array
    {
        return DB::fila('SELECT r.creado_en,r.pago_cobro_id,p.monto,p.recibido,p.cambio,u.nombre AS cajero,q.comprobante
            FROM qr_recuperaciones r JOIN pagos p ON p.id=r.pago_cobro_id JOIN usuarios u ON u.id=r.usuario_id
            JOIN qr_pagos q ON q.pago_id=r.pago_rechazado_id WHERE r.factura_id=? ORDER BY r.creado_en DESC LIMIT 1',[$factura]);
    }
    public static function cobrar(int $factura, string $esperado, string $recibido): array
    {
        if (!puede('modulo.ventas')) throw new ErrorHttp(403);
        $importe=self::centavos($esperado);$rec=self::centavos($recibido);
        if ($importe<=0 || $rec<$importe) throw new DomainException('El efectivo recibido no cubre el saldo por cobrar.');
        $origen=DB::fila('SELECT v.caja_id,c.cajero_id FROM facturas f JOIN ventas v ON v.id=f.venta_id JOIN cajas c ON c.id=v.caja_id WHERE f.id=?',[$factura]);
        if (!$origen) throw ErrorHttp::noEncontrado();
        if (!puede('ventas.todas') && (int)$origen['cajero_id']!==Auth::id()) throw new ErrorHttp(403);
        return DB::transaccion(static function()use($factura,$importe,$rec,$origen):array{
            // Serializa recuperación, cobro y anulación sobre la caja de origen.
            $caja=DB::fila('SELECT id,estado FROM cajas WHERE id=? FOR UPDATE',[$origen['caja_id']]);
            $f=DB::fila('SELECT f.id,f.estado,f.venta_id,v.estado AS venta_estado FROM facturas f JOIN ventas v ON v.id=f.venta_id WHERE f.id=? FOR UPDATE',[$factura]);
            if (!$f || $f['estado']!=='emitida' || $f['venta_estado']!=='cobrada') throw new DomainException('No se puede cobrar el saldo de una venta anulada.');
            $pagos=DB::filas("SELECT p.id,p.monto,q.comprobante FROM pagos p JOIN qr_pagos q ON q.pago_id=p.id WHERE p.venta_id=? AND p.metodo='qr' AND p.estado='rechazado' ORDER BY p.id FOR UPDATE",[$f['venta_id']]);
            if (count($pagos)!==1) throw new DomainException('La venta no tiene un pago QR rechazado por recuperar. Actualiza la pantalla.');
            $p=$pagos[0];
            $previo=DB::fila('SELECT c.id,c.monto,c.recibido,c.cambio FROM qr_recuperaciones r JOIN pagos c ON c.id=r.pago_cobro_id WHERE r.pago_rechazado_id=? FOR UPDATE',[$p['id']]);
            if ($previo) {
                if (self::centavos($previo['monto'])!==$importe || self::centavos($previo['recibido'])!==$rec) throw new DomainException('Este saldo ya se cobró. Actualiza la venta para ver el recibo.');
                return ['cambiado'=>false,'pago_id'=>(int)$previo['id'],'monto'=>$previo['monto'],'recibido'=>$previo['recibido'],'cambio'=>$previo['cambio']];
            }
            if (!$caja || $caja['estado']!=='abierta') throw new DomainException('La caja de esta venta está cerrada. El administrador debe reabrirla antes de cobrar el saldo.');
            $deuda=self::centavos($p['monto']);
            if ($importe!==$deuda) throw new DomainException('Cambió el saldo pendiente. Actualiza la venta antes de cobrar.');
            $monto=VentaService::decimal($deuda);$recibido=VentaService::decimal($rec);$cambio=VentaService::decimal($rec-$deuda);
            $id=DB::insertar("INSERT INTO pagos (venta_id,metodo,monto,recibido,cambio,estado) VALUES (?,'efectivo',?,?,?,'confirmado')",[$f['venta_id'],$monto,$recibido,$cambio]);
            $fecha=Reloj::sql();
            DB::insertar('INSERT INTO qr_recuperaciones (pago_rechazado_id,pago_cobro_id,factura_id,usuario_id,creado_en) VALUES (?,?,?,?,?)',[$p['id'],$id,$factura,Auth::id(),$fecha]);
            Bitacora::registrar('venta.cobro_saldo_qr','ventas',$f['venta_id'],['factura_id'=>$factura,'caja_id'=>(int)$caja['id'],'pago_id'=>$id,'pago_rechazado_id'=>(int)$p['id'],'comprobante'=>$p['comprobante'],'monto'=>$monto,'recibido'=>$recibido,'cambio'=>$cambio]);
            return ['cambiado'=>true,'pago_id'=>$id,'monto'=>$monto,'recibido'=>$recibido,'cambio'=>$cambio];
        });
    }
}
