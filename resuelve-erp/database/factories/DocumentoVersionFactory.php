<?php

namespace Database\Factories;

use App\Domain\GestionDocumental\Enums\EstadoDocumentoVersionEnum;
use App\Domain\GestionDocumental\Enums\OrigenDocumentoVersionEnum;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentoVersionFactory extends Factory
{
    protected $model = DocumentoVersion::class;

    public function definition(): array
    {
        return [
            'documento_id' => Documento::factory(),
            'numero' => 1,
            'estado' => EstadoDocumentoVersionEnum::BORRADOR,
            'origen' => OrigenDocumentoVersionEnum::USUARIO,
            'nombre_original' => 'documento.pdf',
            'ruta_archivo' => 'privado/documento.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'tamano_bytes' => 1024,
            'hash_sha256' => hash('sha256', $this->faker->uuid()),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (DocumentoVersion $version): void {
            $documento = Documento::query()->findOrFail($version->documento_id);
            $version->organizacion_id = $documento->organizacion_id;
            $version->copropiedad_id = $documento->copropiedad_id;
        });
    }
}
