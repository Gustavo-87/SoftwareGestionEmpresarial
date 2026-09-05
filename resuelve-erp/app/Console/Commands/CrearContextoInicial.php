<?php

namespace App\Console\Commands;

use App\Models\ConfiguracionCopropiedad;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\SiteSetting;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CrearContextoInicial extends Command
{
    protected $signature = 'resuelve:crear-contexto-inicial';

    protected $description = 'Crea o recupera el contexto inicial de Organización y Copropiedad';

    public function handle(): int
    {
        $current = SiteSetting::current();

        if (! $current->exists) {
            $this->error('No existe una fila persistida de site_settings para contextualizar.');

            return self::FAILURE;
        }

        try {
            $result = DB::transaction(function () use ($current): array {
                $siteSetting = SiteSetting::query()
                    ->whereKey($current->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $additionalRows = SiteSetting::query()
                    ->whereKeyNot($siteSetting->getKey())
                    ->count();
                $alreadyExisted = $siteSetting->organizacion_id !== null
                    && $siteSetting->copropiedad_id !== null;

                [$organizacion, $copropiedad, $actions] = $this->resolveContext($siteSetting);
                $this->assertInstitutionalIdentityMatches($siteSetting, $copropiedad);
                $configurationCreated = $this->ensureConfiguration(
                    $siteSetting,
                    $organizacion,
                    $copropiedad
                );

                if ($siteSetting->organizacion_id !== $organizacion->id
                    || $siteSetting->copropiedad_id !== $copropiedad->id) {
                    $siteSetting->organizacion()->associate($organizacion);
                    $siteSetting->copropiedad()->associate($copropiedad);
                    $siteSetting->save();
                    $actions[] = 'SiteSetting asociado con el contexto inicial.';
                }

                if ($configurationCreated) {
                    $actions[] = 'Configuración de Copropiedad creada.';
                }

                return compact(
                    'organizacion',
                    'copropiedad',
                    'actions',
                    'additionalRows',
                    'alreadyExisted'
                );
            }, 3);
        } catch (Throwable $exception) {
            $this->error('No fue posible crear el contexto inicial; se revirtieron los cambios.');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($result['additionalRows'] > 0) {
            $this->warn(
                "Se detectaron {$result['additionalRows']} filas adicionales de site_settings; "
                .'no fueron modificadas y requieren conciliación manual.'
            );
        }

        foreach ($result['actions'] as $action) {
            $this->line("- {$action}");
        }

        if ($result['alreadyExisted'] && $result['actions'] === []) {
            $this->info('El contexto inicial ya existía y no requirió cambios.');
        } elseif ($result['alreadyExisted']) {
            $this->info('El contexto inicial ya existía y fue completado de forma segura.');
        } else {
            $this->info('Contexto inicial creado o recuperado correctamente.');
        }

        $this->line("Organización: {$result['organizacion']->id}");
        $this->line("Copropiedad: {$result['copropiedad']->id}");

        return self::SUCCESS;
    }

    private function resolveContext(SiteSetting $siteSetting): array
    {
        $actions = [];
        $organizacion = $this->resolveReferencedOrganizacion($siteSetting);
        $copropiedad = $this->resolveReferencedCopropiedad($siteSetting);

        if ($organizacion && $copropiedad) {
            $this->assertSameContext($organizacion, $copropiedad);

            return [$organizacion, $copropiedad, $actions];
        }

        if ($copropiedad) {
            $organizacion = $copropiedad->organizacion;
            $actions[] = 'Organización recuperada desde la Copropiedad referenciada.';

            return [$organizacion, $copropiedad, $actions];
        }

        if ($organizacion) {
            $copropiedad = $this->findOrCreateCopropiedad($siteSetting, $organizacion, $actions);

            return [$organizacion, $copropiedad, $actions];
        }

        $copropiedad = $this->findUnambiguousCopropiedad($siteSetting);
        if ($copropiedad) {
            $actions[] = 'Contexto recuperado mediante la identidad institucional inequívoca.';

            return [$copropiedad->organizacion, $copropiedad, $actions];
        }

        $organizationName = "Organización inicial — {$siteSetting->nombre_conjunto}";
        if (Organizacion::query()->where('nombre', $organizationName)->exists()) {
            throw new RuntimeException(
                'Existe una posible Organización inicial sin referencia explícita; '
                .'se requiere conciliación manual.'
            );
        }

        $organizacion = Organizacion::create([
            'nombre' => $organizationName,
            'estado' => 'activa',
        ]);
        $actions[] = 'Organización inicial creada.';
        $copropiedad = $this->createCopropiedad($siteSetting, $organizacion);
        $actions[] = 'Copropiedad inicial creada.';

        return [$organizacion, $copropiedad, $actions];
    }

    private function resolveReferencedOrganizacion(SiteSetting $siteSetting): ?Organizacion
    {
        if ($siteSetting->organizacion_id === null) {
            return null;
        }

        return Organizacion::query()->find($siteSetting->organizacion_id)
            ?? throw new RuntimeException(
                "site_settings referencia la Organización inexistente {$siteSetting->organizacion_id}."
            );
    }

    private function resolveReferencedCopropiedad(SiteSetting $siteSetting): ?Copropiedad
    {
        if ($siteSetting->copropiedad_id === null) {
            return null;
        }

        return Copropiedad::query()->find($siteSetting->copropiedad_id)
            ?? throw new RuntimeException(
                "site_settings referencia la Copropiedad inexistente {$siteSetting->copropiedad_id}."
            );
    }

    private function findOrCreateCopropiedad(
        SiteSetting $siteSetting,
        Organizacion $organizacion,
        array &$actions
    ): Copropiedad {
        $query = Copropiedad::query()->where('organizacion_id', $organizacion->id);
        $matches = $this->scopeByStableIdentity($query, $siteSetting)->lockForUpdate()->get();

        if ($matches->count() > 1) {
            throw new RuntimeException(
                'Existen varias Copropiedades candidatas dentro de la Organización; '
                .'se requiere conciliación manual.'
            );
        }

        if ($matches->isNotEmpty()) {
            $actions[] = 'Copropiedad inicial recuperada dentro de la Organización referenciada.';

            return $matches->first();
        }

        $actions[] = 'Copropiedad inicial creada para la Organización referenciada.';

        return $this->createCopropiedad($siteSetting, $organizacion);
    }

    private function findUnambiguousCopropiedad(SiteSetting $siteSetting): ?Copropiedad
    {
        $matches = $this->scopeByStableIdentity(Copropiedad::query(), $siteSetting)
            ->lockForUpdate()
            ->get();

        if ($matches->count() > 1) {
            throw new RuntimeException(
                'La identidad institucional coincide con varias Copropiedades; '
                .'se requiere conciliación manual.'
            );
        }

        return $matches->first();
    }

    private function scopeByStableIdentity(Builder $query, SiteSetting $siteSetting): Builder
    {
        if ($siteSetting->nit !== null && trim($siteSetting->nit) !== '') {
            return $query->where('nit', $siteSetting->nit);
        }

        return $query
            ->where('nombre', $siteSetting->nombre_conjunto)
            ->where('representante_legal', $siteSetting->representante_legal)
            ->where('direccion', $siteSetting->direccion)
            ->where('ciudad', $siteSetting->ciudad)
            ->where('telefono', $siteSetting->telefono)
            ->where('email', $siteSetting->email);
    }

    private function createCopropiedad(
        SiteSetting $siteSetting,
        Organizacion $organizacion
    ): Copropiedad {
        return Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            ...$this->institutionalIdentity($siteSetting),
            'estado' => 'activa',
        ]);
    }

    private function ensureConfiguration(
        SiteSetting $siteSetting,
        Organizacion $organizacion,
        Copropiedad $copropiedad
    ): bool {
        $configuration = ConfiguracionCopropiedad::query()
            ->where('copropiedad_id', $copropiedad->id)
            ->lockForUpdate()
            ->first();

        if (! $configuration) {
            ConfiguracionCopropiedad::create([
                'organizacion_id' => $organizacion->id,
                'copropiedad_id' => $copropiedad->id,
                ...$this->configurationValues($siteSetting),
            ]);

            return true;
        }

        if ($configuration->organizacion_id !== $organizacion->id) {
            throw new RuntimeException(
                'La Configuración de Copropiedad pertenece a una Organización diferente.'
            );
        }

        foreach ($this->configurationValues($siteSetting) as $field => $value) {
            if ($configuration->getAttribute($field) !== $value) {
                throw new RuntimeException(
                    "La Configuración de Copropiedad diverge de site_settings en {$field}."
                );
            }
        }

        return false;
    }

    private function assertSameContext(
        Organizacion $organizacion,
        Copropiedad $copropiedad
    ): void {
        if ($copropiedad->organizacion_id !== $organizacion->id) {
            throw new RuntimeException(
                'La Copropiedad referenciada no pertenece a la Organización indicada.'
            );
        }
    }

    private function assertInstitutionalIdentityMatches(
        SiteSetting $siteSetting,
        Copropiedad $copropiedad
    ): void {
        foreach ($this->institutionalIdentity($siteSetting) as $field => $value) {
            if ($copropiedad->getAttribute($field) !== $value) {
                throw new RuntimeException(
                    "La Copropiedad diverge de site_settings en {$field}."
                );
            }
        }
    }

    private function institutionalIdentity(SiteSetting $siteSetting): array
    {
        return [
            'nombre' => $siteSetting->nombre_conjunto,
            'nit' => $siteSetting->nit,
            'representante_legal' => $siteSetting->representante_legal,
            'direccion' => $siteSetting->direccion,
            'ciudad' => $siteSetting->ciudad,
            'telefono' => $siteSetting->telefono,
            'email' => $siteSetting->email,
        ];
    }

    private function configurationValues(SiteSetting $siteSetting): array
    {
        return [
            'color_principal' => $siteSetting->color_principal,
            'logo_path' => $siteSetting->logo_path,
            'dias_respuesta' => $siteSetting->dias_respuesta,
        ];
    }
}
