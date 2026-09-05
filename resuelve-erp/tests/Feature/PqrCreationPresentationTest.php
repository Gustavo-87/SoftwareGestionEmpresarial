<?php

namespace Tests\Feature;

use App\Models\Pqr;
use App\Models\TipoPqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrCreationPresentationTest extends TestCase
{
    use CreatesInstitutionalContext;
    use RefreshDatabase;

    public function test_authorized_user_sees_creation_labels_help_and_accessible_submission_controls(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'residente', ['pqrs.crear']);
        TipoPqr::factory()->create(['nombre' => 'Petición']);

        $this->actingAsContextual($user)->get(route('pqrs.create'))
            ->assertOk()
            ->assertSee('Radicar una nueva PQRS')
            ->assertSee('Volver a solicitudes')
            ->assertSee('Los campos con')
            ->assertSee('Máximo 150 caracteres.')
            ->assertSee('calcula el plazo máximo de respuesta en días hábiles de Colombia')
            ->assertDontSee('name="fecha_radicacion"', false)
            ->assertDontSee('name="fecha_limite_respuesta"', false)
            ->assertSee('Puedes adjuntar hasta 8 archivos de máximo 10 MB cada uno.')
            ->assertSee('data-radicacion-submit', false)
            ->assertSee('aria-describedby="adjuntos-help fileList"', false);
    }

    public function test_user_without_creation_permission_is_forbidden_from_the_form_and_submission(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'residente', []);
        $tipo = TipoPqr::factory()->create();

        $this->actingAsContextual($user)->get(route('pqrs.create'))->assertForbidden();
        $this->post(route('pqrs.store'), $this->validPayload($tipo))->assertForbidden();
        $this->assertDatabaseCount('pqrs', 0);
    }

    public function test_invalid_submission_redirects_back_with_linked_errors_and_old_values_without_creating_a_pqr(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'residente', ['pqrs.crear']);
        $tipo = TipoPqr::factory()->create();

        $response = $this->actingAsContextual($user)
            ->from(route('pqrs.create'))
            ->post(route('pqrs.store'), [
                'asunto' => '',
                'descripcion' => '',
                'fecha_radicacion' => 'fecha-invalida',
                'fecha_limite_respuesta' => 'fecha-invalida',
                'tipo_pqr_id' => '',
            ]);

        $response->assertRedirect(route('pqrs.create'))
            ->assertSessionHasErrors([
                'asunto' => 'El asunto es obligatorio.',
                'descripcion' => 'La descripción es obligatoria.',
                'tipo_pqr_id' => 'El tipo de solicitud es obligatorio.',
            ])
            ->assertSessionHasInput('fecha_limite_respuesta', 'fecha-invalida');
        $this->assertDatabaseCount('pqrs', 0);

        $this->app['view']->share('errors', $response->getSession()->get('errors'));
        $this->withSession(['_old_input' => $response->getSession()->get('_old_input')]);
        $this->actingAsContextual($user)->view('pqrs.create', ['tipos' => collect([$tipo])])
            ->assertSee('id="pqr-error-summary"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('asunto-error', false)
            ->assertSeeText('El asunto es obligatorio.')
            ->assertSeeText('La descripción es obligatoria.')
            ->assertSeeText('El tipo de solicitud es obligatorio.')
            ->assertDontSeeText('tipo_pqr_id')
            ->assertDontSeeText('The asunto field is required.')
            ->assertDontSeeText('The tipo pqr id field is required.')
            ->assertDontSeeText('The descripcion field is required.')
            ->assertDontSee('name="fecha_limite_respuesta"', false);
    }

    public function test_authorized_submission_redirects_with_the_immutable_expediente_message_and_creates_one_pqr(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        Notification::fake();
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'residente', ['pqrs.crear']);
        $tipo = TipoPqr::factory()->create();

        $response = $this->actingAsContextual($user)->post(route('pqrs.store'), $this->validPayload($tipo, [
            'adjuntos' => [UploadedFile::fake()->create('soporte.pdf', 120, 'application/pdf')],
        ]));

        $pqr = Pqr::query()->sole();
        $response->assertRedirect(route('pqrs.show', $pqr))
            ->assertSessionHas('success', 'PQR radicada correctamente. Ya no puede ser modificada.');
        $this->assertNotSame('PQR radicada correctamente. Puedes consultarla y editarla desde el expediente.', $response->getSession()->get('success'));
        $this->assertSame($user->id, $pqr->user_id);
        $this->assertSame($organizacion->id, $pqr->organizacion_id);
        $this->assertSame($copropiedad->id, $pqr->copropiedad_id);
        $this->assertCount(1, $pqr->attachments);
        Storage::disk('local')->assertExists($pqr->attachments->first()->path);
    }

    public function test_authorized_user_sees_an_accessible_confirmation_without_persisting_a_pqr_before_confirming(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'residente', ['pqrs.crear']);
        TipoPqr::factory()->create();

        $this->actingAsContextual($user)->get(route('pqrs.create'))
            ->assertOk()
            ->assertSee('<dialog class="confirm-dialog pqr-submit-dialog" id="pqrSubmitDialog"', false)
            ->assertSee('¿Confirmas que deseas enviar esta PQRS con la información registrada?', false)
            ->assertSee('Después de radicarla no podrás modificarla.', false)
            ->assertSee('id="pqrSubmitCancel"', false)
            ->assertSee('Sí, radicar PQRS', false);

        $this->assertDatabaseCount('pqrs', 0);
    }

    public function test_attachment_limits_remain_enforced_by_the_existing_backend_validation(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'residente', ['pqrs.crear']);
        $tipo = TipoPqr::factory()->create();

        $this->actingAsContextual($user)->from(route('pqrs.create'))->post(route('pqrs.store'), $this->validPayload($tipo, [
            'adjuntos' => array_fill(0, 9, UploadedFile::fake()->create('soporte.pdf', 10, 'application/pdf')),
        ]))->assertRedirect(route('pqrs.create'))->assertSessionHasErrors('adjuntos');

        $this->actingAsContextual($user)->from(route('pqrs.create'))->post(route('pqrs.store'), $this->validPayload($tipo, [
            'adjuntos' => [UploadedFile::fake()->create('soporte.exe', 10, 'application/octet-stream')],
        ]))->assertRedirect(route('pqrs.create'))->assertSessionHasErrors('adjuntos.0');

        $this->assertDatabaseCount('pqrs', 0);
    }

    private function validPayload(TipoPqr $tipo, array $overrides = []): array
    {
        return array_merge([
            'asunto' => 'Solicitud de revisión',
            'descripcion' => 'Descripción suficiente para registrar la solicitud.',
            'fecha_radicacion' => now()->toDateString(),
            'fecha_limite_respuesta' => now()->addDays(15)->toDateString(),
            'tipo_pqr_id' => $tipo->id,
        ], $overrides);
    }
}
