<?php

namespace Tests\Feature;

use App\Models\{Copropiedad, Documento, DocumentoVersion, Organizacion, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class DocumentoDownloadAndDiagnosticTest extends TestCase
{
    use CreatesInstitutionalContext;
    use RefreshDatabase;

    public function test_approved_current_version_downloads_for_contextual_reader(): void
    {
        [$organization, $copropiedad] = $this->createInstitutionalContext();
        Storage::fake('local');
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $organization, $copropiedad, 'residente', ['documentos.consultar']);
        [$documento, $version] = $this->documentoVersion($organization, $copropiedad, $user, 'aprobada', now()->toDateString());
        $this->storeValidFile($version);

        $this->actingAs($user)
            ->get(route('documentos.versions.download', [$documento, $version]))
            ->assertOk();
    }

    public function test_external_or_nonexistent_document_or_version_returns_not_found(): void
    {
        [$organization, $copropiedad] = $this->createInstitutionalContext();
        [$otherOrganization, $otherCopropiedad] = $this->otherContext();
        Storage::fake('local');
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $organization, $copropiedad, 'residente', ['documentos.consultar']);
        [$externalDocumento, $externalVersion] = $this->documentoVersion($otherOrganization, $otherCopropiedad, $user, 'aprobada', now()->toDateString());
        $this->storeValidFile($externalVersion);
        [$localDocumento, $localVersion] = $this->documentoVersion($organization, $copropiedad, $user, 'aprobada', now()->toDateString());
        $this->storeValidFile($localVersion);

        $this->actingAs($user)->get(route('documentos.versions.download', [$externalDocumento, $externalVersion]))->assertNotFound();
        $this->actingAs($user)->get(route('documentos.versions.download', [$localDocumento, 999999]))->assertNotFound();
        $this->actingAs($user)->get(route('documentos.versions.download', [999999, $localVersion]))->assertNotFound();
    }

    public function test_insufficient_permission_is_forbidden(): void
    {
        [$organization, $copropiedad] = $this->createInstitutionalContext();
        Storage::fake('local');
        $owner = User::factory()->create();
        $readerWithoutPermission = User::factory()->create();
        $this->createContextualIdentity($readerWithoutPermission, $organization, $copropiedad, 'residente', []);
        [$documento, $version] = $this->documentoVersion($organization, $copropiedad, $owner, 'aprobada', now()->toDateString());
        $this->storeValidFile($version);

        $this->actingAs($readerWithoutPermission)
            ->get(route('documentos.versions.download', [$documento, $version]))
            ->assertForbidden();
    }

    public function test_unapproved_noncurrent_and_missing_file_versions_return_not_found(): void
    {
        [$organization, $copropiedad] = $this->createInstitutionalContext();
        Storage::fake('local');
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $organization, $copropiedad, 'residente', ['documentos.consultar']);
        [$draftDocumento, $draft] = $this->documentoVersion($organization, $copropiedad, $user, 'borrador', null);
        [$futureDocumento, $future] = $this->documentoVersion($organization, $copropiedad, $user, 'aprobada', now()->addDay()->toDateString());
        [$missingDocumento, $missing] = $this->documentoVersion($organization, $copropiedad, $user, 'aprobada', now()->toDateString());

        $this->actingAs($user)->get(route('documentos.versions.download', [$draftDocumento, $draft]))->assertNotFound();
        $this->actingAs($user)->get(route('documentos.versions.download', [$futureDocumento, $future]))->assertNotFound();
        $this->actingAs($user)->get(route('documentos.versions.download', [$missingDocumento, $missing]))->assertNotFound();
    }

    public function test_private_document_file_is_not_publicly_accessible(): void
    {
        [$organization, $copropiedad] = $this->createInstitutionalContext();
        Storage::fake('local');
        $user = User::factory()->create();
        [$documento, $version] = $this->documentoVersion($organization, $copropiedad, $user, 'aprobada', now()->toDateString());
        $this->storeValidFile($version);

        $this->get('/storage/'.$version->ruta_archivo)->assertForbidden();
    }

    public function test_diagnostic_reports_all_inconsistencies_without_mutations(): void
    {
        [$organization, $copropiedad] = $this->createInstitutionalContext();
        Storage::fake('local');
        $user = User::factory()->create();
        [, $altered] = $this->documentoVersion($organization, $copropiedad, $user, 'aprobada', now()->toDateString());
        [, $missing] = $this->documentoVersion($organization, $copropiedad, $user, 'aprobada', now()->toDateString());
        [, $inconsistent] = $this->documentoVersion($organization, $copropiedad, $user, 'aprobada', now()->toDateString());
        Storage::disk('local')->put($altered->ruta_archivo, 'alterado');
        $inconsistent->update(['ruta_archivo' => 'documentos/inconsistente.pdf']);
        Storage::disk('local')->put($inconsistent->ruta_archivo, 'consistente-en-disco');
        Storage::disk('local')->put('documentos/99/99/99/orfano.pdf', 'huerfano');

        $this->artisan('documentos:diagnosticar')
            ->expectsOutputToContain('HASH_ALTERADO')
            ->expectsOutputToContain('AUSENTE')
            ->expectsOutputToContain('METADATO_INCONSISTENTE')
            ->expectsOutputToContain('HUERFANO')
            ->assertSuccessful();

        $this->assertDatabaseHas('documento_versiones', ['id' => $missing->id]);
        $this->assertTrue(Storage::disk('local')->exists($altered->ruta_archivo));
        $this->assertTrue(Storage::disk('local')->exists($inconsistent->ruta_archivo));
        $this->assertTrue(Storage::disk('local')->exists('documentos/99/99/99/orfano.pdf'));
    }

    private function documentoVersion(Organizacion $organization, Copropiedad $copropiedad, User $owner, string $estado, ?string $vigenteDesde): array
    {
        $documento = new Documento([
            'ambito' => 'copropiedad', 'propietario_documental_user_id' => $owner->id,
            'tipo' => 'documento_general', 'categoria' => 'administrativo', 'titulo' => uniqid(),
            'nivel_acceso' => 'interno', 'estado' => 'activo',
        ]);
        $documento->forceFill(['organizacion_id' => $organization->id, 'copropiedad_id' => $copropiedad->id])->save();
        $version = new DocumentoVersion([
            'numero' => 1, 'estado' => $estado, 'origen' => 'usuario', 'nombre_original' => 'a.pdf',
            'ruta_archivo' => "documentos/{$organization->id}/{$copropiedad->id}/{$documento->id}/1/a.pdf",
            'mime_type' => 'application/pdf', 'extension' => 'pdf', 'tamano_bytes' => 2,
            'hash_sha256' => hash('sha256', 'ok'), 'vigente_desde' => $vigenteDesde,
        ]);
        $version->forceFill(['documento_id' => $documento->id, 'organizacion_id' => $organization->id, 'copropiedad_id' => $copropiedad->id])->save();

        return [$documento, $version];
    }

    private function storeValidFile(DocumentoVersion $version): void
    {
        Storage::disk('local')->put($version->ruta_archivo, 'ok');
    }

    private function otherContext(): array
    {
        $organization = Organizacion::create(['nombre' => 'Otra '.uniqid(), 'estado' => 'activa']);
        $copropiedad = Copropiedad::create(['organizacion_id' => $organization->id, 'nombre' => 'Otra '.uniqid(), 'estado' => 'activa']);

        return [$organization, $copropiedad];
    }
}
