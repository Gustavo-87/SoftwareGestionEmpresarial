<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * El pie de página (año, aplicación, organización y versión) aparece en todas
 * las vistas del panel de administración mediante el partial reutilizado.
 */
class PieDePaginaAdminTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_el_pie_de_pagina_aparece_en_todas_las_vistas_admin(): void
    {
        [$organizacion] = $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $this->actingAs($plataforma);

        foreach ([
            route('admin.index'),
            route('admin.organizaciones.index'),
            route('admin.copropiedades.index'),
            route('admin.membresias.index'),
            route('admin.usuarios-globales.index'),
            route('roles.index'),
            route('admin.tipos-pqr.index'),
        ] as $ruta) {
            $this->get($ruta)
                ->assertOk()
                ->assertSee('app-footer', false)
                ->assertSee(config('app.name'))
                ->assertSee($organizacion->nombre)
                ->assertSee('v1.0');
        }

        // La aplicación operativa conserva el mismo pie de página.
        $this->get(route('panel'))
            ->assertOk()
            ->assertSee('app-footer', false);
    }
}
