<?php

namespace Tests\Feature;

use App\Models\Pqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PqrInteractionAuthorizationTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_apoyo_can_reply_to_an_unassigned_or_self_assigned_pqr(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $apoyo = User::factory()->create(['role' => 'apoyo']);
        $this->createContextualIdentity($apoyo, $organizacion, $copropiedad, 'apoyo', ['pqrs.gestionar']);
        $unassigned = Pqr::factory()->create(['assigned_to_id' => null]);
        $selfAssigned = Pqr::factory()->create(['assigned_to_id' => $apoyo->id]);

        $this->actingAs($apoyo)->post(route('pqrs.replies.store', $unassigned), $this->replyData())
            ->assertRedirect();
        $this->post(route('pqrs.replies.store', $selfAssigned), $this->replyData())
            ->assertRedirect();

        $this->assertDatabaseCount('pqr_replies', 2);
    }

    public function test_apoyo_cannot_reply_to_a_pqr_assigned_to_another_user(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $apoyo = User::factory()->create(['role' => 'apoyo']);
        $other = User::factory()->create(['role' => 'apoyo']);
        $this->createContextualIdentity($apoyo, $organizacion, $copropiedad, 'apoyo', ['pqrs.gestionar']);
        $pqr = Pqr::factory()->create(['assigned_to_id' => $other->id]);

        $this->actingAs($apoyo)->post(route('pqrs.replies.store', $pqr), $this->replyData())
            ->assertForbidden();

        $this->assertDatabaseCount('pqr_replies', 0);
    }

    public function test_admin_and_gestor_can_reply_to_any_pqr(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        foreach (['admin', 'gestor'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->createContextualIdentity($user, $organizacion, $copropiedad, $role, ['pqrs.gestionar']);
            $pqr = Pqr::factory()->create([
                'assigned_to_id' => User::factory()->create(['role' => 'apoyo'])->id,
            ]);

            $this->actingAs($user)->post(route('pqrs.replies.store', $pqr), $this->replyData())
                ->assertRedirect();
        }

        $this->assertDatabaseCount('pqr_replies', 2);
    }

    public function test_resident_and_auditor_cannot_reply(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        foreach (['residente', 'auditor'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->createContextualIdentity($user, $organizacion, $copropiedad, $role, []);
            $pqr = Pqr::factory()->create(['user_id' => $user->id, 'assigned_to_id' => null]);

            $this->actingAs($user)->post(route('pqrs.replies.store', $pqr), $this->replyData())
                ->assertForbidden();
        }

        $this->assertDatabaseCount('pqr_replies', 0);
    }

    public function test_apoyo_can_comment_only_when_policy_allows_updating_the_pqr(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $apoyo = User::factory()->create(['role' => 'apoyo']);
        $other = User::factory()->create(['role' => 'apoyo']);
        $this->createContextualIdentity($apoyo, $organizacion, $copropiedad, 'apoyo', ['pqrs.gestionar']);
        $allowed = Pqr::factory()->create(['assigned_to_id' => $apoyo->id]);
        $forbidden = Pqr::factory()->create(['assigned_to_id' => $other->id]);

        $this->actingAs($apoyo)->post(route('pqrs.comments.store', $allowed), [
            'body' => 'Comentario permitido.',
        ])->assertRedirect();
        $this->post(route('pqrs.comments.store', $forbidden), [
            'body' => 'Comentario no permitido.',
        ])->assertForbidden();

        $this->assertDatabaseCount('pqr_internal_comments', 1);
    }

    public function test_admin_and_gestor_can_add_internal_comments(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        foreach (['admin', 'gestor'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->createContextualIdentity($user, $organizacion, $copropiedad, $role, ['pqrs.gestionar']);
            $pqr = Pqr::factory()->create([
                'assigned_to_id' => User::factory()->create(['role' => 'apoyo'])->id,
            ]);

            $this->actingAs($user)->post(route('pqrs.comments.store', $pqr), [
                'body' => "Comentario de {$role}.",
            ])->assertRedirect();
        }

        $this->assertDatabaseCount('pqr_internal_comments', 2);
    }

    public function test_resident_and_auditor_cannot_add_internal_comments(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        foreach (['residente', 'auditor'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->createContextualIdentity($user, $organizacion, $copropiedad, $role, []);
            $pqr = Pqr::factory()->create(['user_id' => $user->id, 'assigned_to_id' => null]);

            $this->actingAs($user)->post(route('pqrs.comments.store', $pqr), [
                'body' => 'Comentario no autorizado.',
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('pqr_internal_comments', 0);
    }

    private function replyData(): array
    {
        return [
            'body' => 'Respuesta de prueba.',
            'action' => 'draft',
            'create_draft_key' => (string) Str::uuid(),
        ];
    }
}
