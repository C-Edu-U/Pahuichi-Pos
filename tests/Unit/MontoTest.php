<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MontoTest extends TestCase
{
    public static function montos(): array
    {
        return [
            ['45', 45.0],
            ['45,5', 45.5],
            ['45,50', 45.5],
            ['45.50', 45.5],
            ['1.286,50', 1286.5],
            ['1.286', 1286.0],
            [' 0,50 ', 0.5],
            ['12,345', null],
            ['abc', null],
            ['', null],
            ['-5', null],
        ];
    }

    #[DataProvider('montos')]
    public function testLeeMontosEscritosAMano(string $texto, ?float $esperado): void
    {
        $this->assertSame($esperado, leer_monto($texto));
    }

    public function testFormatoBoliviano(): void
    {
        $this->assertSame('1.286,50', bs(1286.5));
        $this->assertSame('0,50', bs('0.50'));
    }
}
