<?php

namespace App\Console\Commands;

use App\Application\Contexto\ContextResolver;
use App\Models\Persona;
use App\Models\SiteSetting;
use App\Models\UnidadPrivada;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ContextualizarUnidadesYPersonas extends Command
{
    protected $signature = 'resuelve:contextualizar-unidades-y-personas {--dry-run : Diagnostica la migración sin persistir cambios}';

    protected $description = 'Contextualiza Personas y Unidades Privadas desde los campos legados de usuarios';

    public function __construct(private readonly ContextResolver $contextResolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $settings = SiteSetting::current();
            if (! $settings->exists || $settings->organizacion_id === null || $settings->copropiedad_id === null) {
                throw new RuntimeException('SiteSetting no está contextualizado. Ejecute resuelve:crear-contexto-inicial.');
            }

            $contexto = $this->contextResolver->resolverExplicito(
                $settings->organizacion_id,
                $settings->copropiedad_id,
            );
        } catch (Throwable $exception) {
            $this->error('No fue posible contextualizar unidades y personas.');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $conteos = ['personasCreadas' => 0, 'personasExistentes' => 0, 'unidadesCreadas' => 0, 'unidadesExistentes' => 0, 'vinculosOmitidos' => 0, 'inconsistencias' => 0];
        $usuarios = User::query()
            ->where(fn ($query) => $query->whereNotNull('tower')->orWhereNotNull('unit'))
            ->orderBy('id')
            ->get(['id']);

        foreach ($usuarios as $usuario) {
            try {
                $resultado = DB::transaction(function () use ($usuario, $contexto): array {
                    $usuarioBloqueado = User::query()->lockForUpdate()->findOrFail($usuario->id);
                    $unidad = trim((string) $usuarioBloqueado->unit);

                    if ($unidad === '') {
                        return ['inconsistencia' => "Usuario {$usuarioBloqueado->id}: la unidad legada es obligatoria."];
                    }

                    $torre = trim((string) $usuarioBloqueado->tower);
                    $codigo = $torre === '' ? $unidad : "{$torre}-{$unidad}";
                    $dryRun = (bool) $this->option('dry-run');

                    $persona = Persona::query()
                        ->where('organizacion_id', $contexto->organizacion->id)
                        ->where('usuario_id', $usuarioBloqueado->id)
                        ->first();
                    $personaCreada = $persona === null;
                    if ($personaCreada && ! $dryRun) {
                        $persona = new Persona();
                        $persona->forceFill([
                            'organizacion_id' => $contexto->organizacion->id,
                            'usuario_id' => $usuarioBloqueado->id,
                            'tipo_persona' => 'natural',
                            'nombre_razon_social' => $usuarioBloqueado->name,
                            'email' => $usuarioBloqueado->email,
                        ])->save();
                    }

                    $unidadPrivada = UnidadPrivada::query()
                        ->where('organizacion_id', $contexto->organizacion->id)
                        ->where('copropiedad_id', $contexto->copropiedad->id)
                        ->where('codigo', $codigo)
                        ->first();
                    $unidadCreada = $unidadPrivada === null;
                    if ($unidadCreada && ! $dryRun) {
                        $unidadPrivada = $this->crearUnidadManejandoDuplicidad($contexto->organizacion->id, $contexto->copropiedad->id, $codigo, $unidad, $torre);
                    }

                    return [
                        'personaCreada' => $personaCreada,
                        'unidadCreada' => $unidadCreada,
                        'vinculoOmitido' => "Usuario {$usuarioBloqueado->id}: no se creó vínculo para la unidad {$codigo} porque el legado no define tipo_vinculo.",
                    ];
                }, 3);
            } catch (Throwable $exception) {
                $conteos['inconsistencias']++;
                $this->warn("Usuario {$usuario->id}: {$exception->getMessage()}");

                continue;
            }

            if (isset($resultado['inconsistencia'])) {
                $conteos['inconsistencias']++;
                $this->warn($resultado['inconsistencia']);

                continue;
            }

            $conteos[$resultado['personaCreada'] ? 'personasCreadas' : 'personasExistentes']++;
            $conteos[$resultado['unidadCreada'] ? 'unidadesCreadas' : 'unidadesExistentes']++;
            $conteos['vinculosOmitidos']++;
            $conteos['inconsistencias']++;
            $this->warn($resultado['vinculoOmitido']);
        }

        $this->line('Personas creadas: '.$conteos['personasCreadas']);
        $this->line('Personas existentes: '.$conteos['personasExistentes']);
        $this->line('Unidades creadas: '.$conteos['unidadesCreadas']);
        $this->line('Unidades existentes: '.$conteos['unidadesExistentes']);
        $this->line('Vínculos omitidos sin tipo: '.$conteos['vinculosOmitidos']);
        $this->line('Inconsistencias: '.$conteos['inconsistencias']);

        return self::SUCCESS;
    }

    private function crearUnidadManejandoDuplicidad(int $organizacionId, int $copropiedadId, string $codigo, string $numeroNombre, string $torre): UnidadPrivada
    {
        try {
            $unidad = new UnidadPrivada();
            $unidad->forceFill([
                'organizacion_id' => $organizacionId,
                'copropiedad_id' => $copropiedadId,
                'codigo' => $codigo,
                'numero_nombre' => $numeroNombre,
                'torre_bloque' => $torre === '' ? null : $torre,
            ])->save();

            return $unidad;
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                throw $exception;
            }

            return UnidadPrivada::query()
                ->where('organizacion_id', $organizacionId)
                ->where('copropiedad_id', $copropiedadId)
                ->where('codigo', $codigo)
                ->firstOrFail();
        }
    }
}
