<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TipoPqrFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->randomElement([
                'Petición',
                'Queja',
                'Reclamo',
                'Sugerencia',
                'Solicitud',
            ]),
            'descripcion' => $this->faker->sentence(8),
        ];
    }
}