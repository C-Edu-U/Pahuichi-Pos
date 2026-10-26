<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Middleware;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Vista;
use App\Services\CajaService;

/**
 * «caja»: sin caja abierta no se vende (HU-21, RF-19). En lugar de la pantalla
 * pedida muestra directamente el botón «Abrir caja». Las rutas de venta del
 * Sprint 2 lo usan igual; a un pedido JSON le responde 409.
 * HU-20: si la caja abierta es de otro cajero, tampoco se vende en ella: se muestra de
 * quién es. El administrador vende en cualquier caja.
 */
final class CajaAbierta implements Middleware
{
    public function manejar(Peticion $peticion, callable $siguiente): Respuesta
    {
        $abierta = CajaService::abierta();
        if ($abierta !== null && CajaService::esPropia($abierta, Auth::usuario())) {
            return $siguiente($peticion);
        }
        if ($peticion->esperaJson()) {
            return Respuesta::json(['error' => $abierta === null
                ? 'No hay una caja abierta. Abre la caja del turno para vender.'
                : 'La caja abierta es de ' . $abierta['cajero'] . ': solo esa persona vende en ella.'], 409);
        }
        return Respuesta::html(Vista::render('pos/sin-caja', [
            'titulo' => 'Punto de venta',
            'modulo' => 'pos',
            'h1' => 'Punto de venta',
            'css' => ['pos.css'],
            'ajena' => $abierta,
        ]));
    }
}
