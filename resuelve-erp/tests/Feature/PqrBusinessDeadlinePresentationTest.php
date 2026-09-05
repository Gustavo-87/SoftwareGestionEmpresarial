<?php

namespace Tests\Feature;

use App\Models\Pqr;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrBusinessDeadlinePresentationTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_expediente_muestra_dias_habiles_futuros_con_fin_de_semana_y_festivo(): void
    {
        Carbon::setTestNow('2026-08-12 12:00:00 America/Bogota');
        [$org, $cop] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create([
            'estado' => 'radicada', 'fecha_radicacion' => '2026-08-09', 'fecha_limite_respuesta' => '2026-08-24',
        ]);

        $this->actingAsContextual($manager)->get(route('pqrs.show', $pqr))
            ->assertOk()->assertSee('Vence en 7 días hábiles')->assertSee('Fecha límite: 24')->assertDontSee('Vence en 12 días');
        $this->assertSame('2026-08-24', $pqr->fresh()->fecha_limite_respuesta->toDateString());
    }

    public function test_fechas_visibles_de_pqrs_usan_meses_en_espanol_sin_alterar_persistencia(): void
    {
        Carbon::setTestNow('2026-08-12 12:00:00 America/Bogota');
        [$org, $cop] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['name' => 'Gestor de plazos', 'role' => 'gestor']);
        $resident = User::factory()->create(['name' => 'Residente de plazos', 'role' => 'residente']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.listar', 'pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create([
            'user_id' => $resident->id, 'asunto' => 'Verificación determinista del plazo',
            'estado' => 'radicada', 'fecha_radicacion' => '2026-08-09', 'fecha_limite_respuesta' => '2026-08-24',
        ]);
        $englishDate = '/\b\d{2} (?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d{4}\b/';

        $show = $this->actingAsContextual($manager)->get(route('pqrs.show', $pqr));
        $show->assertOk()->assertSee('Vence en 7 días hábiles')->assertSee('Fecha límite: 24 ago 2026')->assertSee('09 ago 2026');
        preg_match('/<div class="expediente-deadline[^>]*>.*?<\/div>/s', $show->getContent(), $deadline);
        preg_match('/<dt>Fecha de radicación<\/dt><dd>09 ago 2026<\/dd>/', $show->getContent(), $filingDate);

        $edit = $this->get(route('pqrs.edit', $pqr));
        $edit->assertOk()->assertSee('09 ago 2026')->assertSee('24 ago 2026');
        preg_match('/<dt>Fecha de radicación<\/dt><dd>09 ago 2026<\/dd>/', $edit->getContent(), $readonlyFilingDate);
        preg_match('/<dt>Fecha límite de respuesta<\/dt><dd>24 ago 2026<\/dd>/', $edit->getContent(), $readonlyDeadline);

        $index = $this->get(route('pqrs.index'));
        $index->assertOk()->assertSee('24 ago 2026');
        preg_match('/<span class="deadline[^>]*>24 ago 2026<\/span>/', $index->getContent(), $inboxDate);

        $dateElements = [$deadline[0] ?? '', $filingDate[0] ?? '', $readonlyFilingDate[0] ?? '', $readonlyDeadline[0] ?? '', $inboxDate[0] ?? ''];
        foreach ($dateElements as $dateElement) {
            $this->assertNotSame('', $dateElement);
            $this->assertDoesNotMatchRegularExpression($englishDate, $dateElement);
        }
        $this->assertSame('2026-08-09', $pqr->fresh()->fecha_radicacion->toDateString());
        $this->assertSame('2026-08-24', $pqr->fresh()->fecha_limite_respuesta->toDateString());
    }

    public function test_presentacion_cubre_hoy_singular_plural_y_nunca_muestra_negativos(): void
    {
        Carbon::setTestNow('2026-08-18 12:00:00 America/Bogota');
        [$org, $cop] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.ver_todas']);
        $casos = [
            ['2026-08-18', 'Vence hoy'],
            ['2026-08-19', 'Vence en 1 día hábil'],
            ['2026-08-20', 'Vence en 2 días hábiles'],
            ['2026-08-14', 'Venció hace 1 día hábil'],
            ['2026-08-07', 'Venció hace 6 días hábiles'],
        ];

        foreach ($casos as [$limite, $texto]) {
            $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['estado' => 'radicada', 'fecha_limite_respuesta' => $limite]);
            $this->actingAsContextual($manager)->get(route('pqrs.show', $pqr))
                ->assertOk()->assertSee($texto)->assertDontSee('Vence en -', false)->assertDontSee('Venció hace -', false);
        }
    }

    public function test_respondida_y_cerrada_con_plazo_pasado_son_neutrales(): void
    {
        Carbon::setTestNow('2026-08-18 12:00:00 America/Bogota');
        [$org, $cop] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $org, $cop, 'gestor', ['pqrs.ver_todas']);

        foreach (['respondida', 'cerrada'] as $estado) {
            $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['estado' => $estado, 'fecha_limite_respuesta' => '2026-08-07']);
            $this->assertFalse($pqr->is_overdue);
            $this->actingAsContextual($manager)->get(route('pqrs.show', $pqr))
                ->assertOk()->assertSee('La gestión ya no está pendiente de respuesta.')->assertDontSee('Venció hace');
        }
    }
}
