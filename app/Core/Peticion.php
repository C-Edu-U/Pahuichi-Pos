<?php

declare(strict_types=1);

namespace App\Core;

/** Petición HTTP en un objeto, para que controladores y pruebas no dependan de las superglobales. */
final class Peticion
{
    public array $parametros = [];

    public function __construct(
        public readonly string $metodo,
        public readonly string $ruta,
        public readonly array $query = [],
        public readonly array $datos = [],
        public readonly array $archivos = [],
        public readonly string $ip = '',
        public readonly array $cabeceras = [],
    ) {
    }

    public static function desdeGlobales(): self
    {
        $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $ruta = '/' . trim(rawurldecode($ruta), '/');

        $cabeceras = [];
        foreach ($_SERVER as $clave => $valor) {
            if (str_starts_with($clave, 'HTTP_')) {
                $cabeceras[strtolower(str_replace('_', '-', substr($clave, 5)))] = $valor;
            }
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $ruta,
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $cabeceras,
        );
    }

    /** Valor enviado por POST, recortado si es texto. */
    public function entrada(string $clave, mixed $porDefecto = ''): mixed
    {
        $valor = $this->datos[$clave] ?? $porDefecto;
        return is_string($valor) ? trim($valor) : $valor;
    }

    public function marcado(string $clave): bool
    {
        return isset($this->datos[$clave]) && $this->datos[$clave] !== '' && $this->datos[$clave] !== '0';
    }

    public function consulta(string $clave, string $porDefecto = ''): string
    {
        $valor = $this->query[$clave] ?? $porDefecto;
        return is_string($valor) ? trim($valor) : $porDefecto;
    }

    public function parametro(string $clave): string
    {
        return $this->parametros[$clave] ?? '';
    }

    public function archivo(string $clave): ?array
    {
        $archivo = $this->archivos[$clave] ?? null;
        if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $archivo;
    }

    public function esperaJson(): bool
    {
        return str_contains($this->cabeceras['accept'] ?? '', 'application/json');
    }
}
