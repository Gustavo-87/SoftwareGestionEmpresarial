<?php

namespace Database\Factories;

use App\Models\Documento;
use App\Models\DocumentoActuacion;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentoActuacionFactory extends Factory
{
    protected $model = DocumentoActuacion::class;

    public function definition(): array
    {
        return [
            'documento_id' => Documento::factory(),
            'accion' => 'creado',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (DocumentoActuacion $actuacion): void {
            $documento = Documento::query()->findOrFail($actuacion->documento_id);
            $actuacion->organizacion_id = $documento->organizacion_id;
            $actuacion->copropiedad_id = $documento->copropiedad_id;
        });
    }
}
