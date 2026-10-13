<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Sesion;
use App\Models\Usuario;
use App\Services\UsuarioService;
use DomainException;

/** Usuarios (HU-03). Solo el administrador (permiso modulo.usuarios). */
final class UsuarioController extends Controller
{
    public function index(Peticion $peticion): Respuesta
    {
        return $this->pantalla();
    }

    public function crear(Peticion $peticion): Respuesta
    {
        $nombre = (string) $peticion->entrada('nombre');
        $usuario = mb_strtolower((string) $peticion->entrada('usuario'));
        $rol = (string) $peticion->entrada('rol');

        $servicio = new UsuarioService();
        $errores = $servicio->validarAlta($nombre, $usuario, $rol);
        if ($errores) {
            return $this->pantalla(['errores' => $errores, 'viejo' => compact('nombre', 'usuario', 'rol')], 422);
        }

        [, $credenciales] = $servicio->crear($nombre, $usuario, $rol);
        return $this->conCredenciales("Usuario {$usuario} creado.", $nombre, $credenciales);
    }

    public function rol(Peticion $peticion): Respuesta
    {
        return $this->accion($peticion, function (UsuarioService $s, array $u) use ($peticion): array {
            $rol = (string) $peticion->entrada('rol');
            $credenciales = $s->cambiarRol((int) $u['id'], $rol);
            return ["{$u['nombre']} ahora es " . mb_strtolower(Usuario::ROLES[$rol]) . '.', $credenciales];
        });
    }

    public function estado(Peticion $peticion): Respuesta
    {
        return $this->accion($peticion, function (UsuarioService $s, array $u) use ($peticion): array {
            $activo = $peticion->entrada('activo') === '1';
            $s->cambiarEstado((int) $u['id'], $activo);
            return [$u['nombre'] . ($activo ? ' puede ingresar de nuevo.' : ' ya no puede ingresar.'), []];
        });
    }

    public function desbloquear(Peticion $peticion): Respuesta
    {
        return $this->accion($peticion, function (UsuarioService $s, array $u): array {
            $s->desbloquear((int) $u['id']);
            return ["{$u['nombre']} puede volver a ingresar.", []];
        });
    }

    public function resetearClave(Peticion $peticion): Respuesta
    {
        return $this->accion($peticion, fn (UsuarioService $s, array $u): array => [
            "Contraseña de {$u['nombre']} reseteada.",
            $s->resetearClave((int) $u['id']),
        ]);
    }

    public function resetearPin(Peticion $peticion): Respuesta
    {
        return $this->accion($peticion, fn (UsuarioService $s, array $u): array => [
            "PIN de {$u['nombre']} reseteado.",
            $s->resetearPin((int) $u['id']),
        ]);
    }

    /** Corre una acción sobre el usuario {id}; los errores de regla vuelven como aviso. */
    private function accion(Peticion $peticion, callable $fn): Respuesta
    {
        $u = Usuario::buscar((int) $peticion->parametro('id'));
        if ($u === null) {
            return $this->redirigirConAviso('/usuarios', 'Ese usuario no existe.');
        }
        try {
            [$aviso, $credenciales] = $fn(new UsuarioService(), $u);
        } catch (DomainException $e) {
            return $this->redirigirConAviso('/usuarios', $e->getMessage());
        }
        return $this->conCredenciales($aviso, $u['nombre'], $credenciales);
    }

    /** Las credenciales temporales se muestran una sola vez, en la página siguiente. */
    private function conCredenciales(string $aviso, string $nombre, array $credenciales): Respuesta
    {
        if ($credenciales) {
            Sesion::flash('credenciales', ['nombre' => $nombre, 'claves' => $credenciales]);
        }
        return $this->redirigirConAviso('/usuarios', $aviso);
    }

    private function pantalla(array $datos = [], int $estado = 200): Respuesta
    {
        $usuarios = DB::filas(
            "SELECT id, nombre, usuario, rol, activo, bloqueado_hasta, ultimo_ingreso, pin_hash IS NOT NULL AS tiene_pin
             FROM usuarios ORDER BY FIELD(rol, 'administrador', 'cajero', 'mesero', 'cocina'), nombre"
        );
        return $this->vista('usuarios/index', $datos + [
            'titulo' => 'Usuarios',
            'modulo' => 'usuarios',
            'h1' => 'Usuarios',
            'css' => ['modulos.css'],
            'js' => ['usuarios.js'],
            'usuarios' => $usuarios,
            'yo' => Auth::id(),
            'errores' => [],
            'viejo' => ['nombre' => '', 'usuario' => '', 'rol' => 'cajero'],
            'credenciales' => Sesion::tomarFlash('credenciales'),
        ], $estado);
    }
}
