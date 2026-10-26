<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;

/** Hora de Bolivia. Las pruebas pueden fijarla para revisar vencimientos y bloqueos. */
final class Reloj
{
    private static ?DateTimeImmutable $fija = null;

    public static function ahora(): DateTimeImmutable
    {
        return self::$fija ?? new DateTimeImmutable('now', new DateTimeZone('America/La_Paz'));
    }

    public static function fijar(?string $momento): void
    {
        self::$fija = $momento === null ? null : new DateTimeImmutable($momento, new DateTimeZone('America/La_Paz'));
    }

    /** Formato para columnas DATETIME. */
    public static function sql(?DateTimeImmutable $momento = null): string
    {
        return ($momento ?? self::ahora())->format('Y-m-d H:i:s');
    }
}
