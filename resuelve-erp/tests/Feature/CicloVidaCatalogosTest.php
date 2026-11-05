<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Persona;
use App\Models\Pqr;
use App\Models\PqrTag;
use App\Models\TipoPqr;
use App\Models\UnidadPrivada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * HU-CONF-03 — Ciclo de vida completo de catálogos maestros (Sprint 24):
 * alta, consulta, edición, baja lógica, papelera y restauración, con Form
 * Requests, permisos actuales y aislamiento Organización/Copropiedad.
 */
class CicloVidaCatalogosTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    // ---------------------------------------------------------------- Unidades

    public function test_unidad_validacion_422_y_alta(): void
    {
        [$organizacion, $copropiedad, $user] = $this->escenario(['residentes.gestionar']);

        $this->actuando($user)->post(route('management.unidades.store'), ['numero_nombre' => 'Sin código'])
            ->assertSessionHasErrors('codigo');
        $this->assertDatabaseCount('unidades_privadas', 0);

        $this->actuando($user)->post(route('management.unidades.store'), [
            'codigo' => 'A-101', 'numero_nombre' => 'Apto 101', 'tipo' => 'apartamento',
        ])->assertRedirect(route('management.unidades.index'));
        $this->assertDatabaseHas('unidades_privadas', ['codigo' => 'A-101', 'copropiedad_id' => $copropiedad->id]);
    }

    public function test_unidad_edicion(): void
    {
        [$organizacion, $copropiedad, $user] = $this->escenario(['residentes.gestionar']);
        $unidad = $this->crearUnidad($organizacion, $copropiedad, 'A-101');

        $this->actuando($user)->put(route('management.unidades.update', $unidad), [
            'codigo' => 'A-101', 'numero_nombre' => 'Nombre nuevo',
        ])->assertRedirect(route('management.unidades.index'));

        $this->assertDatabaseHas('unidades_privadas', ['id' => $unidad->id, 'numero_nombre' => 'Nombre nuevo']);
    }

    public function test_unidad_baja_logica_papelera_y_restauracion(): void
    {
        [$organizacion, $copropiedad, $user] = $this->escenario(['residentes.gestionar']);
        $unidad = $this->crearUnidad($organizacion, $copropiedad, 'A-101');

        $this->actuando($user)->patch(route('management.unidades.desactivar', $unidad))->assertRedirect();
        $this->assertDatabaseCount('unidades_privadas', 1);
        $this->assertDatabaseHas('unidades_privadas', ['id' => $unidad->id, 'estado' => 'desactivada']);

        $this->actuando($user)->get(route('management.unidades.papelera'))
            ->assertOk()->assertSee('A-101');

        $this->actuando($user)->patch(route('management.unidades.reactivar', $unidad), ['activo' => 1]);
        $this->assertDatabaseHas('unidades_privadas', ['id' => $unidad->id, 'estado' => 'activa']);
    }

    public function test_unidad_permiso_y_aislamiento(): void
    {
        [$organizacion, $copropiedad, $user] = $this->escenario([]);
        [$otraOrganizacion, $otra] = $this->createInstitutionalContext();
        $ajena = $this->crearUnidad($otraOrganizacion, $otra, 'Z-9');

        $this->actuando($user)->post(route('management.unidades.store'), ['codigo' => 'A-1'])->assertStatus(403);
        $this->actuando($user)->get(route('management.unidades.papelera'))->assertStatus(403);

        $admin = $this->escenario(['residentes.gestionar'])[2];
        $this->actuando($admin)->get(route('management.unidades.edit', $ajena))->assertStatus(404);
        $this->actuando($admin)->patch(route('management.unidades.reactivar', $ajena), ['activo' => 1])->assertStatus(404);
        $this->actuando($admin)->get(route('management.unidades.papelera'))->assertOk()->assertDontSee('Z-9');
    }

    // ---------------------------------------------------------------- Personas

    public function test_persona_validacion_422_y_alta(): void
    {
        [$organizacion, $copropiedad, $user] = $this->escenario(['residentes.gestionar']);

        $this->actuando($user)->from(route('management.personas.index'))
            ->post(route('management.personas.store'), ['tipo_persona' => 'natural'])
            ->assertSessionHasErrors('nombre_razon_social');
        $this->assertDatabaseCount('personas', 0);

        $this->actuando($user)->from(route('management.personas.index'))
            ->post(route('management.personas.store'), ['tipo_persona' => 'natural', 'nombre_razon_social' => 'Ana Pérez'])
            ->assertRedirect(route('management.personas.index'));
        $this->assertDatabaseHas('personas', ['nombre_razon_social' => 'Ana Pérez', 'organizacion_id' => $organizacion->id]);
    }

    public function test_persona_edicion(): void
    {
        [$organizacion, $copropiedad, $user] = $this->escenario(['residentes.gestionar']);
        $persona = $this->crearPersona($organizacion, 'Ana Pérez');

        $this->actuando($user)->put(route('management.personas.update', $persona), [
            'tipo_persona' => 'juridica', 'nombre_razon_social' => 'Inmobiliaria SAS',
        ])->assertRedirect(route('management.personas.index'));

        $this->assertDatabaseHas('personas', ['id' => $persona->id, 'nombre_razon_social' => 'Inmobiliaria SAS', 'tipo_persona' => 'juridica']);
    }

    public function test_persona_baja_logica_papelera_y_restauracion(): void
    {
        [$organizacion, $copropiedad, $user] = $this->escenario(['residentes.gestionar']);
        $persona = $this->crearPersona($organizacion, 'Ana Pérez');

        $this->actuando($user)->patch(route('management.personas.desactivar', $persona))->assertRedirect();
        $this->assertDatabaseCount('personas', 1);
        $this->assertDatabaseHas('personas', ['id' => $persona->id, 'estado' => 'desactivada']);

        $this->actuando($user)->get(route('management.personas.papelera'))
            ->assertOk()->assertSee('Ana Pérez');

        $this->actuando($user)->patch(route('management.personas.reactivar', $persona), ['activo' => 1]);
        $this->assertDatabaseHas('personas', ['id' => $persona->id, 'estado' => 'activa']);
    }

    public function test_persona_permiso_y_aislamiento(): void
    {
        [$organizacion, $copropiedad, $user] = $this->escenario([]);
        [$otraOrganizacion] = $this->createInstitutionalContext();
        $ajena = $this->crearPersona($otraOrganizacion, 'Persona Ajena');

        $this->actuando($user)->from(route('management.personas.index'))
            ->post(route('management.personas.store'), ['tipo_persona' => 'natural', 'nombre_razon_social' => 'X'])
            ->assertStatus(403);
        $this->actuando($user)->get(route('management.personas.papelera'))->assertStatus(403);

        $admin = $this->escenario(['residentes.gestionar'])[2];
        $this->actuando($admin)->get(route('management.personas.edit', $ajena))->assertStatus(404);
        $this->actuando($admin)->patch(route('management.personas.reactivar', $ajena), ['activo' => 1])->assertStatus(404);
    }

    // ---------------------------------------------------------------- Etiquetas

    public function test_etiqueta_validacion_422_y_alta(): void
    {
        [, , $user] = $this->escenario(['gestion.herramientas_gestionar']);

        $this->actuando($user)->from(route('management.tools'))
            ->post(route('management.tags.store'), ['name' => '', 'color' => 'rojo'])
            ->assertSessionHasErrors(['name', 'color']);
        $this->assertDatabaseCount('pqr_tags', 0);

        $this->actuando($user)->from(route('management.tools'))
            ->post(route('management.tags.store'), ['name' => 'Urgente', 'color' => '#FF0000'])
            ->assertRedirect(route('management.tools'));
        $this->assertDatabaseHas('pqr_tags', ['name' => 'Urgente', 'activo' => 1]);
    }

    public function test_etiqueta_edicion(): void
    {
        [$organizacion, $copropiedad, $user] = $this->escenario(['gestion.herramientas_gestionar']);
        $etiqueta = $this->crearEtiqueta($organizacion, $copropiedad, 'Urgente');

        $this->actuando($user)->patch(route('management.tags.update', $etiqueta), [
            'name' => 'Prioritaria', 'color' => '#00FF00',
        ])->assertRedirect();

        $this->assertDatabaseHas('pqr_tags', ['id' => $etiqueta->id, 'name' => 'Prioritaria']);
    }

    public function test_etiqueta_baja_logica_papelera_y_restauracion(): void
    {
        [$organizacion, $copropiedad, $user] = $this->escenario(['gestion.herramientas_gestionar']);
        $etiqueta = $this->crearEtiqueta($organizacion, $copropiedad, 'Urgente');

        $this->actuando($user)->patch(route('management.tags.status', $etiqueta), ['activo' => 0])->assertRedirect();
        $this->assertDatabaseCount('pqr_tags', 1);
        $this->assertDatabaseHas('pqr_tags', ['id' => $etiqueta->id, 'activo' => 0]);

        $this->actuando($user)->get(route('management.tags.papelera'))
            ->assertOk()->assertSee('Urgente');

        $this->actuando($user)->patch(route('management.tags.status', $etiqueta), ['activo' => 1]);
        $this->assertDatabaseHas('pqr_tags', ['id' => $etiqueta->id, 'activo' => 1]);
    }

    public function test_etiqueta_permiso_y_aislamiento(): void
    {
        [, , $user] = $this->escenario([]);
        [$otraOrganizacion, $otra] = $this->createInstitutionalContext();
        $ajena = $this->crearEtiqueta($otraOrganizacion, $otra, 'Ajena');

        $this->actuando($user)->from(route('management.tools'))
            ->post(route('management.tags.store'), ['name' => 'Nueva', 'color' => '#123456'])
            ->assertStatus(403);
        $this->actuando($user)->get(route('management.tags.papelera'))->assertStatus(403);

        $gestor = $this->escenario(['gestion.herramientas_gestionar'])[2];
        $this->actuando($gestor)->patch(route('management.tags.update', $ajena), [
            'name' => 'Cambiada', 'color' => '#654321',
        ])->assertStatus(404);
        $this->assertDatabaseHas('pqr_tags', ['id' => $ajena->id, 'name' => 'Ajena']);
    }

    // ------------------------------------------------------------ Tipos de PQRS

    public function test_tipo_pqrs_validacion_422_y_alta(): void
    {
        $user = $this->usuarioAdmin();

        $this->actuando($user)->from(route('admin.tipos-pqr.index'))
            ->post(route('admin.tipos-pqr.store'), ['nombre' => ''])
            ->assertSessionHasErrors('nombre');
        $this->assertDatabaseCount('tipo_pqrs', 0);

        $this->actuando($user)->from(route('admin.tipos-pqr.index'))
            ->post(route('admin.tipos-pqr.store'), ['nombre' => 'Petición'])
            ->assertRedirect(route('admin.tipos-pqr.index'));
        $this->assertDatabaseHas('tipo_pqrs', ['nombre' => 'Petición']);
    }

    public function test_tipo_pqrs_edicion_y_duplicado(): void
    {
        $user = $this->usuarioAdmin();
        $tipo = TipoPqr::create(['nombre' => 'Petición']);

        $this->actuando($user)->from(route('admin.tipos-pqr.index'))
            ->post(route('admin.tipos-pqr.store'), ['nombre' => 'Petición'])
            ->assertSessionHasErrors('nombre');

        $this->actuando($user)->put(route('admin.tipos-pqr.update', $tipo), ['nombre' => 'Petición especial'])
            ->assertRedirect(route('admin.tipos-pqr.index'));
        $this->assertDatabaseHas('tipo_pqrs', ['id' => $tipo->id, 'nombre' => 'Petición especial']);
    }

    public function test_tipo_pqrs_permiso_y_guarda_de_uso(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $noAdmin = User::factory()->create();
        $this->createContextualIdentity($noAdmin, $organizacion, $copropiedad, 'gestor_catalogos', []);

        $this->actuando($noAdmin)->from(route('admin.tipos-pqr.index'))
            ->post(route('admin.tipos-pqr.store'), ['nombre' => 'Bloqueado'])
            ->assertStatus(403);
        $this->assertDatabaseCount('tipo_pqrs', 0);

        // La eliminación de un tipo en uso se conserva bloqueada por guardas.
        $user = $this->usuarioAdmin();
        $tipo = TipoPqr::create(['nombre' => 'En uso']);
        Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
            'tipo_pqr_id' => $tipo->id,
            'user_id' => $noAdmin->id,
            'fecha_limite_respuesta' => today()->addDay(),
        ]);

        $this->actuando($user)->delete(route('admin.tipos-pqr.destroy', $tipo))->assertSessionHasErrors();
        $this->assertDatabaseHas('tipo_pqrs', ['id' => $tipo->id]);
    }

    // ---------------------------------------------------------------- Helpers

    /**
     * Cada petición HTTP parte de guards y contexto nuevos (como en
     * producción): en las pruebas el guard y el contexto scoped se conservan
     * entre peticiones del mismo test.
     */
    private function actuando(User $user): self
    {
        auth()->forgetGuards();
        $this->app->forgetScopedInstances();

        return $this->actingAs($user);
    }

    /** @return array{0: Organizacion, 1: Copropiedad, 2: User} */
    private function escenario(array $permisos): array
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor_catalogos', $permisos);

        return [$organizacion, $copropiedad, $user];
    }

    private function usuarioAdmin(): User
    {
        return User::factory()->create(['es_administrador_sistema' => true]);
    }

    private function crearUnidad($organizacion, $copropiedad, string $codigo): UnidadPrivada
    {
        $unidad = new UnidadPrivada(['codigo' => $codigo]);
        $unidad->forceFill(['organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id]);
        $unidad->save();

        return $unidad;
    }

    private function crearPersona($organizacion, string $nombre): Persona
    {
        $persona = new Persona(['tipo_persona' => 'natural', 'nombre_razon_social' => $nombre]);
        $persona->forceFill(['organizacion_id' => $organizacion->id, 'estado' => 'activa']);
        $persona->save();

        return $persona;
    }

    private function crearEtiqueta($organizacion, $copropiedad, string $name): PqrTag
    {
        $etiqueta = new PqrTag(['name' => $name, 'color' => '#112233']);
        $etiqueta->forceFill(['organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id, 'activo' => true]);
        $etiqueta->save();

        return $etiqueta;
    }
}
