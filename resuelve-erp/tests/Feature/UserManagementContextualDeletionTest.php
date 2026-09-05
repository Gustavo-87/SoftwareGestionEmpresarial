<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class UserManagementContextualDeletionTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_non_admin_can_be_deleted_when_there_is_only_one_contextual_admin(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $resident = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', ['usuarios.gestionar']);
        $this->createContextualIdentity($resident, $organizacion, $copropiedad, 'residente', []);

        $this->actingAsContextual($admin)
            ->delete(route('users.destroy', $resident))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['id' => $resident->id]);
    }

    public function test_last_contextual_admin_cannot_be_deleted(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role' => 'gestor']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['usuarios.gestionar']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', []);

        $this->actingAsContextual($manager)
            ->delete(route('users.destroy', $admin))
            ->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_contextual_admin_can_be_deleted_when_another_admin_remains(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $permissions = ['usuarios.gestionar'];
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', $permissions);
        $this->createContextualIdentity($otherAdmin, $organizacion, $copropiedad, 'admin', $permissions);

        $this->actingAsContextual($admin)
            ->delete(route('users.destroy', $otherAdmin))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['id' => $otherAdmin->id]);
    }

    public function test_user_from_another_copropiedad_is_hidden_as_not_found(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create([
            'organizacion_id' => $otraOrganizacion->id,
            'nombre' => 'Otra copropiedad',
            'estado' => 'activa',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $foreignUser = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', ['usuarios.gestionar']);
        $this->createContextualIdentity($foreignUser, $otraOrganizacion, $otraCopropiedad, 'residente', []);

        $this->actingAsContextual($admin)
            ->delete(route('users.destroy', $foreignUser))
            ->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $foreignUser->id]);
    }
}
