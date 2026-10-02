<?php

namespace Tests\Feature;

use App\Models\Copropiedad;
use App\Models\Mantenimiento;
use App\Models\Organizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class MantenimientoTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    private function identidad(string $rol, array $permisos): array
    {
        [$o, $c] = $this->createInstitutionalContext();
        $u = User::factory()->create();
        $this->createContextualIdentity($u, $o, $c, $rol, $permisos);

        return [$u, $o, $c];
    }

    public function test_residente_registra_y_solo_consulta_propias(): void
    {
        [$u, $o, $c] = $this->identidad('residente', ['mantenimiento.crear', 'mantenimiento.ver_propias']);
        $this->actingAsContextual($u)->get(route('mantenimiento.create'))->assertOk();
        $this->post(route('mantenimiento.store'), ['titulo' => 'Luminaria', 'descripcion' => 'Reparar iluminación', 'estado' => 'finalizado', 'solicitante_id' => 999])->assertRedirect();
        $m = Mantenimiento::firstOrFail();
        $this->assertSame('pendiente', $m->estado);
        $this->assertSame($u->id, $m->solicitante_id);
        $this->get(route('mantenimiento.show', $m))->assertOk()->assertDontSee('Gestionar solicitud');
        $this->put(route('mantenimiento.update', $m), ['estado' => 'finalizado'])->assertForbidden();
        $m2 = $m->replicate();
        $m2->titulo = 'Solicitud ajena';
        $m2->solicitante_id = User::factory()->create()->id;
        $m2->save();
        $this->get(route('mantenimiento.index'))->assertOk()->assertSee('Luminaria')->assertDontSee('Solicitud ajena');
        $this->get(route('mantenimiento.show', $m2))->assertNotFound();
    }

    public function test_gestor_asigna_programa_y_finaliza_con_responsable_local(): void
    {
        [$u, $o, $c] = $this->identidad('gestor', ['mantenimiento.crear', 'mantenimiento.ver_todas', 'mantenimiento.gestionar', 'documentos.consultar']);
        $this->actingAsContextual($u)->post(route('mantenimiento.store'), ['titulo' => 'Bomba', 'descripcion' => 'Revisar bomba'])->assertRedirect();
        $m = Mantenimiento::firstOrFail();
        $this->get(route('mantenimiento.show', $m))->assertOk()->assertSee('Gestionar solicitud')->assertSee($u->name);
        foreach (['en_proceso', 'finalizado'] as $estado) {
            $this->put(route('mantenimiento.update', $m), ['estado' => $estado, 'responsable_id' => $u->id, 'fecha_programada' => '2026-10-15'])->assertRedirect();
            $this->assertDatabaseHas('mantenimientos', ['id' => $m->id, 'estado' => $estado, 'responsable_id' => $u->id]);
            $this->assertSame('2026-10-15', $m->fresh()->fecha_programada->format('Y-m-d'));
        }
        $externo = User::factory()->create();
        $this->put(route('mantenimiento.update', $m), ['estado' => 'pendiente', 'responsable_id' => $externo->id])->assertSessionHasErrors('responsable_id');
        $this->put(route('mantenimiento.update', $m), ['estado' => 'invalido'])->assertSessionHasErrors('estado');
        $this->get(route('documentos.index'))->assertOk()->assertSee('Documentos')->assertSee('Mantenimiento');
    }

    public function test_contexto_externo_se_oculta_y_usuario_sin_permiso_no_accede(): void
    {
        [$u, $o, $c] = $this->identidad('admin', ['mantenimiento.crear', 'mantenimiento.ver_todas', 'mantenimiento.gestionar']);
        $this->actingAsContextual($u)->post(route('mantenimiento.store'), ['titulo' => 'Local', 'descripcion' => 'Solicitud'])->assertRedirect();
        $m = Mantenimiento::firstOrFail();
        $otraO = Organizacion::create(['nombre' => 'Otra', 'estado' => 'activa']);
        $otraC = Copropiedad::create(['organizacion_id' => $otraO->id, 'nombre' => 'Otra', 'estado' => 'activa']);
        $m->forceFill(['organizacion_id' => $otraO->id, 'copropiedad_id' => $otraC->id])->save();
        $this->get(route('mantenimiento.show', $m))->assertNotFound();
        $this->put(route('mantenimiento.update', $m), ['estado' => 'finalizado'])->assertNotFound();
        $sin = User::factory()->create();
        $this->createContextualIdentity($sin, $o, $c, 'auditor', []);
        $this->actingAsContextual($sin)->get(route('mantenimiento.index'))->assertForbidden();
        $this->post(route('mantenimiento.store'), ['titulo' => 'No', 'descripcion' => 'No'])->assertForbidden();
    }
}
