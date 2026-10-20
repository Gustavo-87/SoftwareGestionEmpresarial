<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class PanelPresentacionTest extends TestCase
{
    use CreatesInstitutionalContext;
    use RefreshDatabase;

    public function test_el_panel_muestra_el_nombre_del_contexto_sin_repeticiones(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', [
            'pqrs.listar', 'pqrs.ver_todas', 'pqrs.crear',
        ]);

        $response = $this->actingAsContextual($user)->get(route('panel'))
            ->assertOk()
            ->assertSee('class="panel-page"', false)
            ->assertSee('<h1>'.$copropiedad->nombre.'</h1>', false)
            ->assertDontSee('Panel principal')
            ->assertDontSee('Bienvenido')
            ->assertSee(route('pqrs.create'));

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->content());
        $xpath = new DOMXPath($dom);

        $this->assertSame(1, $xpath->query('//h1')->length);
        $this->assertSame(1, $xpath->query('//*[@class="operational-context-label"]')->length);
        $this->assertSame(3, $xpath->query('//section[contains(@class,"atencion-grid")]/a')->length);

        $this->actingAsContextual($user)->get(route('pqrs.index'))
            ->assertOk()
            ->assertDontSee('class="panel-page"', false);
    }

    public function test_el_panel_presenta_solo_las_secciones_compactas(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', [
            'pqrs.listar', 'pqrs.ver_todas',
        ]);

        $this->actingAsContextual($user)->get(route('panel'))
            ->assertOk()
            ->assertSee('Top 5 de PQRS por tipo')
            ->assertSee('Ir a PQRS')
            ->assertDontSee('Actividad reciente')
            ->assertDontSee('Accesos rápidos')
            ->assertDontSee('stats-donut')
            ->assertDontSee('Requieren atención inmediata')
            ->assertDontSee('Requieren gestión o seguimiento');
    }
}
