<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class BrandLogoPresentationTest extends TestCase
{
    use CreatesInstitutionalContext;
    use RefreshDatabase;

    public function test_uploaded_logo_is_rendered_in_all_shell_locations_without_changing_its_reference(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/institucional-horizontal.png', 'logo original');
        $settings = $this->createInitialContext(['logo_path' => 'branding/institucional-horizontal.png']);
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity(
            $manager,
            $settings->organizacion,
            $settings->copropiedad,
            'gestor',
            ['configuracion.gestionar'],
        );

        $panel = $this->actingAsContextual($manager)->get(route('panel'));
        $panel->assertOk()
            ->assertSee('brand-logo-image', false)
            ->assertSee('data-brand-fallback', false)
            ->assertSee('onerror="this.onerror=null;this.src=this.dataset.brandFallback"', false)
            ->assertSee('Logo institucional de '.$settings->nombre_conjunto, false);

        $this->actingAsContextual($manager)->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Vista previa del logo de '.$settings->nombre_conjunto, false)
            ->assertSee('brand-logo-image', false)
            ->assertSee('onerror="this.onerror=null;this.src=this.dataset.brandFallback"', false);

        $this->post(route('logout'));
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Logo institucional de '.$settings->organizacion->nombre, false)
            ->assertSee('brand-logo-image', false);

        $this->assertSame('branding/institucional-horizontal.png', $settings->fresh()->logo_path);
        Storage::disk('public')->assertExists('branding/institucional-horizontal.png');
        $this->assertSame('logo original', Storage::disk('public')->get('branding/institucional-horizontal.png'));
    }

    public function test_brand_uses_the_existing_fallback_when_no_logo_is_configured(): void
    {
        $settings = $this->createInitialContext(['logo_path' => null]);
        $user = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($user, $settings->organizacion, $settings->copropiedad, 'residente', []);

        $this->actingAsContextual($user)->get(route('panel'))
            ->assertOk()
            ->assertSee(asset('logo-resuelve.png'), false)
            ->assertSee('data-brand-fallback', false);
    }

    public function test_brand_renders_an_immediate_fallback_for_an_unavailable_logo_url(): void
    {
        $settings = $this->createInitialContext(['logo_path' => 'branding/no-disponible.png']);
        $user = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($user, $settings->organizacion, $settings->copropiedad, 'residente', []);

        $this->actingAsContextual($user)->get(route('panel'))
            ->assertOk()
            ->assertSee('/storage/branding/no-disponible.png', false)
            ->assertSee('data-brand-fallback="'.asset('logo-resuelve.png').'"', false)
            ->assertSee('onerror="this.onerror=null;this.src=this.dataset.brandFallback"', false);
    }

    private function createInitialContext(array $overrides = []): SiteSetting
    {
        $settings = SiteSetting::create(array_merge([
            'nombre_conjunto' => 'Copropiedad con nombre institucional suficientemente largo para validar el shell',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 15,
        ], $overrides));

        $this->artisan('resuelve:crear-contexto-inicial')->assertSuccessful();

        return $settings->refresh();
    }
}
