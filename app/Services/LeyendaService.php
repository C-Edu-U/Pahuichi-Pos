<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\DB;
use App\Core\ErrorHttp;
use App\Models\Bitacora;
use App\Models\Leyenda;
use DomainException;
use PDOException;

final class LeyendaService
{
    public static function agregar(string $texto): int
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));
        $texto = trim((string) preg_replace('/^Ley\s+N[°ºo.]?\s*453\s*:\s*/iu', '', $texto));
        if ($texto === '') {
            throw new DomainException('Escribe el texto de la leyenda.');
        }
        $texto = 'Ley N° 453: ' . $texto;
        if (mb_strlen($texto) > 255) {
            throw new DomainException('Usa hasta 255 caracteres, incluido el prefijo Ley N° 453:.');
        }
        try {
            return DB::transaccion(static function () use ($texto): int {
                $id = Leyenda::crear($texto);
                Bitacora::registrar('leyenda.alta', 'leyendas', $id, ['texto' => $texto]);
                return $id;
            });
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('Esa leyenda ya existe.');
            }
            throw $e;
        }
    }

    public static function estado(int $id, bool $activo): void
    {
        DB::transaccion(static function () use ($id, $activo): void {
            $filas = Leyenda::todas(true);
            $antes = null;
            $activas = 0;
            foreach ($filas as $f) {
                $activas += (int) $f['activo'];
                if ((int) $f['id'] === $id) $antes = (bool) $f['activo'];
            }
            if ($antes === null) throw ErrorHttp::noEncontrado('Esa leyenda no existe.');
            if ($antes && !$activo && $activas <= 8) {
                throw new DomainException('Deben quedar al menos 8 leyendas activas.');
            }
            if ($antes === $activo) return;
            Leyenda::cambiarEstado($id, $activo);
            Bitacora::registrar($activo ? 'leyenda.activar' : 'leyenda.desactivar', 'leyendas', $id,
                ['activo' => ['antes' => $antes, 'despues' => $activo]]);
        });
    }
}
