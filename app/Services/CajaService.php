<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Reloj;
use App\Models\Bitacora;
use App\Models\Caja;
use App\Models\Turno;
use App\Models\Usuario;
use DomainException;
use PDOException;

/**
 * Apertura de caja (HU-20) y contrato con el POS (HU-21): CajaService::abierta().
 *
 * RN-01: una sola caja abierta a la vez (también lo impide un índice único en la base).
 * RN-02: un turno no abre si la caja anterior no está cerrada y arqueada. El cierre
 * del Sprint 2 cierra y arquea en un solo paso, así que «cerrada» ya implica arqueada.
 */
final class CajaService
{
    public static function abierta(): ?array
    {
        return Caja::abierta();
    }

    /** HU-20: el cajero opera y ve solo su caja; el administrador, todas. */
    public static function esPropia(array $caja, array $usuario): bool
    {
        return $usuario['rol'] === 'administrador' || (int) $caja['cajero_id'] === (int) $usuario['id'];
    }

    /** Turno que corresponde a la hora actual; si no hay, el próximo del día; si no, el primero. */
    public static function turnoSugerido(array $turnos): ?array
    {
        $hora = Reloj::ahora()->format('H:i:s');
        foreach ($turnos as $t) {
            if ($hora >= $t['hora_inicio'] && $hora < $t['hora_fin']) {
                return $t;
            }
        }
        foreach ($turnos as $t) {
            if ($hora < $t['hora_inicio']) {
                return $t;
            }
        }
        return $turnos[0] ?? null;
    }

    /** @return int id de la caja abierta */
    public function abrir(int $turnoId, int $cajeroId, float $fondo, array $quienAbre): int
    {
        $this->sinCajaAbierta();

        $turno = Turno::buscar($turnoId);
        if ($turno === null || !(int) $turno['activo']) {
            throw new DomainException('Elige un turno de la lista.');
        }
        $cajero = Usuario::buscar($cajeroId);
        if ($cajero === null || $cajero['rol'] !== 'cajero' || !(int) $cajero['activo']) {
            throw new DomainException('Elige un cajero activo.');
        }
        if ($quienAbre['rol'] === 'cajero' && (int) $quienAbre['id'] !== $cajeroId) {
            throw new DomainException('Solo puedes abrir tu propia caja.');
        }
        if ($fondo < 0 || $fondo > 99999.99) {
            throw new DomainException('El fondo inicial va de Bs 0 a Bs 99.999,99.');
        }

        $ahora = Reloj::ahora();
        $fecha = $ahora->format('Y-m-d');
        if (Caja::delTurno($fecha, $turnoId) !== null) {
            throw new DomainException("La caja del turno de {$turno['nombre']} de hoy ya se abrió y se cerró. Para volver a usarla, el administrador debe reabrirla.");
        }

        try {
            $id = Caja::crear($turnoId, $fecha, $cajeroId, (int) $quienAbre['id'], $fondo, Reloj::sql($ahora));
        } catch (PDOException $e) {
            // Dos aperturas al mismo tiempo: el índice único deja pasar solo una
            if (($e->errorInfo[1] ?? null) === 1062) {
                $this->sinCajaAbierta();
                throw new DomainException('La caja de ese turno ya se abrió hoy.');
            }
            throw $e;
        }

        Bitacora::registrar('caja.apertura', 'cajas', $id, [
            'turno' => $turno['nombre'],
            'cajero' => $cajero['nombre'],
            'fondo_inicial' => number_format($fondo, 2, '.', ''),
        ]);
        return $id;
    }

    /** Cierra la caja abierta. Solo el cajero que la abrió o un administrador. */
    public function cerrar(int $quienCierraId, string $quienCierraRol): void
    {
        $abierta = Caja::abierta();
        if ($abierta === null) {
            throw new DomainException('No hay una caja abierta para cerrar.');
        }
        if ($quienCierraRol !== 'administrador' && (int) $abierta['cajero_id'] !== $quienCierraId) {
            throw new DomainException('Solo puedes cerrar tu propia caja.');
        }

        Caja::cerrar((int) $abierta['id'], Reloj::sql());

        Bitacora::registrar('caja.cierre', 'cajas', (int) $abierta['id'], [
            'turno' => $abierta['turno'],
            'cajero' => $abierta['cajero'],
            'fondo_inicial' => number_format((float) $abierta['fondo_inicial'], 2, '.', ''),
        ]);
    }

    /**
     * Reabre una caja cerrada para que su turno se pueda volver a usar (solo administrador).
     *
     * Borra el conteo y el arqueo del intento anterior. Las ventas y los movimientos
     * del turno se conservan: siguen siendo de ese turno.
     */
    public function reabrir(int $id): void
    {
        $caja = Caja::buscar($id);
        if ($caja === null) {
            throw new DomainException('Esa caja no existe.');
        }
        if ($caja['estado'] !== 'cerrada') {
            throw new DomainException('Solo se puede reabrir una caja cerrada.');
        }
        if (Caja::abierta() !== null) {
            throw new DomainException('Ya hay una caja abierta: ciérrala antes de reabrir otra.');
        }

        DB::transaccion(static function () use ($id): void {
            DB::consulta('DELETE FROM arqueos WHERE caja_id = ?', [$id]);
            DB::consulta("UPDATE cajas SET estado = 'abierta', cerrada_en = NULL WHERE id = ?", [$id]);
        });

        Bitacora::registrar('caja.reabrir', 'cajas', $id, [
            'turno' => $caja['turno'],
            'cajero' => $caja['cajero'],
            'fondo_inicial' => number_format((float) $caja['fondo_inicial'], 2, '.', ''),
        ]);
    }

    /**
     * Oculta del historial todas las cajas cerradas. Solo el administrador.
     * No borra nada: arqueos, movimientos, ventas y facturas se conservan (RNF-20).
     */
    public function limpiarHistorial(): int
    {
        return Caja::archivarCerradas();
    }

    private function sinCajaAbierta(): void
    {
        $abierta = Caja::abierta();
        if ($abierta !== null) {
            $desde = substr($abierta['abierta_en'], 11, 5);
            $dia = substr($abierta['abierta_en'], 0, 10) === Reloj::ahora()->format('Y-m-d') ? 'hoy' : 'el ' . date('d/m', strtotime($abierta['abierta_en']));
            throw new DomainException(
                "Ya hay una caja abierta: turno de {$abierta['turno']}, de {$abierta['cajero']}, desde {$dia} a las {$desde}. "
                . 'Primero se debe cerrar y arquear esa caja.'
            );
        }
    }

    /** Cajeros activos a los que el administrador puede abrirles caja. */
    public static function cajeros(): array
    {
        return DB::filas("SELECT id, nombre FROM usuarios WHERE rol = 'cajero' AND activo = 1 ORDER BY nombre");
    }
}
