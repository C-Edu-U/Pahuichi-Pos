<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Bitacora;
use App\Models\ParametrosFiscales;
use Tests\CasoPrueba;

/** HU-09: parámetros fiscales simulados. */
final class ParametrosFiscalesTest extends CasoPrueba
{
    private const DATOS = [
        'codigo_sucursal' => '0',
        'codigo_punto_venta' => '1',
        'actividad_economica' => '561011 · Restaurantes y venta de comidas',
        'cuis' => 'c2f45a01',
        'cufd' => 'BQUFDdkRDQUE=MzQ1MDc2RkQ4M0Y5Nzg=',
        'cufd_codigo_control' => '7a3f2c91d4e8b605',
        'cufd_vigencia' => '2026-10-15T23:59',
        'numero_inicial' => '100',
        'modalidad' => 'electronica',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->entrarComo('admin');
    }

    public function testGuardaLosParametros(): void
    {
        $r = $this->post('/config/fiscal', self::DATOS);

        $this->assertRedirige('/config#fiscal', $r);
        $p = ParametrosFiscales::obtener();
        $this->assertSame(1, (int) $p['codigo_punto_venta']);
        $this->assertSame('C2F45A01', $p['cuis']);
        $this->assertSame('7A3F2C91D4E8B605', $p['cufd_codigo_control']);
        $this->assertSame('2026-10-15 23:59:00', $p['cufd_vigencia']);
        $this->assertSame(100, (int) $p['numero_inicial']);
        $this->assertSame('electronica', $p['modalidad']);
        $this->assertStringContainsString('numero_inicial', Bitacora::ultimos('config.fiscal')[0]['detalle']);
    }

    public function testValidaCadaCampo(): void
    {
        $r = $this->post('/config/fiscal', [
            'codigo_sucursal' => '-1',
            'cuis' => 'x',
            'cufd_codigo_control' => 'ZZZZ',
            'cufd_vigencia' => 'mañana',
            'numero_inicial' => '0',
            'modalidad' => 'manual',
        ] + self::DATOS);

        $this->assertSame(422, $r->estado);
        foreach ([
            'Escribe un número de 0 a 9999.',
            'El CUIS tiene de 4 a 20 letras o números.',
            'caracteres hexadecimales',
            'Elige la fecha y la hora de vigencia.',
            'El número inicial es un entero desde 1.',
            'Elige una modalidad de la lista.',
        ] as $mensaje) {
            $this->assertStringContainsString($mensaje, $r->cuerpo);
        }
        $this->assertSame('computarizada', DB::valor('SELECT modalidad FROM parametros_fiscales'));
    }

    public function testElCufdSoloValeElDiaDeSuVigencia(): void
    {
        $p = ['cufd_vigencia' => '2026-10-15 23:59:00'];

        Reloj::fijar('2026-10-15 12:00:00');
        $this->assertTrue(ParametrosFiscales::cufdVigente($p));
        Reloj::fijar('2026-10-16 00:00:01');
        $this->assertFalse(ParametrosFiscales::cufdVigente($p));
        Reloj::fijar('2026-10-14 12:00:00');
        $this->assertFalse(ParametrosFiscales::cufdVigente($p), 'Un CUFD de mañana no vale hoy');
    }

    public function testLaPantallaAvisaSiElCufdVencio(): void
    {
        DB::consulta("UPDATE parametros_fiscales SET cufd_vigencia = '2020-01-01 23:59:00'");

        $this->assertStringContainsString('Vencido: no corresponde al día de hoy', $this->get('/config')->cuerpo);
    }

    public function testLosDatosDePruebaTraenUnCufdVigenteHoy(): void
    {
        $this->assertStringContainsString('Vigente hoy', $this->get('/config')->cuerpo);
    }
}
