<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sesión del usuario. En la web envuelve $_SESSION; en las pruebas usa un arreglo
 * en memoria, así el mismo código se prueba sin cabeceras ni cookies.
 */
final class Sesion
{
    private static array $memoria = [];
    private static bool $web = false;

    public static function iniciar(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        self::$web = true;
    }

    /** Solo pruebas: sesión vacía en memoria. */
    public static function enMemoria(): void
    {
        self::$web = false;
        self::$memoria = [];
    }

    private static function &datos(): array
    {
        if (self::$web) {
            return $_SESSION;
        }
        return self::$memoria;
    }

    public static function get(string $clave, mixed $porDefecto = null): mixed
    {
        return self::datos()[$clave] ?? $porDefecto;
    }

    public static function set(string $clave, mixed $valor): void
    {
        $datos = &self::datos();
        $datos[$clave] = $valor;
    }

    public static function quitar(string $clave): void
    {
        $datos = &self::datos();
        unset($datos[$clave]);
    }

    /** Mensaje para la próxima página (patrón POST, redirección, GET). */
    public static function flash(string $clave, mixed $valor): void
    {
        $datos = &self::datos();
        $datos['_flash'][$clave] = $valor;
    }

    public static function tomarFlash(string $clave, mixed $porDefecto = null): mixed
    {
        $datos = &self::datos();
        $valor = $datos['_flash'][$clave] ?? $porDefecto;
        unset($datos['_flash'][$clave]);
        return $valor;
    }

    /** Nuevo identificador de sesión al iniciar sesión (evita la fijación de sesión). */
    public static function regenerar(): void
    {
        if (self::$web && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destruir(): void
    {
        $datos = &self::datos();
        $datos = [];
        if (self::$web && session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600, 'path' => $p['path'], 'domain' => $p['domain'],
                'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'] ?: 'Lax',
            ]);
            session_destroy();
        }
    }
}
