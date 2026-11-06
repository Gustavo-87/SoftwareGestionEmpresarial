<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Copropiedad;
use App\Models\Mantenimiento;
use App\Models\Pqr;
use App\Models\PqrTag;
use App\Models\UnidadPrivada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class AuditoriaSeguridadTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_cinco_modelos_registran_actor_contexto_y_antes_despues_sin_contenido_privado(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $actor = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($actor, $org, $cop, 'admin', ['auditoria.ver']);
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $actor->id, 'prioridad' => 'media', 'descripcion' => 'CONTENIDO_PRIVADO']);
        $tag = PqrTag::factory()->create(['organizacion_id' => $org->id, 'copropiedad_id' => $cop->id, 'color' => '#000000']);
        $unidad = new UnidadPrivada(['codigo' => 'A-101', 'numero_nombre' => '101', 'tipo' => 'apartamento', 'estado' => 'activa']);
        $unidad->forceFill(['organizacion_id' => $org->id, 'copropiedad_id' => $cop->id])->save();
        $mantenimiento = Mantenimiento::create(['organizacion_id' => $org->id, 'copropiedad_id' => $cop->id, 'solicitante_id' => $actor->id, 'titulo' => 'Revisión de bomba', 'descripcion' => 'INTERNO_PRIVADO', 'estado' => 'pendiente']);
        $this->actingAsContextual($actor);
        foreach ([
            [$cop, 'nombre', $cop->nombre, 'Copropiedad actualizada'],
            [$pqr, 'prioridad', 'media', 'alta'],
            [$tag, 'color', '#000000', '#123456'],
            [$unidad, 'numero_nombre', '101', '102'],
            [$mantenimiento, 'estado', 'pendiente', 'en_proceso'],
        ] as [$modelo, $campo, $antes, $despues]) {
            $modelo->update([$campo => $despues]);
            $registro = Activity::query()->where('subject_type', $modelo::class)->where('subject_id', $modelo->id)->where('event', 'updated')->latest('id')->firstOrFail();
            $this->assertSame($actor->id, $registro->causer_id);
            $this->assertSame($org->id, $registro->organizacion_id);
            $this->assertSame($cop->id, $registro->copropiedad_id);
            $this->assertSame($antes, $registro->properties['old'][$campo]);
            $this->assertSame($despues, $registro->properties['attributes'][$campo]);
        }
        $json = Activity::query()->get()->toJson();
        $this->assertStringNotContainsString('CONTENIDO_PRIVADO', $json);
        $this->assertStringNotContainsString('INTERNO_PRIVADO', $json);
        $this->assertStringNotContainsString('password', $json);
    }

    public function test_un_rollback_revierte_el_cambio_y_su_registro(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $pqr = Pqr::factory()->paraContexto($org, $cop)->create(['user_id' => $user->id, 'prioridad' => 'media']);
        try {
            DB::transaction(function () use ($pqr): void {
                $pqr->update(['prioridad' => 'alta']);
                throw new RuntimeException('fallo inyectado');
            });
        } catch (RuntimeException) {
            $this->assertSame('media', $pqr->fresh()->prioridad);
        }
        $this->assertSame(0, Activity::query()->where('subject_type', Pqr::class)->where('event', 'updated')->count());
    }

    public function test_login_fallido_y_bloqueo_web_se_persisten_sin_secretos_ni_contexto_inventado(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->post(route('login.store'), ['email' => 'ausente@example.test', 'password' => 'SECRETO_NO_GUARDAR'])->assertSessionHasErrors('email');
        }
        $this->assertSame(3, AuditLog::query()->where('action', 'auth.login.fallido')->count());
        $registro = AuditLog::query()->where('action', 'auth.login.bloqueado')->sole();
        $this->assertSame('web', $registro->metadata['canal']);
        $this->assertNull($registro->user_id);
        $this->assertNull($registro->copropiedad_id);
        $json = AuditLog::query()->get()->toJson();
        $this->assertStringNotContainsString('SECRETO_NO_GUARDAR', $json);
        $this->assertStringNotContainsString('ausente@example.test', $json);
    }

    public function test_login_fallido_y_bloqueo_api_se_persisten(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/login', ['email' => 'ausente@example.test', 'password' => 'incorrecta'])->assertUnauthorized();
        }
        $this->postJson('/api/v1/login', ['email' => 'ausente@example.test', 'password' => 'incorrecta'])->assertStatus(429);
        $this->assertSame(3, AuditLog::query()->where('action', 'auth.login.fallido')->where('metadata->canal', 'api')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login.bloqueado']);
    }

    public function test_inicio_y_cierre_web_quedan_registrados(): void
    {
        $user = User::factory()->create(['password' => 'ClavePrueba123']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'ClavePrueba123'])->assertRedirect();
        $this->post(route('logout'))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login.exitoso', 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.logout', 'user_id' => $user->id]);
    }

    public function test_auditoria_operativa_no_filtra_registros_de_otras_copropiedades_o_sin_contexto(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'auditor']);
        $this->createContextualIdentity($user, $org, $cop, 'auditor', ['auditoria.ver']);
        $otra = Copropiedad::factory()->create(['organizacion_id' => $org->id]);
        AuditLog::create(['action' => 'Visible local', 'organizacion_id' => $org->id, 'copropiedad_id' => $cop->id]);
        AuditLog::create(['action' => 'RESERVADO_OTRA_COPROPIEDAD', 'organizacion_id' => $org->id, 'copropiedad_id' => $otra->id]);
        AuditLog::create(['action' => 'RESERVADO_SIN_CONTEXTO']);
        $this->actingAsContextual($user)->get(route('management.audit'))->assertOk()->assertSee('Visible Local')->assertDontSee('RESERVADO_OTRA_COPROPIEDAD')->assertDontSee('RESERVADO_SIN_CONTEXTO');
    }

    public function test_cambios_de_modelos_tienen_detalle_y_aislamiento(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'auditor']);
        $this->createContextualIdentity($user, $org, $cop, 'auditor', ['auditoria.ver']);
        $tag = PqrTag::factory()->create(['organizacion_id' => $org->id, 'copropiedad_id' => $cop->id, 'name' => 'Nombre inicial']);
        $tag->update(['name' => 'Nombre actualizado']);
        $otra = Copropiedad::factory()->create(['organizacion_id' => $org->id]);
        PqrTag::factory()->create(['organizacion_id' => $org->id, 'copropiedad_id' => $otra->id, 'name' => 'ETIQUETA_OTRO_CONTEXTO']);
        $this->actingAsContextual($user)->get(route('management.audit', ['registro' => 'modelos']))->assertOk()->assertSee('Nombre inicial')->assertSee('Nombre actualizado')->assertSee('Ver cambios')->assertDontSee('ETIQUETA_OTRO_CONTEXTO');
    }

    public function test_registros_globales_de_seguridad_solo_son_visibles_en_plataforma(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'auditor']);
        $this->createContextualIdentity($user, $org, $cop, 'auditor', ['auditoria.ver']);
        AuditLog::create(['action' => 'auth.login.fallido', 'ip_address' => '192.0.2.88', 'metadata' => ['canal' => 'api']]);
        $this->actingAsContextual($user)->get(route('management.audit', ['registro' => 'seguridad']))->assertOk()->assertDontSee('192.0.2.88');
        $this->get(route('admin.audit', ['registro' => 'seguridad']))->assertForbidden();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        $this->actingAs($plataforma)->get(route('admin.audit', ['registro' => 'seguridad', 'canal' => 'api']))->assertOk()->assertSee('Intento de acceso fallido')->assertSee('192.0.2.88');
    }

    public function test_la_consulta_exige_permiso_y_valida_filtros(): void
    {
        [$org, $cop] = $this->createInstitutionalContext();
        $user = User::factory()->create();
        $this->createContextualIdentity($user, $org, $cop, 'residente', ['pqrs.listar']);
        $this->actingAsContextual($user)->get(route('management.audit'))->assertForbidden();
        $auditor = User::factory()->create(['role' => 'auditor']);
        $this->createContextualIdentity($auditor, $org, $cop, 'auditor', ['auditoria.ver']);
        $this->actingAsContextual($auditor)->get(route('management.audit', ['desde' => '2026-11-05', 'hasta' => '2026-11-01']))->assertRedirect()->assertSessionHasErrors('hasta');
    }
}
