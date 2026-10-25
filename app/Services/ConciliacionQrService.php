<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Auth,DB,Reloj};
use App\Models\Bitacora;
use DomainException;
final class ConciliacionQrService
{
    private const JOIN = ' FROM pagos p JOIN qr_pagos q ON q.pago_id=p.id JOIN ventas v ON v.id=p.venta_id
        JOIN cajas c ON c.id=v.caja_id JOIN turnos t ON t.id=c.turno_id JOIN usuarios u ON u.id=c.cajero_id
        JOIN facturas f ON f.venta_id=v.id LEFT JOIN qr_conciliaciones r ON r.pago_id=p.id
        LEFT JOIN usuarios administrador ON administrador.id=r.usuario_id ';
    private static function autorizar(): void
    {
        if (!puede('conciliacion.resolver')) throw new DomainException('Solo el administrador puede conciliar pagos QR.');
    }
    public static function filtrar(array $f): array
    {
        self::autorizar();$fecha=$f['fecha']??'';$estado=$f['estado']??'';$turno=$f['turno']??'';
        foreach([$fecha,$estado,$turno] as $s) if (!is_string($s)) throw new DomainException('Revisa los filtros.');
        if ($fecha!=='') {
            $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);
            if (!$d || $d->format('Y-m-d')!==$fecha) throw new DomainException('Selecciona una fecha válida.');
        }
        if (!in_array($estado,['','pendiente','confirmado','rechazado'],true)) throw new DomainException('Selecciona un estado válido.');
        if ($turno!=='' && (!ctype_digit($turno) || !DB::valor('SELECT id FROM turnos WHERE id=?',[$turno]))) throw new DomainException('Selecciona un turno válido.');
        return ['fecha'=>$fecha,'turno'=>$turno,'estado'=>$estado];
    }
    public static function listar(array $f, int $pagina=1): array
    {
        $f=self::filtrar($f);$d=['p.metodo=\'qr\''];$p=[];
        if($f['fecha']!==''){$d[]='v.creado_en>=? AND v.creado_en<DATE_ADD(?,INTERVAL 1 DAY)';array_push($p,$f['fecha'],$f['fecha']);}
        if($f['turno']!==''){$d[]='c.turno_id=?';$p[]=$f['turno'];}
        if($f['estado']!==''){$d[]='p.estado=?';$p[]=$f['estado'];}
        $where=' WHERE '.implode(' AND ',$d);$total=(int)DB::valor('SELECT COUNT(*)'.self::JOIN.$where,$p);
        $paginas=max(1,(int)ceil($total/20));$pagina=max(1,min($pagina,$paginas));$offset=($pagina-1)*20;
        $filas=DB::filas('SELECT p.id,p.estado,p.monto,v.id AS venta_id,v.estado AS venta_estado,v.creado_en,
            q.comprobante,q.pagador,(q.foto IS NOT NULL) AS tiene_foto,c.id AS caja_id,u.nombre AS cajero,
            t.nombre AS turno,f.id AS factura_id,f.numero,r.resuelto_en,administrador.nombre AS administrador'
            .self::JOIN.$where.' ORDER BY v.creado_en DESC,p.id DESC LIMIT 20 OFFSET '.$offset,$p);
        return compact('filas','total','pagina','paginas','f');
    }
    public static function resolver(int $id, string $estado): array
    {
        self::autorizar();
        if(!in_array($estado,['confirmado','rechazado'],true)) throw new DomainException('Elige Confirmar o Rechazar.');
        return DB::transaccion(static function()use($id,$estado):array{
            $p=DB::fila('SELECT p.id,p.estado,p.monto,p.venta_id,v.estado AS venta_estado,v.caja_id,c.cajero_id,q.comprobante
                FROM pagos p JOIN qr_pagos q ON q.pago_id=p.id JOIN ventas v ON v.id=p.venta_id
                JOIN cajas c ON c.id=v.caja_id WHERE p.id=? AND p.metodo=\'qr\' FOR UPDATE',[$id]);
            if(!$p) throw new DomainException('Ese pago QR no existe.');
            if($p['venta_estado']!=='cobrada') throw new DomainException('La venta está anulada; no se puede conciliar su pago.');
            if($p['estado']===$estado)return ['estado'=>$estado,'cambiado'=>false];
            if($p['estado']!=='pendiente')throw new DomainException('El pago ya fue resuelto. Actualiza la bandeja para ver su estado actual.');
            $ahora=Reloj::sql();
            DB::consulta('UPDATE pagos SET estado=? WHERE id=?',[$estado,$id]);
            DB::consulta('INSERT INTO qr_conciliaciones (pago_id,usuario_id,estado,resuelto_en) VALUES (?,?,?,?)',[$id,Auth::id(),$estado,$ahora]);
            Bitacora::registrar($estado==='confirmado'?'qr.confirmar':'qr.rechazar','pagos',$id,
                ['venta_id'=>(int)$p['venta_id'],'caja_id'=>(int)$p['caja_id'],'comprobante'=>$p['comprobante'],'monto'=>$p['monto'],'antes'=>'pendiente','despues'=>$estado]);
            if($estado==='rechazado')DB::consulta('INSERT INTO qr_avisos (pago_id,usuario_id,creado_en) VALUES (?,?,?)',[$id,$p['cajero_id'],$ahora]);
            return ['estado'=>$estado,'cambiado'=>true];
        });
    }
    public static function foto(int $id): ?array
    {
        self::autorizar();return DB::fila('SELECT foto,foto_tipo FROM qr_pagos WHERE pago_id=? AND foto IS NOT NULL',[$id]);
    }
    public static function avisos(): array
    {
        return DB::filas('SELECT a.id,p.venta_id,p.monto,q.comprobante,v.caja_id,f.id AS factura_id
            FROM qr_avisos a JOIN pagos p ON p.id=a.pago_id JOIN qr_pagos q ON q.pago_id=p.id
            JOIN ventas v ON v.id=p.venta_id JOIN facturas f ON f.venta_id=v.id
            WHERE a.usuario_id=? AND a.visto_en IS NULL AND p.estado=\'rechazado\' ORDER BY a.id DESC LIMIT 20',[Auth::id()]);
    }
    public static function enterado(int $id): void
    {
        DB::consulta('UPDATE qr_avisos SET visto_en=? WHERE id=? AND usuario_id=? AND visto_en IS NULL',[Reloj::sql(),$id,Auth::id()]);
    }
}
