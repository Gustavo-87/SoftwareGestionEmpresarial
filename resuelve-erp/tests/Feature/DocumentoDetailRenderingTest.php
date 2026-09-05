<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class DocumentoDetailRenderingTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_detail_renders_empty_draft_pending_and_approved_versions_for_authorized_management(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $manager = User::factory()->create();
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', [
            'documentos.consultar', 'documentos.gestionar', 'documentos.aprobar',
        ]);

        $empty = $this->documento($organizacion->id, $copropiedad->id, $manager->id, 'Sin versiones');
        $documento = $this->documento($organizacion->id, $copropiedad->id, $manager->id, 'Con versiones');
        $this->version($documento, 1, 'borrador');
        $this->version($documento, 2, 'pendiente_aprobacion');
        $this->version($documento, 3, 'aprobada', now()->toDateString());

        $this->actingAsContextual($manager)->get(route('documentos.show', $empty))
            ->assertOk()
            ->assertSee('Sin versiones registradas')
            ->assertSee('Cargar nueva versión');

        $this->get(route('documentos.show', $documento))
            ->assertOk()
            ->assertSee('Borrador')
            ->assertSee('Pendiente de aprobación')
            ->assertSee('Aprobada')
            ->assertSee('Someter a aprobación')
            ->assertSee('Aprobar versión')
            ->assertSee('Cargar nueva versión');
    }

    public function test_detail_hides_management_and_approval_actions_without_contextual_permissions(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $this->createContextualIdentity($reader, $organizacion, $copropiedad, 'residente', ['documentos.consultar']);
        $documento = $this->documento($organizacion->id, $copropiedad->id, $owner->id, 'Solo consulta');
        $this->version($documento, 1, 'borrador');

        $this->actingAsContextual($reader)->get(route('documentos.show', $documento))
            ->assertOk()
            ->assertDontSee('Cargar nueva versión')
            ->assertDontSee('Someter a aprobación')
            ->assertDontSee('Aprobar versión');
    }

    private function documento(int $organizacionId, int $copropiedadId, int $ownerId, string $titulo): Documento
    {
        $documento = new Documento([
            'ambito' => 'copropiedad', 'propietario_documental_user_id' => $ownerId,
            'tipo' => 'documento_general', 'categoria' => 'administrativo',
            'titulo' => $titulo, 'nivel_acceso' => 'interno', 'estado' => 'activo',
        ]);
        $documento->forceFill(['organizacion_id' => $organizacionId, 'copropiedad_id' => $copropiedadId])->save();

        return $documento;
    }

    private function version(Documento $documento, int $numero, string $estado, ?string $vigenteDesde = null): void
    {
        $version = new DocumentoVersion([
            'numero' => $numero, 'estado' => $estado, 'origen' => 'usuario',
            'nombre_original' => "version-{$numero}.pdf", 'ruta_archivo' => "privado/{$documento->id}/{$numero}.pdf",
            'mime_type' => 'application/pdf', 'extension' => 'pdf', 'tamano_bytes' => 10,
            'hash_sha256' => hash('sha256', "{$documento->id}:{$numero}"), 'vigente_desde' => $vigenteDesde,
        ]);
        $version->forceFill([
            'documento_id' => $documento->id,
            'organizacion_id' => $documento->organizacion_id,
            'copropiedad_id' => $documento->copropiedad_id,
        ])->save();
    }
}
