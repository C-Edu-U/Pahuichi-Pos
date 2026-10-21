<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Services\AutorizacionService;
use DomainException;

/** HU-06: el administrador autoriza con su PIN una acción del cajero; responde un permiso de un solo uso. */
final class AutorizacionController extends Controller
{
    public function crear(Peticion $peticion): Respuesta
    {
        try {
            $a = AutorizacionService::autorizar(
                (string) $peticion->entrada('accion'),
                (string) $peticion->entrada('pin'),
                ['monto' => $peticion->entrada('monto'), 'motivo' => $peticion->entrada('motivo')],
            );
            return Respuesta::json($a);
        } catch (DomainException $e) {
            return Respuesta::json(['error' => $e->getMessage()], 422);
        }
    }
}
