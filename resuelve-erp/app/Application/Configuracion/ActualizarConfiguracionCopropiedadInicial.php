<?php

namespace App\Application\Configuracion;

use App\Models\ConfiguracionCopropiedad;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\SiteSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ActualizarConfiguracionCopropiedadInicial
{
    public function execute(SiteSetting $siteSetting, array $data): void
    {
        DB::transaction(function () use ($siteSetting, $data): void {
            if (! $siteSetting->exists) {
                throw $this->invalidContext('No existe una configuración persistida.');
            }

            $settings = SiteSetting::query()
                ->whereKey($siteSetting->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($settings->organizacion_id === null || $settings->copropiedad_id === null) {
                throw $this->invalidContext('SiteSetting todavía no tiene contexto asociado.');
            }

            $organizacion = Organizacion::query()
                ->whereKey($settings->organizacion_id)
                ->lockForUpdate()
                ->first();
            $copropiedad = Copropiedad::query()
                ->whereKey($settings->copropiedad_id)
                ->lockForUpdate()
                ->first();

            if (! $organizacion || ! $copropiedad) {
                throw $this->invalidContext('Las referencias de contexto no existen.');
            }

            if ($copropiedad->organizacion_id !== $organizacion->id) {
                throw $this->invalidContext(
                    'La Copropiedad no pertenece a la Organización referenciada.'
                );
            }

            $configuracion = ConfiguracionCopropiedad::query()
                ->where('copropiedad_id', $copropiedad->id)
                ->lockForUpdate()
                ->first();

            if (! $configuracion || $configuracion->organizacion_id !== $organizacion->id) {
                throw $this->invalidContext(
                    'La Configuración de Copropiedad no pertenece al contexto indicado.'
                );
            }

            $data['color_principal'] = SiteSetting::COLOR_INSTITUCIONAL;

            $settings->fill(Arr::only($data, [
                'nombre_conjunto',
                'nit',
                'representante_legal',
                'direccion',
                'ciudad',
                'telefono',
                'email',
                'color_principal',
                'logo_path',
                'logo_scale',
                'logo_offset_x',
                'logo_offset_y',
                'dias_respuesta',
            ]));
            $copropiedad->fill($this->copropiedadData($data));
            $configuracion->fill(Arr::only($data, [
                'color_principal',
                'logo_path',
                'dias_respuesta',
            ]));

            $settings->save();
            $copropiedad->save();
            $configuracion->save();
        }, 3);
    }

    private function copropiedadData(array $data): array
    {
        $mapping = [
            'nombre_conjunto' => 'nombre',
            'nit' => 'nit',
            'representante_legal' => 'representante_legal',
            'direccion' => 'direccion',
            'ciudad' => 'ciudad',
            'telefono' => 'telefono',
            'email' => 'email',
        ];
        $mapped = [];

        foreach ($mapping as $source => $destination) {
            if (array_key_exists($source, $data)) {
                $mapped[$destination] = $data[$source];
            }
        }

        return $mapped;
    }

    private function invalidContext(string $diagnostic): RuntimeException
    {
        return new RuntimeException(
            $diagnostic.' Ejecuta: php artisan resuelve:crear-contexto-inicial'
        );
    }
}
