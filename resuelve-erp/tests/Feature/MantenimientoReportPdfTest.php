<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\Mantenimiento;
use App\Models\Organizacion;
use App\Models\SiteSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class MantenimientoReportPdfTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    private function crearMantenimiento(Organizacion $org, Copropiedad $cop, array $atributos = []): Mantenimiento
    {
        return Mantenimiento::forceCreate($atributos + [
            'organizacion_id' => $org->id,
            'copropiedad_id' => $cop->id,
            'solicitante_id' => User::factory()->create()->id,
            'titulo' => 'Solicitud de mantenimiento',
            'descripcion' => 'Descripción de la solicitud',
            'estado' => 'pendiente',
            'fecha_programada' => null,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('reports.mantenimiento.pdf'))->assertRedirect(route('login'));
    }

    public function test_requires_export_permission(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['mantenimiento.ver_todas']);

        $this->actingAs($user)->get(route('reports.mantenimiento.pdf'))->assertForbidden();
    }

    public function test_requires_mantenimiento_contextual_permission(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'exportador', ['informes.exportar']);

        $this->actingAs($user)->get(route('reports.mantenimiento.pdf'))->assertForbidden();
    }

    public function test_global_admin_role_is_not_enough(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($user, $org, $property, 'lector', []);

        $this->actingAs($user)->get(route('reports.mantenimiento.pdf'))->assertForbidden();
    }

    public function test_pdf_is_generated_with_filters_and_download_headers(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['informes.exportar', 'mantenimiento.ver_todas']);
        $this->crearMantenimiento($org, $property, ['estado' => 'finalizado', 'fecha_programada' => '2026-10-12']);

        $response = $this->actingAs($user)->get(route('reports.mantenimiento.pdf', [
            'desde' => '2026-10-01', 'hasta' => '2026-10-31', 'estado' => 'finalizado',
        ]))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString('informe-mantenimiento-'.now()->format('Ymd'), (string) $response->headers->get('content-disposition'));
    }

    public function test_pdf_endpoint_rejects_invalid_filters(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['informes.exportar', 'mantenimiento.ver_todas']);

        $this->actingAs($user)->get(route('reports.mantenimiento.pdf', ['estado' => 'inexistente']))
            ->assertSessionHasErrors('estado');
        $this->get(route('reports.mantenimiento.pdf', ['desde' => '2026-10-31', 'hasta' => '2026-10-01']))
            ->assertSessionHasErrors('hasta');
    }

    public function test_template_includes_identity_indicators_and_detail(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $responsable = User::factory()->create(['name' => 'Responsable asignado']);
        $solicitud = $this->crearMantenimiento($org, $property, ['titulo' => 'Reparación de bombas', 'responsable_id' => $responsable->id]);
        $settings = SiteSetting::current();

        $html = view('reports.mantenimiento', [
            'rows' => Mantenimiento::query()->whereKey($solicitud->id)->with(['solicitante', 'responsable'])->get(),
            'settings' => $settings,
            'copropiedad' => $property,
            'stats' => ['total' => 1, 'pendientes' => 1, 'en_proceso' => 0, 'finalizados' => 0, 'programados' => 0, 'atrasados' => 0],
            'period' => 'Sin registros - Sin registros',
            'generatedAt' => Carbon::parse('2026-10-16 10:00'),
        ])->render();

        $this->assertStringContainsString($settings->nombre_conjunto, $html);
        $this->assertStringContainsString('Informe de mantenimiento', $html);
        $this->assertStringContainsString('Total del periodo', $html);
        $this->assertStringContainsString('Reparación de bombas', $html);
        $this->assertStringContainsString('Responsable asignado', $html);
    }

    public function test_section_excludes_records_from_other_contexts(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $otherOrg = Organizacion::create(['nombre' => 'Otra', 'estado' => 'activa']);
        $otherProperty = Copropiedad::create(['organizacion_id' => $otherOrg->id, 'nombre' => 'Otra', 'estado' => 'activa']);
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['pqrs.listar', 'pqrs.ver_todas', 'informes.exportar', 'mantenimiento.ver_todas']);
        $visible = $this->crearMantenimiento($org, $property);
        $ajena = $this->crearMantenimiento($org, $property);
        $this->crearMantenimiento($otherOrg, $otherProperty);

        $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('mantenimiento', fn ($resumen) => $resumen['rows']->modelKeys() === [$visible->id, $ajena->id]);
    }

    public function test_section_scopes_to_own_records_without_ver_todas(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'apoyo', ['pqrs.listar', 'pqrs.ver_propias', 'informes.exportar', 'mantenimiento.ver_propias']);
        $propia = $this->crearMantenimiento($org, $property, ['solicitante_id' => $user->id]);
        $this->crearMantenimiento($org, $property);

        $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('mantenimiento', fn ($resumen) => $resumen['rows']->modelKeys() === [$propia->id]);
    }

    public function test_filters_by_state_and_responsible(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $responsable = User::factory()->create();
        $otro = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['pqrs.listar', 'pqrs.ver_todas', 'informes.exportar', 'mantenimiento.ver_todas']);
        $pendiente = $this->crearMantenimiento($org, $property, ['responsable_id' => $responsable->id]);
        $finalizado = $this->crearMantenimiento($org, $property, ['estado' => 'finalizado', 'responsable_id' => $otro->id]);

        $this->actingAs($user)->get(route('reports.index', ['mantenimiento_estado' => 'finalizado']))
            ->assertOk()
            ->assertViewHas('mantenimiento', fn ($resumen) => $resumen['rows']->modelKeys() === [$finalizado->id] && $resumen['total'] === 1);
        $this->get(route('reports.index', ['mantenimiento_responsable_id' => $responsable->id]))
            ->assertOk()
            ->assertViewHas('mantenimiento', fn ($resumen) => $resumen['rows']->modelKeys() === [$pendiente->id]);
    }

    public function test_filters_by_scheduled_period(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['pqrs.listar', 'pqrs.ver_todas', 'informes.exportar', 'mantenimiento.ver_todas']);
        $dentro = $this->crearMantenimiento($org, $property, ['fecha_programada' => '2026-10-12']);
        $this->crearMantenimiento($org, $property, ['fecha_programada' => '2026-10-25']);
        $this->crearMantenimiento($org, $property, ['fecha_programada' => null]);

        $this->actingAs($user)->get(route('reports.index', [
            'mantenimiento_desde' => '2026-10-01', 'mantenimiento_hasta' => '2026-10-20',
        ]))
            ->assertOk()
            ->assertViewHas('mantenimiento', fn ($resumen) => $resumen['rows']->modelKeys() === [$dentro->id]);
    }

    public function test_download_link_carries_active_filters(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $responsable = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['pqrs.listar', 'pqrs.ver_todas', 'informes.exportar', 'mantenimiento.ver_todas']);
        $filtros = ['mantenimiento_desde' => '2026-10-01', 'mantenimiento_hasta' => '2026-10-31', 'mantenimiento_estado' => 'finalizado', 'mantenimiento_responsable_id' => $responsable->id];

        $this->actingAs($user)->get(route('reports.index', $filtros))
            ->assertOk()
            ->assertSee(route('reports.mantenimiento.pdf', [
                'desde' => '2026-10-01', 'hasta' => '2026-10-31', 'estado' => 'finalizado', 'responsable_id' => $responsable->id,
            ]));
    }

    public function test_download_button_is_absent_without_module_permission(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['pqrs.listar', 'pqrs.ver_todas', 'informes.exportar']);

        $html = $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('informes/mantenimiento.pdf', $html);
    }
}
