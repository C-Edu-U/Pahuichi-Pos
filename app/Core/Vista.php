<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Plantillas PHP de app/Views. La vista se dibuja primero y su HTML llega al
 * layout como $contenido; las variables de la vista (titulo, modulo, js...)
 * también llegan al layout.
 */
final class Vista
{
    public static function render(string $vista, array $datos = [], ?string $layout = 'layouts/panel'): string
    {
        $contenido = self::incluir($vista, $datos);
        if ($layout === null) {
            return $contenido;
        }
        return self::incluir($layout, $datos + ['contenido' => $contenido]);
    }

    private static function incluir(string $vista, array $datos): string
    {
        $archivo = App::raiz() . "/app/Views/{$vista}.php";
        if (!is_file($archivo)) {
            throw new RuntimeException("No existe la vista {$vista}");
        }
        extract($datos, EXTR_SKIP);
        ob_start();
        try {
            require $archivo;
        } finally {
            $html = ob_get_clean();
        }
        return $html;
    }
}
