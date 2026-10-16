<?php

declare(strict_types=1);

namespace App\Core;

final class Respuesta
{
    public function __construct(
        public string $cuerpo = '',
        public int $estado = 200,
        public array $cabeceras = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {
    }

    public static function html(string $cuerpo, int $estado = 200): self
    {
        return new self($cuerpo, $estado);
    }

    public static function redirigir(string $destino, int $estado = 303): self
    {
        return new self('', $estado, ['Location' => $destino]);
    }

    public static function json(mixed $datos, int $estado = 200): self
    {
        return new self(
            json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $estado,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public function cabecera(string $nombre, string $valor): self
    {
        $this->cabeceras[$nombre] = $valor;
        return $this;
    }

    public function enviar(): void
    {
        http_response_code($this->estado);
        foreach ($this->cabeceras as $nombre => $valor) {
            header("{$nombre}: {$valor}");
        }
        echo $this->cuerpo;
    }
}
