<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class AuditLogPresentationTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_accion_tecnica_se_traduce_a_lenguaje_humano(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $org, $cop, 'admin', ['auditoria.ver']);

        AuditLog::create(['user_id' => $admin->id, 'action' => 'PATCH pqrs.quick-update', 'ip_address' => '127.0.0.1']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'POST pqrs.store', 'ip_address' => '127.0.0.1']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'PUT pqrs.update', 'ip_address' => '127.0.0.1']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'DELETE pqrs.destroy', 'ip_address' => '127.0.0.1']);

        $this->actingAsContextual($admin)->get(route('management.audit'))
            ->assertOk()
            ->assertSee('Actualización rápida de PQRS')
            ->assertSee('Radicó una PQRS')
            ->assertSee('Actualizó una PQRS')
            ->assertSee('Eliminó una PQRS')
            ->assertDontSee('pqrs.quick-update')
            ->assertDontSee('pqrs.store')
            ->assertDontSee('pqrs.update')
            ->assertDontSee('pqrs.destroy');
    }

    public function test_acciones_de_respuestas_se_traducen(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $org, $cop, 'admin', ['auditoria.ver']);

        AuditLog::create(['user_id' => $admin->id, 'action' => 'POST pqrs.replies.store', 'ip_address' => '127.0.0.1']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'POST pqrs.replies.send', 'ip_address' => '127.0.0.1']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'POST pqrs.comments.store', 'ip_address' => '127.0.0.1']);

        $this->actingAsContextual($admin)->get(route('management.audit'))
            ->assertOk()
            ->assertSee('Registró una respuesta')
            ->assertSee('Envió una respuesta oficial')
            ->assertSee('Agregó un comentario interno');
    }

    public function test_acciones_de_etiquetas_se_traducen(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $org, $cop, 'admin', ['auditoria.ver']);

        AuditLog::create(['user_id' => $admin->id, 'action' => 'Etiqueta creada', 'ip_address' => '127.0.0.1']);

        $log = AuditLog::first();
        $this->assertSame('Etiqueta creada', $log->human_action);
    }

    public function test_metodo_http_se_muestra_como_badge(): void
    {
        $log = new AuditLog(['action' => 'PATCH pqrs.quick-update']);
        $this->assertSame('PATCH', $log->method_badge);
    }

    public function test_no_se_muestra_metadata_json(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $org, $cop, 'admin', ['auditoria.ver']);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'PATCH pqrs.quick-update',
            'ip_address' => '127.0.0.1',
            'metadata' => ['path' => 'pqrs/1/accion-rapida', 'before' => ['estado' => 'radicada']],
        ]);

        $this->actingAsContextual($admin)->get(route('management.audit'))
            ->assertOk()
            ->assertDontSee('"path"')
            ->assertDontSee('"before"')
            ->assertDontSee('"estado"');
    }

    public function test_fecha_se_muestra_en_formato_humano(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $org, $cop, 'admin', ['auditoria.ver']);

        AuditLog::create(['user_id' => $admin->id, 'action' => 'PATCH pqrs.quick-update', 'ip_address' => '127.0.0.1']);

        $this->actingAsContextual($admin)->get(route('management.audit'))
            ->assertOk()
            ->assertSee('hace');
    }
}
