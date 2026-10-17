<?php

declare(strict_types=1);
namespace Tests\Unit;
use App\Services\NumeroALetras;
use PHPUnit\Framework\TestCase;

final class NumeroALetrasTest extends TestCase
{
    public function testImportesDeFactura(): void
    {
        foreach ([0 => 'Cero 00/100 Bolivianos', 7550 => 'Setenta y cinco 50/100 Bolivianos', 10000 => 'Cien 00/100 Bolivianos', 100001 => 'Mil 01/100 Bolivianos', 2100000 => 'Veintiún mil 00/100 Bolivianos', 100000000 => 'Un millón 00/100 Bolivianos'] as $centavos => $esperado) {
            $this->assertSame($esperado, NumeroALetras::convertir($centavos));
        }
    }
}
