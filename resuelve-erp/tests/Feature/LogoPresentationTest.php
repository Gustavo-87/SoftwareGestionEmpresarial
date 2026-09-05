<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class LogoPresentationTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_settings_saves_logo_presentation_values(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $this->artisan('resuelve:crear-contexto-inicial')->assertSuccessful();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', ['configuracion.gestionar']);

        $this->actingAs($admin)
            ->put(route('settings.update'), [
                'nombre_conjunto' => 'Test Conjunto',
                'dias_respuesta' => 15,
                'logo_scale' => 1.5,
                'logo_offset_x' => 10,
                'logo_offset_y' => -5,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $settings = SiteSetting::firstOrFail();
        $this->assertSame(1.5, (float) $settings->logo_scale);
        $this->assertSame(10, $settings->logo_offset_x);
        $this->assertSame(-5, $settings->logo_offset_y);
    }

    public function test_settings_persists_values_after_reload(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $this->artisan('resuelve:crear-contexto-inicial')->assertSuccessful();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', ['configuracion.gestionar']);

        $this->actingAs($admin)
            ->put(route('settings.update'), [
                'nombre_conjunto' => 'Test Conjunto',
                'dias_respuesta' => 15,
                'logo_scale' => 1.25,
                'logo_offset_x' => 20,
                'logo_offset_y' => -10,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $settings = SiteSetting::current();
        $this->assertSame(1.25, (float) $settings->logo_scale);
        $this->assertSame(20, $settings->logo_offset_x);
        $this->assertSame(-10, $settings->logo_offset_y);
    }

    public function test_settings_rejects_values_out_of_range(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', ['configuracion.gestionar']);

        $this->actingAs($admin)
            ->put(route('settings.update'), [
                'nombre_conjunto' => 'Test Conjunto',
                'dias_respuesta' => 15,
                'logo_scale' => 3.0, // max is 2.0
            ])
            ->assertSessionHasErrors('logo_scale');

        $this->actingAs($admin)
            ->put(route('settings.update'), [
                'nombre_conjunto' => 'Test Conjunto',
                'dias_respuesta' => 15,
                'logo_offset_x' => 100, // max is 50
            ])
            ->assertSessionHasErrors('logo_offset_x');
    }

    public function test_settings_defaults_when_not_provided(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', ['configuracion.gestionar']);

        $this->actingAs($admin)
            ->put(route('settings.update'), [
                'nombre_conjunto' => 'Test Conjunto',
                'dias_respuesta' => 15,
            ])
            ->assertSessionHasNoErrors();

        $settings = SiteSetting::firstOrFail();
        $this->assertSame(1.0, (float) $settings->logo_scale);
        $this->assertSame(0, $settings->logo_offset_x);
        $this->assertSame(0, $settings->logo_offset_y);
    }

    public function test_login_uses_persisted_logo_values(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();

        SiteSetting::firstOrFail()->update([
            'logo_scale' => 1.5,
            'logo_offset_x' => 10,
            'logo_offset_y' => -5,
        ]);

        $user = User::factory()->create();
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'residente', []);

        $this->actingAs($user)
            ->get(route('panel'))
            ->assertOk()
            ->assertSee('scale(1.50)')
            ->assertSee('translate(10px, -5px)');
    }

    public function test_admin_layout_uses_persisted_logo_values(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();

        SiteSetting::firstOrFail()->update([
            'logo_scale' => 1.25,
            'logo_offset_x' => 5,
            'logo_offset_y' => -3,
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'es_administrador_sistema' => true]);
        $this->createContextualIdentity($admin, $organizacion, $copropiedad, 'admin', []);

        $this->actingAs($admin)
            ->get(route('admin.index'))
            ->assertOk()
            ->assertSee('scale(1.25)')
            ->assertSee('translate(5px, -3px)');
    }
}
