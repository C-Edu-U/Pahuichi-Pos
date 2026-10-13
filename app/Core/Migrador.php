<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Aplica database/migraciones/*.sql en orden, una sola vez cada una (tabla migraciones),
 * y carga database/seeds.sql si la base no tiene usuarios.
 */
final class Migrador
{
    public function __construct(private readonly PDO $pdo, private readonly string $carpeta)
    {
    }

    /** @return string[] archivos aplicados en esta corrida */
    public function migrar(): array
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS migraciones (
                archivo VARCHAR(120) NOT NULL PRIMARY KEY,
                aplicada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB'
        );
        $hechas = $this->pdo->query('SELECT archivo FROM migraciones')->fetchAll(PDO::FETCH_COLUMN);

        $aplicadas = [];
        $archivos = glob($this->carpeta . '/migraciones/*.sql') ?: [];
        sort($archivos, SORT_STRING);
        foreach ($archivos as $ruta) {
            $nombre = basename($ruta);
            if (in_array($nombre, $hechas, true)) {
                continue;
            }
            $this->ejecutarArchivo($ruta);
            $this->pdo->prepare('INSERT INTO migraciones (archivo) VALUES (?)')->execute([$nombre]);
            $aplicadas[] = $nombre;
        }
        return $aplicadas;
    }

    /** Carga los datos de prueba solo si la base está vacía. */
    public function sembrarSiVacia(): bool
    {
        if ((int) $this->pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0) {
            return false;
        }
        $this->ejecutarArchivo($this->carpeta . '/seeds.sql');
        return true;
    }

    /** Borra todas las tablas (para volver a empezar la demo o las pruebas). */
    public function vaciar(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as [$tabla, $tipo]) {
            $this->pdo->exec(($tipo === 'VIEW' ? 'DROP VIEW ' : 'DROP TABLE ') . "`{$tabla}`");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function ejecutarArchivo(string $ruta): void
    {
        foreach (self::sentencias((string) file_get_contents($ruta)) as $sql) {
            $this->pdo->exec($sql);
        }
    }

    /** Separa un archivo SQL en sentencias: cada una termina en «;» al final de la línea. */
    public static function sentencias(string $sql): array
    {
        $sinComentarios = preg_replace('/^\s*--.*$/m', '', str_replace("\r\n", "\n", $sql));
        $partes = preg_split('/;\s*(?:\n|$)/', (string) $sinComentarios);
        return array_values(array_filter(array_map('trim', $partes), static fn (string $s): bool => $s !== ''));
    }
}
