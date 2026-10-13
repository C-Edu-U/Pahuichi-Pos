<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Peticion;
use App\Core\Respuesta;

final class InicioController extends Controller
{
    public function index(Peticion $peticion): Respuesta
    {
        return $this->redirigir('/tablero');
    }
}
