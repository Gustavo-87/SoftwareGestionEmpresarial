<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\Documento;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\TipoPqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PanelOperativoTest extends TestCase
{
    use CreatesInstitutionalContext;
    use RefreshDatabase;

    public function test_panel_shows_active_copropiedad_and_authorized_navigation(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'residente', [
            'pqrs.listar', 'pqrs.ver_todas', 'pqrs.crear', 'documentos.consultar', 'notificaciones.consultar',
        ]);

        $this->actingAsContextual($user)->get(route('panel'))
            ->assertOk()
            ->assertSee($copropiedad->nombre)
            ->assertSee('Inicio')
            ->assertSee('PQRS')
            ->assertSee('Personas')
            ->assertSee('Documentos')
            ->assertSee('Notificaciones');
    }

    public function test_navigation_hides_unauthorized_access_but_backend_rejects_it(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'admin', []);

        $this->actingAsContextual($user)->get(route('panel'))
            ->assertOk()
            ->assertDontSee('>Documentos<', false)
            ->assertDontSee('>Notificaciones<', false);

        $this->withExceptionHandling();
        $this->actingAsContextual($user)->get(route('documentos.index'))->assertForbidden()->assertSee('No tienes autorización');
        $this->actingAsContextual($user)->get(route('pqrs.index'))->assertForbidden();
    }

    public function test_panel_summary_uses_only_the_active_context_and_keeps_empty_states(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create(['organizacion_id' => $otraOrganizacion->id, 'nombre' => 'Otra copropiedad', 'estado' => 'activa']);
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar', 'pqrs.ver_todas']);
        $tipo = TipoPqr::factory()->create();

        Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['user_id' => $user->id, 'tipo_pqr_id' => $tipo->id, 'estado' => 'radicada']);
        Pqr::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create(['user_id' => $user->id, 'tipo_pqr_id' => $tipo->id, 'estado' => 'radicada']);

        $this->actingAsContextual($user)->get(route('panel'))
            ->assertOk()
            ->assertSee('>1<', false)
            ->assertSee('No hay accesos adicionales disponibles');
    }

    public function test_panel_shows_overdue_metric_when_present(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar', 'pqrs.ver_todas']);
        $tipo = TipoPqr::factory()->create();

        Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
            'user_id' => $user->id,
            'tipo_pqr_id' => $tipo->id,
            'estado' => 'radicada',
            'fecha_limite_respuesta' => now()->subDays(5),
        ]);

        $this->actingAsContextual($user)->get(route('panel'))
            ->assertOk()
            ->assertSee('Vencidas')
            ->assertSee('>1<', false);
    }

    public function test_panel_shows_zero_overdue_when_none_exist(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar', 'pqrs.ver_todas']);
        $tipo = TipoPqr::factory()->create();

        Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
            'user_id' => $user->id,
            'tipo_pqr_id' => $tipo->id,
            'estado' => 'radicada',
            'fecha_limite_respuesta' => now()->addDays(10),
        ]);

        $this->actingAsContextual($user)->get(route('panel'))
            ->assertOk()
            ->assertSee('Vencidas')
            ->assertSee('>0<', false)
            ->assertSee('No hay PQRS con plazo superado');
    }
}
