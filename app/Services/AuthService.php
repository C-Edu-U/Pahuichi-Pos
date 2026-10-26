<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Reloj;
use App\Models\Bitacora;
use App\Models\IntentoAcceso;
use App\Models\Usuario;
use DateTimeImmutable;

/**
 * Inicio de sesión (HU-02): contraseña para todos y PIN de 4 a 6 dígitos para el
 * cajero. Tras 5 fallos seguidos, 15 minutos de bloqueo con registro en la bitácora.
 *
 * Devuelve un arreglo: ['ok' => true, 'usuario' => [...]] o
 * ['ok' => false, 'motivo' => credenciales|bloqueado|cocina|inactivo, 'quedan' => n, 'hasta' => DateTimeImmutable]
 */
final class AuthService
{
    public function entrarConClave(string $usuario, string $clave, string $ip): array
    {
        $u = Usuario::buscarPorUsuario($usuario);

        // Usuario inexistente: se cuenta por equipo para que la respuesta sea igual a una clave errada
        if ($u === null) {
            $claveIntento = 'clave:' . $ip;
            if ($hasta = IntentoAcceso::bloqueadoHasta($claveIntento)) {
                return $this->bloqueado($hasta);
            }
            return $this->fallo(IntentoAcceso::sumar($claveIntento), static fn (DateTimeImmutable $h) => IntentoAcceso::bloquear($claveIntento, $h), null, 'clave');
        }

        if ($u['rol'] === 'cocina') {
            return ['ok' => false, 'motivo' => 'cocina'];
        }
        if ($hasta = Usuario::bloqueadoHasta($u)) {
            return $this->bloqueado($hasta);
        }
        if ($u['password_hash'] === null || !password_verify($clave, $u['password_hash'])) {
            $id = (int) $u['id'];
            Bitacora::registrar('sesion.fallo', 'usuarios', $id, ['modo' => 'clave'], $id);
            return $this->fallo(Usuario::sumarFallo($id), static fn (DateTimeImmutable $h) => Usuario::bloquear($id, $h), $id, 'clave');
        }
        if (!(int) $u['activo']) {
            return ['ok' => false, 'motivo' => 'inactivo'];
        }

        return $this->exito($u, 'clave');
    }

    public function entrarConPin(string $pin, string $ip): array
    {
        $claveIntento = 'pin:' . $ip;
        if ($hasta = IntentoAcceso::bloqueadoHasta($claveIntento)) {
            return $this->bloqueado($hasta);
        }

        if (preg_match('/^\d{4,6}$/', $pin)) {
            foreach (Usuario::cajerosConPin() as $u) {
                if (password_verify($pin, $u['pin_hash'])) {
                    if ($hasta = Usuario::bloqueadoHasta($u)) {
                        return $this->bloqueado($hasta);
                    }
                    IntentoAcceso::limpiar($claveIntento);
                    return $this->exito($u, 'pin');
                }
            }
        }

        Bitacora::registrar('sesion.fallo', null, null, ['modo' => 'pin']);
        return $this->fallo(IntentoAcceso::sumar($claveIntento), static fn (DateTimeImmutable $h) => IntentoAcceso::bloquear($claveIntento, $h), null, 'pin');
    }

    private function exito(array $u, string $modo): array
    {
        Usuario::registrarIngreso((int) $u['id']);
        Bitacora::registrar('sesion.inicio', 'usuarios', (int) $u['id'], ['modo' => $modo], (int) $u['id']);
        return ['ok' => true, 'usuario' => $u];
    }

    private function fallo(int $intentos, callable $bloquear, ?int $usuarioId, string $modo): array
    {
        $max = (int) Config::get('app.sesion.intentos_max');
        if ($intentos >= $max) {
            $hasta = Reloj::ahora()->modify('+' . (int) Config::get('app.sesion.bloqueo_min') . ' minutes');
            $bloquear($hasta);
            Bitacora::registrar('sesion.bloqueo', $usuarioId ? 'usuarios' : null, $usuarioId, [
                'modo' => $modo,
                'intentos' => $intentos,
                'hasta' => $hasta->format('H:i'),
            ], $usuarioId);
            return $this->bloqueado($hasta);
        }
        return ['ok' => false, 'motivo' => 'credenciales', 'quedan' => $max - $intentos];
    }

    private function bloqueado(DateTimeImmutable $hasta): array
    {
        return ['ok' => false, 'motivo' => 'bloqueado', 'hasta' => $hasta];
    }
}
