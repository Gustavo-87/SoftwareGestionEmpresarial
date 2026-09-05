<?php

namespace Database\Factories;

use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\PqrTag;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

class PqrTagFactory extends Factory
{
    protected $model = PqrTag::class;

    public function configure(): static
    {
        return $this->afterMaking(function (PqrTag $tag): void {
            if ($tag->organizacion_id !== null || $tag->copropiedad_id !== null) {
                return;
            }

            $settings = SiteSetting::query()
                ->whereNotNull('organizacion_id')
                ->whereNotNull('copropiedad_id')
                ->first();

            if (! $settings) {
                throw new InvalidArgumentException('La factory de etiquetas requiere un contexto institucional de prueba.');
            }

            $tag->organizacion()->associate($settings->organizacion);
            $tag->copropiedad()->associate($settings->copropiedad);
        });
    }

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'color' => '#1f6b57',
        ];
    }

    public function paraContexto(Organizacion $organizacion, Copropiedad $copropiedad): static
    {
        if ($copropiedad->organizacion_id !== $organizacion->id) {
            throw new InvalidArgumentException('La Copropiedad no pertenece a la Organización indicada.');
        }

        return $this->afterMaking(function (PqrTag $tag) use ($organizacion, $copropiedad): void {
            $tag->organizacion()->associate($organizacion);
            $tag->copropiedad()->associate($copropiedad);
        });
    }
}
