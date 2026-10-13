<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

/** Conexión PDO única, con sentencias preparadas reales y la hora de Bolivia (UTC-4). */
final class DB
{
    private static ?PDO $pdo = null;

    public static function conexion(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::conectar(Config::get('app.db'));
        }
        return self::$pdo;
    }

    public static function conectar(array $c, bool $conBase = true): PDO
    {
        $dsn = "mysql:host={$c['host']};port={$c['puerto']};charset=utf8mb4";
        if ($conBase) {
            $dsn .= ";dbname={$c['nombre']}";
        }
        $pdo = new PDO($dsn, $c['usuario'], $c['clave'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $pdo->exec("SET time_zone = '-04:00'");
        return $pdo;
    }

    /** Solo pruebas y consola: usa otra conexión (por ejemplo, la base de pruebas). */
    public static function usar(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function consulta(string $sql, array $parametros = []): PDOStatement
    {
        $sentencia = self::conexion()->prepare($sql);
        $sentencia->execute($parametros);
        return $sentencia;
    }

    public static function fila(string $sql, array $parametros = []): ?array
    {
        $fila = self::consulta($sql, $parametros)->fetch();
        return $fila === false ? null : $fila;
    }

    public static function filas(string $sql, array $parametros = []): array
    {
        return self::consulta($sql, $parametros)->fetchAll();
    }

    public static function valor(string $sql, array $parametros = []): mixed
    {
        $valor = self::consulta($sql, $parametros)->fetchColumn();
        return $valor === false ? null : $valor;
    }

    public static function insertar(string $sql, array $parametros = []): int
    {
        self::consulta($sql, $parametros);
        return (int) self::conexion()->lastInsertId();
    }

    /**
     * Ejecuta $fn dentro de una transacción; si algo falla, deshace todo.
     * Si ya hay una transacción abierta, usa un punto de guardado (así se anidan).
     */
    public static function transaccion(callable $fn): mixed
    {
        $pdo = self::conexion();
        $anidada = $pdo->inTransaction();
        $punto = 'p' . bin2hex(random_bytes(4));
        $anidada ? $pdo->exec("SAVEPOINT {$punto}") : $pdo->beginTransaction();
        try {
            $resultado = $fn();
            $anidada ? $pdo->exec("RELEASE SAVEPOINT {$punto}") : $pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            if ($anidada) {
                $pdo->exec("ROLLBACK TO SAVEPOINT {$punto}");
            } elseif ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
