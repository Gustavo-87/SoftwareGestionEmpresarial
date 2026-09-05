<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class DocumentoExperienceTest extends TestCase
{
    use CreatesInstitutionalContext;
    use RefreshDatabase;

    public function test_owner_from_another_context_is_rejected_by_the_existing_document_flow(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create(['organizacion_id' => $otraOrganizacion->id, 'nombre' => 'Otra copropiedad', 'estado' => 'activa']);
        $manager = User::factory()->create();
        $externalOwner = User::factory()->create();
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['documentos.gestionar']);
        $this->createContextualIdentity($externalOwner, $otraOrganizacion, $otraCopropiedad, 'residente', []);

        $this->actingAsContextual($manager)->post(route('documentos.store'), [
            'tipo' => 'acta', 'categoria' => 'administrativo', 'titulo' => 'Acta',
            'nivel_acceso' => 'interno', 'propietario_documental_user_id' => $externalOwner->id,
        ])->assertSessionHasErrors('propietario_documental_user_id');

        $this->assertDatabaseCount('documentos', 0);
    }
}
