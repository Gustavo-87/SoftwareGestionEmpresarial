<?php

namespace Database\Factories;

use App\Models\MembresiaCopropiedad;
use App\Models\User;
use App\Models\Organizacion;
use App\Models\Copropiedad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembresiaCopropiedad>
 */
class MembresiaCopropiedadFactory extends Factory
{
    protected $model = MembresiaCopropiedad::class;

    public function definition(): array
    {
        $organizacion = Organizacion::factory()->create();
        $copropiedad = Copropiedad::factory()->create([
            'organizacion_id' => $organizacion->id,
        ]);

        return [
            'usuario_id' => User::factory(),
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now(),
            'vigente_hasta' => null,
        ];
    }
}
