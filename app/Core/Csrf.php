<?php

declare(strict_types=1);

namespace App\Core;

/** Token contra CSRF: uno por sesión, se renueva al iniciar sesión. */
final class Csrf
{
    private const CLAVE = '_csrf';

    public static function token(): string
    {
        $token = Sesion::get(self::CLAVE);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Sesion::set(self::CLAVE, $token);
        }
        return $token;
    }

    public static function valido(mixed $token): bool
    {
        $actual = Sesion::get(self::CLAVE);
        return is_string($token) && is_string($actual) && $actual !== '' && hash_equals($actual, $token);
    }

    public static function renovar(): void
    {
        Sesion::quitar(self::CLAVE);
    }
}
