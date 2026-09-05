<?php

namespace Database\Factories;

use App\Models\Copropiedad;
use App\Models\Organizacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Copropiedad>
 */
class CopropiedadFactory extends Factory
{
    protected $model = Copropiedad::class;

    public function definition(): array
    {
        return [
            'organizacion_id' => Organizacion::factory(),
            'nombre' => fake()->company() . ' Residencial',
            'estado' => 'activa',
        ];
    }
}
