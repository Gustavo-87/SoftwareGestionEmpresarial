<?php

namespace Database\Factories;

use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\TipoPqr;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

class PqrFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (Pqr $pqr): void {
            if ($pqr->organizacion_id !== null || $pqr->copropiedad_id !== null) {
                return;
            }

            [$organizacion, $copropiedad] = $this->contextoDePrueba();
            $pqr->organizacion()->associate($organizacion);
            $pqr->copropiedad()->associate($copropiedad);
        });
    }

    public function definition(): array
    {
        return [
            'asunto' => $this->faker->sentence(4),
            'descripcion' => $this->faker->paragraph(),
            'fecha_radicacion' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'fecha_limite_respuesta' => $this->faker->dateTimeBetween('now', '+15 days'),
            'estado' => $this->faker->randomElement(['radicada', 'en_revision', 'respondida', 'cerrada']),
            'prioridad' => $this->faker->randomElement(['alta', 'media', 'baja']),
            'user_id' => User::factory(),
            'tipo_pqr_id' => TipoPqr::factory(),
        ];
    }

    public function paraContexto(Organizacion $organizacion, Copropiedad $copropiedad): static
    {
        if ($copropiedad->organizacion_id !== $organizacion->id) {
            throw new InvalidArgumentException('La Copropiedad no pertenece a la Organización indicada.');
        }

        return $this->afterMaking(function (Pqr $pqr) use ($organizacion, $copropiedad): void {
            $pqr->organizacion()->associate($organizacion);
            $pqr->copropiedad()->associate($copropiedad);
        });
    }

    public function sinContexto(): static
    {
        return $this->afterMaking(function (Pqr $pqr): void {
            $pqr->organizacion()->dissociate();
            $pqr->copropiedad()->dissociate();
        });
    }

    /** @return array{Organizacion, Copropiedad} */
    private function contextoDePrueba(): array
    {
        $settings = SiteSetting::query()
            ->whereNotNull('organizacion_id')
            ->whereNotNull('copropiedad_id')
            ->first();

        if ($settings) {
            return [$settings->organizacion, $settings->copropiedad];
        }

        $organizacion = Organizacion::create([
            'nombre' => 'Organización de pruebas',
            'estado' => 'activa',
        ]);
        $copropiedad = Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Copropiedad de pruebas',
            'estado' => 'activa',
        ]);
        $settings = SiteSetting::create([
            'nombre_conjunto' => 'Copropiedad de pruebas',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 15,
        ]);
        $settings->organizacion()->associate($organizacion);
        $settings->copropiedad()->associate($copropiedad);
        $settings->save();

        return [$organizacion, $copropiedad];
    }
}
