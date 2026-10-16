<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Middleware;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Sesion;
use App\Models\Bitacora;

/**
 * Exige sesión iniciada con un usuario activo. Cierra la sesión tras 15 minutos
 * sin actividad, salvo al cajero (RNF-18), y obliga a cambiar una contraseña temporal.
 */
final class Autenticado implements Middleware
{
    private const RUTAS_CAMBIO_CLAVE = ['/cambiar-clave', '/salir'];

    public function manejar(Peticion $peticion, callable $siguiente): Respuesta
    {
        $usuario = Auth::usuario();
        if ($usuario === null || !(int) $usuario['activo'] || $usuario['rol'] === 'cocina') {
            Auth::cerrar();
            return $this->aLogin($peticion, '');
        }

        if (Auth::vencida()) {
            Bitacora::registrar('sesion.inactividad', 'usuarios', (int) $usuario['id']);
            Auth::cerrar();
            return $this->aLogin($peticion, 'inactividad');
        }
        Auth::tocar();

        if ((int) $usuario['debe_cambiar_clave'] && Sesion::get('modo') === 'clave'
            && !in_array($peticion->ruta, self::RUTAS_CAMBIO_CLAVE, true)) {
            return Respuesta::redirigir('/cambiar-clave');
        }

        return $siguiente($peticion);
    }

    private function aLogin(Peticion $peticion, string $estado): Respuesta
    {
        if ($peticion->esperaJson()) {
            return Respuesta::json(['error' => 'Tu sesión terminó. Ingresa de nuevo.'], 401);
        }
        return Respuesta::redirigir('/login' . ($estado !== '' ? '?estado=' . $estado : ''));
    }
}
