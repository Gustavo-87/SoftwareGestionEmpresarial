<?php

namespace App\Application\Identidad;

use App\Application\Contexto\ContextResolver;
use App\Models\MembresiaCopropiedad;
use App\Models\Rol;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SincronizarIdentidadContextualUsuario
{
    public function __construct(private readonly ContextResolver $contextResolver) {}

    public function crearUsuario(array $atributos): User
    {
        return DB::transaction(function () use ($atributos): User {
            $usuario = User::create($atributos);
            $this->sincronizar($usuario);

            return $usuario;
        }, 3);
    }

    public function actualizarUsuario(User $usuario, array $atributos): User
    {
        return DB::transaction(function () use ($usuario, $atributos): User {
            $usuarioBloqueado = User::query()->whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            $usuarioBloqueado->update($atributos);
            $this->sincronizar($usuarioBloqueado);

            return $usuarioBloqueado;
        }, 3);
    }

    private function sincronizar(User $usuario): void
    {
        $siteSetting = SiteSetting::current();

        if (! $siteSetting->exists
            || $siteSetting->organizacion_id === null
            || $siteSetting->copropiedad_id === null) {
            throw new RuntimeException(
                'No es posible sincronizar la identidad: falta el contexto inicial de Resuelve.'
            );
        }

        $contexto = $this->contextResolver->resolverExplicito(
            $siteSetting->organizacion_id,
            $siteSetting->copropiedad_id
        );
        $rol = Rol::query()
            ->where('clave', $usuario->role)
            ->where('ambito_aplicable', 'copropiedad')
            ->where('estado', 'activo')
            ->lockForUpdate()
            ->first();

        if (! $rol) {
            throw new RuntimeException(
                "No existe un Rol contextual activo para users.role={$usuario->role}."
            );
        }

        $membresia = MembresiaCopropiedad::query()
            ->where('usuario_id', $usuario->id)
            ->where('copropiedad_id', $contexto->copropiedad->id)
            ->lockForUpdate()
            ->first();

        if ($membresia && $membresia->organizacion_id !== $contexto->organizacion->id) {
            throw new RuntimeException('La Membresía existente tiene un contexto institucional inconsistente.');
        }

        if (! $membresia) {
            $membresia = MembresiaCopropiedad::create([
                'usuario_id' => $usuario->id,
                'organizacion_id' => $contexto->organizacion->id,
                'copropiedad_id' => $contexto->copropiedad->id,
                'estado' => 'activa',
                'vigente_desde' => now(),
            ]);
        } elseif ($membresia->estado !== 'activa'
            || $membresia->vigente_hasta !== null
            || $membresia->vigente_desde->isFuture()) {
            $membresia->update([
                'estado' => 'activa',
                'vigente_desde' => now(),
                'vigente_hasta' => null,
                'motivo_terminacion' => null,
            ]);
        }

        $asignacionesActivas = DB::table('membresia_copropiedad_rol')
            ->where('membresia_copropiedad_id', $membresia->id)
            ->where('estado', 'activa')
            ->whereNull('vigente_hasta')
            ->lockForUpdate()
            ->get();
        $equivalente = $asignacionesActivas->first(
            fn (object $asignacion) => (int) $asignacion->rol_id === $rol->id
        );

        DB::table('membresia_copropiedad_rol')
            ->whereIn('id', $asignacionesActivas
                ->reject(fn (object $asignacion) => (int) $asignacion->rol_id === $rol->id)
                ->pluck('id'))
            ->update([
                'estado' => 'terminada',
                'vigente_hasta' => now(),
                'updated_at' => now(),
            ]);

        if ($equivalente) {
            return;
        }

        DB::table('membresia_copropiedad_rol')->insert([
            'membresia_copropiedad_id' => $membresia->id,
            'rol_id' => $rol->id,
            'organizacion_id' => $contexto->organizacion->id,
            'copropiedad_id' => $contexto->copropiedad->id,
            'ambito_rol' => 'copropiedad',
            'estado' => 'activa',
            'vigente_desde' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
