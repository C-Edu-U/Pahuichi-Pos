<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\DB;
use App\Models\Bitacora;
use App\Models\Cliente;
use DomainException;
use PDOException;

final class ClienteService
{
    public static function normalizar(mixed $doc, mixed $nombre, mixed $tipo): array
    {
        if (!is_string($doc) || !is_string($nombre) || !is_string($tipo)) throw new DomainException('Revisa el NIT o CI y el nombre del cliente.');
        $doc = strtoupper(trim($doc));
        $nombre = preg_replace('/\s+/u', ' ', trim($nombre));
        if ($nombre === null || preg_match('/[\x00-\x1F\x7F]/u', $nombre)) throw new DomainException('Escribe un nombre válido.');
        if ($doc === '' || $doc === '0') {
            if ($nombre !== '' && $nombre !== 'S/N') throw new DomainException('Escribe el NIT o CI del cliente, o selecciona S/N.');
            return ['doc' => '0', 'nombre' => 'S/N', 'tipo' => 'NIT'];
        }
        if (!in_array($tipo, ['NIT', 'CI'], true)) throw new DomainException('Selecciona NIT o CI.');
        $patron = $tipo === 'NIT' ? '/^[0-9]{1,20}$/D' : '/^[0-9]{1,20}(?:-[A-Z0-9]{1,5})?$/D';
        if (!preg_match($patron, $doc) || preg_match('/^0+$/D', $doc)) throw new DomainException('Escribe un NIT o CI válido. Para CI puedes añadir el complemento separado por un guion.');
        if ($nombre !== '' && (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 120)) throw new DomainException('El nombre del cliente debe tener de 2 a 120 caracteres.');
        return ['doc' => $doc, 'nombre' => $nombre, 'tipo' => $tipo];
    }

    /** Se invoca dentro de la transacción del cobro o del alta independiente. */
    public static function resolver(array $d): array
    {
        if (!DB::conexion()->inTransaction()) throw new \LogicException('El cliente se registra dentro de una transacción.');
        if ($d['doc'] === '0') return ['id' => null] + $d;
        $existente = Cliente::porDocumento($d['doc']);
        if ($existente) return $existente; // Un cobro nunca modifica el nombre de un cliente existente.
        if ($d['nombre'] === '') throw new DomainException('Cliente no registrado. Escribe su nombre o razón social.');
        try {
            $id = Cliente::crear($d);
        } catch (PDOException $e) {
            // Dos equipos pueden intentar dar de alta el mismo documento: el índice único evita duplicados.
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) throw $e;
            return Cliente::porDocumento($d['doc'], true) ?? throw $e;
        }
        Bitacora::registrar('cliente.alta', 'clientes', $id, ['tipo' => $d['tipo'], 'documento' => $d['doc'], 'nombre' => $d['nombre']]);
        return ['id' => $id] + $d;
    }

    public static function guardar(mixed $doc, mixed $nombre, mixed $tipo): array
    {
        $d = self::normalizar($doc, $nombre, $tipo);
        if ($d['doc'] === '0') throw new DomainException('S/N con NIT 0 es el valor por defecto; no requiere registro.');
        return DB::transaccion(static fn (): array => self::resolver($d));
    }
}
