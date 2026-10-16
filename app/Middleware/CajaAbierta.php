<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Vista;
use App\Services\CajaService;

/**
 * «caja»: sin caja abierta no se vende (HU-21, RF-19). En lugar de la pantalla
 * pedida muestra directamente el botón «Abrir caja». Las rutas de venta del
 * Sprint 2 lo usan igual; a un pedido JSON le responde 409.
 */
final class CajaAbierta implements Middleware
{
    public function manejar(Peticion $peticion, callable $siguiente): Respuesta
    {
        if (CajaService::abierta() !== null) {
            return $siguiente($peticion);
        }
        if ($peticion->esperaJson()) {
            return Respuesta::json(['error' => 'No hay una caja abierta. Abre la caja del turno para vender.'], 409);
        }
        return Respuesta::html(Vista::render('pos/sin-caja', [
            'titulo' => 'Punto de venta',
            'modulo' => 'pos',
            'h1' => 'Punto de venta',
            'css' => ['pos.css'],
        ]));
    }
}
