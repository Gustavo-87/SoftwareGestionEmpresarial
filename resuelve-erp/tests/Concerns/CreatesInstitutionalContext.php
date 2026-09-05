<?php

namespace Tests\Concerns;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Contexto\ContextResolver;
use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\Organizacion;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\SiteSetting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

trait CreatesInstitutionalContext
{
    /** @return array{Organizacion, Copropiedad} */
    protected function createInstitutionalContext(): array
    {
        $organizacion = Organizacion::create([
            'nombre' => 'Organización inicial',
            'estado' => 'activa',
        ]);
        $copropiedad = Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Copropiedad inicial',
            'estado' => 'activa',
        ]);
        $settings = SiteSetting::create([
            'nombre_conjunto' => 'Copropiedad inicial',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 15,
        ]);
        $settings->organizacion()->associate($organizacion);
        $settings->copropiedad()->associate($copropiedad);
        $settings->save();

        return [$organizacion, $copropiedad];
    }

    /**
     * Crea una identidad contextual exacta para un Usuario de prueba.
     *
     * No infiere permisos desde users.role ni asigna permisos por defecto.
     *
     * @param  list<string>  $permisos
     * @return array{MembresiaCopropiedad, Rol, ContextoOperativo}
     */
    protected function createContextualIdentity(
        User $usuario,
        Organizacion $organizacion,
        Copropiedad $copropiedad,
        string $rol,
        array $permisos,
        string $estadoMembresia = 'activa',
        ?CarbonInterface $vigenteDesde = null,
        ?CarbonInterface $vigenteHasta = null,
    ): array {
        if ($copropiedad->organizacion_id !== $organizacion->id) {
            throw new InvalidArgumentException('La Copropiedad de prueba no pertenece a la Organización indicada.');
        }

        $rolContextual = Rol::query()->updateOrCreate(
            ['clave' => $rol],
            [
                'nombre' => ucfirst($rol),
                'ambito_aplicable' => 'copropiedad',
                'estado' => 'activo',
            ]
        );

        $permisosContextuales = collect($permisos)->unique()->mapWithKeys(function (string $clave): array {
            [$modulo, $accion] = array_pad(explode('.', $clave, 2), 2, 'usar');
            $permiso = Permiso::query()->updateOrCreate(
                ['clave' => $clave],
                [
                    'modulo' => $modulo,
                    'accion' => $accion,
                    'ambito_aplicable' => 'copropiedad',
                    'estado' => 'activo',
                ]
            );

            return [$permiso->id => ['ambito_aplicable' => 'copropiedad']];
        })->all();

        $rolContextual->permisos()->sync($permisosContextuales);

        $membresia = MembresiaCopropiedad::query()->updateOrCreate(
            [
                'usuario_id' => $usuario->id,
                'copropiedad_id' => $copropiedad->id,
            ],
            [
                'organizacion_id' => $organizacion->id,
                'estado' => $estadoMembresia,
                'vigente_desde' => $vigenteDesde ?? now()->subMinute(),
                'vigente_hasta' => $vigenteHasta,
            ]
        );

        DB::table('membresia_copropiedad_rol')
            ->where('membresia_copropiedad_id', $membresia->id)
            ->delete();
        DB::table('membresia_copropiedad_rol')->insert([
            'membresia_copropiedad_id' => $membresia->id,
            'rol_id' => $rolContextual->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'ambito_rol' => 'copropiedad',
            'estado' => 'activa',
            'vigente_desde' => $vigenteDesde ?? now()->subMinute(),
            'vigente_hasta' => $vigenteHasta,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contexto = app(ContextResolver::class)->resolverExplicito(
            $organizacion->id,
            $copropiedad->id,
            $usuario->id,
        );

        return [$membresia, $rolContextual, $contexto];
    }

    protected function actingAsContextual(User $usuario): static
    {
        $this->app->forgetInstance(ContextoOperativo::class);
        $this->actingAs($usuario);

        return $this;
    }
}
