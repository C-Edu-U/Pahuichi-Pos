<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Error con código HTTP que la app convierte en su página (404, 405...). */
final class ErrorHttp extends RuntimeException
{
    public function __construct(public readonly int $estado, string $mensaje = '')
    {
        parent::__construct($mensaje, $estado);
    }

    public static function noEncontrado(string $mensaje = 'No encontramos lo que buscas.'): self
    {
        return new self(404, $mensaje);
    }
}
