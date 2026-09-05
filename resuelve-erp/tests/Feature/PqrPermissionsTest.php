<?php

namespace Tests\Feature;

use App\Models\Pqr;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\TipoPqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrPermissionsTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_resident_can_only_view_their_own_pqrs(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $resident = User::factory()->create(['role' => 'residente']);
        $other = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($resident, $organizacion, $copropiedad, 'residente', ['pqrs.ver_propias']);
        $ownPqr = $this->makePqr($resident);
        $otherPqr = $this->makePqr($other);

        $this->actingAs($resident)->get(route('pqrs.show', $ownPqr))->assertOk();
        $this->actingAs($resident)->get(route('pqrs.show', $otherPqr))->assertForbidden();
    }

    public function test_resident_cannot_edit_a_submitted_pqr(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $resident = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($resident, $organizacion, $copropiedad, 'residente', []);
        $pqr = $this->makePqr($resident);

        $this->actingAs($resident)->get(route('pqrs.edit', $pqr))->assertForbidden();
        $this->actingAs($resident)->delete(route('pqrs.destroy', $pqr))->assertForbidden();
    }

    public function test_manager_can_edit_any_pqr(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['pqrs.gestionar']);
        $pqr = $this->makePqr(User::factory()->create(['role' => 'residente']));

        $this->actingAs($manager)->get(route('pqrs.edit', $pqr))->assertOk();
    }

    public function test_resident_can_submit_a_pqr_with_a_private_attachment(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        Storage::fake('local');
        $resident = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($resident, $organizacion, $copropiedad, 'residente', ['pqrs.crear']);
        $tipo = TipoPqr::create(['nombre' => 'Petición', 'descripcion' => 'Petición']);

        $this->actingAs($resident)->post(route('pqrs.store'), [
            'asunto' => 'Solicitud con soporte',
            'descripcion' => 'Descripción completa de la solicitud.',
            'fecha_radicacion' => now()->format('Y-m-d'),
            'tipo_pqr_id' => $tipo->id,
            'adjuntos' => [UploadedFile::fake()->image('evidencia.jpg')],
        ])->assertRedirect();

        $pqr = Pqr::firstOrFail();
        $attachment = $pqr->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame($resident->id, $pqr->user_id);
    }

    public function test_inactive_membership_does_not_authorize_a_user(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $resident = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity(
            $resident,
            $organizacion,
            $copropiedad,
            'residente',
            ['pqrs.ver_propias'],
            'inactiva',
        );
        $pqr = $this->makePqr($resident);

        $this->actingAs($resident)->get(route('pqrs.show', $pqr))->assertForbidden();
    }

    public function test_membership_in_another_copropiedad_does_not_authorize_a_user(): void
    {
        $this->createInstitutionalContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create([
            'organizacion_id' => $otraOrganizacion->id,
            'nombre' => 'Otra copropiedad',
            'estado' => 'activa',
        ]);
        $resident = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity(
            $resident,
            $otraOrganizacion,
            $otraCopropiedad,
            'residente',
            ['pqrs.ver_propias'],
        );
        $pqr = $this->makePqr($resident);

        // El contexto activo del usuario es otraCopropiedad, por lo que la PQR
        // del contexto inicial es completamente invisible (404 en vez de 403).
        $this->actingAs($resident)->get(route('pqrs.show', $pqr))->assertNotFound();
    }

    private function makePqr(User $user): Pqr
    {
        $tipo = TipoPqr::firstOrCreate(['nombre' => 'Queja'], ['descripcion' => 'Queja']);

        return Pqr::factory()->create(['user_id' => $user->id, 'tipo_pqr_id' => $tipo->id]);
    }
}
