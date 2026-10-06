<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class DocumentoCreationFlowTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_document_can_be_created_without_initial_file(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['documentos.gestionar']);

        $this->actingAs($manager)->post(route('documentos.store'), ['tipo' => 'acta', 'categoria' => 'gobierno_copropiedad', 'titulo' => 'Sin archivo inicial', 'nivel_acceso' => 'interno', 'propietario_documental_user_id' => $manager->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Documento creado correctamente.');

        $documento = Documento::query()->firstOrFail();
        $this->assertSame(0, $documento->versiones()->count());
    }

    public function test_document_with_initial_file_creates_draft_version_one(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        Storage::fake('local');
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['documentos.gestionar']);

        $this->actingAs($manager)->post(route('documentos.store'), ['tipo' => 'acta', 'categoria' => 'gobierno_copropiedad', 'titulo' => 'Con archivo inicial', 'nivel_acceso' => 'interno', 'propietario_documental_user_id' => $manager->id, 'archivo' => UploadedFile::fake()->create('acta.pdf', 12, 'application/pdf')])
            ->assertRedirect()
            ->assertSessionHas('success', 'Documento creado correctamente.');

        $documento = Documento::query()->firstOrFail();
        $version = $documento->versiones()->firstOrFail();
        $this->assertSame(1, $version->numero);
        $this->assertSame('borrador', $version->estado->value);
        $this->assertSame($manager->name, $version->nombre_cargador);
        $this->assertSame($manager->id, $version->cargada_por_user_id);
    }

    public function test_initial_version_failure_rolls_back_document_creation(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        Storage::fake('local');
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['documentos.gestionar']);

        // Fallo real en la capa de persistencia durante la creación de la versión inicial.
        DocumentoVersion::creating(function () {
            throw ValidationException::withMessages(['archivo' => ['Fallo simulado al cargar la versión.']]);
        });

        $this->actingAs($manager)->post(route('documentos.store'), ['tipo' => 'acta', 'categoria' => 'gobierno_copropiedad', 'titulo' => 'Con fallo de versión', 'nivel_acceso' => 'interno', 'propietario_documental_user_id' => $manager->id, 'archivo' => UploadedFile::fake()->create('acta.pdf', 12, 'application/pdf')])
            ->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('documentos', 0);
        $this->assertDatabaseCount('documento_versiones', 0);
    }

    public function test_creation_requires_management_permission(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $lector = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($lector, $organizacion, $copropiedad, 'residente', ['documentos.consultar']);

        $this->actingAs($lector)->post(route('documentos.store'), ['tipo' => 'acta', 'categoria' => 'gobierno_copropiedad', 'titulo' => 'No permitido', 'nivel_acceso' => 'interno', 'propietario_documental_user_id' => $lector->id])
            ->assertForbidden();

        $this->assertDatabaseCount('documentos', 0);
    }
}
