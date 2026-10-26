<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Usuario;

/**
 * Usuario de la sesión. El registro se vuelve a leer en cada petición: si el
 * administrador lo desactiva o le cambia el rol, vale desde la siguiente acción.
 */
final class Auth
{
    private static ?array $usuario = null;
    private static bool $cargado = false;

    public static function iniciar(array $usuario, string $modo): void
    {
        Sesion::regenerar();
        Csrf::renovar();
        Sesion::set('usuario_id', (int) $usuario['id']);
        Sesion::set('modo', $modo);
        Sesion::set('ultimo_uso', Reloj::ahora()->getTimestamp());
        self::$usuario = $usuario;
        self::$cargado = true;
    }

    public static function usuario(): ?array
    {
        if (!self::$cargado) {
            $id = Sesion::get('usuario_id');
            self::$usuario = is_int($id) ? Usuario::buscar($id) : null;
            self::$cargado = true;
        }
        return self::$usuario;
    }

    public static function id(): ?int
    {
        $u = self::usuario();
        return $u === null ? null : (int) $u['id'];
    }

    public static function rol(): ?string
    {
        return self::usuario()['rol'] ?? null;
    }

    /** Minutos sin actividad que cierran la sesión; 0 para el cajero (RNF-18). */
    public static function minutosInactividad(): int
    {
        return self::rol() === 'cajero' ? 0 : (int) Config::get('app.sesion.inactividad_min');
    }

    /** ¿Pasó el tiempo de inactividad desde la última petición? */
    public static function vencida(): bool
    {
        $minutos = self::minutosInactividad();
        $ultimoUso = (int) Sesion::get('ultimo_uso', 0);
        return $minutos > 0 && Reloj::ahora()->getTimestamp() - $ultimoUso > $minutos * 60;
    }

    /** Marca actividad: la sesión sigue viva otros 15 minutos. */
    public static function tocar(): void
    {
        Sesion::set('ultimo_uso', Reloj::ahora()->getTimestamp());
    }

    public static function inicio(): string
    {
        return Usuario::INICIO[self::rol() ?? ''] ?? '/login';
    }

    public static function cerrar(): void
    {
        Sesion::destruir();
        self::olvidar();
    }

    /** Olvida el usuario cargado (al cambiar de petición en las pruebas). */
    public static function olvidar(): void
    {
        self::$usuario = null;
        self::$cargado = false;
    }
}
