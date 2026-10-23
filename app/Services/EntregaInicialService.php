<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Sesion;
use App\Models\ConfiguracionTickets;
use App\Models\Configuracion;
use App\Models\Bitacora;
use DomainException;

/** HU-56: permiso de entrega inicial ligado a la sesión; los PDF iniciales se conservan por perfil. */
final class EntregaInicialService
{
    private const SESION = 'entregas_iniciales';

    /** Se llama exclusivamente después de confirmar el cobro; el reintento conserva el mismo permiso. */
    public static function abrir(array $factura): ?string
    {
        $id = (int) $factura['id'];
        $permisos = Sesion::get(self::SESION, []);
        $p = $permisos[$id] ?? null;
        if ($p && self::vigente($id, $p['token'])) return $p['token'];
        // La PK impide volver a abrir la entrega desde otra sesión o repitiendo el cobro histórico.
        $nueva = DB::consulta('INSERT IGNORE INTO entregas_tickets (factura_id) VALUES (?)', [$id])->rowCount();
        if ($nueva !== 1) return null;
        $token = bin2hex(random_bytes(24));
        $permisos[$id] = ['token' => $token, 'usuario' => Auth::id(), 'pdf' => []];
        Sesion::set(self::SESION, $permisos);
        return $token;
    }

    public static function vigente(int $id, string $token): bool
    {
        $p = Sesion::get(self::SESION, [])[$id] ?? null;
        return $p !== null && $p['usuario'] === Auth::id() && $token !== ''
            && hash_equals($p['token'], $token)
            && (empty($p['consulta_original']) || self::puedeConsultarOriginal())
            && ((string) DB::valor('SELECT cerrada FROM entregas_tickets WHERE factura_id=?', [$id]) === '0'
                || (!empty($p['consulta_original']) && self::puedeConsultarOriginal()));
    }

    public static function puedeConsultarOriginal(): bool
    {
        return Auth::rol() === 'administrador' || (Auth::rol() === 'cajero'
            && Configuracion::valor(Configuracion::ORIGINALES_CAJEROS, '0') === '1');
    }

    /** Consulta autorizada explícita; no reabre la entrega guardada ni modifica la factura. */
    public static function consultarOriginal(array $factura): string
    {
        if (!self::puedeConsultarOriginal()) throw new DomainException('No tienes permiso para consultar los documentos emitidos.');
        if ($factura['estado'] !== 'emitida' || DB::valor('SELECT estado FROM ventas WHERE id=?', [$factura['venta_id']]) !== 'cobrada') {
            throw new DomainException('La venta debe estar cobrada y no anulada.');
        }
        $id = (int) $factura['id'];
        $token = bin2hex(random_bytes(24));
        DB::transaccion(static function () use ($factura, $id): void {
            DB::consulta('INSERT IGNORE INTO entregas_tickets (factura_id, cerrada) VALUES (?, 1)', [$id]);
            Bitacora::registrar('factura.consulta_original', 'facturas', $id,
                ['numero' => $factura['numero'], 'venta_id' => $factura['venta_id'], 'medio' => 'boton_originales', 'usuario_rol' => Auth::rol()]);
        });
        $permisos = Sesion::get(self::SESION, []);
        $permisos[$id] = ['token' => $token, 'usuario' => Auth::id(), 'pdf' => [], 'consulta_original' => true];
        Sesion::set(self::SESION, $permisos);
        return $token;
    }

    public static function cerrar(int $id): void
    {
        DB::consulta('INSERT INTO entregas_tickets (factura_id, cerrada) VALUES (?, 1) ON DUPLICATE KEY UPDATE cerrada=1', [$id]);
        $p = Sesion::get(self::SESION, []);
        unset($p[$id]);
        Sesion::set(self::SESION, $p);
    }

    public static function cerrarTodas(): void
    {
        foreach (array_keys(Sesion::get(self::SESION, [])) as $id) self::cerrar((int) $id);
    }

    public static function entregados(int $id): array
    {
        $fila = DB::fila('SELECT * FROM entregas_tickets WHERE factura_id=?', [$id]);
        $tipos = [];
        foreach (array_keys(ConfiguracionTickets::TIPOS) as $tipo) {
            if (!empty($fila['entregado_' . $tipo])) $tipos[] = $tipo;
        }
        return $tipos;
    }

    /** Ver y descargar reutilizan exactamente los mismos bytes durante la entrega inicial. */
    public static function generar(array $factura, mixed $destino, string $token): array
    {
        $id = (int) $factura['id'];
        if (!self::vigente($id, $token)) throw new DomainException('La entrega inicial terminó. Recupera el ticket mediante Reimpresión.');
        if (!is_string($destino) || !isset(ConfiguracionTickets::DESTINOS[$destino])) throw new DomainException('Elige Caja o Cocina.');
        if ($factura['estado'] !== 'emitida' || DB::valor('SELECT estado FROM ventas WHERE id=?', [$factura['venta_id']]) !== 'cobrada') {
            throw new DomainException('La venta debe estar cobrada y no anulada.');
        }
        return DB::transaccion(static function () use ($factura, $id, $destino): array {
            $fila = DB::fila('SELECT * FROM entregas_tickets WHERE factura_id=? FOR UPDATE', [$id]);
            if (!$fila || ($fila['cerrada'] && !(
                !empty(Sesion::get(self::SESION, [])[$id]['consulta_original']) && self::puedeConsultarOriginal()
            ))) throw new DomainException('La entrega inicial terminó. Usa Reimpresión desde Ventas y facturas.');
            $permisos = Sesion::get(self::SESION, []);
            $guardado = $permisos[$id]['pdf'][$destino] ?? null;
            if ($guardado !== null) return ['bytes' => base64_decode($guardado['bytes'], true), 'nombre' => $guardado['nombre']];
            $salida = TicketsPdf::preparar($destino);
            $bytes = TicketsPdf::generar($factura['snapshot'], $salida);
            $nombre = 'Original-' . $destino . '-factura-' . (int) $factura['numero'] . '.pdf';
            foreach ($salida['tipos'] as $tipo) {
                // Claves validadas por TicketsPdf::preparar, nunca SQL recibido del navegador.
                DB::consulta('UPDATE entregas_tickets SET entregado_' . $tipo . '=1 WHERE factura_id=?', [$id]);
            }
            $permisos[$id]['pdf'][$destino] = ['bytes' => base64_encode($bytes), 'nombre' => $nombre,
                'tipos' => $salida['tipos']];
            Sesion::set(self::SESION, $permisos);
            return ['bytes' => $bytes, 'nombre' => $nombre];
        });
    }

    /** Describe el contenido conservado, o el perfil vigente antes de la primera generación. */
    public static function tipos(int $id, string $destino): array
    {
        return Sesion::get(self::SESION, [])[$id]['pdf'][$destino]['tipos']
            ?? TicketsPdf::preparar($destino)['tipos'];
    }
}
