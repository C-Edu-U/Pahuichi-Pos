<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/** Arma el router, corre los middleware y el controlador, y convierte los errores en páginas. */
final class App
{
    /** Middleware que las rutas nombran con un alias (con parámetro: 'permiso:modulo.caja'). */
    private const MIDDLEWARE = [
        'csrf' => \App\Middleware\VerificarCsrf::class,
        'auth' => \App\Middleware\Autenticado::class,
        'invitado' => \App\Middleware\Invitado::class,
        'permiso' => \App\Middleware\Permiso::class,
    ];

    /** Middleware de todas las rutas. */
    private const GLOBALES = ['csrf'];

    private static ?Peticion $actual = null;

    public function __construct(private readonly Router $router)
    {
    }

    public static function raiz(): string
    {
        return dirname(__DIR__, 2);
    }

    /** Petición en curso (la usa la bitácora para guardar la IP). */
    public static function peticion(): ?Peticion
    {
        return self::$actual;
    }

    public static function preparar(): void
    {
        date_default_timezone_set(Config::get('app.zona_horaria'));
        // Cualquier aviso de PHP es un error: el sistema debe correr sin avisos
        set_error_handler(static function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
            if (!(error_reporting() & $nivel)) {
                return false;
            }
            throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
        });
    }

    public static function crear(): self
    {
        $router = new Router();
        (static function (Router $router): void {
            require self::raiz() . '/app/rutas.php';
        })($router);
        return new self($router);
    }

    public function manejar(Peticion $peticion): Respuesta
    {
        self::$actual = $peticion;
        Auth::olvidar();
        try {
            [$estado, $accion, $middleware, $parametros] = $this->router->resolver($peticion->metodo, $peticion->ruta);
            if ($estado !== 200) {
                throw new ErrorHttp($estado);
            }
            $peticion->parametros = $parametros;

            $destino = static function (Peticion $p) use ($accion): Respuesta {
                [$clase, $metodo] = $accion;
                return (new $clase())->$metodo($p);
            };

            $cadena = array_reverse([...self::GLOBALES, ...$middleware]);
            foreach ($cadena as $alias) {
                $siguiente = $destino;
                $destino = static fn (Peticion $p): Respuesta => self::middleware($alias)->manejar($p, $siguiente);
            }

            return $destino($peticion);
        } catch (ErrorHttp $e) {
            return self::paginaError($e->estado, $e->getMessage());
        } catch (Throwable $e) {
            error_log('Pahuichi: ' . $e::class . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            $detalle = Config::get('app.entorno') === 'desarrollo' ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : '';
            return self::paginaError(500, '', $detalle);
        }
    }

    private static function middleware(string $alias): Middleware
    {
        [$nombre, $argumento] = array_pad(explode(':', $alias, 2), 2, null);
        $clase = self::MIDDLEWARE[$nombre] ?? throw new \LogicException("Middleware desconocido: {$nombre}");
        return $argumento === null ? new $clase() : new $clase($argumento);
    }

    public static function paginaError(int $estado, string $mensaje = '', string $detalle = ''): Respuesta
    {
        $textos = [
            404 => ['No encontramos esta página', 'La dirección no existe o cambió.'],
            405 => ['Acción no permitida', 'Esta dirección no acepta esa acción.'],
            419 => ['La página venció', 'Por seguridad, el formulario dejó de valer. Vuelve atrás, recarga la página e inténtalo de nuevo.'],
            500 => ['Algo falló de nuestro lado', 'No se guardó nada a medias. Inténtalo de nuevo; si sigue fallando, avisa al administrador.'],
        ];
        [$titulo, $texto] = $textos[$estado] ?? $textos[500];
        $html = Vista::render('errores/error', [
            'titulo' => $titulo,
            'texto' => $mensaje !== '' ? $mensaje : $texto,
            'estado' => $estado,
            'detalle' => $detalle,
        ], 'layouts/simple');
        return Respuesta::html($html, $estado === 419 ? 403 : $estado);
    }
}
