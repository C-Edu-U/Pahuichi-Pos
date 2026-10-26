<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\ErrorHttp;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Sesion;
use App\Models\Bitacora;
use App\Models\Arqueo;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Negocio;
use App\Models\Turno;
use App\Services\ArqueoService;
use App\Services\CajaService;
use App\Services\ConteoService;
use App\Services\MovimientoCajaService;
use DomainException;

/**
 * Caja (HU-20 a HU-27): apertura, ingresos y egresos, cierre ciego, arqueo, reporte y
 * reapertura. HU-20: el cajero opera y ve solo su caja; el administrador, todas. Un
 * intento sobre la caja de otro (por la dirección directa) da 403 y queda en la bitácora.
 */
final class CajaController extends Controller
{
    /** La caja abierta si el usuario puede operarla; null si no hay. 403 si es de otro cajero. */
    private function abiertaPropia(): ?array
    {
        $abierta = CajaService::abierta();
        if ($abierta !== null) {
            $this->exigirPropia($abierta);
        }
        return $abierta;
    }

    private function exigirPropia(array $caja): void
    {
        if (!CajaService::esPropia($caja, Auth::usuario())) {
            Bitacora::registrar('acceso.denegado', 'cajas', (int) $caja['id'], ['ruta' => 'caja de ' . $caja['cajero']]);
            throw new ErrorHttp(403);
        }
    }

    /** El cajero ve solo sus cajas; el administrador, todas. */
    private function cajeroFiltro(): ?int
    {
        $usuario = Auth::usuario();
        return $usuario['rol'] === 'administrador' ? null : (int) $usuario['id'];
    }

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
        $abierta = $this->abiertaPropia();
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
        $abierta = $this->abiertaPropia();
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
        $abierta = $this->abiertaPropia();
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
        $abierta = $this->abiertaPropia();
        if ($abierta !== null) {
            $conteo = Arqueo::porCaja((int) $abierta['id']);
            if ($conteo === null) {
                return $this->redirigirConAviso('/caja/cierre', 'Cuenta el efectivo antes de arquear.');
            }
            $esperado = ArqueoService::esperado($abierta);
            $diferencia = ArqueoService::diferencia((float) $conteo['total_contado'], $esperado);

            return $this->pantallaArqueo($abierta, $conteo, $esperado, $diferencia, false, [], '');
        }

        // Sin caja abierta: se muestra el arqueo de la última caja cerrada (del cajero, si es cajero).
        $caja = Caja::ultimaCerrada($this->cajeroFiltro());
        if ($caja === null) {
            return $this->redirigirConAviso('/caja', 'No hay ninguna caja cerrada todavía.');
        }
        $conteo = Arqueo::porCaja((int) $caja['id']);
        if ($conteo === null) {
            return $this->redirigirConAviso('/caja', 'No hay ninguna caja cerrada todavía.');
        }

        return $this->pantallaArqueo(
            $caja,
            $conteo,
            (float) ($conteo['efectivo_esperado'] ?? 0),
            (float) ($conteo['diferencia'] ?? 0),
            true,
            [],
            (string) ($conteo['observacion'] ?? ''),
        );
    }

    /** Confirma el cierre: guarda la diferencia y el resumen, y cierra la caja (HU-24). */
    public function confirmarArqueo(Peticion $peticion): Respuesta
    {
        $abierta = $this->abiertaPropia();
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

        // HU-26: una diferencia igual o mayor al umbral obliga a explicar.
        if ($observacion === '' && ArqueoService::exigeObservacion($diferencia)) {
            $faltante = $diferencia < 0;
            $errores['observacion'] = 'La diferencia es de Bs ' . number_format(abs($diferencia), 2, ',', '.')
                . '. Escribe una observación para explicar el ' . ($faltante ? 'faltante' : 'sobrante') . '.';
            return $this->pantallaArqueo($abierta, $conteo, $esperado, $diferencia, false, $errores, $observacion, 422);
        }

        try {
            (new ArqueoService())->confirmarCierre((int) $abierta['id'], $esperado, $diferencia, $resumen, $observacion);
        } catch (DomainException $e) {
            return $this->redirigirConAviso('/caja/cierre', $e->getMessage());
        }

        return $this->redirigirConAviso('/caja/arqueo', 'Caja cerrada y arqueada.');
    }

    /** Pantalla del arqueo: comparación, resumen y confirmación del cierre. */
    private function pantallaArqueo(array $caja, array $conteo, float $esperado, float $diferencia, bool $cerrada, array $errores = [], string $observacion = '', int $estado = 200): Respuesta
    {
        return $this->vista('caja/arqueo', [
            'titulo' => 'Arqueo de caja',
            'modulo' => 'caja',
            'h1' => 'Arqueo de caja',
            'css' => ['modulos.css'],
            'js' => [],
            'caja' => $caja,
            'conteo' => $conteo,
            'esperado' => $esperado,
            'diferencia' => $diferencia,
            'resumen' => $conteo['resumen'] ?: ArqueoService::resumen((int) $caja['id']),
            'movimientos' => MovimientoCaja::porCaja((int) $caja['id']),
            'cerrada' => $cerrada,
            'observacion' => $observacion,
            'umbral' => ArqueoService::umbralObservacion(),
            'errores' => $errores,
        ], $estado);
    }

    /** Reporte de cierre: se imprime y se guarda en PDF (HU-25). */
    public function reporte(Peticion $peticion): Respuesta
    {
        $id = (int) $peticion->parametro('id');
        $caja = Caja::buscar($id);
        if ($caja === null) {
            throw ErrorHttp::noEncontrado();
        }
        $this->exigirPropia($caja);
        $conteo = Arqueo::porCaja($id);
        if ($conteo === null) {
            return $this->redirigirConAviso('/caja', 'Esa caja todavía no tiene arqueo.');
        }

        $esperado = $conteo['efectivo_esperado'] !== null
            ? (float) $conteo['efectivo_esperado']
            : ArqueoService::esperado($caja);
        $diferencia = $conteo['diferencia'] !== null
            ? (float) $conteo['diferencia']
            : ArqueoService::diferencia((float) $conteo['total_contado'], $esperado);
        $resumen = $conteo['resumen'] ?: ArqueoService::resumen($id);

        return $this->vista('caja/reporte', [
            'titulo' => 'Reporte de cierre',
            'css' => ['reporte-cierre.css'],
            'js' => ['reporte.js'],
            'caja' => $caja,
            'conteo' => $conteo,
            'esperado' => $esperado,
            'diferencia' => $diferencia,
            'resumen' => $resumen,
            'movimientos' => MovimientoCaja::porCaja($id),
            'negocio' => Negocio::obtener(),
        ], 200, 'layouts/simple');
    }

    /** Reabre una caja cerrada para repetir el turno (solo administrador). */
    public function reabrir(Peticion $peticion): Respuesta
    {
        try {
            (new CajaService())->reabrir((int) $peticion->parametro('id'));
        } catch (DomainException $e) {
            Sesion::flash('bloqueo', $e->getMessage());
            return $this->redirigir('/caja');
        }
        return $this->redirigirConAviso('/caja', 'Caja reabierta: el turno vuelve a estar disponible.');
    }

    public function limpiarHistorial(): Respuesta
    {
        $usuario = Auth::usuario();
        if ($usuario['rol'] !== 'administrador') {
            return $this->redirigirConAviso('/caja', 'Solo el administrador puede limpiar el historial.');
        }
        $archivadas = (new CajaService())->limpiarHistorial();
        Bitacora::registrar('caja.limpiar_historial', 'cajas', null, ['archivadas' => $archivadas]);
        return $this->redirigirConAviso('/caja', "Historial limpiado: {$archivadas} caja(s) cerrada(s) archivada(s). Sus datos se conservan.");
    }

    private function pantalla(array $datos = [], int $estado = 200): Respuesta
    {
        $turnos = Turno::activos();
        $sugerido = CajaService::turnoSugerido($turnos);
        $abierta = CajaService::abierta();
        $propia = $abierta !== null && CajaService::esPropia($abierta, Auth::usuario());
        return $this->vista('caja/index', $datos + [
            'titulo' => 'Caja',
            'modulo' => 'caja',
            'h1' => 'Caja',
            'css' => ['modulos.css'],
            'js' => ['caja.js'],
            'abierta' => $abierta,
            'propia' => $propia,
            'turnos' => $turnos,
            'cajeros' => CajaService::cajeros(),
            'usuario' => Auth::usuario(),
            'historial' => Caja::ultimas(10, $this->cajeroFiltro()),
            'movimientos' => $propia ? MovimientoCaja::porCaja((int) $abierta['id']) : [],
            'totales' => $propia ? MovimientoCaja::totales((int) $abierta['id']) : ['ingresos' => 0.0, 'egresos' => 0.0, 'neto' => 0.0],
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
