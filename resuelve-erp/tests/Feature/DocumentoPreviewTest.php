<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class DocumentoPreviewTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_authorized_user_can_preview_image(): void
    {
        Storage::fake('local');
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['documentos.consultar']);

        $documento = Documento::factory()->create([
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activo',
        ]);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $path = $file->store('documentos', 'local');

        $version = DocumentoVersion::factory()->create([
            'documento_id' => $documento->id,
            'estado' => 'aprobada',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'ruta_archivo' => $path,
            'vigente_desde' => now()->toDateString(),
        ]);

        $this->actingAsContextual($user)
            ->get(route('documentos.versions.preview', [$documento, $version]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Content-Disposition', 'inline');
    }

    public function test_authorized_user_can_preview_pdf(): void
    {
        Storage::fake('local');
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['documentos.consultar']);

        $documento = Documento::factory()->create([
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activo',
        ]);

        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');
        $path = $file->store('documentos', 'local');

        $version = DocumentoVersion::factory()->create([
            'documento_id' => $documento->id,
            'estado' => 'aprobada',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'ruta_archivo' => $path,
            'vigente_desde' => now()->toDateString(),
        ]);

        $this->actingAsContextual($user)
            ->get(route('documentos.versions.preview', [$documento, $version]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline');
    }

    public function test_unauthorized_user_cannot_preview(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'residente', []);

        $documento = Documento::factory()->create([
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activo',
        ]);

        $version = DocumentoVersion::factory()->create([
            'documento_id' => $documento->id,
            'estado' => 'aprobada',
            'mime_type' => 'application/pdf',
            'vigente_desde' => now()->toDateString(),
        ]);

        $this->actingAsContextual($user)
            ->get(route('documentos.versions.preview', [$documento, $version]))
            ->assertForbidden();
    }

    public function test_preview_respects_contextual_isolation(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$otherOrg, $otherCop] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['documentos.consultar']);

        $documento = Documento::factory()->create([
            'organizacion_id' => $otherOrg->id,
            'copropiedad_id' => $otherCop->id,
            'estado' => 'activo',
        ]);

        $version = DocumentoVersion::factory()->create([
            'documento_id' => $documento->id,
            'estado' => 'aprobada',
            'mime_type' => 'application/pdf',
            'vigente_desde' => now()->toDateString(),
        ]);

        $this->actingAsContextual($user)
            ->get(route('documentos.versions.preview', [$documento, $version]))
            ->assertNotFound();
    }

    public function test_document_index_shows_preview_for_compatible_version(): void
    {
        Storage::fake('local');
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['documentos.consultar']);

        $documento = Documento::factory()->create([
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activo',
        ]);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $path = $file->store('documentos', 'local');

        $version = DocumentoVersion::factory()->create([
            'documento_id' => $documento->id,
            'estado' => 'aprobada',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'ruta_archivo' => $path,
            'vigente_desde' => now()->toDateString(),
        ]);

        $this->actingAsContextual($user)
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('document-preview-cell')
            ->assertSee('document-preview-thumb');
    }
}
