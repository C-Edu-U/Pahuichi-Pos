<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Reloj;
use App\Models\Bitacora;
use DomainException;

final class QrDiarioService
{
    public static function estado(bool $bloquear = false): array
    {
        $ahora = Reloj::ahora();
        $disponible = true;
        $demoDisponible = true;
        try {
            $q = DB::fila('SELECT id, fecha, monto, vencimiento_demo FROM qr_diarios WHERE fecha = ? ORDER BY id DESC LIMIT 1' . ($bloquear ? ' LOCK IN SHARE MODE' : ''), [$ahora->format('Y-m-d')]);
        } catch (\PDOException $e) {
            // Una restauración de una copia anterior a HU-38 conserva su esquema anterior.
            // Se puede revisar y cobrar en efectivo sin migrar ni alterar esa copia.
            if (getenv('APP_RESTAURADA') !== '1' || !in_array($e->getCode(), ['42S02', '42S22'], true)) throw $e;
            $demoDisponible = false;
            if ($e->getCode() === '42S22') {
                $q = DB::fila('SELECT id, fecha, monto FROM qr_diarios WHERE fecha = ? ORDER BY id DESC LIMIT 1' . ($bloquear ? ' LOCK IN SHARE MODE' : ''), [$ahora->format('Y-m-d')]);
            } else {
                $q = null;
                $disponible = false;
            }
        }
        $normal = $ahora->modify('tomorrow')->setTime(0, 0)->getTimestamp();
        $demo = isset($q['vencimiento_demo']) ? (new \DateTimeImmutable($q['vencimiento_demo'], new \DateTimeZone('America/La_Paz')))->getTimestamp() : null;
        $vence = $demo === null ? $normal : min($normal, $demo);
        $vigente = $q !== null && $ahora->getTimestamp() < $vence;
        return ['demo_disponible' => $demoDisponible, 'simulado' => $demo !== null, 'disponible' => $disponible, 'vigente' => $vigente, 'id' => $q ? (int) $q['id'] : null, 'monto' => $q['monto'] ?? null,
            'fecha' => $ahora->format('Y-m-d'), 'ahora' => $ahora->getTimestamp(),
            'vence' => $vence,
            'aviso' => $vigente ? ($demo === null ? 'QR vigente hasta las 23:59:59.' : 'Demostración: el QR vencerá en pocos segundos.') : ($demo === null ? 'No hay QR vigente. Solicita al administrador el QR de hoy.' : 'QR vencido por simulación. Solicita al administrador el QR de hoy.')];
    }

    /** Acorta solamente la vigencia del QR actual; nunca cambia el reloj del sistema. */
    public static function simular(string $accion, int $id): array
    {
        if (!puede('qr.subir')) throw new DomainException('Solo el administrador puede realizar esta demostración.');
        if (!in_array($accion, ['vencer', 'restablecer'], true)) throw new DomainException('Acción de demostración inválida.');
        if ($id < 1) throw new DomainException('Primero carga un QR vigente del día.');
        return DB::transaccion(static function () use ($accion, $id): array {
            $estado = self::estado();
            if (!$estado['demo_disponible']) throw new DomainException('Esta copia restaurada no incluye la demostración de vencimiento.');
            $q = DB::fila('SELECT id, vencimiento_demo FROM qr_diarios WHERE fecha = ? ORDER BY id DESC LIMIT 1 FOR UPDATE', [Reloj::ahora()->format('Y-m-d')]);
            if (!$q || (int) $q['id'] !== $id) throw new DomainException('El QR cambió o ya no es de hoy. Recarga y revisa el QR vigente.');
            if ($accion === 'vencer') {
                // Los clics repetidos no alargan la cuenta ni generan más registros.
                if ($q['vencimiento_demo'] !== null) return self::estado();
                $hasta = Reloj::sql(Reloj::ahora()->modify('+5 seconds'));
                DB::consulta('UPDATE qr_diarios SET vencimiento_demo = ? WHERE id = ?', [$hasta, $id]);
                Bitacora::registrar('qr.simular_vencimiento', 'qr_diarios', $id, ['hasta' => $hasta, 'modo' => 'Demostración de 5 segundos']);
            } else {
                if ($q['vencimiento_demo'] === null) return self::estado();
                DB::consulta('UPDATE qr_diarios SET vencimiento_demo = NULL WHERE id = ?', [$id]);
                Bitacora::registrar('qr.restablecer_vigencia', 'qr_diarios', $id, ['modo' => 'Fin de demostración; vigencia habitual de hoy']);
            }
            return self::estado();
        });
    }

    public static function validar(string $fecha, string $monto): string
    {
        if ($fecha !== Reloj::ahora()->format('Y-m-d')) throw new DomainException('La fecha del QR debe ser la de hoy (hora de Bolivia).');
        if (!preg_match('/^\d{1,8}(?:[.,]\d{1,2})?$/D', $monto)) throw new DomainException('Escribe un monto válido para el QR.');
        $valor = number_format((float) str_replace(',', '.', $monto), 2, '.', '');
        return $valor;
    }

    public static function imagen(string $bytes, int $maxBytes = 2097152): string
    {
        if ($bytes === '' || strlen($bytes) > $maxBytes) throw new DomainException('La imagen supera el tamaño permitido.');
        $info = @getimagesizefromstring($bytes);
        if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true) || $info[0] * $info[1] > 24000000) {
            throw new DomainException('Elige una imagen PNG, JPG o WebP válida, de hasta 24 millones de píxeles.');
        }
        return $info['mime'];
    }

    public static function subir(string $fecha, string $monto, ?array $archivo): void
    {
        if (!self::estado()['disponible']) throw new DomainException('Esta copia fue creada antes de HU-38 y no incluye la configuración QR.');
        $monto = self::validar($fecha, $monto);
        if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) throw new DomainException('Selecciona la imagen del QR del día.');
        if (($archivo['size'] ?? 0) > 2097152) throw new DomainException('La imagen supera el tamaño permitido.');
        $bytes = (string) file_get_contents($archivo['tmp_name']);
        $tipo = self::imagen($bytes);
        DB::transaccion(static function () use ($fecha, $monto, $bytes, $tipo): void {
            // Volver a comprobar tras recibir la imagen: también protege un envío a medianoche.
            self::validar($fecha, $monto);
            $id = DB::insertar('INSERT INTO qr_diarios (fecha, monto, imagen, imagen_tipo, usuario_id, creado_en) VALUES (?, ?, ?, ?, ?, ?)', [$fecha, $monto, $bytes, $tipo, Auth::id(), Reloj::sql()]);
            Bitacora::registrar('qr.subir', 'qr_diarios', $id, ['fecha' => $fecha, 'monto' => $monto]);
        });
    }
}
