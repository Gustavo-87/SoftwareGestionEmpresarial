<?php

namespace Tests\Feature;

use App\Application\Contexto\ContextResolver;
use App\Application\Pqrs\ConsultaPqrsContextuales;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\User;
use App\Notifications\PqrEventNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class Sprint3RemainingConsumersTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_csv_xlsx_and_pdf_reports_only_use_the_active_context(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$otraOrganizacion, $otraCopropiedad] = $this->createOtherContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', [
            'pqrs.listar',
            'pqrs.ver_todas',
            'informes.exportar',
        ]);
        Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
            'asunto' => 'VISIBLE-EN-INFORME',
        ]);
        Pqr::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create([
            'asunto' => 'SECRETO-OTRO-TENANT',
        ]);

        $csv = $this->actingAs($admin)->get(route('reports.csv', [
            'organizacion_id' => $otraOrganizacion->id,
            'copropiedad_id' => $otraCopropiedad->id,
        ]));
        $csv->assertOk();
        $csvContent = $csv->streamedContent();
        $this->assertStringContainsString('VISIBLE-EN-INFORME', $csvContent);
        $this->assertStringNotContainsString('SECRETO-OTRO-TENANT', $csvContent);

        $xlsx = $this->get(route('reports.xlsx'));
        $xlsx->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'resuelve-xlsx-');
        file_put_contents($path, $xlsx->streamedContent());
        try {
            $book = IOFactory::load($path);
            $text = collect($book->getAllSheets())
                ->map(fn ($sheet) => implode(' ', $sheet->toArray(null, true, true, false)[0] ?? [])
                    .' '.json_encode($sheet->toArray()))
                ->implode(' ');
        } finally {
            @unlink($path);
        }
        $this->assertStringContainsString('VISIBLE-EN-INFORME', $text);
        $this->assertStringNotContainsString('SECRETO-OTRO-TENANT', $text);

        $this->get(route('reports.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_workload_counts_only_pqrs_from_the_active_context(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$otraOrganizacion, $otraCopropiedad] = $this->createOtherContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['gestion.carga_ver']);
        Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
            'assigned_to_id' => $manager->id,
            'estado' => 'radicada',
        ]);
        Pqr::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create([
            'assigned_to_id' => $manager->id,
            'estado' => 'radicada',
        ]);

        $response = $this->actingAs($manager)->get(route('management.workload'));
        $response->assertOk();
        $resolvedManager = $response->viewData('users')->firstWhere('id', $manager->id);

        $this->assertSame(1, $resolvedManager->active_count);
    }

    public function test_reminders_process_each_validated_context_without_duplicates(): void
    {
        Notification::fake();
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$otraOrganizacion, $otraCopropiedad] = $this->createOtherContext();
        $localResident = User::factory()->create(['role' => 'residente']);
        $foreignResident = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($localResident, $organizacion, $copropiedad, 'residente_local', ['notificaciones.consultar']);
        $this->createContextualIdentity($foreignResident, $otraOrganizacion, $otraCopropiedad, 'residente_externo', ['notificaciones.consultar']);
        $local = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
            'user_id' => $localResident->id,
            'estado' => 'radicada',
            'fecha_limite_respuesta' => today()->addDay(),
            'last_reminder_at' => null,
        ]);
        $foreign = Pqr::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create([
            'user_id' => $foreignResident->id,
            'estado' => 'en_revision',
            'fecha_limite_respuesta' => today()->addDays(2),
            'last_reminder_at' => null,
        ]);

        $this->artisan('pqrs:send-reminders')
            ->expectsOutput('Recordatorios enviados: 2')
            ->assertSuccessful();
        Notification::assertSentToTimes($localResident, PqrEventNotification::class, 1);
        Notification::assertSentToTimes($foreignResident, PqrEventNotification::class, 1);
        $this->assertNotNull($local->fresh()->last_reminder_at);
        $this->assertNotNull($foreign->fresh()->last_reminder_at);

        $this->artisan('pqrs:send-reminders')
            ->expectsOutput('Recordatorios enviados: 0')
            ->assertSuccessful();
        Notification::assertSentToTimes($localResident, PqrEventNotification::class, 1);
        Notification::assertSentToTimes($foreignResident, PqrEventNotification::class, 1);
    }

    public function test_internal_context_query_cannot_recover_a_pqr_from_another_scope(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$otraOrganizacion, $otraCopropiedad] = $this->createOtherContext();
        $foreign = Pqr::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create();
        $contexto = app(ContextResolver::class)->resolverExplicito(
            $organizacion->id,
            $copropiedad->id
        );

        $this->expectException(ModelNotFoundException::class);
        app(ConsultaPqrsContextuales::class)->resolver($contexto, $foreign->id);
    }

    /** @return array{Organizacion, Copropiedad} */
    private function createOtherContext(): array
    {
        $organizacion = Organizacion::create(['nombre' => 'Otra Organización', 'estado' => 'activa']);
        $copropiedad = Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Otra Copropiedad',
            'estado' => 'activa',
        ]);

        return [$organizacion, $copropiedad];
    }
}
