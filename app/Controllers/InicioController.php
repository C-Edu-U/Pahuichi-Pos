<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Peticion;
use App\Core\Respuesta;

/** «/» lleva a la pantalla de entrada del rol, o al login si no hay sesión. */
final class InicioController extends Controller
{
    public function index(Peticion $peticion): Respuesta
    {
        return $this->redirigir(Auth::usuario() !== null && !Auth::vencida() ? Auth::inicio() : '/login');
    }
}
