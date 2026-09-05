<?php

namespace Database\Factories;

use App\Domain\GestionDocumental\Enums\AmbitoDocumentoEnum;
use App\Domain\GestionDocumental\Enums\CategoriaDocumentoEnum;
use App\Domain\GestionDocumental\Enums\EstadoDocumentoEnum;
use App\Domain\GestionDocumental\Enums\NivelAccesoDocumentoEnum;
use App\Domain\GestionDocumental\Enums\TipoDocumentoEnum;
use App\Models\Copropiedad;
use App\Models\Documento;
use App\Models\Organizacion;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

class DocumentoFactory extends Factory
{
    protected $model = Documento::class;

    public function definition(): array
    {
        return [
            'ambito' => AmbitoDocumentoEnum::COPROPIEDAD,
            'propietario_documental_user_id' => User::factory(),
            'tipo' => TipoDocumentoEnum::DOCUMENTO_GENERAL,
            'categoria' => CategoriaDocumentoEnum::ADMINISTRATIVO,
            'titulo' => $this->faker->sentence(4),
            'descripcion' => $this->faker->sentence(),
            'nivel_acceso' => NivelAccesoDocumentoEnum::INTERNO,
            'estado' => EstadoDocumentoEnum::ACTIVO,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Documento $documento): void {
            if ($documento->organizacion_id !== null || $documento->copropiedad_id !== null) {
                return;
            }

            $settings = SiteSetting::current();
            if (! $settings->organizacion_id || ! $settings->copropiedad_id) {
                throw new InvalidArgumentException('La factory de Documentos requiere un contexto institucional de prueba.');
            }

            $documento->organizacion_id = $settings->organizacion_id;
            $documento->copropiedad_id = $settings->copropiedad_id;
        });
    }

    public function paraContexto(Organizacion $organizacion, Copropiedad $copropiedad): static
    {
        if ($copropiedad->organizacion_id !== $organizacion->id) {
            throw new InvalidArgumentException('La Copropiedad no pertenece a la Organización indicada.');
        }

        return $this->state(fn () => [
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'ambito' => AmbitoDocumentoEnum::COPROPIEDAD,
        ]);
    }
}
