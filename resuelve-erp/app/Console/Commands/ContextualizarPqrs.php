<?php

namespace App\Console\Commands;

use App\Application\Contexto\ContextResolver;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\SiteSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ContextualizarPqrs extends Command
{
    protected $signature = 'resuelve:contextualizar-pqrs';

    protected $description = 'Asigna el contexto institucional inicial a las PQRS históricas';

    public function __construct(private readonly ContextResolver $contextResolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = DB::transaction(function (): array {
                $siteSettingActual = SiteSetting::current();

                if (! $siteSettingActual->exists) {
                    throw new RuntimeException('No existe una fila persistida de site_settings.');
                }

                $siteSetting = SiteSetting::query()
                    ->whereKey($siteSettingActual->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($siteSetting->organizacion_id === null || $siteSetting->copropiedad_id === null) {
                    throw new RuntimeException(
                        'SiteSetting no está contextualizado. Ejecute resuelve:crear-contexto-inicial.'
                    );
                }

                $contexto = $this->contextResolver->resolverExplicito(
                    $siteSetting->organizacion_id,
                    $siteSetting->copropiedad_id
                );
                $organizacion = Organizacion::query()
                    ->whereKey($contexto->organizacion->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $copropiedad = Copropiedad::query()
                    ->whereKey($contexto->copropiedad->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($copropiedad->organizacion_id !== $organizacion->id) {
                    throw new RuntimeException(
                        'La Copropiedad inicial no pertenece a la Organización inicial.'
                    );
                }

                $pqrs = Pqr::query()->orderBy('id')->lockForUpdate()->get();
                $pendientes = [];
                $existentes = 0;

                foreach ($pqrs as $pqr) {
                    $tieneOrganizacion = $pqr->organizacion_id !== null;
                    $tieneCopropiedad = $pqr->copropiedad_id !== null;

                    if ($tieneOrganizacion !== $tieneCopropiedad) {
                        throw new RuntimeException(
                            "La PQR {$pqr->id} tiene un contexto parcial y requiere conciliación manual."
                        );
                    }

                    if (! $tieneOrganizacion) {
                        $pendientes[] = $pqr->id;

                        continue;
                    }

                    $organizacionPqr = Organizacion::query()->find($pqr->organizacion_id);
                    $copropiedadPqr = Copropiedad::query()->find($pqr->copropiedad_id);

                    if (! $organizacionPqr || ! $copropiedadPqr) {
                        throw new RuntimeException(
                            "La PQR {$pqr->id} contiene referencias contextuales inexistentes."
                        );
                    }

                    if ($copropiedadPqr->organizacion_id !== $organizacionPqr->id) {
                        throw new RuntimeException(
                            "La PQR {$pqr->id} contiene referencias contextuales cruzadas."
                        );
                    }

                    if ($organizacionPqr->id !== $organizacion->id
                        || $copropiedadPqr->id !== $copropiedad->id) {
                        throw new RuntimeException(
                            "La PQR {$pqr->id} pertenece a un contexto distinto del contexto inicial."
                        );
                    }

                    $existentes++;
                }

                $contextualizadas = 0;
                if ($pendientes !== []) {
                    $contextualizadas = DB::table('pqrs')
                        ->whereIn('id', $pendientes)
                        ->whereNull('organizacion_id')
                        ->whereNull('copropiedad_id')
                        ->update([
                            'organizacion_id' => $organizacion->id,
                            'copropiedad_id' => $copropiedad->id,
                        ]);
                }

                if ($contextualizadas !== count($pendientes)) {
                    throw new RuntimeException(
                        'El conjunto de PQRS cambió durante la contextualización; se revirtió la operación.'
                    );
                }

                return compact('organizacion', 'copropiedad', 'contextualizadas', 'existentes');
            }, 3);
        } catch (Throwable $exception) {
            $this->error('No fue posible contextualizar las PQRS; se revirtieron los cambios.');
            $this->error($exception->getMessage());
            $this->line('Inconsistencias: 1');

            return self::FAILURE;
        }

        $this->info('PQRS contextualizadas correctamente.');
        $this->line("Organización: {$result['organizacion']->id}");
        $this->line("Copropiedad: {$result['copropiedad']->id}");
        $this->line("Contextualizadas: {$result['contextualizadas']}");
        $this->line("Existentes: {$result['existentes']}");
        $this->line('Inconsistencias: 0');

        return self::SUCCESS;
    }
}
