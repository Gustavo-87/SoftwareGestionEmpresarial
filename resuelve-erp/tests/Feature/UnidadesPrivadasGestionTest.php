<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\UnidadPrivada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * Gestión de Unidades Privadas (HU-RYP-01, Sprint 20) — primer bloque:
 * listado, creación, edición, unicidad contextual y aislamiento entre
 * Copropiedades. La gestión de unidades residenciales no altera Membresías ni
 * permisos de acceso (regla transversal del Sprint 20).
 */
class UnidadesPrivadasGestionTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_crea_lista_y_edita_unidades_con_residentes_gestionar(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $gestor = User::factory()->create();
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', ['residentes.gestionar']);

        $membresiasAntes = MembresiaCopropiedad::count();
        $rolesAntes = DB::table('model_has_roles')->count();

        // El contexto jamás se acepta del payload.
        $this->actingAsContextual($gestor)->post(route('management.unidades.store'), [
            'codigo' => 'A-101',
            'numero_nombre' => 'Apartamento 101',
            'torre_bloque' => 'Torre A',
            'tipo' => 'apartamento',
            'organizacion_id' => 999,
            'copropiedad_id' => 999,
        ])->assertRedirect(route('management.unidades.index'));

        $this->assertDatabaseHas('unidades_privadas', [
            'codigo' => 'A-101',
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'tipo' => 'apartamento',
            'estado' => 'activa',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'residencia.unidad.crear']);

        $respuesta = $this->actingAsContextual($gestor)->get(route('management.unidades.index'))->assertOk();
        $respuesta->assertSee('A-101')->assertSee('Apartamento 101')->assertSee('Activa');

        $unidad = UnidadPrivada::query()->firstOrFail();
        $this->actingAsContextual($gestor)->get(route('management.unidades.edit', $unidad))->assertOk()->assertSee('A-101');
        $this->actingAsContextual($gestor)->put(route('management.unidades.update', $unidad), [
            'codigo' => 'A-101B',
            'numero_nombre' => 'Apartamento 101B',
            'torre_bloque' => 'Torre A',
            'tipo' => 'apartamento',
        ])->assertRedirect(route('management.unidades.index'));
        $this->assertDatabaseHas('unidades_privadas', ['id' => $unidad->id, 'codigo' => 'A-101B']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'residencia.unidad.actualizar']);

        // Separación de dominio: gestionar unidades no crea ni altera Membresías
        // ni asignaciones de roles/permisos de acceso.
        $this->assertSame($membresiasAntes, MembresiaCopropiedad::count());
        $this->assertSame($rolesAntes, DB::table('model_has_roles')->count());
    }

    public function test_el_formulario_de_creacion_de_unidad_se_renderiza_con_valores_previos(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $gestor = User::factory()->create();
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', ['residentes.gestionar']);

        // Renderiza el formulario completo en modo creación (HTTP 200).
        $respuesta = $this->actingAsContextual($gestor)->get(route('management.unidades.create'))->assertOk();
        $respuesta->assertSee('Nueva unidad privada')
            ->assertSee('Crear unidad')
            ->assertSee('name="codigo"', false)
            ->assertSee('name="numero_nombre"', false)
            ->assertSee('name="torre_bloque"', false)
            ->assertSee('name="tipo"', false)
            ->assertSee('action="'.route('management.unidades.store').'"', false);

        // Los valores anteriores se conservan con old() tras un error de validación.
        $this->actingAsContextual($gestor)
            ->from(route('management.unidades.create'))
            ->post(route('management.unidades.store'), ['numero_nombre' => 'Apartamento previo', 'tipo' => 'casa'])
            ->assertRedirect(route('management.unidades.create'))
            ->assertSessionHasErrors('codigo');
        $this->actingAsContextual($gestor)->get(route('management.unidades.create'))
            ->assertOk()
            ->assertSee('Apartamento previo')
            ->assertSee('value="casa" selected', false);
    }

    public function test_el_codigo_es_unico_por_copropiedad(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $gestor = User::factory()->create();
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', ['residentes.gestionar']);

        $this->actingAsContextual($gestor)->post(route('management.unidades.store'), ['codigo' => 'A-101'])->assertRedirect(route('management.unidades.index'));

        // Duplicado en la misma Copropiedad (con espacios y mayúsculas) se rechaza.
        $this->actingAsContextual($gestor)
            ->post(route('management.unidades.store'), ['codigo' => '  a-101 '])
            ->assertSessionHasErrors('codigo');
        $this->assertSame(1, UnidadPrivada::count());

        // El mismo código en otra Copropiedad no choca (unicidad contextual).
        $otra = Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Otra copropiedad', 'estado' => 'activa']);
        $otraUnidad = new UnidadPrivada();
        $otraUnidad->forceFill(['organizacion_id' => $organizacion->id, 'copropiedad_id' => $otra->id, 'codigo' => 'A-101'])->save();
        $this->assertSame(2, UnidadPrivada::count());
    }

    public function test_las_unidades_de_otra_copropiedad_son_invisibles_e_ineditables(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $gestor = User::factory()->create();
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', ['residentes.gestionar']);
        $propia = new UnidadPrivada();
        $propia->forceFill(['organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id, 'codigo' => 'A-101'])->save();

        $otra = Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Otra copropiedad', 'estado' => 'activa']);
        $ajena = new UnidadPrivada();
        $ajena->forceFill(['organizacion_id' => $organizacion->id, 'copropiedad_id' => $otra->id, 'codigo' => 'B-201'])->save();

        $respuesta = $this->actingAsContextual($gestor)->get(route('management.unidades.index'))->assertOk();
        $respuesta->assertSee('A-101')->assertDontSee('B-201');

        $this->actingAsContextual($gestor)->get(route('management.unidades.edit', $ajena))->assertNotFound();
        $this->actingAsContextual($gestor)->put(route('management.unidades.update', $ajena), ['codigo' => 'B-202'])->assertNotFound();
        $this->assertDatabaseHas('unidades_privadas', ['id' => $ajena->id, 'codigo' => 'B-201']);
    }

    public function test_sin_residentes_gestionar_no_se_administran_unidades(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $sinPermiso = User::factory()->create();
        $this->createContextualIdentity($sinPermiso, $organizacion, $copropiedad, 'apoyo', ['pqrs.listar']);
        $unidad = new UnidadPrivada();
        $unidad->forceFill(['organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id, 'codigo' => 'A-101'])->save();

        $this->actingAsContextual($sinPermiso)->get(route('management.unidades.index'))->assertForbidden();
        $this->actingAsContextual($sinPermiso)->get(route('management.unidades.create'))->assertForbidden();
        $this->actingAsContextual($sinPermiso)->post(route('management.unidades.store'), ['codigo' => 'C-301'])->assertForbidden();
        $this->actingAsContextual($sinPermiso)->get(route('management.unidades.edit', $unidad))->assertForbidden();
        $this->actingAsContextual($sinPermiso)->put(route('management.unidades.update', $unidad), ['codigo' => 'C-301'])->assertForbidden();
        $this->assertSame(1, UnidadPrivada::count());
    }
}
