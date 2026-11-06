<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\Pqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PanelPqrsRecientesTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_el_panel_limita_a_cinco_ordena_y_excluye_otra_copropiedad(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($user, $org, $cop, 'admin', ['pqrs.listar', 'pqrs.ver_todas', 'pqrs.ver_propias']);
        $ids = [];
        for ($i = 0; $i < 7; $i++) {
            $ids[] = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $user->id, 'asunto' => 'Solicitud reciente '.$i, 'created_at' => now()->subDays(7 - $i)])->id;
        }
        $otra = Copropiedad::factory()->create(['organizacion_id' => $org->id]);
        Pqr::factory()->paraContexto($org, $otra)->create(['user_id' => $user->id, 'asunto' => 'EXTERNA_NO_VISIBLE']);
        $this->actingAsContextual($user)->get(route('panel'))->assertOk()
            ->assertViewHas('pqrsRecientes', fn ($registros) => $registros->pluck('id')->all() === array_reverse(array_slice($ids, -5)))
            ->assertSee('PQRS recientes')->assertSee('dashboard-pqrs-mobile')
            ->assertDontSee('Solicitud reciente 0')->assertDontSee('EXTERNA_NO_VISIBLE');
    }

    public function test_sin_ver_todas_se_muestran_solo_las_propias(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'residente']);
        $otro = User::factory()->create();
        $this->createContextualIdentity($user, $org, $cop, 'residente', ['pqrs.listar', 'pqrs.ver_propias']);
        $propia = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $user->id, 'asunto' => 'Solicitud propia']);
        Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $otro->id, 'asunto' => 'AJENA_NO_VISIBLE']);
        $this->actingAsContextual($user)->get(route('panel'))->assertOk()->assertSee('Solicitud propia')->assertDontSee('AJENA_NO_VISIBLE')
            ->assertViewHas('pqrsRecientes', fn ($registros) => $registros->modelKeys() === [$propia->id]);
    }

    public function test_listar_no_concede_enlace_a_un_expediente_sin_permiso_de_ver(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $cop, 'solo_listar', ['pqrs.listar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $user->id]);
        $this->actingAsContextual($user)->get(route('panel'))->assertOk()->assertDontSee('href="'.route('pqrs.show', $pqr).'"', false);
        $this->get(route('pqrs.show', $pqr))->assertForbidden();
    }
}
