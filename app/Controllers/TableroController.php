<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Peticion;
use App\Core\Reloj;
use App\Core\Respuesta;
use App\Services\TableroService;

/**
 * Tablero del día (HU-64). El administrador ve las cifras del día; el cajero, solo su
 * turno y cuántas ventas lleva, sin montos (el cierre de caja es ciego).
 */
final class TableroController extends Controller
{
    public function index(Peticion $peticion): Respuesta
    {
        $usuario = Auth::usuario();
        $esCajero = $usuario['rol'] === 'cajero';
        return $this->vista('tablero/index', [
            'titulo' => 'Tablero',
            'modulo' => 'tablero',
            'h1' => $esCajero ? 'Tu turno' : 'Tablero del día',
            'css' => ['modulos.css'],
            'js' => ['tablero.js'],
            'esCajero' => $esCajero,
            't' => $esCajero ? TableroService::delCajero((int) $usuario['id']) : TableroService::delDia(Reloj::ahora()),
            'ahora' => Reloj::ahora(),
        ]);
    }
}
