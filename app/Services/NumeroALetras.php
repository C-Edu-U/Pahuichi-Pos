<?php

declare(strict_types=1);
namespace App\Services;

/** Portado del conversor de demo; recibe centavos enteros para evitar pérdida de precisión. */
final class NumeroALetras
{
    private const U = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];
    private const D = ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
    private const C = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

    private static function menorMil(int $n): string
    {
        if ($n === 100) return 'cien';
        $resto = $n % 100;
        return trim(self::C[intdiv($n, 100)] . ' ' . ($resto < 30 ? self::U[$resto] : self::D[intdiv($resto, 10)] . ($resto % 10 ? ' y ' . self::U[$resto % 10] : '')));
    }

    private static function apocopar(string $texto): string
    {
        return (string) preg_replace('/uno$/u', 'un', str_replace('veintiuno', 'veintiún', $texto));
    }

    private static function entero(int $n): string
    {
        if ($n === 0) return 'cero';
        $partes = [];
        $millones = intdiv($n, 1000000);
        $miles = intdiv($n % 1000000, 1000);
        if ($millones) $partes[] = $millones === 1 ? 'un millón' : self::apocopar(self::entero($millones)) . ' millones';
        if ($miles) $partes[] = $miles === 1 ? 'mil' : self::apocopar(self::menorMil($miles)) . ' mil';
        if ($n % 1000) $partes[] = self::menorMil($n % 1000);
        return implode(' ', $partes);
    }

    public static function convertir(int $centavos): string
    {
        $t = self::entero(intdiv($centavos, 100));
        return mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1) . ' ' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT) . '/100 Bolivianos';
    }
}
