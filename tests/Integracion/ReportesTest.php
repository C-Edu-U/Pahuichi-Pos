<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Bitacora;
use App\Services\ReporteExportar;
use App\Services\ReporteService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\CasoPrueba;

/**
 * HU-63: reportes de ventas por fecha y turno (RF-59), con exportación a PDF y Excel.
 * Las ventas anuladas no suman: van en «Anulaciones» y en el libro con estado A.
 */
final class ReportesTest extends CasoPrueba
{
    private int $majadito;   // Bs 40
    private int $locro;      // Bs 35

    protected function setUp(): void
    {
        parent::setUp();
        $this->majadito = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-01'");
        $this->locro = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'PL-02'");

        $this->vender('2026-10-20 12:00:00', 'mediodia', 'mariela', [$this->majadito => 1], '40.00');
        $this->vender('2026-10-22 12:00:00', 'mediodia', 'mariela', [$this->majadito => 2], '80.00');
        $this->vender('2026-10-22 12:30:00', 'mediodia', 'mariela', [$this->locro => 1], '35.00');
        $this->vender('2026-10-22 19:00:00', 'noche', 'jorge', [$this->locro => 2], '70.00');
        $anulada = $this->vender('2026-10-22 19:30:00', 'noche', 'jorge', [$this->majadito => 1], '40.00');
        DB::consulta("UPDATE ventas SET estado = 'anulada' WHERE id = ?", [$anulada]);
        DB::consulta("UPDATE facturas SET estado = 'anulada' WHERE venta_id = ?", [$anulada]);

        Reloj::fijar('2026-10-23 10:00:00');
        $this->entrarComo('admin');
    }

    /** Cobra a la hora indicada en la caja de ese turno y día (la abre si hace falta). */
    private function vender(string $momento, string $turno, string $cajero, array $cantidades, string $total, string $regalos = '0.00'): int
    {
        Reloj::fijar($momento);
        $turnoId = (int) DB::valor('SELECT id FROM turnos WHERE clave = ?', [$turno]);
        $fecha = substr($momento, 0, 10);
        $caja = DB::valor("SELECT id FROM cajas WHERE estado = 'abierta' AND turno_id = ? AND fecha = ?", [$turnoId, $fecha]);
        if ($caja === null) {
            DB::consulta("UPDATE cajas SET estado = 'cerrada' WHERE estado = 'abierta'");
            $this->entrarComo($cajero, 'pin');
            $this->post('/caja/abrir', ['turno_id' => $turnoId, 'fondo' => '200']);
            $caja = DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
        }
        $this->entrarComo($cajero, 'pin');
        $lineas = [];
        foreach ($cantidades as $id => $n) {
            $lineas[] = ['id' => $id, 'cantidad' => $n, 'nota' => ''];
        }
        $r = $this->post('/pos/cobrar', [
            'uuid' => '12345678-1234-4123-a123-' . bin2hex(random_bytes(6)), 'caja_id' => (string) $caja,
            'tipo' => 'llevar', 'mesa' => '', 'exacto' => '1', 'total_esperado' => $total, 'regalos' => $regalos, 'lineas' => json_encode($lineas),
        ]);
        $this->assertSame(200, $r->estado, $r->cuerpo);
        return (int) DB::valor('SELECT MAX(id) FROM ventas');
    }

    private function reporte(string $desde, string $hasta, string $turno = ''): array
    {
        [$filtros, $error] = ReporteService::filtros($desde, $hasta, $turno);
        $this->assertNull($error);
        return ReporteService::generar($filtros);
    }

    private function seccion(array $reporte, string $clave): array
    {
        foreach ($reporte['secciones'] as $s) {
            if ($s['clave'] === $clave) {
                return $s;
            }
        }
        $this->fail("No existe la sección {$clave}");
    }

    private function noche(): string
    {
        return (string) DB::valor("SELECT id FROM turnos WHERE clave = 'noche'");
    }

    /**
     * Escenario 1: Filtros
     *   Dado hay ventas de varios días
     *   Cuando elijo un rango de fechas y un turno
     *   Entonces el reporte muestra solo ese rango
     */
    public function testElReporteMuestraSoloElRangoYElTurno(): void
    {
        $r = $this->get('/reportes', ['desde' => '2026-10-22', 'hasta' => '2026-10-22', 'turno' => $this->noche()]);

        $this->assertSame(200, $r->estado);
        $this->assertStringContainsString('22/10/2026 · Noche', $r->cuerpo);

        $noche = $this->reporte('2026-10-22', '2026-10-22', $this->noche());
        $this->assertSame(1, ReporteService::delResumen($noche['resumen'], 'Ventas cobradas'));
        $this->assertSame(70.0, ReporteService::delResumen($noche['resumen'], 'Total vendido'));
        $this->assertSame([['Jorge Rivero Chávez', 1, 70.0, 70.0]], $this->seccion($noche, 'cajeros')['filas']);
        $this->assertCount(2, $this->seccion($noche, 'libro')['filas']);   // la válida y la anulada

        $dia = $this->reporte('2026-10-22', '2026-10-22');
        $this->assertSame(3, ReporteService::delResumen($dia['resumen'], 'Ventas cobradas'));
        $this->assertSame(185.0, ReporteService::delResumen($dia['resumen'], 'Total vendido'));

        $todo = $this->reporte('2026-10-20', '2026-10-22');
        $this->assertSame(225.0, ReporteService::delResumen($todo['resumen'], 'Total vendido'));

        $ninguna = $this->reporte('2026-10-21', '2026-10-21');
        $this->assertSame(0, ReporteService::delResumen($ninguna['resumen'], 'Ventas cobradas'));
        $this->assertSame([], $this->seccion($ninguna, 'productos')['filas']);
    }

    public function testLasFechasInvertidasSeOrdenanYElRangoTieneTope(): void
    {
        [$f] = ReporteService::filtros('2026-10-22', '2026-10-20', '');
        $this->assertSame(['2026-10-20', '2026-10-22'], [$f['desde'], $f['hasta']]);

        [$f] = ReporteService::filtros('no-es-fecha', '', '999');
        $this->assertSame(['2026-10-23', '2026-10-23', null], [$f['desde'], $f['hasta'], $f['turno']]);

        $r = $this->get('/reportes', ['desde' => '2025-01-01', 'hasta' => '2026-10-22']);
        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('El rango va hasta 366 días.', $r->cuerpo);
        $this->assertSame(422, $this->get('/reportes/exportar', ['desde' => '2025-01-01', 'hasta' => '2026-10-22', 'formato' => 'pdf'])->estado);
    }

    public function testVentasPorProductoCategoriaYCajero(): void
    {
        $dia = $this->reporte('2026-10-22', '2026-10-22');

        // El majadito anulado no cuenta: 2 unidades, no 3
        $this->assertSame([
            ['Locro carretero', 'Platos', 3, 0, 105.0, 0.0, 105.0],
            ['Majadito de charque', 'Platos', 2, 0, 80.0, 0.0, 80.0],
        ], $this->seccion($dia, 'productos')['filas']);
        $this->assertSame(['Total', null, 5, 0, 185.0, 0.0, 185.0], $this->seccion($dia, 'productos')['total']);
        $this->assertSame([['Platos', 5, 185.0, 0.0, 185.0]], $this->seccion($dia, 'categorias')['filas']);
        $this->assertSame([
            ['Mariela Suárez Añez', 2, 115.0, 57.5],
            ['Jorge Rivero Chávez', 1, 70.0, 70.0],
        ], $this->seccion($dia, 'cajeros')['filas']);
    }

    public function testMediosDePagoConQrPendiente(): void
    {
        $caja = (int) DB::valor("SELECT id FROM cajas WHERE estado = 'abierta'");
        $jorge = (int) DB::valor("SELECT id FROM usuarios WHERE usuario = 'jorge'");
        $qr = $this->ventaDirecta($caja, $jorge, 'qr', 'pendiente', 0.50);
        DB::consulta("UPDATE ventas SET creado_en = '2026-10-22 20:00:00' WHERE id = ?", [$qr]);

        $medios = $this->seccion($this->reporte('2026-10-22', '2026-10-22'), 'medios');

        $this->assertSame([
            ['Efectivo', 3, 185.0], ['QR confirmado', 0, 0.0], ['QR pendiente', 1, 0.5], ['QR rechazado', 0, 0.0],
        ], $medios['filas']);
        $this->assertSame(185.5, $medios['total'][2]);
    }

    public function testPromocionesYRegalos(): void
    {
        $soda = (int) DB::valor("SELECT id FROM productos WHERE codigo = 'SO-01'");
        DB::insertar("INSERT INTO promociones (nombre, tipo, producto_id, cantidad_compra, regalo_producto_id) VALUES ('Soda de regalo', 'regalo', ?, 1, ?)", [$this->majadito, $soda]);
        $this->vender('2026-10-21 12:00:00', 'mediodia', 'mariela', [$this->majadito => 3, $this->locro => 1], '155.00', '24.00');

        $dia = $this->reporte('2026-10-21', '2026-10-21');

        $soda = array_values(array_filter($this->seccion($dia, 'productos')['filas'], static fn (array $f): bool => $f[0] === 'Soda cola 500 ml'));
        $this->assertSame([['Soda cola 500 ml', 'Sodas', 0, 3, 24.0, 24.0, 0.0]], $soda);
        $this->assertSame([['Soda de regalo', 'Regalo', 1, 3, 24.0]], $this->seccion($dia, 'promociones')['filas']);
        $this->assertSame(24.0, ReporteService::delResumen($dia['resumen'], 'Descuentos de promociones'));
        $this->assertSame(155.0, ReporteService::delResumen($dia['resumen'], 'Total vendido'));

        // En el libro: importe 179 (precio de lista), descuentos 24, base 155 y débito 20,15
        $this->assertSame([179.0, 24.0, 155.0, 20.15, 'V'], array_slice($this->seccion($dia, 'libro')['filas'][0], 6));
    }

    public function testAnulacionesYLibroDeVentas(): void
    {
        $noche = $this->reporte('2026-10-22', '2026-10-22', $this->noche());

        $anulaciones = $this->seccion($noche, 'anulaciones');
        $this->assertCount(1, $anulaciones['filas']);
        $this->assertSame(['22/10/2026 19:30', 'Noche', 'Jorge Rivero Chávez', 40.0], [$anulaciones['filas'][0][0], $anulaciones['filas'][0][3], $anulaciones['filas'][0][4], $anulaciones['filas'][0][5]]);
        $this->assertSame(1, ReporteService::delResumen($noche['resumen'], 'Ventas anuladas'));
        $this->assertSame(40.0, ReporteService::delResumen($noche['resumen'], 'Monto anulado'));

        $libro = $this->seccion($noche, 'libro');
        $this->assertSame([70.0, 0.0, 70.0, 9.1, 'V'], array_slice($libro['filas'][0], 6));
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 'A'], array_slice($libro['filas'][1], 6));   // anulada: importes en cero
        $this->assertSame(28, strlen($libro['filas'][0][3]));                                   // CUF
        $this->assertSame(['0', 'S/N'], [$libro['filas'][0][4], $libro['filas'][0][5]]);
        $this->assertSame(70.0, $libro['total'][8]);
        $this->assertSame(9.1, $libro['total'][9]);
    }

    /**
     * Escenario 2: Exportar
     *   Dado un reporte generado
     *   Cuando lo exporto a PDF y a Excel
     *   Entonces ambos archivos tienen los mismos totales
     */
    public function testElPdfYElExcelTienenLosMismosTotales(): void
    {
        $consulta = ['desde' => '2026-10-20', 'hasta' => '2026-10-22'];

        $pdf = $this->get('/reportes/exportar', $consulta + ['formato' => 'pdf']);
        $this->assertSame(200, $pdf->estado);
        $this->assertSame('application/pdf', $pdf->cabeceras['Content-Type']);
        $this->assertSame('attachment; filename="Reporte-de-ventas-2026-10-20-al-2026-10-22.pdf"', $pdf->cabeceras['Content-Disposition']);
        $this->assertStringStartsWith('%PDF-', $pdf->cuerpo);

        $xlsx = $this->get('/reportes/exportar', $consulta + ['formato' => 'xlsx']);
        $this->assertSame(200, $xlsx->estado);
        $this->assertStringContainsString('spreadsheetml', $xlsx->cabeceras['Content-Type']);
        $this->assertStringStartsWith('PK', $xlsx->cuerpo);

        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($ruta, $xlsx->cuerpo);
        $libro = IOFactory::load($ruta);
        unlink($ruta);

        // Lo que dice el PDF sale del mismo HTML que Dompdf dibuja
        $reporte = $this->reporte('2026-10-20', '2026-10-22');
        $html = ReporteExportar::html($reporte);

        // Resumen: cada cifra igual en las dos
        $hoja = $libro->getSheetByName('Resumen');
        foreach ($reporte['resumen'] as $i => [$etiqueta, $valor, $tipo]) {
            $this->assertSame($etiqueta, $hoja->getCell([1, 5 + $i])->getValue());
            $this->assertEqualsWithDelta((float) $valor, (float) $hoja->getCell([2, 5 + $i])->getValue(), 0.001, $etiqueta);
            $this->assertStringContainsString('<th scope="row">' . e($etiqueta) . '</th><td class="num">' . ($tipo === 'monto' ? 'Bs ' : '') . ReporteExportar::texto($valor, $tipo) . '</td>', $html);
        }
        $this->assertEqualsWithDelta(225.0, (float) $hoja->getCell('B6')->getValue(), 0.001);

        // Cada reporte: la fila «Total» del Excel es la del PDF
        $comparadas = 0;
        foreach ($reporte['secciones'] as $s) {
            if ($s['total'] === null) {
                continue;
            }
            $hoja = $libro->getSheetByName(mb_substr(trim((string) preg_replace('/[\/\\\\?*\[\]:()]+/', ' ', $s['titulo'])), 0, 31));
            $this->assertNotNull($hoja, $s['titulo']);
            $filaTotal = null;
            foreach ($hoja->getRowIterator() as $fila) {
                if ($hoja->getCell([1, $fila->getRowIndex()])->getValue() === 'Total') {
                    $filaTotal = $fila->getRowIndex();
                }
            }
            $this->assertNotNull($filaTotal, $s['titulo']);
            $celdasPdf = [];
            foreach ($s['total'] as $i => $valor) {
                if ($i === 0 || $valor === null) {
                    continue;
                }
                $tipo = $s['columnas'][$i][1];
                $this->assertEqualsWithDelta((float) $valor, (float) $hoja->getCell([$i + 1, $filaTotal])->getValue(), 0.001, $s['titulo'] . ' · ' . $s['columnas'][$i][0]);
                $celdasPdf[] = ReporteExportar::texto($valor, $tipo);
            }
            $this->assertMatchesRegularExpression('/<tfoot><tr><th scope="row">Total<\/th>.*' . implode('.*', array_map(static fn (string $t): string => preg_quote('>' . $t . '<', '/'), $celdasPdf)) . '.*<\/tr><\/tfoot>/', $html, $s['titulo']);
            $comparadas++;
        }
        $this->assertSame(6, $comparadas);   // promociones no tiene filas en este rango: no lleva total

        $exportes = Bitacora::ultimos('reporte.exportar');
        $this->assertCount(2, $exportes);
        $this->assertSame(['formato' => 'Excel', 'rango' => '20/10/2026 al 22/10/2026 · Todos los turnos'], json_decode((string) $exportes[0]['detalle'], true));
    }

    public function testUnFormatoDesconocidoSeRechaza(): void
    {
        $this->assertSame(422, $this->get('/reportes/exportar', ['formato' => 'csv'])->estado);
    }

    public function testLaPantallaOfreceExportarConLosMismosFiltros(): void
    {
        $html = $this->get('/reportes', ['desde' => '2026-10-22', 'hasta' => '2026-10-22', 'turno' => $this->noche()])->cuerpo;

        $this->assertStringContainsString('href="/reportes/exportar?desde=2026-10-22&amp;hasta=2026-10-22&amp;turno=' . $this->noche() . '&amp;formato=pdf"', $html);
        $this->assertStringContainsString('formato=xlsx"', $html);
        $this->assertStringContainsString('id="r-libro"', $html);
        $this->assertStringNotContainsString('Próximamente', $html);
    }

    /**
     * Escenario 3: Solo Administrador
     *   Dado soy Cajero
     *   Cuando intento abrir los reportes
     *   Entonces el sistema me niega el acceso
     */
    public function testElCajeroNoEntraALosReportes(): void
    {
        $this->entrarComo('mariela', 'pin');

        $this->assertSame(403, $this->get('/reportes')->estado);
        $this->assertSame(403, $this->get('/reportes/exportar', ['formato' => 'pdf'])->estado);
        $this->assertCount(2, Bitacora::ultimos('acceso.denegado'));
        $this->assertCount(0, Bitacora::ultimos('reporte.exportar'));
    }
}
