<?php

declare(strict_types=1);

// Funciones cortas para las vistas.

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;

/** ¿El rol (por defecto, el del usuario de la sesión) tiene este permiso? (config/permisos.php) */
function puede(string $permiso, ?string $rol = null): bool
{
    $rol ??= Auth::rol();
    return $rol !== null && in_array($rol, Config::get('permisos')[$permiso] ?? [], true);
}

/** Permisos del usuario de la sesión, para que el JS muestre u oculte botones. */
function permisos_actuales(): array
{
    $rol = Auth::rol();
    return $rol === null ? [] : array_keys(array_filter(
        Config::get('permisos'),
        static fn (array $roles): bool => in_array($rol, $roles, true),
    ));
}

/** Escapa texto para HTML. Toda salida variable pasa por aquí. */
function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Monto en bolivianos con separadores de Bolivia: 1.286,50 */
function bs(float|int|string|null $monto): string
{
    return number_format((float) $monto, 2, ',', '.');
}

/** Ícono del sprite (assets/img/iconos.svg, incluido en el layout). */
function icono(string $nombre, string $clase = ''): string
{
    $clase = trim('icono ' . $clase);
    return '<svg class="' . e($clase) . '" aria-hidden="true"><use href="#i-' . e($nombre) . '"></use></svg>';
}

/** Campo oculto con el token CSRF; va en todo formulario POST. */
function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Atributos de un campo con error: aria-invalid y aria-describedby. */
function campo_error(array $errores, string $campo, string $ayuda = ''): string
{
    $describe = trim($ayuda . (isset($errores[$campo]) ? " {$campo}-error" : ''));
    $atributos = $describe !== '' ? ' aria-describedby="' . e($describe) . '"' : '';
    return $atributos . (isset($errores[$campo]) ? ' aria-invalid="true"' : '');
}

/** Mensaje de error debajo del campo, con ícono (vacío si no hay error). */
function mensaje_error(array $errores, string $campo): string
{
    if (!isset($errores[$campo])) {
        return '<p class="campo__error" id="' . e($campo) . '-error" hidden></p>';
    }
    return '<p class="campo__error" id="' . e($campo) . '-error">' . icono('error', 'icono--sm') . '<span>' . e($errores[$campo]) . '</span></p>';
}

/** Hora corta para tablas: «Hoy, 10:48», «Ayer, 23:48» o «08/10/2026». */
function fecha_corta(?string $momento): string
{
    if ($momento === null || $momento === '') {
        return 'Nunca';
    }
    $fecha = new DateTimeImmutable($momento);
    $hoy = App\Core\Reloj::ahora()->setTime(0, 0);
    $dia = $fecha->setTime(0, 0);
    $dias = (int) $hoy->diff($dia)->format('%r%a');
    return match ($dias) {
        0 => 'Hoy, ' . $fecha->format('H:i'),
        -1 => 'Ayer, ' . $fecha->format('H:i'),
        default => $fecha->format('d/m/Y'),
    };
}
