<?php

namespace Tests\Feature;

use App\Application\Contexto\ContextResolver;
use App\Application\Notificaciones\ConsultaNotificacionesContextuales;
use App\Application\Notificaciones\EmitirNotificacionPqrs;
use App\Application\Notificaciones\ResolverDestinatariosNotificacionPqrs;
use App\Models\{Copropiedad, Organizacion, Pqr, User};
use App\Notifications\PqrEventNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class NotificacionesContextualesTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_payload_is_contextual_and_has_no_url(): void
    {
        [$org, $cop] = $this->createInstitutionalContext(); $user = User::factory()->create(['role' => 'residente']);
        [, , $contexto] = $this->createContextualIdentity($user, $org, $cop, 'lector', ['notificaciones.consultar']);
        $pqr = $this->pqr($org, $cop, $user); Notification::fake();
        app(EmitirNotificacionPqrs::class)->ejecutar($contexto, $pqr, 'pqr_estado_actualizado');
        Notification::assertSentTo($user, PqrEventNotification::class, function ($notification) use ($user, $pqr, $org, $cop) {
            $this->assertSame(['resource_type' => 'pqr', 'resource_id' => $pqr->id, 'event' => 'pqr_estado_actualizado', 'organizacion_id' => $org->id, 'copropiedad_id' => $cop->id, 'title' => 'Estado de solicitud actualizado', 'message' => 'La PQR-'.str_pad((string) $pqr->id, 4, '0', STR_PAD_LEFT).' ahora está Radicada.'], $notification->toArray($user));
            return true;
        });
    }

    public function test_query_rejects_missing_inactive_future_expired_or_insufficient_membership(): void
    {
        [$org, $cop] = $this->createInstitutionalContext(); $consulta = app(ConsultaNotificacionesContextuales::class); $resolver = app(ContextResolver::class);
        foreach ([['sin', null, null, null], ['inactiva', 'inactiva', null, null], ['futura', 'activa', now()->addDay(), null], ['vencida', 'activa', null, now()->subDay()], ['sinpermiso', 'activa', null, null]] as [$role, $estado, $desde, $hasta]) {
            $user = User::factory()->create(['role' => $role]);
            if ($estado) $this->createContextualIdentity($user, $org, $cop, $role, $role === 'sinpermiso' ? [] : ['notificaciones.consultar'], $estado, $desde, $hasta);
            $contexto = $resolver->resolverExplicito($org->id, $cop->id, $user->id);
            try { $consulta->para($contexto, $user); $this->fail('Debía rechazar el acceso sin Membresía vigente y permiso.'); } catch (AuthorizationException) { $this->assertTrue(true); }
        }
    }

    public function test_context_and_recipient_deduplication_are_enforced(): void
    {
        [$org, $cop] = $this->createInstitutionalContext(); $otherOrg = Organizacion::create(['nombre' => 'Otra', 'estado' => 'activa']); $otherCop = Copropiedad::create(['organizacion_id' => $otherOrg->id, 'nombre' => 'Otra', 'estado' => 'activa']);
        $user = User::factory()->create(['role' => 'otro']); $this->createContextualIdentity($user, $org, $cop, 'operador', ['notificaciones.consultar', 'pqrs.gestionar']);
        [, , $contexto] = $this->createContextualIdentity($user, $otherOrg, $otherCop, 'operador_otro', ['notificaciones.consultar', 'pqrs.gestionar']);
        $pqr = $this->pqr($org, $cop, $user); $resolver = app(ResolverDestinatariosNotificacionPqrs::class);
        $this->assertCount(1, $resolver->resolver(app(ContextResolver::class)->resolverExplicito($org->id, $cop->id, $user->id), $pqr, 'pqr_recordatorio_vencimiento'));
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(EmitirNotificacionPqrs::class)->ejecutar($contexto, $pqr, 'pqr_estado_actualizado');
    }

    private function pqr(Organizacion $org, Copropiedad $cop, User $user): Pqr
    {
        $pqr = Pqr::factory()->create(['user_id' => $user->id, 'estado' => 'radicada']); $pqr->forceFill(['organizacion_id' => $org->id, 'copropiedad_id' => $cop->id])->save(); return $pqr;
    }
}
