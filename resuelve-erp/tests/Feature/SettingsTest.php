<?php

namespace Tests\Feature;

use App\Models\ConfiguracionCopropiedad;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_only_manager_can_open_settings(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $resident = User::factory()->create(['role' => 'residente']);
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($resident, $organizacion, $copropiedad, 'residente', []);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['configuracion.gestionar']);

        $this->actingAsContextual($resident)->get(route('settings.edit'))->assertForbidden();
        $this->actingAsContextual($manager)->get(route('settings.edit'))->assertOk();
    }

    public function test_manager_can_update_residential_property_identity(): void
    {
        $manager = User::factory()->create(['role' => 'gestor']);
        $settings = $this->createInitialContext();
        $this->createContextualIdentity(
            $manager,
            $settings->organizacion,
            $settings->copropiedad,
            'gestor',
            ['configuracion.gestionar'],
        );

        $this->actingAs($manager)->put(route('settings.update'), [
            'nombre_conjunto' => 'Conjunto Los Robles',
            'nit' => '900.123.456-7',
            'representante_legal' => 'Ana Pérez',
            'direccion' => 'Calle 10 # 20-30',
            'ciudad' => 'Cartago',
            'telefono' => '3001234567',
            'email' => 'admin@losrobles.co',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 12,
        ])->assertSessionHasNoErrors();

        $settings = SiteSetting::firstOrFail();
        $this->assertSame('Conjunto Los Robles', $settings->nombre_conjunto);
        $this->assertSame('Conjunto Los Robles', $settings->copropiedad->nombre);
        $this->assertSame('#1e3a5f', $settings->copropiedad->configuracion->color_principal);
        $this->assertSame(12, $settings->copropiedad->configuracion->dias_respuesta);
    }

    public function test_manager_can_upload_an_institutional_logo(): void
    {
        Storage::fake('public');
        $manager = User::factory()->create(['role' => 'gestor']);
        Storage::disk('public')->put('branding/anterior.png', 'logo anterior');
        $settings = $this->createInitialContext(['logo_path' => 'branding/anterior.png']);
        $this->createContextualIdentity(
            $manager,
            $settings->organizacion,
            $settings->copropiedad,
            'gestor',
            ['configuracion.gestionar'],
        );

        $this->actingAs($manager)->put(route('settings.update'), [
            'nombre_conjunto' => 'Conjunto Los Robles',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 12,
            'logo' => UploadedFile::fake()->image('logo.png', 500, 500),
        ])->assertSessionHasNoErrors();

        $settings = SiteSetting::firstOrFail();
        $this->assertNotNull($settings->logo_path);
        Storage::disk('public')->assertExists($settings->logo_path);
        Storage::disk('public')->assertMissing('branding/anterior.png');
        $this->assertSame(
            $settings->logo_path,
            ConfiguracionCopropiedad::firstOrFail()->logo_path
        );
    }

    private function createInitialContext(array $overrides = []): SiteSetting
    {
        $settings = SiteSetting::create(array_merge([
            'nombre_conjunto' => 'Conjunto inicial',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 15,
        ], $overrides));

        $this->artisan('resuelve:crear-contexto-inicial')->assertSuccessful();

        return $settings->refresh();
    }
}
