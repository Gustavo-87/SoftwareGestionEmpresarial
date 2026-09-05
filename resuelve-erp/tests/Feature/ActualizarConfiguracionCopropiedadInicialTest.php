<?php

namespace Tests\Feature;

use App\Application\Configuracion\ActualizarConfiguracionCopropiedadInicial;
use App\Models\ConfiguracionCopropiedad;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ActualizarConfiguracionCopropiedadInicialTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_synchronizes_all_three_representations(): void
    {
        $settings = $this->createInitialContext();

        $this->service()->execute($settings, $this->updatedData());

        $settings->refresh();
        $copropiedad = $settings->copropiedad;
        $configuracion = $copropiedad->configuracion;

        $this->assertSame('Conjunto actualizado', $settings->nombre_conjunto);
        $this->assertSame($settings->nombre_conjunto, $copropiedad->nombre);
        $this->assertSame($settings->nit, $copropiedad->nit);
        $this->assertSame($settings->representante_legal, $copropiedad->representante_legal);
        $this->assertSame($settings->direccion, $copropiedad->direccion);
        $this->assertSame($settings->ciudad, $copropiedad->ciudad);
        $this->assertSame($settings->telefono, $copropiedad->telefono);
        $this->assertSame($settings->email, $copropiedad->email);
        $this->assertSame($settings->color_principal, $configuracion->color_principal);
        $this->assertSame($settings->logo_path, $configuracion->logo_path);
        $this->assertSame($settings->dias_respuesta, $configuracion->dias_respuesta);
    }

    public function test_partial_update_preserves_unspecified_fields(): void
    {
        $settings = $this->createInitialContext();

        $this->service()->execute($settings, ['telefono' => '3100000000']);

        $settings->refresh();
        $this->assertSame('3100000000', $settings->telefono);
        $this->assertSame('3100000000', $settings->copropiedad->telefono);
        $this->assertSame('Conjunto inicial', $settings->nombre_conjunto);
        $this->assertSame('900.000.000-1', $settings->nit);
        $this->assertSame('#1e3a5f', $settings->color_principal);
        $this->assertSame(15, $settings->dias_respuesta);
        $this->assertSame('#1e3a5f', $settings->copropiedad->configuracion->color_principal);
    }

    public function test_failure_in_one_write_rolls_back_all_database_changes(): void
    {
        $settings = $this->createInitialContext();
        ConfiguracionCopropiedad::updating(function (ConfiguracionCopropiedad $config): void {
            if ($config->color_principal === '#1e3a5f') {
                throw new RuntimeException('Fallo controlado.');
            }
        });

        try {
            $this->service()->execute($settings, $this->updatedData());
            $this->fail('La actualización debía fallar.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo controlado.', $exception->getMessage());
        }

        $settings->refresh();
        $this->assertSame('Conjunto inicial', $settings->nombre_conjunto);
        $this->assertSame('Conjunto inicial', $settings->copropiedad->nombre);
        $this->assertSame('#1e3a5f', $settings->copropiedad->configuracion->color_principal);
    }

    public function test_inconsistent_context_is_rejected(): void
    {
        $settings = $this->createInitialContext();
        $settings->forceFill(['copropiedad_id' => null])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('todavía no tiene contexto asociado');

        $this->service()->execute($settings->fresh(), ['telefono' => '3100000000']);
    }

    public function test_site_setting_without_context_is_not_partially_updated(): void
    {
        $manager = User::factory()->create(['role' => 'gestor']);
        $settings = SiteSetting::create([
            'nombre_conjunto' => 'Conjunto sin contexto',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 15,
        ]);

        $this->actingAs($manager)->put(route('settings.update'), [
            'nombre_conjunto' => 'No debe persistirse',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 12,
        ])->assertSessionHasErrors('contexto');

        $settings->refresh();
        $this->assertSame('Conjunto sin contexto', $settings->nombre_conjunto);
        $this->assertSame('#1e3a5f', $settings->color_principal);
        $this->assertSame(15, $settings->dias_respuesta);
    }

    private function service(): ActualizarConfiguracionCopropiedadInicial
    {
        return $this->app->make(ActualizarConfiguracionCopropiedadInicial::class);
    }

    private function createInitialContext(): SiteSetting
    {
        $settings = SiteSetting::create([
            'nombre_conjunto' => 'Conjunto inicial',
            'nit' => '900.000.000-1',
            'representante_legal' => 'Representante inicial',
            'direccion' => 'Dirección inicial',
            'ciudad' => 'Cartago',
            'telefono' => '3000000000',
            'email' => 'inicial@example.com',
            'color_principal' => '#1e3a5f',
            'logo_path' => 'branding/inicial.png',
            'dias_respuesta' => 15,
        ]);

        $this->artisan('resuelve:crear-contexto-inicial')->assertSuccessful();

        return $settings->refresh();
    }

    private function updatedData(): array
    {
        return [
            'nombre_conjunto' => 'Conjunto actualizado',
            'nit' => '900.999.999-9',
            'representante_legal' => 'Representante actualizado',
            'direccion' => 'Dirección actualizada',
            'ciudad' => 'Pereira',
            'telefono' => '3100000000',
            'email' => 'actualizado@example.com',
            'color_principal' => '#1e3a5f',
            'logo_path' => 'branding/actualizado.png',
            'dias_respuesta' => 30,
        ];
    }
}
