<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Models\Bitacora;
use App\Models\Usuario;
use DomainException;

/**
 * Gestión de usuarios (HU-03). Nada se borra: un usuario se desactiva.
 * Las contraseñas y PIN generados se devuelven una sola vez para entregarlos en persona.
 * Los errores de regla llegan como DomainException con un mensaje para el usuario.
 */
final class UsuarioService
{
    private const PALABRAS = ['cunape', 'majadito', 'somo', 'locro', 'keperi', 'masaco', 'sonso', 'tari', 'pacumutu', 'chicha'];

    /** @return array{0: int, 1: array<string, string>} id y credenciales temporales */
    public function crear(string $nombre, string $usuario, string $rol): array
    {
        $credenciales = $this->credencialesPara($rol);
        $id = DB::insertar(
            'INSERT INTO usuarios (nombre, usuario, password_hash, pin_hash, rol, debe_cambiar_clave) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $nombre,
                $usuario,
                isset($credenciales['Contraseña']) ? password_hash($credenciales['Contraseña'], PASSWORD_BCRYPT) : null,
                isset($credenciales['PIN']) ? password_hash($credenciales['PIN'], PASSWORD_BCRYPT) : null,
                $rol,
                isset($credenciales['Contraseña']) ? 1 : 0,
            ],
        );
        Bitacora::registrar('usuario.alta', 'usuarios', $id, ['usuario' => $usuario, 'rol' => $rol]);
        return [$id, $credenciales];
    }

    /** Valida los datos del alta. @return array<string, string> errores por campo */
    public function validarAlta(string $nombre, string $usuario, string $rol): array
    {
        $errores = [];
        if ($nombre === '') {
            $errores['u-nombre'] = 'Escribe el nombre completo.';
        } elseif (mb_strlen($nombre) > 80) {
            $errores['u-nombre'] = 'Usa hasta 80 caracteres.';
        }
        if ($usuario === '') {
            $errores['u-usuario'] = 'Escribe el usuario.';
        } elseif (!preg_match('/^[a-z0-9]{3,20}$/', $usuario)) {
            $errores['u-usuario'] = 'Usa de 3 a 20 minúsculas o números, sin espacios.';
        } elseif (Usuario::buscarPorUsuario($usuario) !== null) {
            $errores['u-usuario'] = 'Ese usuario ya existe.';
        }
        if (!isset(Usuario::ROLES[$rol])) {
            $errores['u-rol'] = 'Elige un rol de la lista.';
        }
        return $errores;
    }

    /** @return array<string, string> credenciales nuevas que hicieron falta para el rol */
    public function cambiarRol(int $id, string $rol): array
    {
        $u = $this->editable($id, 'cambiar tu propio rol');
        if (!isset(Usuario::ROLES[$rol])) {
            throw new DomainException('Elige un rol de la lista.');
        }
        if ($u['rol'] === $rol) {
            return [];
        }
        if ($u['rol'] === 'administrador') {
            $this->exigirOtroAdministrador($id);
        }

        // El rol nuevo puede necesitar una contraseña o un PIN que el usuario aún no tiene
        $credenciales = [];
        if ($rol !== 'cocina' && $u['password_hash'] === null) {
            $credenciales['Contraseña'] = $this->claveTemporal();
        }
        if (in_array($rol, ['cajero', 'administrador'], true) && $u['pin_hash'] === null) {
            $credenciales['PIN'] = $this->pinNuevo();
        }

        DB::transaccion(function () use ($id, $rol, $credenciales): void {
            DB::consulta('UPDATE usuarios SET rol = ? WHERE id = ?', [$rol, $id]);
            $this->guardarCredenciales($id, $credenciales);
        });
        Bitacora::registrar('usuario.rol', 'usuarios', $id, ['antes' => $u['rol'], 'despues' => $rol]);
        return $credenciales;
    }

    public function cambiarEstado(int $id, bool $activo): void
    {
        $u = $this->editable($id, 'desactivar tu propio usuario');
        if (!$activo && $u['rol'] === 'administrador') {
            $this->exigirOtroAdministrador($id);
        }
        DB::consulta('UPDATE usuarios SET activo = ? WHERE id = ?', [$activo ? 1 : 0, $id]);
        Bitacora::registrar($activo ? 'usuario.activar' : 'usuario.desactivar', 'usuarios', $id);
    }

    public function desbloquear(int $id): void
    {
        $this->buscar($id);
        DB::consulta('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?', [$id]);
        Bitacora::registrar('usuario.desbloquear', 'usuarios', $id);
    }

    /** @return array<string, string> */
    public function resetearClave(int $id): array
    {
        $u = $this->buscar($id);
        if ($u['rol'] === 'cocina') {
            throw new DomainException('Cocina no entra al panel: no usa contraseña.');
        }
        $credenciales = ['Contraseña' => $this->claveTemporal()];
        $this->guardarCredenciales($id, $credenciales);
        DB::consulta('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?', [$id]);
        Bitacora::registrar('usuario.reseteo_clave', 'usuarios', $id);
        return $credenciales;
    }

    /** @return array<string, string> */
    public function resetearPin(int $id): array
    {
        $u = $this->buscar($id);
        if (!in_array($u['rol'], ['cajero', 'administrador'], true)) {
            throw new DomainException('Solo el cajero y el administrador usan PIN.');
        }
        $credenciales = ['PIN' => $this->pinNuevo($id)];
        $this->guardarCredenciales($id, $credenciales);
        Bitacora::registrar('usuario.reseteo_pin', 'usuarios', $id);
        return $credenciales;
    }

    /**
     * HU-06: el administrador elige su propio PIN de autorización. Confirma con su
     * contraseña. El PIN no puede ser obvio ni repetir el de otro usuario: si fuera
     * igual al de un cajero, ese cajero podría autorizarse a sí mismo.
     * @return array<string, string> errores por campo (vacío si se guardó)
     */
    public function cambiarMiPin(string $clave, string $pin, string $confirmacion): array
    {
        $u = $this->buscar((int) Auth::id());
        if ($u['rol'] !== 'administrador') {
            throw new DomainException('Solo el administrador tiene PIN de autorización.');
        }
        $errores = [];
        if ($u['password_hash'] === null || !password_verify($clave, $u['password_hash'])) {
            $errores['mp-clave'] = 'Tu contraseña actual no coincide.';
        }
        if (!preg_match('/^\d{4,6}$/D', $pin)) {
            $errores['mp-pin'] = 'El PIN tiene de 4 a 6 dígitos.';
        } elseif ($this->pinObvio($pin)) {
            $errores['mp-pin'] = 'Elige un PIN menos obvio: sin dígitos repetidos ni seguidos (1111, 1234).';
        } elseif ($this->pinUsado($pin, (int) $u['id'])) {
            $errores['mp-pin'] = 'Ese PIN ya lo usa otra persona. Elige otro.';
        } elseif ($pin !== $confirmacion) {
            $errores['mp-confirmacion'] = 'Los dos PIN no coinciden.';
        }
        if ($errores) {
            return $errores;
        }
        DB::consulta('UPDATE usuarios SET pin_hash = ? WHERE id = ?', [password_hash($pin, PASSWORD_BCRYPT), (int) $u['id']]);
        Bitacora::registrar('usuario.cambio_pin', 'usuarios', (int) $u['id']);
        return [];
    }

    private function pinUsado(string $pin, int $excepto): bool
    {
        foreach (DB::filas('SELECT pin_hash FROM usuarios WHERE pin_hash IS NOT NULL AND id <> ?', [$excepto]) as $o) {
            if (password_verify($pin, $o['pin_hash'])) {
                return true;
            }
        }
        return false;
    }

    /** Credenciales iniciales según el rol: cocina no usa ninguna. */
    private function credencialesPara(string $rol): array
    {
        $c = [];
        if ($rol !== 'cocina') {
            $c['Contraseña'] = $this->claveTemporal();
        }
        if ($rol === 'cajero') {
            $c['PIN'] = $this->pinNuevo();
        }
        return $c;
    }

    private function guardarCredenciales(int $id, array $credenciales): void
    {
        if (isset($credenciales['Contraseña'])) {
            Usuario::cambiarClave($id, $credenciales['Contraseña'], true);
        }
        if (isset($credenciales['PIN'])) {
            DB::consulta('UPDATE usuarios SET pin_hash = ? WHERE id = ?', [password_hash($credenciales['PIN'], PASSWORD_BCRYPT), $id]);
        }
    }

    /** Contraseña temporal fácil de dictar: «somo-4821». Se cambia al entrar. */
    private function claveTemporal(): string
    {
        return self::PALABRAS[random_int(0, count(self::PALABRAS) - 1)] . '-' . random_int(1000, 9999);
    }

    /**
     * PIN de 4 dígitos que no use ningún otro usuario: al entrar con PIN no se
     * escribe el usuario, así que dos PIN iguales no se podrían distinguir.
     */
    private function pinNuevo(?int $excepto = null): string
    {
        $otros = DB::filas('SELECT pin_hash FROM usuarios WHERE pin_hash IS NOT NULL AND id <> ?', [$excepto ?? 0]);
        for ($i = 0; $i < 50; $i++) {
            $pin = (string) random_int(1000, 9999);
            if ($this->pinObvio($pin)) {
                continue;
            }
            foreach ($otros as $o) {
                if (password_verify($pin, $o['pin_hash'])) {
                    continue 2;
                }
            }
            return $pin;
        }
        throw new DomainException('No se pudo generar un PIN distinto. Inténtalo de nuevo.');
    }

    private function pinObvio(string $pin): bool
    {
        return count(array_unique(str_split($pin))) === 1
            || str_contains('0123456789', $pin)
            || str_contains('9876543210', $pin);
    }

    private function buscar(int $id): array
    {
        return Usuario::buscar($id) ?? throw new DomainException('Ese usuario no existe.');
    }

    private function editable(int $id, string $accion): array
    {
        $u = $this->buscar($id);
        if ($id === Auth::id()) {
            throw new DomainException("No puedes {$accion}. Pídeselo a otro administrador.");
        }
        return $u;
    }

    private function exigirOtroAdministrador(int $id): void
    {
        $otros = (int) DB::valor("SELECT COUNT(*) FROM usuarios WHERE rol = 'administrador' AND activo = 1 AND id <> ?", [$id]);
        if ($otros === 0) {
            throw new DomainException('Debe quedar al menos un administrador activo.');
        }
    }
}
