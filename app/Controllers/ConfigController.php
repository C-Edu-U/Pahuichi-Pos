<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorHttp;
use App\Core\Peticion;
use App\Core\Reloj;
use App\Core\Respuesta;
use App\Models\Bitacora;
use App\Models\Negocio;
use App\Models\Leyenda;
use App\Services\LeyendaService;
use App\Models\ParametrosFiscales;
use App\Models\Turno;
use App\Services\Imagenes;
use DomainException;
use Throwable;

/** Configuración: negocio (HU-08), parámetros fiscales (HU-09) y turnos (HU-11). Solo el administrador. */
final class ConfigController extends Controller
{
    private const LOGO_MAX = 1048576;

    public function index(Peticion $peticion): Respuesta
    {
        return $this->pantalla();
    }

    public function negocio(Peticion $peticion): Respuesta
    {
        $datos = [];
        foreach (Negocio::CAMPOS as $campo) {
            $datos[$campo] = (string) $peticion->entrada($campo);
        }
        $datos['nit'] = preg_replace('/\s+/', '', $datos['nit']);

        $errores = $this->validarNegocio($datos);
        $logo = $peticion->archivo('logo');
        $nuevoLogo = null;
        if (!$errores && $logo !== null) {
            try {
                $nuevoLogo = Imagenes::guardar($logo, 'logo', self::LOGO_MAX);
            } catch (DomainException $e) {
                $errores['n-logo'] = $e->getMessage();
            }
        }
        if ($errores) {
            return $this->pantalla(['errores' => $errores, 'negocio' => $datos + Negocio::obtener()], 422);
        }

        $antes = Negocio::obtener();
        Negocio::guardar($datos);
        if ($nuevoLogo !== null || $peticion->marcado('quitar_logo')) {
            Negocio::cambiarLogo($nuevoLogo);
            Imagenes::borrar($antes['logo']);
        }
        Bitacora::registrar('config.negocio', 'negocio', 1, $this->cambios($antes, $datos));
        return $this->redirigirConAviso('/config#negocio', 'Datos del negocio guardados.');
    }

    /** Parámetros fiscales simulados (HU-09). */
    public function fiscal(Peticion $peticion): Respuesta
    {
        $d = [
            'codigo_sucursal' => (string) $peticion->entrada('codigo_sucursal'),
            'codigo_punto_venta' => (string) $peticion->entrada('codigo_punto_venta'),
            'actividad_economica' => (string) $peticion->entrada('actividad_economica'),
            'cuis' => strtoupper((string) $peticion->entrada('cuis')),
            'cufd' => (string) $peticion->entrada('cufd'),
            'cufd_codigo_control' => strtoupper((string) $peticion->entrada('cufd_codigo_control')),
            'cufd_vigencia' => (string) $peticion->entrada('cufd_vigencia'),
            'numero_inicial' => (string) $peticion->entrada('numero_inicial'),
            'modalidad' => (string) $peticion->entrada('modalidad'),
        ];

        $errores = [];
        foreach (['codigo_sucursal' => 'f-sucursal', 'codigo_punto_venta' => 'f-pv'] as $campo => $id) {
            if (!preg_match('/^\d{1,4}$/', $d[$campo])) {
                $errores[$id] = 'Escribe un número de 0 a 9999.';
            }
        }
        if ($d['actividad_economica'] === '' || mb_strlen($d['actividad_economica']) > 120) {
            $errores['f-actividad'] = 'Escribe el código y la descripción de la actividad (hasta 120 caracteres).';
        }
        if (!preg_match('/^[A-Z0-9]{4,20}$/', $d['cuis'])) {
            $errores['f-cuis'] = 'El CUIS tiene de 4 a 20 letras o números.';
        }
        if (!preg_match('/^[A-Za-z0-9+\/=]{8,120}$/', $d['cufd'])) {
            $errores['f-cufd'] = 'El CUFD tiene de 8 a 120 letras, números o los signos + / =.';
        }
        if (!preg_match('/^[A-F0-9]{8,40}$/', $d['cufd_codigo_control'])) {
            $errores['f-control'] = 'El código de control tiene de 8 a 40 caracteres hexadecimales (0-9 y A-F).';
        }
        $vigencia = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $d['cufd_vigencia']);
        if ($vigencia === false || $vigencia->format('Y-m-d\TH:i') !== $d['cufd_vigencia']) {
            $errores['f-vigencia'] = 'Elige la fecha y la hora de vigencia.';
        } else {
            $d['cufd_vigencia'] = $vigencia->format('Y-m-d H:i:00');
        }
        if (!preg_match('/^[1-9]\d{0,8}$/', $d['numero_inicial'])) {
            $errores['f-inicial'] = 'El número inicial es un entero desde 1.';
        }
        if (!isset(ParametrosFiscales::MODALIDADES[$d['modalidad']])) {
            $errores['f-modalidad'] = 'Elige una modalidad de la lista.';
        }
        if ($errores) {
            return $this->pantalla(['errores' => $errores, 'fiscal' => $d + ParametrosFiscales::obtener()], 422);
        }

        $antes = ParametrosFiscales::obtener();
        ParametrosFiscales::guardar($d);
        Bitacora::registrar('config.fiscal', 'parametros_fiscales', 1, $this->cambios($antes, $d));
        return $this->redirigirConAviso('/config#fiscal', 'Parámetros fiscales guardados.');
    }

    /** Horario de los turnos (HU-11). 23:59 en el fin significa hasta medianoche (24:00). */
    public function turnos(Peticion $peticion): Respuesta
    {
        $accion = (string) $peticion->entrada('accion');
        $turnos = Turno::todos();
        $errores = [];
        $nuevos = [];
        $eliminar = is_array($peticion->datos['turnos_eliminar'] ?? null) ? $peticion->datos['turnos_eliminar'] : [];

        // Detectar acción por qué campos trae el POST (no hay input oculto de accion).
        if ($eliminar !== []) {
            $accion = 'eliminar';
        } elseif ($peticion->entrada('nueva_clave') !== null) {
            $accion = 'agregar';
        } else {
            $accion = 'editar';
        }

        // ---------- AGREGAR turno ----------
        if ($accion === 'agregar') {
            $clave = trim((string) $peticion->entrada('nueva_clave'));
            $nombre = trim((string) $peticion->entrada('nuevo_nombre'));
            $inicio = trim((string) $peticion->entrada('nuevo_inicio'));
            $fin = trim((string) $peticion->entrada('nuevo_fin'));
            $ordenTexto = trim((string) $peticion->entrada('nuevo_orden'));

            if ($clave === '' || !preg_match('/^[a-z0-9_]{3,20}$/', $clave)) {
                $errores['nueva_clave'] = 'La clave tiene de 3 a 20 letras minúsculas, números o guion bajo.';
            } elseif (DB::valor('SELECT id FROM turnos WHERE clave = ?', [$clave]) !== null) {
                $errores['nueva_clave'] = 'Esa clave de turno ya existe.';
            }
            if ($nombre === '' || mb_strlen($nombre) > 30) {
                $errores['nuevo_nombre'] = 'El nombre tiene de 1 a 30 caracteres.';
            }
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $inicio)) {
                $errores['nuevo_inicio'] = 'Elige la hora de inicio.';
            }
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $fin)) {
                $errores['nuevo_fin'] = 'Elige la hora de fin.';
            }
            if (!preg_match('/^[1-9]\d*$/', $ordenTexto)) {
                $errores['nuevo_orden'] = 'El orden es un número desde 1.';
            }
            if (!$errores && $inicio >= $fin) {
                $errores['nuevo_fin'] = 'Debe terminar después de empezar.';
            }
            if (!$errores) {
                $finDb = $fin === '23:59' ? '24:00:00' : "{$fin}:00";
                $inicioDb = "{$inicio}:00";
                // No se cruza con los existentes
                foreach ($turnos as $t) {
                    if ($inicioDb < $t['hora_fin'] && $t['hora_inicio'] < $finDb) {
                        $errores['nuevo_inicio'] = "Se cruza con el turno de {$t['nombre']}.";
                        break;
                $anterior = $t;
            }
                }
            }
            if (!$errores) {
                try {
                    Turno::crear($clave, $nombre, $inicioDb, $finDb, (int) $ordenTexto);
                    Bitacora::registrar('config.turnos.agregar', 'turnos', null, [
                        'clave' => $clave,
                        'nombre' => $nombre,
                        'horario' => Turno::horario(['hora_inicio' => $inicioDb, 'hora_fin' => $finDb]),
                    ]);
                    return $this->redirigirConAviso('/config#turnos', 'Turno agregado.');
                } catch (Throwable $e) {
                    $errores['nuevo_general'] = 'No se pudo agregar el turno.';
                }
            }
        }

        // ---------- ELIMINAR turnos ----------
        if ($accion === 'eliminar' && $eliminar !== []) {
            $quedan = count(Turno::activos()) - count(array_filter($eliminar, static fn ($id): bool => Turno::buscar((int) $id) !== null));
            foreach ($eliminar as $idRaw) {
                $id = (int) $idRaw;
                $t = Turno::buscar($id);
                if ($t === null) {
                    continue;
                }
                if ($quedan <= 0) {
                    $errores["e{$id}"] = 'No se puede eliminar el último turno.';
                    continue;
                }
                if (Turno::tieneCajasHoy($id)) {
                    $errores["e{$id}"] = "El turno de {$t['nombre']} ya tiene cajas hoy y no se puede eliminar.";
                    continue;
                }
                if (Turno::estaEnCurso($id)) {
                    $errores["e{$id}"] = "El turno de {$t['nombre']} está en curso y no se puede eliminar.";
                    continue;
                }
                Turno::eliminar($id);
                $quedan--;
                Bitacora::registrar('config.turnos.eliminar', 'turnos', $id, ['nombre' => $t['nombre']]);
            }
            if (!$errores) {
                return $this->redirigirConAviso('/config#turnos', 'Turno(s) eliminado(s).');
            }
        }

        // ---------- EDITAR horarios ----------
        $enviados = is_array($peticion->datos['turnos'] ?? null) ? $peticion->datos['turnos'] : [];
        $cambios = [];
        $nuevos = [];

        foreach ($turnos as $t) {
            $id = (int) $t['id'];
            $enviado = $enviados[$id] ?? [];
            $inicio = trim((string) ($enviado['inicio'] ?? ''));
            $fin = trim((string) ($enviado['fin'] ?? ''));
            $nuevos[$id] = ['inicio' => '', 'fin' => '', 'nombre' => $t['nombre'], 'clave' => $t['clave']];

            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $inicio)) {
                $errores["t{$id}-ini"] = 'Elige la hora de inicio.';
            }
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $fin)) {
                $errores["t{$id}-fin"] = 'Elige la hora de fin.';
            }
            if (isset($errores["t{$id}-ini"]) || isset($errores["t{$id}-fin"])) {
                continue;
            }
            $finDb = $fin === '23:59' ? '24:00:00' : "{$fin}:00";
            $inicioDb = "{$inicio}:00";
            if ($inicio >= $fin) {
                $errores["t{$id}-fin"] = 'Debe terminar después de empezar.';
                continue;
            }
            $nuevos[$id]['inicio'] = $inicioDb;
            $nuevos[$id]['fin'] = $finDb;
        }

        // Los turnos no se cruzan: cada uno empieza cuando el anterior ya terminó
        if (!$errores) {
            $orden = $nuevos;
            uasort($orden, static fn (array $a, array $b): int => strcmp($a['inicio'], $b['inicio']));
            $anterior = null;
            foreach ($orden as $idTurno => $tOrden) {
                if ($anterior !== null && $tOrden['inicio'] < $anterior['fin']) {
                    $errores["t{$idTurno}-ini"] = "Se cruza con el turno de {$anterior['nombre']}, que termina a las " . substr($anterior['fin'], 0, 5) . '.';
                }
                $anterior = $tOrden;
            }
        }

        if ($errores) {
            $mostrar = array_map(static function (array $t) use ($nuevos): array {
                $n = $nuevos[(int) $t['id']];
                return ['hora_inicio' => $n['inicio'], 'hora_fin' => $n['fin']] + $t;
            }, $turnos);
            return $this->pantalla(['errores' => $errores, 'turnos' => $mostrar], 422);
        }

        DB::transaccion(function () use ($turnos, $nuevos, &$cambios): void {
            foreach ($turnos as $t) {
                $n = $nuevos[(int) $t['id']];
                if ($n['inicio'] !== $t['hora_inicio'] || $n['fin'] !== $t['hora_fin']) {
                    Turno::cambiarHorario((int) $t['id'], $n['inicio'], $n['fin']);
                    $cambios[$t['clave']] = ['antes' => Turno::horario($t), 'despues' => Turno::horario(['hora_inicio' => $n['inicio'], 'hora_fin' => $n['fin']])];
                }
            }
        });
        if ($cambios) {
            Bitacora::registrar('config.turnos', 'turnos', null, $cambios);
        }
        return $this->redirigirConAviso('/config#turnos', 'Turnos guardados.');
    }

    /** Logo del negocio (también lo usarán la factura y la pantalla del cliente). */
    public function logo(Peticion $peticion): Respuesta
    {
        $nombre = Negocio::obtener()['logo'] ?? null;
        $ruta = $nombre !== null ? Imagenes::ruta($nombre) : null;
        if ($ruta === null) {
            throw ErrorHttp::noEncontrado();
        }
        return new Respuesta((string) file_get_contents($ruta), 200, [
            'Content-Type' => Imagenes::tipo($nombre),
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function validarNegocio(array $d): array
    {
        $errores = [];
        $requeridos = [
            'razon_social' => ['n-razon', 'Escribe la razón social.', 120],
            'nombre_comercial' => ['n-comercial', 'Escribe el nombre comercial.', 80],
            'direccion' => ['n-direccion', 'Escribe la dirección.', 160],
            'municipio' => ['n-municipio', 'Escribe el municipio.', 60],
        ];
        foreach ($requeridos as $campo => [$id, $mensaje, $max]) {
            if ($d[$campo] === '') {
                $errores[$id] = $mensaje;
            } elseif (mb_strlen($d[$campo]) > $max) {
                $errores[$id] = "Usa hasta {$max} caracteres.";
            }
        }
        if (!preg_match('/^\d{7,13}$/', $d['nit'])) {
            $errores['n-nit'] = 'El NIT tiene solo números, entre 7 y 13 dígitos.';
        }
        if ($d['telefono'] !== '' && !preg_match('/^[0-9 +()-]{6,20}$/', $d['telefono'])) {
            $errores['n-telefono'] = 'Usa solo números, espacios y + ( ) -.';
        }
        return $errores;
    }

    /** Campos que cambiaron, con su valor anterior y el nuevo (para la bitácora). */
    private function cambios(array $antes, array $despues): array
    {
        $cambios = [];
        foreach ($despues as $campo => $valor) {
            if ((string) ($antes[$campo] ?? '') !== (string) $valor) {
                $cambios[$campo] = ['antes' => $antes[$campo] ?? null, 'despues' => $valor];
            }
        }
        return $cambios;
    }

    public function agregarLeyenda(Peticion $peticion): Respuesta
    {
        $entrada = $peticion->entrada('texto');
        $texto = is_string($entrada) ? $entrada : '';
        try {
            LeyendaService::agregar($texto);
        } catch (DomainException $e) {
            return $this->pantalla(['errores' => ['l-texto' => $e->getMessage()], 'leyendaTexto' => $texto], 422);
        }
        return $this->redirigirConAviso('/config#leyendas', 'Leyenda agregada.');
    }

    public function estadoLeyenda(Peticion $peticion): Respuesta
    {
        try {
            if (!in_array($peticion->entrada('activo'), ['0', '1'], true)) {
                throw new DomainException('Estado de leyenda inválido.');
            }
            LeyendaService::estado((int) $peticion->parametro('id'), $peticion->entrada('activo') === '1');
        } catch (DomainException $e) {
            return $peticion->esperaJson()
                ? Respuesta::json(['error' => $e->getMessage()], 422)
                : $this->pantalla(['errorLeyendas' => $e->getMessage()], 422);
        }
        return $peticion->esperaJson()
            ? Respuesta::json(['aviso' => 'Estado de la leyenda guardado.'])
            : $this->redirigirConAviso('/config#leyendas', 'Estado de la leyenda guardado.');
    }

    private function pantalla(array $datos = [], int $estado = 200): Respuesta
    {
        return $this->vista('config/index', $datos + [
            'titulo' => 'Configuración',
            'modulo' => 'config',
            'h1' => 'Configuración',
            'css' => ['modulos.css'],
            'js' => ['config.js', 'leyendas.js'],
            'errores' => [],
            'negocio' => Negocio::obtener(),
            'fiscal' => ParametrosFiscales::obtener(),
            'turnos' => Turno::todos(),
            'leyendas' => Leyenda::todas(),
        ], $estado);
    }
}
