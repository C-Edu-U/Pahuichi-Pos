<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Reloj;
use App\Models\Arqueo;
use App\Models\Bitacora;
use DomainException;

/**
 * Conteo de efectivo por denominación (HU-23). Cierre ciego: el cajero escribe
 * cuántas piezas tiene de cada denominación y el sistema solo suma lo contado.
 * El efectivo esperado no se muestra ni se calcula en este paso.
 */
final class ConteoService
{
    /** Unidades por denominación: más que esto es un error de tipeo. */
    private const MAXIMO = 9999;

    /**
     * @param array<string, mixed> $conteo cantidad escrita por cada clave de denominación
     * @return float total contado
     * @throws DomainException si alguna cantidad no es un entero válido
     */
    public function registrar(int $cajaId, array $conteo, array $usuario): float
    {
        $limpio = [];
        foreach (Arqueo::DENOMINACIONES as $d) {
            $clave = $d['clave'];
            $escrito = $conteo[$clave] ?? 0;
            if (is_string($escrito)) {
                $escrito = trim($escrito);
            }
            if ($escrito === '' || $escrito === null) {
                $escrito = 0;
            }
            if (filter_var($escrito, FILTER_VALIDATE_INT) === false || (int) $escrito < 0) {
                throw new DomainException('Las cantidades deben ser números enteros, sin decimales ni negativos.');
            }
            if ((int) $escrito > self::MAXIMO) {
                throw new DomainException('Revisa las cantidades: cada una va de 0 a ' . self::MAXIMO . '.');
            }
            $limpio[$clave] = (int) $escrito;
        }

        $total = Arqueo::totalContado($limpio);

        Arqueo::guardar($cajaId, $limpio, $total, Reloj::sql());

        Bitacora::registrar('caja.conteo', 'cajas', $cajaId, [
            'total_contado' => number_format($total, 2, '.', ''),
        ]);

        return $total;
    }
}
