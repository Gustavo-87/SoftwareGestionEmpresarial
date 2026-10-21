<?php

namespace App\Application\Contexto;

use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\Organizacion;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

final class ContextResolver
{
    public function resolverParaHttp(Request $request): ContextoOperativo
    {
        $usuario = $request->user();

        if ($usuario !== null) {
            return $this->resolverParaUsuarioAutenticado($usuario, $request);
        }

        $siteSetting = SiteSetting::current();

        if (! $siteSetting->exists
            || $siteSetting->organizacion_id === null
            || $siteSetting->copropiedad_id === null) {
            throw new ContextoInstitucionalNoConfigurado(
                'El contexto institucional inicial no está configurado. '
                .'Ejecute php artisan resuelve:crear-contexto-inicial.'
            );
        }

        return $this->resolverExplicito(
            $siteSetting->organizacion_id,
            $siteSetting->copropiedad_id,
        );
    }

    private function resolverParaUsuarioAutenticado(User $usuario, Request $request): ContextoOperativo
    {
        $copropiedadIdSesion = $request->session()->get('copropiedad_activa_id');

        if ($copropiedadIdSesion !== null) {
            $membresia = $this->resolveMembresiaVigentePorId($usuario, (int) $copropiedadIdSesion);

            if ($membresia !== null) {
                return $this->resolverExplicito(
                    $membresia->organizacion_id,
                    $membresia->copropiedad_id,
                    $usuario->id,
                );
            }

            $request->session()->forget('copropiedad_activa_id');
        }

        $membresiaUnica = $this->resolveMembresiaUnica($usuario);

        if ($membresiaUnica !== null) {
            $request->session()->put('copropiedad_activa_id', $membresiaUnica->copropiedad_id);

            return $this->resolverExplicito(
                $membresiaUnica->organizacion_id,
                $membresiaUnica->copropiedad_id,
                $usuario->id,
            );
        }

        $siteSetting = SiteSetting::current();

        if (! $siteSetting->exists
            || $siteSetting->organizacion_id === null
            || $siteSetting->copropiedad_id === null) {
            throw new ContextoInstitucionalNoConfigurado(
                'El contexto institucional inicial no está configurado. '
                .'Ejecute php artisan resuelve:crear-contexto-inicial.'
            );
        }

        return $this->resolverExplicito(
            $siteSetting->organizacion_id,
            $siteSetting->copropiedad_id,
            $usuario->id,
        );
    }

    private function resolveMembresiaVigentePorId(User $usuario, int $copropiedadId): ?MembresiaCopropiedad
    {
        return MembresiaCopropiedad::query()
            ->where('usuario_id', $usuario->id)
            ->where('copropiedad_id', $copropiedadId)
            ->where('estado', 'activa')
            ->where('vigente_desde', '<=', now())
            ->where(fn ($query) => $query
                ->whereNull('vigente_hasta')
                ->orWhere('vigente_hasta', '>', now()))
            ->first();
    }

    private function resolveMembresiaUnica(User $usuario): ?MembresiaCopropiedad
    {
        return MembresiaCopropiedad::query()
            ->where('usuario_id', $usuario->id)
            ->where('estado', 'activa')
            ->where('vigente_desde', '<=', now())
            ->where(fn ($query) => $query
                ->whereNull('vigente_hasta')
                ->orWhere('vigente_hasta', '>', now()))
            ->first();
    }

    public function resolverExplicito(
        int $organizacionId,
        int $copropiedadId,
        ?int $usuarioId = null
    ): ContextoOperativo {
        $organizacion = Organizacion::query()->find($organizacionId)
            ?? throw new RuntimeException("La Organización {$organizacionId} no existe.");
        $copropiedad = Copropiedad::query()->find($copropiedadId)
            ?? throw new RuntimeException("La Copropiedad {$copropiedadId} no existe.");

        if ($copropiedad->organizacion_id !== $organizacion->id) {
            throw new RuntimeException(
                "La Copropiedad {$copropiedadId} no pertenece a la Organización {$organizacionId}."
            );
        }

        $usuario = $usuarioId === null
            ? null
            : User::query()->find($usuarioId)
                ?? throw new RuntimeException("El Usuario {$usuarioId} no existe.");

        // Equipo (team) de Spatie: la Copropiedad activa del contexto.
        setPermissionsTeamId($copropiedad->id);
        $usuario?->unsetRelation('roles')->unsetRelation('permissions');

        $membresia = $usuario === null
            ? null
            : $this->resolveMembresiaVigente($usuario, $organizacion, $copropiedad);

        // Fuente efectiva desde el Bloque 2B: RBAC de Spatie por Copropiedad.
        // La Membresía vigente sigue siendo requisito obligatorio: sin ella no
        // se resuelven roles ni permisos, aunque existan asignaciones en Spatie.
        // HU-USR-04: con Rol activo seleccionado los permisos efectivos son los
        // del Rol activo más los permisos directos; sin selección se conserva la
        // unión de los Roles asignados (compatibilidad por defecto). HU-USR-05:
        // los permisos directos de Spatie se suman en ambos casos. Un permiso
        // desactivado nunca concede acceso (HU-USR-07).
        $roles = [];
        $rolesAsignados = [];
        $permisos = [];
        $rolActivoClave = null;
        if ($membresia !== null && $usuario !== null) {
            $rolesSpatie = $usuario->roles()->with('permissions')->get();
            foreach ($rolesSpatie as $rolSpatie) {
                $rolesAsignados[] = new Rol([
                    'clave' => $rolSpatie->name,
                    'nombre' => $rolSpatie->name,
                    'ambito_aplicable' => 'copropiedad',
                    'estado' => 'activo',
                ]);
            }

            $rolActivo = null;
            if ($membresia->rol_activo_id !== null) {
                $rolActivo = $rolesSpatie->first(
                    fn ($rolSpatie) => (int) $rolSpatie->id === (int) $membresia->rol_activo_id
                );
                if ($rolActivo === null) {
                    // Revalidación de ADR-013: la selección caducó (el Rol ya no
                    // está asignado) y la Membresía recupera la unión de Roles.
                    $membresia->forceFill(['rol_activo_id' => null])->saveQuietly();
                }
            }

            foreach ($rolActivo !== null ? [$rolActivo] : $rolesSpatie as $rolSpatie) {
                $roles[] = new Rol([
                    'clave' => $rolSpatie->name,
                    'nombre' => $rolSpatie->name,
                    'ambito_aplicable' => 'copropiedad',
                    'estado' => 'activo',
                ]);

                foreach ($rolSpatie->permissions as $permisoSpatie) {
                    if (($permisoSpatie->estado ?? 'activo') !== 'activo') {
                        continue;
                    }
                    $clave = (string) $permisoSpatie->name;
                    if (isset($permisos[$clave])) {
                        continue;
                    }
                    [$modulo, $accion] = array_pad(explode('.', $clave, 2), 2, 'usar');
                    $permisos[$clave] = new Permiso([
                        'clave' => $clave,
                        'modulo' => $modulo,
                        'accion' => $accion,
                        'ambito_aplicable' => 'copropiedad',
                        'estado' => 'activo',
                    ]);
                }
            }

            if ($rolActivo !== null) {
                $rolActivoClave = $rolActivo->name;
            }

            // Permisos directos del Usuario (model_has_permissions, equipo =
            // Copropiedad activa). Solo conceden acceso los activos.
            foreach ($usuario->permissions()->where('permissions.estado', 'activo')->get() as $permisoSpatie) {
                $clave = (string) $permisoSpatie->name;
                if (isset($permisos[$clave])) {
                    continue;
                }
                [$modulo, $accion] = array_pad(explode('.', $clave, 2), 2, 'usar');
                $permisos[$clave] = new Permiso([
                    'clave' => $clave,
                    'modulo' => $modulo,
                    'accion' => $accion,
                    'ambito_aplicable' => 'copropiedad',
                    'estado' => 'activo',
                ]);
            }
            $permisos = array_values($permisos);
        }

        return new ContextoOperativo(
            $usuario,
            $organizacion,
            $copropiedad,
            $membresia,
            $roles,
            $permisos,
            (string) Str::uuid(),
            $rolActivoClave,
            $rolesAsignados,
        );
    }

    private function resolveMembresiaVigente(
        User $usuario,
        Organizacion $organizacion,
        Copropiedad $copropiedad
    ): ?MembresiaCopropiedad {
        return MembresiaCopropiedad::query()
            ->where('usuario_id', $usuario->id)
            ->where('organizacion_id', $organizacion->id)
            ->where('copropiedad_id', $copropiedad->id)
            ->where('estado', 'activa')
            ->where('vigente_desde', '<=', now())
            ->where(fn ($query) => $query
                ->whereNull('vigente_hasta')
                ->orWhere('vigente_hasta', '>', now()))
            ->first();
    }
}
