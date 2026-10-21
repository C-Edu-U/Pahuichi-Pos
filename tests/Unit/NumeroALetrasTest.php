<?php

declare(strict_types=1);
namespace Tests\Unit;

use App\Services\NumeroALetras;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NumeroALetrasTest extends TestCase
{
    public static function importes(): array
    {
        return [
            'escenario 1: centavos' => [3850, 'TREINTA Y OCHO 50/100 BOLIVIANOS'],
            'escenario 2: uno' => [100, 'UN 00/100 BOLIVIANOS'],
            'escenario 3: miles' => [125075, 'UN MIL DOSCIENTOS CINCUENTA 75/100 BOLIVIANOS'],
            'cero' => [0, 'CERO 00/100 BOLIVIANOS'],
            'centavo con cero inicial' => [1, 'CERO 01/100 BOLIVIANOS'],
            'veintiuno' => [2105, 'VEINTIÚN 05/100 BOLIVIANOS'],
            'treinta y uno' => [3199, 'TREINTA Y UN 99/100 BOLIVIANOS'],
            'cien' => [10000, 'CIEN 00/100 BOLIVIANOS'],
            'ciento uno' => [10101, 'CIENTO UN 01/100 BOLIVIANOS'],
            'quinientos' => [50000, 'QUINIENTOS 00/100 BOLIVIANOS'],
            'mil exactos según HU' => [100000, 'UN MIL 00/100 BOLIVIANOS'],
            'veintiún mil' => [2100000, 'VEINTIÚN MIL 00/100 BOLIVIANOS'],
            'un millón' => [100000000, 'UN MILLÓN 00/100 BOLIVIANOS'],
            'dos millones' => [200000000, 'DOS MILLONES 00/100 BOLIVIANOS'],
            'límite de venta' => [9999999999, 'NOVENTA Y NUEVE MILLONES NOVECIENTOS NOVENTA Y NUEVE MIL NOVECIENTOS NOVENTA Y NUEVE 99/100 BOLIVIANOS'],
        ];
    }

    #[DataProvider('importes')]
    public function testConvierteCentavosSinPerderPrecision(int $centavos, string $esperado): void
    {
        $this->assertSame($esperado, NumeroALetras::convertir($centavos));
    }

    public function testRechazaMontosNegativos(): void
    {
        $this->expectException(DomainException::class);
        NumeroALetras::convertir(-1);
    }

    public function testRechazaMontosFueraDelRangoDeVenta(): void
    {
        $this->expectException(DomainException::class);
        NumeroALetras::convertir(10000000000);
    }
}
