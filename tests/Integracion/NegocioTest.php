<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Core\DB;
use App\Models\Bitacora;
use Tests\CasoPrueba;

/** HU-08: datos del negocio. */
final class NegocioTest extends CasoPrueba
{
    private const DATOS = [
        'razon_social' => 'PAHUICHI S.R.L.',
        'nombre_comercial' => 'Pahuichi',
        'nit' => '1023456021',
        'direccion' => 'Av. Cañoto 345',
        'telefono' => '+591 3 3334455',
        'municipio' => 'Santa Cruz de la Sierra',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->entrarComo('admin');
    }

    public function testMuestraLosDatosCargados(): void
    {
        $r = $this->get('/config');

        $this->assertSame(200, $r->estado);
        $this->assertStringContainsString('value="1023456021"', $r->cuerpo);
        $this->assertStringContainsString('PAHUICHI S.R.L.', $r->cuerpo);
    }

    public function testGuardaLosDatosYLoRegistraEnLaBitacora(): void
    {
        $r = $this->post('/config/negocio', self::DATOS);

        $this->assertRedirige('/config#negocio', $r);
        $n = DB::fila('SELECT * FROM negocio');
        $this->assertSame('Av. Cañoto 345', $n['direccion']);
        $this->assertSame('+591 3 3334455', $n['telefono']);
        $registro = Bitacora::ultimos('config.negocio')[0];
        $this->assertStringContainsString('Calle por confirmar', $registro['detalle']);
        $this->assertStringContainsString('Av. Cañoto 345', $registro['detalle']);
    }

    public function testValidaNitYCamposObligatorios(): void
    {
        $r = $this->post('/config/negocio', ['nit' => '12AB', 'razon_social' => ''] + self::DATOS);

        $this->assertSame(422, $r->estado);
        $this->assertStringContainsString('El NIT tiene solo números, entre 7 y 13 dígitos.', $r->cuerpo);
        $this->assertStringContainsString('Escribe la razón social.', $r->cuerpo);
        $this->assertSame('1023456021', DB::valor('SELECT nit FROM negocio'));
    }

    public function testTelefonoOpcional(): void
    {
        $this->post('/config/negocio', ['telefono' => ''] + self::DATOS);

        $this->assertNull(DB::valor('SELECT telefono FROM negocio'));
    }

    public function testSinLogoNoHayImagen(): void
    {
        $this->assertSame(404, $this->get('/negocio/logo')->estado);
    }

    public function testCajeroNoEntraAConfiguracion(): void
    {
        $this->entrarComo('mariela', 'pin');

        $this->assertSame(403, $this->get('/config')->estado);
        $this->assertSame(403, $this->post('/config/negocio', self::DATOS)->estado);
    }
}
