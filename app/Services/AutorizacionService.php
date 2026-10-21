<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Reloj;
use App\Core\Sesion;
use App\Models\Bitacora;
use App\Models\IntentoAcceso;
use App\Models\Usuario;
use DomainException;

/**
 * Autorización con el PIN del administrador (HU-06, RF-05) para acciones del cajero
 * que la necesitan: hoy el descuento manual; la anulación (HU-44) usará la misma.
 *
 * El PIN se compara con los administradores activos; el de un cajero no autoriza.
 * Tras 5 PIN errados seguidos, la autorización queda bloqueada 15 minutos para ese
 * cajero (como el login). Un PIN correcto entrega un permiso de un solo uso, guardado
 * en la sesión del cajero, que vence en 5 minutos y lleva lo que se autorizó.
 */
final class AutorizacionService
{
    public const ACCIONES = ['descuento' => 'del descuento'];
    private const VIGENCIA_MIN = 5;
    private const SESION = 'autorizaciones';

    /**
     * @param array{monto?: string, motivo?: string} $datos
     * @return array{token: string, autorizado_por: string, monto: string, motivo: string}
     */
    public static function autorizar(string $accion, string $pin, array $datos): array
    {
        if (!isset(self::ACCIONES[$accion])) {
            throw new DomainException('Esa acción no necesita autorización.');
        }
        $monto = self::monto($datos['monto'] ?? null);
        $motivo = trim((string) ($datos['motivo'] ?? ''));
        if (mb_strlen($motivo) < 3 || mb_strlen($motivo) > 120) {
            throw new DomainException('Escribe el motivo del descuento (de 3 a 120 caracteres).');
        }

        $clave = 'autoriza:' . Auth::id();
        if ($hasta = IntentoAcceso::bloqueadoHasta($clave)) {
            throw new DomainException('Demasiados PIN errados. La autorización se habilita a las ' . $hasta->format('H:i') . '.');
        }

        $admin = null;
        if (preg_match('/^\d{4,6}$/D', $pin)) {
            foreach (Usuario::administradoresConPin() as $u) {
                if (password_verify($pin, $u['pin_hash'])) {
                    $admin = $u;
                    break;
                }
            }
        }

        if ($admin === null) {
            Bitacora::registrar('autorizacion.fallo', null, null, ['accion' => $accion]);
            $intentos = IntentoAcceso::sumar($clave);
            $max = (int) Config::get('app.sesion.intentos_max');
            if ($intentos >= $max) {
                $hasta = Reloj::ahora()->modify('+' . (int) Config::get('app.sesion.bloqueo_min') . ' minutes');
                IntentoAcceso::bloquear($clave, $hasta);
                Bitacora::registrar('autorizacion.bloqueo', null, null, ['accion' => $accion, 'intentos' => $intentos, 'hasta' => $hasta->format('H:i')]);
                throw new DomainException('Demasiados PIN errados. La autorización se habilita a las ' . $hasta->format('H:i') . '.');
            }
            $quedan = $max - $intentos;
            throw new DomainException('PIN del administrador incorrecto. ' . ($quedan === 1 ? 'Queda 1 intento.' : 'Quedan ' . $quedan . ' intentos.'));
        }

        IntentoAcceso::limpiar($clave);
        $token = bin2hex(random_bytes(16));
        $permisos = self::vigentes();
        $permisos[$token] = [
            'accion' => $accion,
            'monto' => $monto,
            'motivo' => $motivo,
            'admin_id' => (int) $admin['id'],
            'admin' => $admin['nombre'],
            'vence' => Reloj::ahora()->modify('+' . self::VIGENCIA_MIN . ' minutes')->getTimestamp(),
        ];
        Sesion::set(self::SESION, $permisos);

        return ['token' => $token, 'autorizado_por' => $admin['nombre'], 'monto' => $monto, 'motivo' => $motivo];
    }

    /** Lee un permiso sin gastarlo: el cobro lo gasta recién al guardarse la venta. */
    public static function revisar(string $token, string $accion): array
    {
        $p = self::vigentes()[$token] ?? null;
        if ($p === null || $p['accion'] !== $accion) {
            throw new AutorizacionInvalida('La autorización ' . (self::ACCIONES[$accion] ?? 'de la acción') . ' venció o ya se usó. Pide el PIN del administrador otra vez.');
        }
        return $p;
    }

    /** Gasta el permiso: no se puede usar dos veces. */
    public static function consumir(string $token): void
    {
        $permisos = self::vigentes();
        unset($permisos[$token]);
        Sesion::set(self::SESION, $permisos);
    }

    /** Permisos de la sesión que no vencieron. */
    private static function vigentes(): array
    {
        $ahora = Reloj::ahora()->getTimestamp();
        return array_filter((array) Sesion::get(self::SESION, []), static fn (array $p): bool => $p['vence'] > $ahora);
    }

    private static function monto(mixed $dato): string
    {
        if (!is_string($dato) || !preg_match('/^\d{1,8}(?:[.,]\d{1,2})?$/D', $dato)) {
            throw new DomainException('Escribe el monto del descuento, con hasta dos decimales.');
        }
        $p = explode('.', str_replace(',', '.', $dato));
        $centavos = (int) $p[0] * 100 + (int) str_pad($p[1] ?? '', 2, '0');
        if ($centavos < 1) {
            throw new DomainException('El descuento debe ser mayor que cero.');
        }
        return VentaService::decimal($centavos);
    }
}
