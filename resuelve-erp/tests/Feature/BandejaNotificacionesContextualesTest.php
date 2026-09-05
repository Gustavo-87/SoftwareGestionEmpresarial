<?php

namespace Tests\Feature;

use App\Models\{Copropiedad, MembresiaCopropiedad, Organizacion, Pqr, User};
use App\Notifications\PqrEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class BandejaNotificacionesContextualesTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_list_and_unread_counter_are_contextual_and_paginated(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $otherOrg = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $otherCop = Copropiedad::create(['organizacion_id' => $otherOrg->id, 'nombre' => 'Otra copropiedad', 'estado' => 'activa']);
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $cop, 'residente', ['notificaciones.consultar', 'pqrs.ver_propias']);
        $pqr = $this->pqr($org, $cop, $user);

        foreach (range(1, 16) as $index) {
            $this->notificacion($user, $org, $cop, $pqr->id, title: "Local {$index}");
        }
        $this->notificacion($user, $otherOrg, $otherCop, $pqr->id, title: 'No debe aparecer');

        $this->actingAsContextual($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertViewHas('notifications', fn ($items) => $items->total() === 16 && $items->count() === 15)
            ->assertViewHas('unreadNotificationsCount', 16)
            ->assertSee('Local 1')
            ->assertDontSee('No debe aparecer');
    }

    public function test_owner_can_open_notification_and_it_is_marked_as_read(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $cop, 'residente', ['notificaciones.consultar', 'pqrs.ver_propias']);
        $pqr = $this->pqr($org, $cop, $user);
        $id = $this->notificacion($user, $org, $cop, $pqr->id);

        $this->actingAsContextual($user)
            ->patch(route('notifications.read', $id))
            ->assertRedirect(route('pqrs.show', $pqr));

        $this->assertNotNull(DB::table('notifications')->where('id', $id)->value('read_at'));
    }

    public function test_other_user_cannot_open_notification(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $this->createContextualIdentity($owner, $org, $cop, 'residente', ['notificaciones.consultar', 'pqrs.ver_propias']);
        $this->createContextualIdentity($intruder, $org, $cop, 'otro', ['notificaciones.consultar', 'pqrs.ver_propias']);
        $pqr = $this->pqr($org, $cop, $owner);
        $id = $this->notificacion($owner, $org, $cop, $pqr->id);

        $this->actingAsContextual($intruder)
            ->patch(route('notifications.read', $id))
            ->assertNotFound();

        $this->assertNull(DB::table('notifications')->where('id', $id)->value('read_at'));
    }

    public function test_mark_all_only_changes_active_context(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $otherOrg = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $otherCop = Copropiedad::create(['organizacion_id' => $otherOrg->id, 'nombre' => 'Otra copropiedad', 'estado' => 'activa']);
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $cop, 'residente', ['notificaciones.consultar']);
        $local = $this->notificacion($user, $org, $cop, 1);
        $foreign = $this->notificacion($user, $otherOrg, $otherCop, 2);

        $this->actingAsContextual($user)
            ->patch(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertNotNull(DB::table('notifications')->where('id', $local)->value('read_at'));
        $this->assertNull(DB::table('notifications')->where('id', $foreign)->value('read_at'));
    }

    public function test_revoked_external_and_missing_resources_are_not_revealed_or_marked_as_read(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $otherOrg = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $otherCop = Copropiedad::create(['organizacion_id' => $otherOrg->id, 'nombre' => 'Otra copropiedad', 'estado' => 'activa']);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->createContextualIdentity($user, $org, $cop, 'residente', ['notificaciones.consultar', 'pqrs.ver_propias']);
        $revokedPqr = $this->pqr($org, $cop, $other);
        $externalPqr = $this->pqr($otherOrg, $otherCop, $user);
        $ids = [
            $this->notificacion($user, $org, $cop, $revokedPqr->id),
            $this->notificacion($user, $org, $cop, $externalPqr->id),
            $this->notificacion($user, $org, $cop, 999999),
        ];

        foreach ($ids as $id) {
            $this->actingAsContextual($user)
                ->patch(route('notifications.read', $id))
                ->assertNotFound();
            $this->assertNull(DB::table('notifications')->where('id', $id)->value('read_at'));
        }
    }

    public function test_read_mutations_require_patch_routes_and_forms_have_csrf_without_persisted_urls(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $cop, 'residente', ['notificaciones.consultar', 'pqrs.ver_propias']);
        $pqr = $this->pqr($org, $cop, $user);
        $id = $this->notificacion($user, $org, $cop, $pqr->id);

        $this->actingAsContextual($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="PATCH"', false);

        $this->get("/notificaciones/{$id}/leer")->assertMethodNotAllowed();
        $data = json_decode(DB::table('notifications')->where('id', $id)->value('data'), true);
        $this->assertArrayNotHasKey('url', $data);
    }

    public function test_missing_inactive_future_and_expired_memberships_cannot_access_inbox(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $missing = User::factory()->create();
        $inactive = User::factory()->create();
        $future = User::factory()->create();
        $expired = User::factory()->create();
        $this->createContextualIdentity($inactive, $org, $cop, 'inactivo', ['notificaciones.consultar'], 'inactiva');
        $this->createContextualIdentity($future, $org, $cop, 'futuro', ['notificaciones.consultar'], 'activa', now()->addDay());
        $this->createContextualIdentity($expired, $org, $cop, 'vencido', ['notificaciones.consultar'], 'activa', now()->subDays(2), now()->subDay());

        foreach ([$missing, $inactive, $future, $expired] as $user) {
            $this->actingAsContextual($user)
                ->get(route('notifications.index'))
                ->assertForbidden();
        }
    }

    private function pqr(Organizacion $org, Copropiedad $cop, User $user): Pqr
    {
        $pqr = Pqr::factory()->create(['user_id' => $user->id]);
        $pqr->forceFill(['organizacion_id' => $org->id, 'copropiedad_id' => $cop->id])->save();

        return $pqr;
    }

    private function notificacion(User $user, Organizacion $org, Copropiedad $cop, int $pqrId, string $title = 'Estado actualizado'): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => PqrEventNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'resource_type' => 'pqr',
                'resource_id' => $pqrId,
                'event' => 'pqr_estado_actualizado',
                'organizacion_id' => $org->id,
                'copropiedad_id' => $cop->id,
                'title' => $title,
                'message' => 'La solicitud cambió de estado.',
            ], JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
