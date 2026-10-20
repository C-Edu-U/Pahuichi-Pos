<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorHttp;
use App\Core\Peticion;
use App\Core\Reloj;
use App\Core\Respuesta;
use App\Core\Sesion;
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
        // La acción se deduce del botón enviado: el formulario no lleva un input oculto de acción.
        // Cada rama es excluyente: un error al agregar no borra los horarios ya escritos.
        if (isset($peticion->datos['eliminar'])) {
            return $this->eliminarTurno((int) $peticion->datos['eliminar']);
        }
        if (isset($peticion->datos['agregar'])) {
            return $this->agregarTurno($peticion);
        }
        return $this->editarTurnos($peticion);
    }

    /** Agrega un turno nuevo (HU-11). La clave se arma sola con el nombre. */
    private function agregarTurno(Peticion $peticion): Respuesta
    {
        $nombre = trim((string) $peticion->entrada('nuevo_nombre'));
        $inicio = trim((string) $peticion->entrada('nuevo_inicio'));
        $fin = trim((string) $peticion->entrada('nuevo_fin'));
        $ordenTexto = trim((string) $peticion->entrada('nuevo_orden'));
        $viejo = [
            'nuevo_nombre' => $nombre,
            'nuevo_inicio' => $inicio,
            'nuevo_fin' => $fin,
            'nuevo_orden' => $ordenTexto,
        ];

        $errores = [];
        if ($nombre === '' || mb_strlen($nombre) > 30) {
            $errores['nuevo_nombre'] = 'El nombre tiene de 1 a 30 caracteres.';
        } elseif (DB::valor('SELECT id FROM turnos WHERE nombre = ?', [$nombre]) !== null) {
            $errores['nuevo_nombre'] = 'Ese nombre de turno ya existe.';
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $inicio)) {
            $errores['nuevo_inicio'] = 'Elige la hora de inicio.';
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $fin)) {
            $errores['nuevo_fin'] = 'Elige la hora de fin.';
        }
        if (!isset($errores['nuevo_inicio'], $errores['nuevo_fin']) && $fin <= $inicio) {
            $errores['nuevo_fin'] = 'Debe terminar después de empezar.';
        }
        if (!preg_match('/^[1-9]\d*$/', $ordenTexto)) {
            $errores['nuevo_orden'] = 'El orden es un número desde 1.';
        }

        $inicioDb = "{$inicio}:00";
        $finDb = $fin === '23:59' ? '24:00:00' : "{$fin}:00";
        // El turno nuevo no puede cruzarse con los que ya existen
        if (!isset($errores['nuevo_inicio'], $errores['nuevo_fin'])) {
            foreach (Turno::todos() as $t) {
                if ($inicioDb < $t['hora_fin'] && $t['hora_inicio'] < $finDb) {
                    $errores['nuevo_inicio'] = "Se cruza con el turno de {$t['nombre']}, que va de " . Turno::hora($t['hora_inicio']) . ' a ' . Turno::hora($t['hora_fin']) . '.';
                    break;
                }
            }
        }
        if ($errores) {
            Sesion::flash('aviso', (string) reset($errores));
            return $this->pantalla(['errores' => $errores, 'viejo' => $viejo], 422);
        }

        $clave = self::claveDe($nombre);
        try {
            $nuevoId = Turno::crear($clave, $nombre, $inicioDb, $finDb, (int) $ordenTexto);
        } catch (Throwable $e) {
            Sesion::flash('aviso', 'No se pudo agregar el turno.');
            return $this->pantalla(['errores' => ['nuevo_general' => 'No se pudo agregar el turno.'], 'viejo' => $viejo], 422);
        }
        Bitacora::registrar('config.turnos.agregar', 'turnos', $nuevoId, [
            'clave' => $clave,
            'nombre' => $nombre,
            'horario' => Turno::horario(['hora_inicio' => $inicioDb, 'hora_fin' => $finDb]),
        ], null, $nombre);
        return $this->redirigirConAviso('/config#turnos', 'Turno agregado.');
    }

    /** Elimina un turno (HU-11). */
    private function eliminarTurno(int $id): Respuesta
    {
        $t = $id > 0 ? Turno::buscar($id) : null;
        if ($t === null) {
            return $this->rechazarTurno(['eliminar_general' => 'Elige el turno que quieres eliminar.']);
        }
        if (count(Turno::activos()) <= 1) {
            return $this->rechazarTurno(["e{$id}" => 'No se puede eliminar el único turno que queda.']);
        }
        if (Turno::tieneCajasHoy($id)) {
            return $this->rechazarTurno(["e{$id}" => "El turno de {$t['nombre']} ya tiene cajas hoy y no se puede eliminar."]);
        }
        if (Turno::tieneCajas($id)) {
            return $this->rechazarTurno(["e{$id}" => "El turno de {$t['nombre']} ya tiene cajas registradas y no se puede eliminar."]);
        }

        Turno::eliminar($id);
        Bitacora::registrar('config.turnos.eliminar', 'turnos', $id, [
            'nombre' => $t['nombre'],
            'horario' => Turno::horario($t),
        ], null, $t['nombre']);
        return $this->redirigirConAviso('/config#turnos', 'Turno eliminado.');
    }

    /**
     * Vuelve a la pantalla con el error y un aviso flotante: el motivo se ve
     * sin tener que buscarlo bajo el campo.
     *
     * @param array<string, string> $errores
     */
    private function rechazarTurno(array $errores): Respuesta
    {
        Sesion::flash('aviso', (string) reset($errores));
        return $this->pantalla(['errores' => $errores], 422);
    }

    /**
     * Clave interna del turno, armada con el nombre: sin acentos, sin espacios y
     * única. El administrador no la escribe; el sistema la pone.
     */
    private static function claveDe(string $nombre): string
    {
        $texto = strtr(mb_strtolower($nombre), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '_', $texto), '_');
        if ($base === '') {
            $base = 'turno';
        }
        $base = substr($base, 0, 18);
        $clave = $base;
        $n = 1;
        while (DB::valor('SELECT id FROM turnos WHERE clave = ?', [$clave]) !== null) {
            $n++;
            $clave = $base . $n;
        }
        return $clave;
    }

    /** Cambia el horario de los turnos (HU-11). */
    private function editarTurnos(Peticion $peticion): Respuesta
    {
        $turnos = Turno::todos();
        $enviados = is_array($peticion->datos['turnos'] ?? null) ? $peticion->datos['turnos'] : [];
        $errores = [];
        $nuevos = [];

        foreach ($turnos as $t) {
            $id = (int) $t['id'];
            $enviado = is_array($enviados[$id] ?? null) ? $enviados[$id] : [];
            // El nombre viene en el campo de la ficha; si no viene, se deja el que ya tiene
            $nombre = array_key_exists('nombre', $enviado)
                ? trim((string) $enviado['nombre'])
                : $t['nombre'];
            $inicio = trim((string) ($enviado['inicio'] ?? ''));
            $fin = trim((string) ($enviado['fin'] ?? ''));
            $nuevos[$id] = [
                'nombre' => $nombre,
                'inicio' => $inicio,
                'fin' => $fin,
                'inicio_db' => '',
                'fin_db' => '',
                'clave' => $t['clave'],
            ];

            if ($nombre === '' || mb_strlen($nombre) > 30) {
                $errores["t{$id}-nombre"] = 'El nombre tiene de 1 a 30 caracteres.';
            }
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $inicio)) {
                $errores["t{$id}-ini"] = 'Elige la hora de inicio.';
            }
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $fin)) {
                $errores["t{$id}-fin"] = 'Elige la hora de fin.';
            }
            if (!isset($errores["t{$id}-ini"], $errores["t{$id}-fin"]) && $fin <= $inicio) {
                $errores["t{$id}-fin"] = 'Debe terminar después de empezar.';
            }
            if (isset($errores["t{$id}-ini"], $errores["t{$id}-fin"])) {
                continue;
            }

            $nuevos[$id]['inicio_db'] = "{$inicio}:00";
            $nuevos[$id]['fin_db'] = $fin === '23:59' ? '24:00:00' : "{$fin}:00";
        }

        // El nombre de un turno es único: dos fichas no pueden llamarse igual
        $vistos = [];
        foreach ($nuevos as $id => $nuevo) {
            $clave = mb_strtolower($nuevo['nombre']);
            if ($clave === '') {
                continue;
            }
            if (isset($vistos[$clave])) {
                $errores["t{$id}-nombre"] = 'Ese nombre de turno ya existe.';
            }
            $vistos[$clave] = $id;
        }

        // Los turnos no se cruzan: cada uno empieza cuando el anterior ya terminó
        if (!$errores) {
            $orden = $nuevos;
            uasort($orden, static fn (array $a, array $b): int => strcmp($a['inicio_db'], $b['inicio_db']));
            $anterior = null;
            foreach ($orden as $idTurno => $tOrden) {
                if ($anterior !== null && $tOrden['inicio_db'] < $anterior['fin_db']) {
                    $errores["t{$idTurno}-ini"] = "Se cruza con el turno de {$anterior['nombre']}, que termina a las " . substr($anterior['fin_db'], 0, 5) . '.';
                }
                $anterior = $tOrden;
            }
        }

        if ($errores) {
            Sesion::flash('aviso', (string) reset($errores));
            $mostrar = array_map(static function (array $t) use ($nuevos): array {
                $n = $nuevos[(int) $t['id']];
                return ['hora_inicio' => $n['inicio'], 'hora_fin' => $n['fin'], 'nombre' => $n['nombre']] + $t;
            }, $turnos);
            return $this->pantalla(['errores' => $errores, 'turnos' => $mostrar], 422);
        }

        $cambios = [];
        $renombres = [];
        DB::transaccion(function () use ($turnos, $nuevos, &$cambios, &$renombres): void {
            foreach ($turnos as $t) {
                $id = (int) $t['id'];
                $n = $nuevos[$id];
                if ($n['nombre'] !== $t['nombre']) {
                    Turno::cambiarNombre($id, $n['nombre']);
                    $renombres[$id] = $t;
                }
                if ($n['inicio_db'] !== $t['hora_inicio'] || $n['fin_db'] !== $t['hora_fin']) {
                    Turno::cambiarHorario($id, $n['inicio_db'], $n['fin_db']);
                    $cambios[$id] = $t;
                }
            }
        });
        foreach ($cambios as $id => $t) {
            $n = $nuevos[$id];
            Bitacora::registrar('config.turnos', 'turnos', $id, [
                'antes' => Turno::horario($t),
                'despues' => Turno::horario(['hora_inicio' => $n['inicio_db'], 'hora_fin' => $n['fin_db']]),
            ], null, $n['nombre']);
        }
        foreach ($renombres as $id => $t) {
            $n = $nuevos[$id];
            Bitacora::registrar('config.turnos.nombre', 'turnos', $id, [
                'nombre' => ['antes' => $t['nombre'], 'despues' => $n['nombre']],
            ], null, $n['nombre']);
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
            'viejo' => [],
            'ordenTurno' => (int) DB::valor('SELECT COALESCE(MAX(orden), 0) + 1 FROM turnos'),
            'negocio' => Negocio::obtener(),
            'fiscal' => ParametrosFiscales::obtener(),
            'turnos' => Turno::todos(),
            'leyendas' => Leyenda::todas(),
        ], $estado);
    }
}
