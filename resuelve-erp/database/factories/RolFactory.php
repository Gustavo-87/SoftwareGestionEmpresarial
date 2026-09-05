<?php

namespace Database\Factories;

use App\Models\Rol;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rol>
 */
class RolFactory extends Factory
{
    protected $model = Rol::class;

    public function definition(): array
    {
        return [
            'clave' => fake()->unique()->slug(),
            'nombre' => fake()->word(),
            'descripcion' => fake()->sentence(),
            'ambito_aplicable' => 'copropiedad',
            'estado' => 'activo',
        ];
    }
}
