<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Csrf;
use App\Core\Middleware;
use App\Core\Peticion;
use App\Core\Respuesta;

/** Todo POST lleva el token de la sesión, en el campo _csrf o en la cabecera X-CSRF-Token. */
final class VerificarCsrf implements Middleware
{
    public function manejar(Peticion $peticion, callable $siguiente): Respuesta
    {
        if ($peticion->metodo === 'POST') {
            $token = $peticion->datos['_csrf'] ?? ($peticion->cabeceras['x-csrf-token'] ?? null);
            if (!Csrf::valido($token)) {
                return $peticion->esperaJson()
                    ? Respuesta::json(['error' => 'La página venció. Recárgala e inténtalo de nuevo.', 'recargar' => true], 403)
                    : App::paginaError(419);
            }
        }
        return $siguiente($peticion);
    }
}
