<?php

namespace Tests\Feature\Api;

use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\Pqr;
use App\Models\PqrInternalComment;
use App\Models\PqrReply;
use App\Models\TipoPqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * HU-API-01 — Consulta, detalle y radicación de PQRS por API (Sprint 21).
 */
class ApiPqrsTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_el_listado_refleja_los_permisos_del_rol_activo(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', [
            'pqrs.listar', 'pqrs.ver_todas', 'pqrs.crear',
        ]);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($residente, $organizacion, $copropiedad, 'residente', [
            'pqrs.listar', 'pqrs.ver_propias', 'pqrs.crear',
        ]);

        $this->crearPqr($gestor, $organizacion, $copropiedad, 'Solicitud del gestor');
        $deResidente = $this->crearPqr($residente, $organizacion, $copropiedad, 'Solicitud del residente');

        $this->getConToken('/api/v1/pqrs', $this->login($gestor))
            ->assertOk()->assertJsonCount(2, 'data');

        $this->getConToken('/api/v1/pqrs', $this->login($residente))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $deResidente->id);
    }

    public function test_el_listado_y_el_detalle_aislan_entre_copropiedades(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', [
            'pqrs.listar', 'pqrs.ver_todas', 'pqrs.crear',
        ]);

        $otra = Copropiedad::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Otra Copropiedad',
            'estado' => 'activa',
        ]);
        $externo = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($externo, $organizacion, $otra, 'gestor', [
            'pqrs.listar', 'pqrs.ver_todas', 'pqrs.crear',
        ]);
        $ajena = $this->crearPqr($externo, $organizacion, $otra, 'PQR de otra Copropiedad');

        $token = $this->login($gestor);

        $this->getConToken('/api/v1/pqrs', $token)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getConToken('/api/v1/pqrs/'.$ajena->id, $token)
            ->assertStatus(404);
    }

    public function test_el_detalle_conserva_las_reglas_de_visibilidad(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', [
            'pqrs.listar', 'pqrs.ver_todas', 'pqrs.crear', 'pqrs.gestionar', 'pqrs.ver_borradores',
        ]);
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($residente, $organizacion, $copropiedad, 'residente', [
            'pqrs.listar', 'pqrs.ver_propias', 'pqrs.crear',
        ]);

        $pqr = $this->crearPqr($residente, $organizacion, $copropiedad, 'Con conversación interna');
        PqrReply::create(['pqr_id' => $pqr->id, 'user_id' => $gestor->id, 'body' => 'Borrador interno', 'is_draft' => true]);
        PqrReply::create(['pqr_id' => $pqr->id, 'user_id' => $gestor->id, 'body' => 'Respuesta oficial', 'is_draft' => false, 'sent_at' => now()]);
        PqrInternalComment::create(['pqr_id' => $pqr->id, 'user_id' => $gestor->id, 'body' => 'Comentario interno']);

        $detalleResidente = $this->getConToken('/api/v1/pqrs/'.$pqr->id, $this->login($residente))
            ->assertOk()
            ->assertJsonPath('data.id', $pqr->id)
            ->assertJsonCount(1, 'data.respuestas')
            ->assertJsonPath('data.comentarios_internos', null)
            ->assertJsonPath('data.respuestas.0.body', 'Respuesta oficial')
            ->assertJsonPath('data.respuestas.0.es_borrador', false);
        $this->assertStringNotContainsString('Borrador interno', (string) $detalleResidente->getContent());
        $this->assertStringNotContainsString('Comentario interno', (string) $detalleResidente->getContent());

        $this->getConToken('/api/v1/pqrs/'.$pqr->id, $this->login($gestor))
            ->assertOk()
            ->assertJsonCount(2, 'data.respuestas')
            ->assertJsonCount(1, 'data.comentarios_internos');
    }

    public function test_radicacion_valida_crea_la_pqr_en_el_contexto_activo(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($residente, $organizacion, $copropiedad, 'residente', [
            'pqrs.listar', 'pqrs.ver_propias', 'pqrs.crear',
        ]);
        $tipo = TipoPqr::create(['nombre' => 'Petición']);

        $respuesta = $this->postConToken('/api/v1/pqrs', [
            'asunto' => 'Fuga en el sótano',
            'descripcion' => 'Se reporta una fuga cerca del parqueadero.',
            'tipo_pqr_id' => $tipo->id,
        ], $this->login($residente));

        $respuesta->assertStatus(201)->assertJsonPath('data.asunto', 'Fuga en el sótano');
        $this->assertDatabaseHas('pqrs', [
            'asunto' => 'Fuga en el sótano',
            'user_id' => $residente->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
        ]);
    }

    public function test_radicacion_invalida_responde_422(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $residente = User::factory()->create(['role' => 'residente']);
        $this->createContextualIdentity($residente, $organizacion, $copropiedad, 'residente', [
            'pqrs.listar', 'pqrs.ver_propias', 'pqrs.crear',
        ]);

        $this->postConToken('/api/v1/pqrs', [
            'descripcion' => 'Sin asunto.',
            'tipo_pqr_id' => 999,
        ], $this->login($residente))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['asunto', 'tipo_pqr_id']);
    }

    public function test_radicacion_sin_permiso_de_creacion_responde_403(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $soloConsulta = User::factory()->create(['role' => 'auditor']);
        $this->createContextualIdentity($soloConsulta, $organizacion, $copropiedad, 'auditor', ['pqrs.listar']);
        $tipo = TipoPqr::create(['nombre' => 'Petición']);

        $this->postConToken('/api/v1/pqrs', [
            'asunto' => 'No autorizada',
            'descripcion' => 'Sin permiso de creación.',
            'tipo_pqr_id' => $tipo->id,
        ], $this->login($soloConsulta))
            ->assertStatus(403);
    }

    public function test_sin_token_y_sin_permiso_de_consulta_no_hay_acceso(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $sinPermisos = User::factory()->create(['role' => 'auditor']);
        $this->createContextualIdentity($sinPermisos, $organizacion, $copropiedad, 'auditor', []);

        $this->getJson('/api/v1/pqrs')->assertStatus(401);

        $this->getConToken('/api/v1/pqrs', $this->login($sinPermisos))
            ->assertStatus(403);
    }

    public function test_un_token_de_usuario_inactivo_no_permite_acceso(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', [
            'pqrs.listar', 'pqrs.ver_todas', 'pqrs.crear',
        ]);
        $token = $this->login($gestor);

        $gestor->forceFill(['estado' => 'inactivo', 'desactivado_at' => now()])->save();

        $this->getConToken('/api/v1/pqrs', $token)->assertStatus(403);
        $this->postConToken('/api/v1/pqrs', [
            'asunto' => 'Bloqueada',
            'descripcion' => 'Cuenta desactivada.',
        ], $token)->assertStatus(403);
    }

    public function test_un_token_con_membresia_vencida_no_permite_acceso(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $gestor = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', [
            'pqrs.listar', 'pqrs.ver_todas', 'pqrs.crear',
        ]);
        $token = $this->login($gestor);

        // La vigencia se cesa: el token sigue existiendo pero el contexto no.
        MembresiaCopropiedad::query()
            ->where('usuario_id', $gestor->id)
            ->firstOrFail()
            ->forceFill(['vigente_hasta' => now()->subDay()])
            ->save();

        $this->getConToken('/api/v1/pqrs', $token)->assertStatus(403);
    }

    private function login(User $user, array $extra = []): string
    {
        return $this->postJson('/api/v1/login', array_merge([
            'email' => $user->email,
            'password' => 'password',
        ], $extra))->assertOk()->json('token');
    }

    /** Cada petición HTTP parte de guards nuevos (como en producción). */
    private function getConToken(string $uri, string $token)
    {
        auth()->forgetGuards();

        return $this->getJson($uri, ['Authorization' => 'Bearer '.$token]);
    }

    /** Cada petición HTTP parte de guards nuevos (como en producción). */
    private function postConToken(string $uri, array $datos, string $token)
    {
        auth()->forgetGuards();

        return $this->postJson($uri, $datos, ['Authorization' => 'Bearer '.$token]);
    }

    private function crearPqr(User $user, $organizacion, $copropiedad, string $asunto): Pqr
    {
        $tipo = TipoPqr::query()->first() ?? TipoPqr::create(['nombre' => 'Petición']);

        // El contexto se asocia como en los casos de uso: no viaja en el payload.
        $pqr = new Pqr([
            'asunto' => $asunto,
            'descripcion' => 'Descripción de la solicitud.',
            'tipo_pqr_id' => $tipo->id,
            'estado' => 'radicada',
            'prioridad' => 'media',
            'fecha_radicacion' => now()->toDateString(),
            'fecha_limite_respuesta' => now()->addDays(5)->toDateString(),
        ]);
        $pqr->user()->associate($user);
        $pqr->organizacion()->associate($organizacion);
        $pqr->copropiedad()->associate($copropiedad);
        $pqr->save();

        return $pqr;
    }
}
