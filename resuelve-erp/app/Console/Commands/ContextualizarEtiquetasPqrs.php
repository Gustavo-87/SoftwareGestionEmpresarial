<?php

namespace App\Console\Commands;

use App\Application\Contexto\ContextResolver;
use App\Models\Pqr;
use App\Models\PqrTag;
use App\Models\SiteSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ContextualizarEtiquetasPqrs extends Command
{
    protected $signature = 'resuelve:contextualizar-etiquetas-pqrs';

    protected $description = 'Asigna contexto institucional a las etiquetas y sus asociaciones de PQRS';

    public function __construct(private readonly ContextResolver $contextResolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $resultado = DB::transaction(function (): array {
                $settings = SiteSetting::current();

                if (! $settings->exists || $settings->organizacion_id === null || $settings->copropiedad_id === null) {
                    throw new RuntimeException(
                        'SiteSetting no está contextualizado. Ejecute resuelve:crear-contexto-inicial.'
                    );
                }

                $settings = SiteSetting::query()->whereKey($settings->id)->lockForUpdate()->firstOrFail();
                $contexto = $this->contextResolver->resolverExplicito(
                    $settings->organizacion_id,
                    $settings->copropiedad_id,
                );
                $organizacionId = $contexto->organizacion->id;
                $copropiedadId = $contexto->copropiedad->id;

                $pqrs = Pqr::query()->lockForUpdate()->get()->keyBy('id');
                foreach ($pqrs as $pqr) {
                    if ($pqr->organizacion_id === null || $pqr->copropiedad_id === null) {
                        throw new RuntimeException("La PQR {$pqr->id} no tiene contexto completo.");
                    }
                }

                $tags = PqrTag::query()->lockForUpdate()->get()->keyBy('id');
                $etiquetasActualizadas = 0;
                $etiquetasExistentes = 0;
                $contextosEtiquetas = [];

                foreach ($tags as $tag) {
                    $tieneOrganizacion = $tag->organizacion_id !== null;
                    $tieneCopropiedad = $tag->copropiedad_id !== null;

                    if ($tieneOrganizacion !== $tieneCopropiedad) {
                        throw new RuntimeException("La etiqueta {$tag->id} tiene un contexto parcial.");
                    }

                    if (! $tieneOrganizacion) {
                        $contextosEtiquetas[$tag->id] = [$organizacionId, $copropiedadId];
                        $etiquetasActualizadas++;

                        continue;
                    }

                    $contextoEtiqueta = $this->contextResolver->resolverExplicito(
                        $tag->organizacion_id,
                        $tag->copropiedad_id,
                    );
                    $contextosEtiquetas[$tag->id] = [
                        $contextoEtiqueta->organizacion->id,
                        $contextoEtiqueta->copropiedad->id,
                    ];
                    $etiquetasExistentes++;
                }

                $pivotes = DB::table('pqr_pqr_tag')->lockForUpdate()->get();
                $pivotesActualizados = 0;
                $pivotesExistentes = 0;
                $actualizacionesPivote = [];

                foreach ($pivotes as $pivot) {
                    $pqr = $pqrs->get($pivot->pqr_id);
                    $contextoEtiqueta = $contextosEtiquetas[$pivot->pqr_tag_id] ?? null;

                    if (! $pqr || ! $contextoEtiqueta) {
                        throw new RuntimeException(
                            "La asociación PQR {$pivot->pqr_id} / etiqueta {$pivot->pqr_tag_id} es huérfana."
                        );
                    }

                    [$organizacionEtiqueta, $copropiedadEtiqueta] = $contextoEtiqueta;
                    if ($pqr->organizacion_id !== $organizacionEtiqueta || $pqr->copropiedad_id !== $copropiedadEtiqueta) {
                        throw new RuntimeException(
                            "La asociación PQR {$pivot->pqr_id} / etiqueta {$pivot->pqr_tag_id} cruza contextos."
                        );
                    }

                    $tieneOrganizacion = $pivot->organizacion_id !== null;
                    $tieneCopropiedad = $pivot->copropiedad_id !== null;
                    if ($tieneOrganizacion !== $tieneCopropiedad) {
                        throw new RuntimeException(
                            "La asociación PQR {$pivot->pqr_id} / etiqueta {$pivot->pqr_tag_id} tiene contexto parcial."
                        );
                    }

                    if (! $tieneOrganizacion) {
                        $actualizacionesPivote[] = $pivot;
                        $pivotesActualizados++;
                    } elseif ($pivot->organizacion_id === $pqr->organizacion_id
                        && $pivot->copropiedad_id === $pqr->copropiedad_id) {
                        $pivotesExistentes++;
                    } else {
                        throw new RuntimeException(
                            "La asociación PQR {$pivot->pqr_id} / etiqueta {$pivot->pqr_tag_id} tiene contexto divergente."
                        );
                    }
                }

                foreach ($tags as $tag) {
                    if ($tag->organizacion_id === null) {
                        DB::table('pqr_tags')->where('id', $tag->id)->update([
                            'organizacion_id' => $organizacionId,
                            'copropiedad_id' => $copropiedadId,
                        ]);
                    }
                }
                foreach ($actualizacionesPivote as $pivot) {
                    $pqr = $pqrs->get($pivot->pqr_id);
                    DB::table('pqr_pqr_tag')
                        ->where('pqr_id', $pivot->pqr_id)
                        ->where('pqr_tag_id', $pivot->pqr_tag_id)
                        ->update([
                            'organizacion_id' => $pqr->organizacion_id,
                            'copropiedad_id' => $pqr->copropiedad_id,
                        ]);
                }

                return compact(
                    'tags', 'pivotes', 'etiquetasActualizadas', 'etiquetasExistentes',
                    'pivotesActualizados', 'pivotesExistentes'
                );
            }, 3);
        } catch (Throwable $exception) {
            $this->error('No fue posible contextualizar etiquetas y asociaciones; se revirtieron los cambios.');
            $this->error($exception->getMessage());
            $this->line('Inconsistencias: 1');

            return self::FAILURE;
        }

        $this->info('Etiquetas y asociaciones contextualizadas correctamente.');
        $this->line('Etiquetas examinadas: '.count($resultado['tags']));
        $this->line("Etiquetas actualizadas: {$resultado['etiquetasActualizadas']}");
        $this->line("Etiquetas existentes: {$resultado['etiquetasExistentes']}");
        $this->line('Asociaciones examinadas: '.count($resultado['pivotes']));
        $this->line("Asociaciones actualizadas: {$resultado['pivotesActualizados']}");
        $this->line("Asociaciones existentes: {$resultado['pivotesExistentes']}");
        $this->line('Inconsistencias: 0');

        return self::SUCCESS;
    }
}
