<?php

namespace Tests\Feature;

use App\Domain\GestionDocumental\Enums\AmbitoDocumentoEnum;
use App\Models\Copropiedad;
use App\Models\Documento;
use App\Models\Organizacion;
use App\Models\SiteSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class DocumentoReportPdfTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    private function crearDocumento(Organizacion $org, Copropiedad $cop, array $atributos = []): Documento
    {
        return Documento::factory()->paraContexto($org, $cop)->create($atributos + [
            'titulo' => 'Documento de la copropiedad',
            'descripcion' => 'Descripción del documento',
            'propietario_documental_user_id' => User::factory()->create()->id,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('reports.documentos.pdf'))->assertRedirect(route('login'));
    }

    public function test_requires_export_permission(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['documentos.consultar']);

        $this->actingAs($user)->get(route('reports.documentos.pdf'))->assertForbidden();
    }

    public function test_requires_documentos_contextual_permission(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'exportador', ['informes.exportar']);

        $this->actingAs($user)->get(route('reports.documentos.pdf'))->assertForbidden();
    }

    public function test_global_admin_role_is_not_enough(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($user, $org, $property, 'lector', []);

        $this->actingAs($user)->get(route('reports.documentos.pdf'))->assertForbidden();
    }

    public function test_pdf_is_generated_with_filters_and_download_headers(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['informes.exportar', 'documentos.consultar']);
        $this->crearDocumento($org, $property, ['estado' => 'activo', 'created_at' => '2026-10-12 09:00']);

        $response = $this->actingAs($user)->get(route('reports.documentos.pdf', [
            'desde' => '2026-10-01', 'hasta' => '2026-10-31', 'estado' => 'activo',
        ]))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString('informe-documentos-'.now()->format('Ymd'), (string) $response->headers->get('content-disposition'));
    }

    public function test_pdf_endpoint_rejects_invalid_filters(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['informes.exportar', 'documentos.consultar']);

        $this->actingAs($user)->get(route('reports.documentos.pdf', ['estado' => 'inexistente']))
            ->assertSessionHasErrors('estado');
        $this->get(route('reports.documentos.pdf', ['desde' => '2026-10-31', 'hasta' => '2026-10-01']))
            ->assertSessionHasErrors('hasta');
    }

    public function test_template_includes_identity_indicators_and_detail(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $propietario = User::factory()->create(['name' => 'Propietario documental']);
        $documento = $this->crearDocumento($org, $property, ['titulo' => 'Reglamento de propiedad horizontal', 'propietario_documental_user_id' => $propietario->id]);
        $settings = SiteSetting::current();

        $html = view('reports.documentos', [
            'rows' => Documento::query()->whereKey($documento->id)->with(['propietarioDocumental', 'versiones'])->get(),
            'settings' => $settings,
            'copropiedad' => $property,
            'stats' => ['total' => 1, 'activos' => 1, 'archivados' => 0],
            'period' => 'Sin registros - Sin registros',
            'generatedAt' => Carbon::parse('2026-10-16 10:00'),
        ])->render();

        $this->assertStringContainsString($settings->nombre_conjunto, $html);
        $this->assertStringContainsString('Informe de documentos', $html);
        $this->assertStringContainsString('Total del periodo', $html);
        $this->assertStringContainsString('Reglamento de propiedad horizontal', $html);
        $this->assertStringContainsString('Propietario documental', $html);
    }

    public function test_section_excludes_records_from_other_contexts_and_scopes(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $otherOrg = Organizacion::create(['nombre' => 'Otra', 'estado' => 'activa']);
        $otherProperty = Copropiedad::create(['organizacion_id' => $otherOrg->id, 'nombre' => 'Otra', 'estado' => 'activa']);
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['pqrs.listar', 'pqrs.ver_todas', 'informes.exportar', 'documentos.consultar']);
        $visible = $this->crearDocumento($org, $property);
        $this->crearDocumento($otherOrg, $otherProperty);
        Documento::factory()->create([
            'organizacion_id' => $org->id, 'copropiedad_id' => null,
            'ambito' => AmbitoDocumentoEnum::ORGANIZACION,
        ]);

        $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('documentos', fn ($resumen) => $resumen['rows']->modelKeys() === [$visible->id]);
    }

    public function test_section_filters_by_state_and_registration_period(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['pqrs.listar', 'pqrs.ver_todas', 'informes.exportar', 'documentos.consultar']);
        $dentro = $this->crearDocumento($org, $property, ['estado' => 'activo', 'created_at' => '2026-10-12 09:00']);
        $this->crearDocumento($org, $property, ['estado' => 'activo', 'created_at' => '2026-10-25 09:00']);
        $this->crearDocumento($org, $property, ['estado' => 'archivado', 'created_at' => '2026-10-12 09:00']);

        $this->actingAs($user)->get(route('reports.index', [
            'documentos_desde' => '2026-10-01', 'documentos_hasta' => '2026-10-20', 'documentos_estado' => 'activo',
        ]))
            ->assertOk()
            ->assertViewHas('documentos', fn ($resumen) => $resumen['rows']->modelKeys() === [$dentro->id] && $resumen['total'] === 1 && $resumen['activos'] === 1);
        $this->get(route('reports.index', ['documentos_estado' => 'archivado']))
            ->assertOk()
            ->assertViewHas('documentos', fn ($resumen) => $resumen['archivados'] === 1 && $resumen['total'] === 1);
    }

    public function test_download_link_carries_active_filters(): void
    {
        [$org, $property] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $property, 'gestor', ['pqrs.listar', 'pqrs.ver_todas', 'informes.exportar', 'documentos.consultar']);
        $filtros = ['documentos_desde' => '2026-10-01', 'documentos_hasta' => '2026-10-31', 'documentos_estado' => 'archivado'];

        $this->actingAs($user)->get(route('reports.index', $filtros))
            ->assertOk()
            ->assertSee(route('reports.documentos.pdf', [
                'desde' => '2026-10-01', 'hasta' => '2026-10-31', 'estado' => 'archivado',
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

        $this->assertStringNotContainsString('informes/documentos.pdf', $html);
    }
}
