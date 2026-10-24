<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Reloj;
use App\Core\Vista;
use App\Models\Arqueo;
use App\Models\MovimientoCaja;
use App\Models\Negocio;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Reporte de cierre de caja en PDF (HU-25). Sale de lo que se guardó al confirmar el
 * arqueo (esperado, diferencia, resumen y observación), no de un cálculo nuevo: el PDF
 * de hoy y el de dentro de un mes dicen lo mismo. El sistema solo genera el PDF; se
 * imprime desde el visor.
 */
final class ReporteCierre
{
    /** Datos del reporte: la caja y su arqueo cerrado. */
    public static function datos(array $caja, array $arqueo): array
    {
        return [
            'caja' => $caja,
            'arqueo' => $arqueo,
            'resumen' => $arqueo['resumen'],
            'movimientos' => MovimientoCaja::porCaja((int) $caja['id']),
            'reemplazados' => Arqueo::reemplazados((int) $caja['id']),
            'negocio' => Negocio::obtener(),
            'generado' => Reloj::ahora(),
        ];
    }

    /** HTML interno para Dompdf; nunca llega al navegador. */
    public static function html(array $datos): string
    {
        return Vista::render('caja/reporte-pdf', $datos, null);
    }

    public static function pdf(array $datos): string
    {
        $opciones = new Options();
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('isPhpEnabled', false);
        $opciones->set('isJavascriptEnabled', false);
        $opciones->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($opciones);
        $pdf->setPaper('A4');
        $pdf->loadHtml(self::html($datos), 'UTF-8');
        $pdf->render();
        return $pdf->output();
    }

    /** Nombre del archivo: Cierre-de-caja-2026-10-24-mediodia-15.pdf */
    public static function nombreArchivo(array $caja): string
    {
        $turno = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', (string) $caja['turno']) ?: ''), '-'));
        return 'Cierre-de-caja-' . $caja['fecha'] . ($turno !== '' ? '-' . $turno : '') . '-' . (int) $caja['id'] . '.pdf';
    }
}
