<?php

namespace Tests\Feature;

use App\Models\Pqr;
use App\Models\PqrTag;
use App\Models\ResponseTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrExpedientePresentationTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_apoyo_con_permiso_general_lee_sin_gestionar_una_pqrs_asignada_a_otra_persona(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $apoyo = User::factory()->create(['role' => 'apoyo']);
        $otroResponsable = User::factory()->create(['role' => 'apoyo']);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($apoyo, $org, $cop, 'apoyo', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create([
            'user_id' => $residente->id,
            'assigned_to_id' => $otroResponsable->id,
        ]);
        $pqr->internalComments()->create(['user_id' => $otroResponsable->id, 'body' => 'Dato interno restringido.']);
        $pqr->replies()->create(['user_id' => $otroResponsable->id, 'body' => 'Borrador interno restringido.', 'is_draft' => true]);
        ResponseTemplate::create(['name' => 'Plantilla interna restringida', 'body' => 'Contenido auxiliar restringido.']);
        $tag = PqrTag::make([
            'name' => 'Etiqueta gestionable restringida',
            'color' => '#123456',
        ]);
        $tag->organizacion()->associate($org);
        $tag->copropiedad()->associate($cop);
        $tag->save();

        $response = $this->actingAsContextual($apoyo)->get(route('pqrs.show', $pqr));

        $response->assertOk()
            ->assertViewHas('puedeGestionar', false)
            ->assertDontSee('Gestión administrativa')
            ->assertDontSee('Enviar respuesta al radicador')
            ->assertDontSee('Comentarios internos')
            ->assertDontSee('Guardar etiquetas')
            ->assertDontSee('Usar plantilla')
            ->assertDontSee('Dato interno restringido.')
            ->assertDontSee('Borrador interno restringido.')
            ->assertDontSee('Plantilla interna restringida')
            ->assertDontSee('Etiqueta gestionable restringida');
        $this->assertFalse($response->viewData('pqr')->relationLoaded('internalComments'));
        $this->assertTrue($response->viewData('templates')->isEmpty());
        $this->assertTrue($response->viewData('availableTags')->isEmpty());
    }

    public function test_apoyo_con_autorizacion_efectiva_ve_la_gestion_de_su_pqrs_asignada(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $apoyo = User::factory()->create(['role' => 'apoyo']);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($apoyo, $org, $cop, 'apoyo', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create([
            'user_id' => $residente->id,
            'assigned_to_id' => $apoyo->id,
        ]);

        $response = $this->actingAsContextual($apoyo)->get(route('pqrs.show', $pqr));

        $response->assertOk()
            ->assertViewHas('puedeGestionar', true)
            ->assertSee('Gestión administrativa')
            ->assertSee('Enviar respuesta al radicador')
            ->assertSee('Comentarios internos')
            ->assertSee('No hay etiquetas disponibles')
            ->assertDontSee('Guardar etiquetas');
        $this->assertTrue($response->viewData('pqr')->relationLoaded('internalComments'));
    }

    public function test_manager_sees_existing_internal_sections_and_natural_upcoming_deadline(): void
    {
        Carbon::setTestNow('2026-08-12 12:00:00 America/Bogota');
        [$org, $cop] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $resident = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $resident->id, 'fecha_limite_respuesta' => now()->addDay(), 'estado' => 'en_revision']);
        $pqr->replies()->create(['user_id' => $manager->id, 'body' => 'Respuesta localizada.', 'is_draft' => false, 'sent_at' => now()->subWeek()]);
        $comment = $pqr->internalComments()->create(['user_id' => $manager->id, 'body' => 'Comentario localizado.']);
        $comment->update(['created_at' => now()->subWeek(), 'updated_at' => now()->subWeek()]);
        $activity = $pqr->activities()->create(['user_id' => $manager->id, 'action' => 'sent_reply', 'description' => 'Envió una respuesta.']);
        $activity->update(['created_at' => now()->subWeek(), 'updated_at' => now()->subWeek()]);

        $this->actingAsContextual($manager)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertSee('Expediente · PQR-')
            ->assertSee('Vence en 1 día hábil')
            ->assertSee('Próximo paso')
            ->assertSee('Comentarios internos')
            ->assertSee('Historial de actuaciones')
            ->assertSee('Gestionar PQRS')
            ->assertSee('hace 1 semana')
            ->assertDontSee('week ago')
            ->assertDontSee('days ago')
            ->assertDontSee('hours ago')
            ->assertDontSee('minutes ago')
            ->assertDontSee('día(s)', false)
            ->assertDontSee('Vence en -', false);
    }

    public function test_resident_does_not_see_internal_content_and_gets_natural_overdue_deadline(): void
    {
        Carbon::setTestNow('2026-08-12 12:00:00 America/Bogota');
        [$org, $cop] = $this->createInstitutionalContext();
        $resident = User::factory()->create(['role' => 'residente']);
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($resident, $org, $cop, 'residente', ['pqrs.ver_propias']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $resident->id, 'fecha_limite_respuesta' => now()->subDays(2), 'estado' => 'en_revision']);
        $pqr->internalComments()->create(['user_id' => $manager->id, 'body' => 'Solo equipo']);

        $this->actingAsContextual($resident)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertSee('Venció hace 2 días hábiles')
            ->assertDontSee('Solo equipo')
            ->assertDontSee('Comentarios internos')
            ->assertDontSee('Guardar etiquetas')
            ->assertDontSee('Borrador interno')
            ->assertDontSee('día(s)', false);
    }

    public function test_authorized_manager_sees_administrative_management_with_current_state_and_assignee(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $assignee = User::factory()->create(['name' => 'Responsable asignado', 'role' => 'gestor']);
        $resident = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $this->createContextualIdentity($assignee, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $resident->id, 'estado' => 'en_revision', 'assigned_to_id' => $assignee->id]);

        $this->actingAsContextual($manager)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertSee('Gestión administrativa')
            ->assertSee('Actualiza el estado y el responsable de esta PQRS.')
            ->assertSee('Los cambios quedan registrados en el historial.')
            ->assertSee('En revisión')
            ->assertSee('Responsable asignado')
            ->assertSee(route('pqrs.edit', $pqr), false);
    }

    public function test_authorized_manager_sees_unassigned_responsibility_and_administrative_management(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $resident = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $resident->id, 'assigned_to_id' => null]);

        $this->actingAsContextual($manager)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertSee('Gestión administrativa')
            ->assertSee('Sin asignar');
    }

    public function test_administrative_management_preserves_each_existing_state_label(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);

        foreach (['radicada' => 'Radicada', 'en_revision' => 'En revisión', 'respondida' => 'Respondida', 'cerrada' => 'Cerrada'] as $state => $label) {
            $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['estado' => $state]);

            $this->actingAsContextual($manager)->get(route('pqrs.show', $pqr))
                ->assertOk()
                ->assertSee('Gestión administrativa')
                ->assertSee($label);
        }
    }

    public function test_resident_and_auditor_do_not_see_administrative_management_or_edit_access(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $resident = User::factory()->create(['role' => 'residente']);
        $auditor = User::factory()->create(['role' => 'auditor']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $resident->id]);
        $this->createContextualIdentity($resident, $org, $cop, 'residente', ['pqrs.ver_propias']);
        $this->createContextualIdentity($auditor, $org, $cop, 'auditor', ['pqrs.ver_todas']);

        $this->actingAsContextual($resident)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertDontSee('Gestión administrativa')
            ->assertDontSee('Actualiza el estado y el responsable de esta PQRS.')
            ->assertDontSee(route('pqrs.edit', $pqr), false);
        $this->get(route('pqrs.edit', $pqr))->assertForbidden();

        $this->actingAsContextual($auditor)->get(route('pqrs.show', $pqr))
            ->assertOk()
            ->assertDontSee('Gestión administrativa')
            ->assertDontSee('Actualiza el estado y el responsable de esta PQRS.')
            ->assertDontSee(route('pqrs.edit', $pqr), false);
        $this->get(route('pqrs.edit', $pqr))->assertForbidden();
    }
}
