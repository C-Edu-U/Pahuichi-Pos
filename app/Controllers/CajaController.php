<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Sesion;
use App\Models\Bitacora;
use App\Models\Caja;
use App\Models\Turno;
use App\Services\CajaService;
use DomainException;

/** Caja (HU-20): apertura con turno, cajero y fondo inicial. */
final class CajaController extends Controller
{
    public function index(Peticion $peticion): Respuesta
    {
        return $this->pantalla(['bloqueo' => Sesion::tomarFlash('bloqueo')]);
    }

    public function abrir(Peticion $peticion): Respuesta
    {
        $usuario = Auth::usuario();
        $turnoId = (int) $peticion->entrada('turno_id');
        // El cajero abre su propia caja; el administrador elige el cajero
        $cajeroId = $usuario['rol'] === 'cajero' ? (int) $usuario['id'] : (int) $peticion->entrada('cajero_id');
        $fondoTexto = (string) $peticion->entrada('fondo');
        $fondo = leer_monto($fondoTexto);

        if ($fondo === null) {
            return $this->pantalla([
                'errores' => ['fondo' => 'Escribe el fondo inicial, por ejemplo 300,00.'],
                'viejo' => ['turno_id' => $turnoId, 'cajero_id' => $cajeroId, 'fondo' => $fondoTexto],
            ], 422);
        }

        try {
            (new CajaService())->abrir($turnoId, $cajeroId, $fondo, $usuario);
        } catch (DomainException $e) {
            if (CajaService::abierta() !== null) {
                // Ya hay una caja abierta: se vuelve al resumen con el motivo
                Sesion::flash('bloqueo', $e->getMessage());
                return $this->redirigir('/caja');
            }
            return $this->pantalla([
                'bloqueo' => $e->getMessage(),
                'viejo' => ['turno_id' => $turnoId, 'cajero_id' => $cajeroId, 'fondo' => $fondoTexto],
            ], 422);
        }

        // El cajero sigue directo al punto de venta; el administrador ve la caja abierta
        return $this->redirigirConAviso($usuario['rol'] === 'cajero' ? '/pos' : '/caja', 'Caja abierta.');
    }

    public function cerrar(Peticion $peticion): Respuesta
    {
        $usuario = Auth::usuario();
        try {
            (new CajaService())->cerrar((int) $usuario['id'], $usuario['rol']);
        } catch (DomainException $e) {
            Sesion::flash('bloqueo', $e->getMessage());
            return $this->redirigir('/caja');
        }
        return $this->redirigirConAviso('/caja', 'Caja cerrada.');
    }

    public function limpiarHistorial(): Respuesta
    {
        $usuario = Auth::usuario();
        if ($usuario['rol'] !== 'administrador') {
            return $this->redirigirConAviso('/caja', 'Solo el administrador puede limpiar el historial.');
        }
        $borradas = (new CajaService())->limpiarHistorial();
        Bitacora::registrar('caja.limpiar_historial', 'cajas', null, ['borradas' => $borradas]);
        return $this->redirigirConAviso('/caja', "Historial limpiado: {$borradas} caja(s) cerrada(s) borrada(s).");
    }

    private function pantalla(array $datos = [], int $estado = 200): Respuesta
    {
        $turnos = Turno::activos();
        $sugerido = CajaService::turnoSugerido($turnos);
        return $this->vista('caja/index', $datos + [
            'titulo' => 'Caja',
            'modulo' => 'caja',
            'h1' => 'Caja',
            'css' => ['modulos.css'],
            'js' => ['caja.js'],
            'abierta' => CajaService::abierta(),
            'turnos' => $turnos,
            'cajeros' => CajaService::cajeros(),
            'usuario' => Auth::usuario(),
            'historial' => Caja::ultimas(),
            'errores' => [],
            'bloqueo' => null,
            'viejo' => ['turno_id' => (int) ($sugerido['id'] ?? 0), 'cajero_id' => 0, 'fondo' => '300,00'],
        ], $estado);
    }
}
