<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Middleware;
use App\Core\Peticion;
use App\Core\Respuesta;

/** Pantallas solo para quien no inició sesión (el login): si ya entró, va a su inicio. */
final class Invitado implements Middleware
{
    public function manejar(Peticion $peticion, callable $siguiente): Respuesta
    {
        $usuario = Auth::usuario();
        if ($usuario !== null && (int) $usuario['activo'] && $usuario['rol'] !== 'cocina' && !Auth::vencida()) {
            return Respuesta::redirigir(Auth::inicio());
        }
        return $siguiente($peticion);
    }
}
