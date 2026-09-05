<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class DocumentoManagementFlowsTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_manager_creates_a_contextual_document_and_single_activity(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $owner = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['documentos.gestionar']);
        $this->createContextualIdentity($owner, $organizacion, $copropiedad, 'residente', ['documentos.consultar']);

        $this->actingAs($manager)->post(route('documentos.store'), ['tipo' => 'acta', 'categoria' => 'gobierno_copropiedad', 'titulo' => 'Acta inicial', 'nivel_acceso' => 'interno', 'propietario_documental_user_id' => $owner->id])
            ->assertRedirect()->assertSessionHas('success', 'Documento creado correctamente.');

        $documento = Documento::query()->firstOrFail();
        $this->assertSame($organizacion->id, $documento->organizacion_id);
        $this->assertSame($copropiedad->id, $documento->copropiedad_id);
        $this->assertSame($owner->id, $documento->propietario_documental_user_id);
        $this->assertDatabaseCount('documento_actuaciones', 1);
    }

    public function test_manager_uploads_private_version_with_hash_and_single_activity(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        Storage::fake('local');
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['documentos.gestionar']);
        $documento = $this->documento($organizacion->id, $copropiedad->id, $manager->id);

        $this->actingAs($manager)->post(route('documentos.versions.store', $documento), ['archivo' => UploadedFile::fake()->create('acta.pdf', 12, 'application/pdf')])
            ->assertRedirect()->assertSessionHas('success', 'Versión cargada correctamente.');

        $version = $documento->versiones()->firstOrFail();
        $this->assertSame(1, $version->numero);
        $this->assertSame('borrador', $version->estado->value);
        $this->assertSame(64, strlen($version->hash_sha256));
        Storage::disk('local')->assertExists($version->ruta_archivo);
        $this->assertCount(0, Storage::disk('local')->allFiles('documentos/staging'));
        $this->assertDatabaseCount('documento_actuaciones', 1);
    }

    public function test_archiving_requires_permission_and_preserves_document_history(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $archiver = User::factory()->create(['role' => 'gestor']);
        $owner = User::factory()->create();
        $this->createContextualIdentity($archiver, $organizacion, $copropiedad, 'gestor', ['documentos.archivar']);
        $documento = $this->documento($organizacion->id, $copropiedad->id, $owner->id);

        $this->actingAs($archiver)->patch(route('documentos.archive', $documento))->assertRedirect()->assertSessionHas('success', 'Documento archivado correctamente.');
        $this->assertSame('archivado', $documento->fresh()->estado->value);
        $this->assertNotNull($documento->fresh()->archivado_at);
        $this->assertDatabaseCount('documento_actuaciones', 1);
    }

    public function test_user_deletion_is_rejected_when_the_user_owns_an_active_document(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', ['usuarios.gestionar']);
        $this->createContextualIdentity($owner, $organizacion, $copropiedad, 'residente', []);
        $this->documento($organizacion->id, $copropiedad->id, $owner->id);

        $this->actingAsContextual($admin)->delete(route('users.destroy', $owner))->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    private function documento(int $organizacionId, int $copropiedadId, int $ownerId): Documento
    {
        $documento = new Documento(['ambito' => 'copropiedad', 'propietario_documental_user_id' => $ownerId, 'tipo' => 'documento_general', 'categoria' => 'administrativo', 'titulo' => 'Documento', 'nivel_acceso' => 'interno', 'estado' => 'activo']);
        $documento->forceFill(['organizacion_id' => $organizacionId, 'copropiedad_id' => $copropiedadId])->save();
        return $documento;
    }
}
