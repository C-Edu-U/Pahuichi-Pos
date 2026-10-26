<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Reloj;
use App\Core\Vista;
use App\Models\Negocio;
use DateTimeImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exporta los reportes (HU-63) a PDF y a Excel. Los dos salen del mismo arreglo que
 * arma ReporteService::generar(): mismas filas y mismos totales.
 */
final class ReporteExportar
{
    public const FORMATOS = ['pdf' => 'application/pdf', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

    /** Valor de una celda como se lee en pantalla y en el PDF: montos con dos decimales. */
    public static function texto(mixed $valor, string $tipo): string
    {
        if ($valor === null) {
            return '';
        }
        return match ($tipo) {
            'monto' => bs((float) $valor),
            'entero' => number_format((int) $valor, 0, ',', '.'),
            default => (string) $valor,
        };
    }

    /** «01/10/2026 al 24/10/2026 · Todos los turnos» */
    public static function rango(array $f): string
    {
        $d = (new DateTimeImmutable($f['desde']))->format('d/m/Y');
        $h = (new DateTimeImmutable($f['hasta']))->format('d/m/Y');
        return ($d === $h ? $d : "{$d} al {$h}") . ' · ' . $f['turno_nombre'];
    }

    /** Reporte-de-ventas-2026-10-01-al-2026-10-24-noche.pdf */
    public static function nombreArchivo(array $f, string $formato): string
    {
        $turno = $f['turno'] !== null
            ? '-' . strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $f['turno_nombre']) ?: ''), '-'))
            : '';
        $fechas = $f['desde'] === $f['hasta'] ? $f['desde'] : "{$f['desde']}-al-{$f['hasta']}";
        return "Reporte-de-ventas-{$fechas}{$turno}.{$formato}";
    }

    /** HTML interno para Dompdf; nunca llega al navegador. */
    public static function html(array $reporte): string
    {
        return Vista::render('reportes/pdf', $reporte + ['negocio' => Negocio::obtener(), 'generado' => Reloj::ahora()], null);
    }

    public static function pdf(array $reporte): string
    {
        $opciones = new Options();
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('isPhpEnabled', false);
        $opciones->set('isJavascriptEnabled', false);
        $opciones->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($opciones);
        $pdf->setPaper('A4', 'landscape');
        $pdf->loadHtml(self::html($reporte), 'UTF-8');
        $pdf->render();
        return $pdf->output();
    }

    /** Un libro con la hoja «Resumen» y una hoja por reporte. Los números van como números. */
    public static function excel(array $reporte): string
    {
        $libro = new Spreadsheet();
        $libro->getProperties()->setCreator('Pahuichi POS')->setTitle('Reporte de ventas');

        $resumen = $libro->getActiveSheet();
        $resumen->setTitle('Resumen');
        $fila = self::encabezado($resumen, 'Reporte de ventas', $reporte['filtros']);
        foreach ($reporte['resumen'] as [$etiqueta, $valor, $tipo]) {
            $resumen->setCellValue([1, $fila], $etiqueta);
            self::numero($resumen, 2, $fila, $valor, $tipo);
            $fila++;
        }
        $resumen->getColumnDimension('A')->setAutoSize(true);
        $resumen->getColumnDimension('B')->setAutoSize(true);

        foreach ($reporte['secciones'] as $s) {
            $hoja = $libro->createSheet();
            $hoja->setTitle(self::tituloHoja($s['titulo']));
            $fila = self::encabezado($hoja, $s['titulo'], $reporte['filtros']);
            foreach ($s['columnas'] as $i => [$texto]) {
                $hoja->setCellValue([$i + 1, $fila], $texto);
            }
            $hoja->getStyle([1, $fila, count($s['columnas']), $fila])->getFont()->setBold(true);
            $fila++;
            foreach ($s['filas'] as $datos) {
                foreach ($datos as $i => $valor) {
                    self::numero($hoja, $i + 1, $fila, $valor, $s['columnas'][$i][1]);
                }
                $fila++;
            }
            if ($s['filas'] === []) {
                $hoja->setCellValue([1, $fila++], 'Sin datos en este rango.');
            }
            if ($s['total'] !== null) {
                foreach ($s['total'] as $i => $valor) {
                    self::numero($hoja, $i + 1, $fila, $valor, $i === 0 ? 'texto' : $s['columnas'][$i][1]);
                }
                $hoja->getStyle([1, $fila, count($s['columnas']), $fila])->getFont()->setBold(true);
                $fila++;
            }
            if ($s['nota'] !== null) {
                $hoja->setCellValue([1, $fila + 1], $s['nota']);
            }
            foreach (array_keys($s['columnas']) as $i) {
                $hoja->getColumnDimensionByColumn($i + 1)->setAutoSize(true);
            }
        }
        $libro->setActiveSheetIndex(0);

        $ruta = tempnam(sys_get_temp_dir(), 'reporte');
        try {
            (new Xlsx($libro))->save($ruta);
            return (string) file_get_contents($ruta);
        } finally {
            @unlink($ruta);
            $libro->disconnectWorksheets();
        }
    }

    /** Título, negocio y filtros arriba de cada hoja; devuelve la primera fila libre. */
    private static function encabezado(Worksheet $hoja, string $titulo, array $f): int
    {
        $hoja->setCellValue('A1', $titulo);
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $hoja->setCellValue('A2', (Negocio::obtener()['nombre_comercial'] ?? 'Pahuichi') . ' · ' . self::rango($f));
        $hoja->setCellValue('A3', 'Generado el ' . Reloj::ahora()->format('d/m/Y H:i') . ' · Documento sin valor fiscal');
        return 5;
    }

    private static function numero(Worksheet $hoja, int $columna, int $fila, mixed $valor, string $tipo): void
    {
        if ($valor === null) {
            return;
        }
        if ($tipo === 'texto') {
            // Los números de factura, NIT y CUF son texto: sin notación científica ni ceros perdidos
            $hoja->setCellValueExplicit([$columna, $fila], (string) $valor, DataType::TYPE_STRING);
            return;
        }
        $hoja->setCellValue([$columna, $fila], $tipo === 'entero' ? (int) $valor : (float) $valor);
        $hoja->getStyle([$columna, $fila])->getNumberFormat()->setFormatCode($tipo === 'entero' ? '#,##0' : '#,##0.00');
    }

    /** Excel admite hasta 31 caracteres en el nombre de la hoja y no admite «/ \ ? * [ ] :». */
    private static function tituloHoja(string $titulo): string
    {
        return mb_substr(trim((string) preg_replace('/[\/\\\\?*\[\]:()]+/', ' ', $titulo)), 0, 31);
    }
}
