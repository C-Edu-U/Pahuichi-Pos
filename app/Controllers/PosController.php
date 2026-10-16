<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Services\CajaService;

/** Punto de venta. El middleware «caja» ya garantizó que hay una caja abierta (HU-21). */
final class PosController extends Controller
{
    public function index(Peticion $peticion): Respuesta
    {
        return $this->vista('pos/index', [
            'titulo' => 'Punto de venta',
            'modulo' => 'pos',
            'h1' => 'Punto de venta',
            'css' => ['pos.css'],
            'caja' => CajaService::abierta(),
        ]);
    }
}
