<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\ErrorHttp;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Models\Bitacora;
use App\Models\Turno;
use App\Services\ReporteExportar;
use App\Services\ReporteService;

/** Reportes de ventas por fecha y turno (HU-63). Solo el administrador. */
final class ReporteController extends Controller
{
    public function index(Peticion $peticion): Respuesta
    {
        [$filtros, $error] = $this->filtros($peticion);

        return $this->vista('reportes/index', [
            'titulo' => 'Reportes',
            'modulo' => 'reportes',
            'h1' => 'Reportes',
            'css' => ['modulos.css'],
            'filtros' => $filtros,
            'error' => $error,
            'turnos' => Turno::todos(),
            'reporte' => $error === null ? ReporteService::generar($filtros) : null,
            'consulta' => http_build_query(['desde' => $filtros['desde'], 'hasta' => $filtros['hasta'], 'turno' => $filtros['turno'] ?? '']),
        ], $error === null ? 200 : 422);
    }

    /** Descarga el reporte en PDF o en Excel, con los filtros de la pantalla. */
    public function exportar(Peticion $peticion): Respuesta
    {
        $formato = $peticion->consulta('formato');
        if (!isset(ReporteExportar::FORMATOS[$formato])) {
            throw new ErrorHttp(422, 'Elige PDF o Excel.');
        }
        [$filtros, $error] = $this->filtros($peticion);
        if ($error !== null) {
            throw new ErrorHttp(422, $error);
        }

        $reporte = ReporteService::generar($filtros);
        $bytes = $formato === 'pdf' ? ReporteExportar::pdf($reporte) : ReporteExportar::excel($reporte);
        Bitacora::registrar('reporte.exportar', null, null, [
            'formato' => $formato === 'pdf' ? 'PDF' : 'Excel',
            'rango' => ReporteExportar::rango($filtros),
        ]);

        return new Respuesta($bytes, 200, [
            'Content-Type' => ReporteExportar::FORMATOS[$formato],
            'Content-Disposition' => 'attachment; filename="' . ReporteExportar::nombreArchivo($filtros, $formato) . '"',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function filtros(Peticion $peticion): array
    {
        return ReporteService::filtros($peticion->consulta('desde'), $peticion->consulta('hasta'), $peticion->consulta('turno'));
    }
}
