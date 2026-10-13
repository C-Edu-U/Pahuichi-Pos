<?php

declare(strict_types=1);

namespace App\Core;

/** Lee config/*.php con notación de puntos: Config::get('app.sesion.intentos_max'). */
final class Config
{
    private static array $cache = [];

    public static function get(string $clave, mixed $porDefecto = null): mixed
    {
        $partes = explode('.', $clave);
        $archivo = array_shift($partes);
        if (!array_key_exists($archivo, self::$cache)) {
            $ruta = App::raiz() . "/config/{$archivo}.php";
            self::$cache[$archivo] = is_file($ruta) ? require $ruta : [];
        }
        $valor = self::$cache[$archivo];
        foreach ($partes as $parte) {
            if (!is_array($valor) || !array_key_exists($parte, $valor)) {
                return $porDefecto;
            }
            $valor = $valor[$parte];
        }
        return $valor;
    }

    /** Solo para pruebas: reemplaza un valor ya cargado. */
    public static function fijar(string $clave, mixed $valor): void
    {
        self::get($clave);
        $partes = explode('.', $clave);
        $destino = &self::$cache;
        foreach ($partes as $parte) {
            $destino = &$destino[$parte];
        }
        $destino = $valor;
    }
}
