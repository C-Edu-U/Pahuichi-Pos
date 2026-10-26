<?php

declare(strict_types=1);
namespace Tests\Integracion;
use Tests\CasoPrueba;
final class RespaldosAccesoTest extends CasoPrueba
{
    public function testSoloAdministradorPuedeConsultarYSimular(): void
    {
        $this->entrarComo('mariela','pin');
        self::assertSame(403,$this->get('/config/respaldos/estado')->estado);
        self::assertSame(403,$this->post('/config/respaldos/simular',['accion'=>'copiar'])->estado);
        $this->entrarComo('admin');
        self::assertSame(200,$this->get('/config/respaldos/estado')->estado);
        self::assertSame(403,$this->post('/config/respaldos/simular',['accion'=>'copiar','_csrf'=>'incorrecto'])->estado);
    }
}
