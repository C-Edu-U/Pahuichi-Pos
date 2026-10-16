<?php

declare(strict_types=1);

namespace App\Core;

/** Filtro que corre antes del controlador: puede responder él mismo o dejar pasar. */
interface Middleware
{
    /** @param callable(Peticion): Respuesta $siguiente */
    public function manejar(Peticion $peticion, callable $siguiente): Respuesta;
}
