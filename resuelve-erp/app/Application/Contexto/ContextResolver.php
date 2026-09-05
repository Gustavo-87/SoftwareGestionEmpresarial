<?php

namespace App\Application\Contexto;

use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\Organizacion;
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

        $membresia = $usuario === null
            ? null
            : $this->resolveMembresiaVigente($usuario, $organizacion, $copropiedad);
        $roles = $membresia === null ? [] : $this->resolveRolesVigentes($membresia);
        $permisos = collect($roles)
            ->flatMap(fn ($rol) => $rol->permisos()
                ->where('permisos.estado', 'activo')
                ->where('permisos.ambito_aplicable', 'copropiedad')
                ->wherePivot('ambito_aplicable', 'copropiedad')
                ->get())
            ->unique('id')
            ->values()
            ->all();

        return new ContextoOperativo(
            $usuario,
            $organizacion,
            $copropiedad,
            $membresia,
            $roles,
            $permisos,
            (string) Str::uuid(),
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

    private function resolveRolesVigentes(MembresiaCopropiedad $membresia): array
    {
        return $membresia->roles()
            ->where('roles.estado', 'activo')
            ->where('roles.ambito_aplicable', 'copropiedad')
            ->wherePivot('ambito_rol', 'copropiedad')
            ->wherePivot('estado', 'activa')
            ->wherePivot('vigente_desde', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('membresia_copropiedad_rol.vigente_hasta')
                    ->orWhere('membresia_copropiedad_rol.vigente_hasta', '>', now());
            })
            ->get()
            ->all();
    }
}
