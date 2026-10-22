<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Services\AutorizacionService;
use DomainException;

/**
 * HU-06: el administrador autoriza con su PIN una acción del cajero. El descuento recibe un
 * permiso de un solo uso; la anulación solo valida el PIN por ahora: anular la venta es HU-44.
 */
final class AutorizacionController extends Controller
{
    public function crear(Peticion $peticion): Respuesta
    {
        try {
            if ($peticion->entrada('accion') === 'anulacion') {
                $admin = AutorizacionService::verificarPin('anulacion', (string) $peticion->entrada('pin'));
                return Respuesta::json(['autorizado_por' => $admin['nombre'], 'pendiente' => 'HU-44',
                    'aviso' => 'PIN correcto. La anulación de ventas llega con HU-44: esta venta no se modificó.']);
            }
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
