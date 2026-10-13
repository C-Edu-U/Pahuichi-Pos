<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Models\Bitacora;
use App\Models\Usuario;
use App\Services\AuthService;

/** Inicio y cierre de sesión (HU-02). */
final class AuthController extends Controller
{
    public function mostrar(Peticion $peticion): Respuesta
    {
        return $this->login([
            'modo' => $peticion->consulta('modo') === 'clave' ? 'clave' : 'pin',
            'salida' => in_array($peticion->consulta('estado'), ['salida', 'inactividad'], true) ? $peticion->consulta('estado') : null,
        ]);
    }

    public function entrar(Peticion $peticion): Respuesta
    {
        $servicio = new AuthService();
        $modo = $peticion->entrada('modo') === 'clave' ? 'clave' : 'pin';

        if ($modo === 'clave') {
            $usuario = mb_strtolower((string) $peticion->entrada('usuario'));
            $clave = (string) ($peticion->datos['clave'] ?? '');
            $errores = [];
            if ($usuario === '') {
                $errores['usuario'] = 'Escribe tu usuario.';
            }
            if ($clave === '') {
                $errores['clave'] = 'Escribe tu contraseña.';
            }
            if ($errores) {
                return $this->login(['modo' => 'clave', 'usuario' => $usuario, 'errores' => $errores], 422);
            }
            $resultado = $servicio->entrarConClave($usuario, $clave, $peticion->ip);
        } else {
            $resultado = $servicio->entrarConPin((string) ($peticion->datos['pin'] ?? ''), $peticion->ip);
            $usuario = '';
        }

        if ($resultado['ok']) {
            Auth::iniciar($resultado['usuario'], $modo);
            return $this->redirigir(Auth::inicio());
        }

        return $this->login(['modo' => $modo, 'usuario' => $usuario, 'resultado' => $resultado], 422);
    }

    public function salir(Peticion $peticion): Respuesta
    {
        $porInactividad = $peticion->entrada('motivo') === 'inactividad';
        if (Auth::id() !== null) {
            Bitacora::registrar($porInactividad ? 'sesion.inactividad' : 'sesion.salida', 'usuarios', Auth::id());
        }
        Auth::cerrar();
        return $this->redirigir('/login?estado=' . ($porInactividad ? 'inactividad' : 'salida'));
    }

    /** «Seguir trabajando» en el aviso de inactividad: el middleware ya renovó la sesión. */
    public function seguir(Peticion $peticion): Respuesta
    {
        return Respuesta::json(['ok' => true]);
    }

    /** Contraseña temporal (alta o reseteo): se cambia antes de seguir. */
    public function formCambiarClave(Peticion $peticion): Respuesta
    {
        return $this->vista('auth/cambiar-clave', ['titulo' => 'Cambiar contraseña', 'errores' => []], 200, 'layouts/acceso');
    }

    public function cambiarClave(Peticion $peticion): Respuesta
    {
        $usuario = Auth::usuario();
        $nueva = (string) ($peticion->datos['nueva'] ?? '');
        $repetir = (string) ($peticion->datos['repetir'] ?? '');

        $errores = [];
        if (mb_strlen($nueva) < 8 || !preg_match('/\pL/u', $nueva) || !preg_match('/\d/', $nueva)) {
            $errores['nueva'] = 'Usa al menos 8 caracteres, con letras y números.';
        } elseif (mb_strtolower($nueva) === $usuario['usuario']) {
            $errores['nueva'] = 'No puede ser igual a tu usuario.';
        } elseif ($usuario['password_hash'] !== null && password_verify($nueva, $usuario['password_hash'])) {
            $errores['nueva'] = 'Elige una distinta a la temporal.';
        } elseif ($nueva !== $repetir) {
            $errores['repetir'] = 'Las dos contraseñas no coinciden.';
        }
        if ($errores) {
            return $this->vista('auth/cambiar-clave', ['titulo' => 'Cambiar contraseña', 'errores' => $errores], 422, 'layouts/acceso');
        }

        Usuario::cambiarClave((int) $usuario['id'], $nueva);
        Bitacora::registrar('usuario.cambio_clave', 'usuarios', (int) $usuario['id']);
        return $this->redirigirConAviso(Auth::inicio(), 'Contraseña actualizada.');
    }

    private function login(array $datos, int $estado = 200): Respuesta
    {
        return $this->vista('auth/login', $datos + [
            'titulo' => 'Iniciar sesión',
            'js' => ['login.js'],
            'modo' => 'pin',
            'usuario' => '',
            'errores' => [],
            'resultado' => null,
            'salida' => null,
            'demo' => Config::get('app.entorno') !== 'produccion',
        ], $estado, 'layouts/acceso');
    }
}
