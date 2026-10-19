<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Sesion;
use App\Models\Bitacora;
use App\Models\Arqueo;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Turno;
use App\Services\ArqueoService;
use App\Services\CajaService;
use App\Services\ConteoService;
use App\Services\MovimientoCajaService;
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

    /** Ingreso o egreso de efectivo durante el turno (HU-22). */
    public function movimiento(Peticion $peticion): Respuesta
    {
        $usuario = Auth::usuario();
        $abierta = CajaService::abierta();
        if ($abierta === null) {
            return $this->redirigirConAviso('/caja', 'No hay una caja abierta.');
        }

        $tipo = (string) $peticion->entrada('tipo');
        $motivo = trim((string) $peticion->entrada('motivo'));
        $montoTexto = trim((string) $peticion->entrada('monto'));
        $monto = leer_monto($montoTexto);

        $errores = [];
        if (!in_array($tipo, MovimientoCajaService::TIPOS, true)) {
            $errores['tipo'] = 'Elige si es un ingreso o un egreso.';
        }
        if ($monto === null || $monto <= 0) {
            $errores['monto'] = 'Escribe el monto, por ejemplo 50,00.';
        }
        if (mb_strlen($motivo) < 3) {
            $errores['motivo'] = 'Escribe el motivo del movimiento.';
        }

        if ($errores) {
            return $this->pantalla([
                'errores' => $errores,
                'viejo' => ['turno_id' => 0, 'cajero_id' => 0, 'fondo' => '300,00', 'tipo' => $tipo, 'monto' => $montoTexto, 'motivo' => $motivo],
            ], 422);
        }

        try {
            (new MovimientoCajaService())->registrar((int) $abierta['id'], $tipo, (float) $monto, $motivo, $usuario);
        } catch (DomainException $e) {
            return $this->pantalla([
                'errores' => ['monto' => $e->getMessage()],
                'viejo' => ['turno_id' => 0, 'cajero_id' => 0, 'fondo' => '300,00', 'tipo' => $tipo, 'monto' => $montoTexto, 'motivo' => $motivo],
            ], 422);
        }

        return $this->redirigirConAviso('/caja', $tipo === 'ingreso' ? 'Ingreso registrado.' : 'Egreso registrado.');
    }

    /** Cierre ciego: conteo por denominación (HU-23). No muestra el efectivo esperado. */
    public function cierre(Peticion $peticion): Respuesta
    {
        $abierta = CajaService::abierta();
        if ($abierta === null) {
            return $this->redirigirConAviso('/caja', 'No hay una caja abierta.');
        }

        $conteo = Arqueo::porCaja((int) $abierta['id']);

        return $this->vista('caja/conteo', [
            'titulo' => 'Cerrar caja',
            'modulo' => 'caja',
            'h1' => 'Cerrar caja',
            'css' => ['modulos.css'],
            'js' => ['conteo.js'],
            'abierta' => $abierta,
            'conteo' => $conteo,
            'denominaciones' => Arqueo::DENOMINACIONES,
            'errores' => [],
        ]);
    }

    /** Guarda el conteo por denominación (HU-23). */
    public function conteo(Peticion $peticion): Respuesta
    {
        $abierta = CajaService::abierta();
        if ($abierta === null) {
            return $this->redirigirConAviso('/caja', 'No hay una caja abierta.');
        }

        $enviados = is_array($peticion->datos['conteo'] ?? null) ? $peticion->datos['conteo'] : [];

        try {
            $total = (new ConteoService())->registrar((int) $abierta['id'], $enviados, Auth::usuario());
        } catch (DomainException $e) {
            return $this->vista('caja/conteo', [
                'titulo' => 'Cerrar caja',
                'modulo' => 'caja',
                'h1' => 'Cerrar caja',
                'css' => ['modulos.css'],
                'js' => ['conteo.js'],
                'abierta' => $abierta,
                'conteo' => null,
                'denominaciones' => Arqueo::DENOMINACIONES,
                'errores' => ['conteo' => $e->getMessage()],
                'viejo' => $enviados,
            ], 422);
        }

        return $this->redirigirConAviso('/caja/cierre', 'Conteo registrado: Bs ' . number_format($total, 2, ',', '.'));
    }

    /** Arqueo automático y resumen de cierre (HU-24). */
    public function arqueo(Peticion $peticion): Respuesta
    {
        $abierta = CajaService::abierta();
        if ($abierta !== null) {
            $conteo = Arqueo::porCaja((int) $abierta['id']);
            if ($conteo === null) {
                return $this->redirigirConAviso('/caja/cierre', 'Cuenta el efectivo antes de arquear.');
            }
            $esperado = ArqueoService::esperado($abierta);
            $diferencia = ArqueoService::diferencia((float) $conteo['total_contado'], $esperado);

            return $this->vista('caja/arqueo', [
                'titulo' => 'Arqueo de caja',
                'modulo' => 'caja',
                'h1' => 'Arqueo de caja',
                'css' => ['modulos.css'],
                'js' => [],
                'caja' => $abierta,
                'conteo' => $conteo,
                'esperado' => $esperado,
                'diferencia' => $diferencia,
                'resumen' => ArqueoService::resumen((int) $abierta['id']),
                'movimientos' => MovimientoCaja::porCaja((int) $abierta['id']),
                'cerrada' => false,
                'observacion' => '',
                'errores' => [],
            ]);
        }

        // Sin caja abierta: se muestra el arqueo de la última caja cerrada.
        $caja = Caja::ultimaCerrada();
        if ($caja === null) {
            return $this->redirigirConAviso('/caja', 'No hay ninguna caja cerrada todavía.');
        }
        $conteo = Arqueo::porCaja((int) $caja['id']);
        if ($conteo === null) {
            return $this->redirigirConAviso('/caja', 'No hay ninguna caja cerrada todavía.');
        }

        return $this->vista('caja/arqueo', [
            'titulo' => 'Arqueo de caja',
            'modulo' => 'caja',
            'h1' => 'Arqueo de caja',
            'css' => ['modulos.css'],
            'js' => [],
            'caja' => $caja,
            'conteo' => $conteo,
            'esperado' => (float) ($conteo['efectivo_esperado'] ?? 0),
            'diferencia' => (float) ($conteo['diferencia'] ?? 0),
            'resumen' => $conteo['resumen'] ?: ArqueoService::resumen((int) $caja['id']),
            'movimientos' => MovimientoCaja::porCaja((int) $caja['id']),
            'cerrada' => true,
            'observacion' => (string) ($conteo['observacion'] ?? ''),
            'errores' => [],
        ]);
    }

    /** Confirma el cierre: guarda la diferencia y el resumen, y cierra la caja (HU-24). */
    public function confirmarArqueo(Peticion $peticion): Respuesta
    {
        $abierta = CajaService::abierta();
        if ($abierta === null) {
            return $this->redirigirConAviso('/caja', 'No hay una caja abierta.');
        }
        $conteo = Arqueo::porCaja((int) $abierta['id']);
        if ($conteo === null) {
            return $this->redirigirConAviso('/caja/cierre', 'Cuenta el efectivo antes de arquear.');
        }

        $esperado = ArqueoService::esperado($abierta);
        $diferencia = ArqueoService::diferencia((float) $conteo['total_contado'], $esperado);
        $resumen = ArqueoService::resumen((int) $abierta['id']);
        $observacion = trim((string) $peticion->entrada('observacion'));

        try {
            (new ArqueoService())->confirmarCierre((int) $abierta['id'], $esperado, $diferencia, $resumen, $observacion);
        } catch (DomainException $e) {
            return $this->redirigirConAviso('/caja/cierre', $e->getMessage());
        }

        return $this->redirigirConAviso('/caja/arqueo', 'Caja cerrada y arqueada.');
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
        $abierta = CajaService::abierta();
        return $this->vista('caja/index', $datos + [
            'titulo' => 'Caja',
            'modulo' => 'caja',
            'h1' => 'Caja',
            'css' => ['modulos.css'],
            'js' => ['caja.js'],
            'abierta' => $abierta,
            'turnos' => $turnos,
            'cajeros' => CajaService::cajeros(),
            'usuario' => Auth::usuario(),
            'historial' => Caja::ultimas(),
            'movimientos' => $abierta !== null ? MovimientoCaja::porCaja((int) $abierta['id']) : [],
            'totales' => $abierta !== null ? MovimientoCaja::totales((int) $abierta['id']) : ['ingresos' => 0.0, 'egresos' => 0.0, 'neto' => 0.0],
            'errores' => [],
            'bloqueo' => null,
            'viejo' => [
                'turno_id' => (int) ($sugerido['id'] ?? 0),
                'cajero_id' => 0,
                'fondo' => '300,00',
                'tipo' => 'egreso',
                'monto' => '',
                'motivo' => '',
            ],
        ], $estado);
    }
}
