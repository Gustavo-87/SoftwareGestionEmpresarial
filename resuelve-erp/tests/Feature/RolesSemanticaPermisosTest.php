<?php

namespace Tests\Feature;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Roles\CapacidadAdministrativa;
use App\Application\Pqrs\VisibilidadBorradoresPqrs;
use App\Models\Pqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * Semántica por permisos (Sprint 16, B4): las capacidades efectivas sustituyen
 * las comparaciones por nombres de rol. Un rol personalizado con los permisos
 * adecuados obtiene el mismo comportamiento que un rol base equivalente.
 */
class RolesSemanticaPermisosTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_rol_personalizado_con_permisos_equivalentes_conserva_comportamiento(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $dueno = User::factory()->create();
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
            'user_id' => $dueno->id, 'assigned_to_id' => User::factory()->create()->id,
        ]);
        $reply = $pqr->replies()->create(['user_id' => $dueno->id, 'body' => 'Borrador ajeno', 'is_draft' => true]);
        $autorizacion = app(AutorizacionContextual::class);
        $visibilidad = app(VisibilidadBorradoresPqrs::class);

        // Rol personalizado equivalente a gestor (sin llamarse gestor).
        $gestor = User::factory()->create();
        [, , $contextoGestor] = $this->createContextualIdentity(
            $gestor, $organizacion, $copropiedad, 'coordinador',
            ['pqrs.ver_todas', 'pqrs.gestionar', 'pqrs.ver_borradores'],
        );
        $this->assertTrue($autorizacion->puedeGestionarPqr($contextoGestor, $pqr));
        $this->assertSame('visible', $visibilidad->estado($contextoGestor, $pqr, $reply));

        // Rol personalizado equivalente a apoyo (sin llamarse apoyo).
        $apoyo = User::factory()->create();
        [, , $contextoApoyo] = $this->createContextualIdentity(
            $apoyo, $organizacion, $copropiedad, 'ayudante',
            ['pqrs.ver_todas', 'pqrs.gestionar', 'pqrs.gestionar_asignadas'],
        );
        $this->assertFalse($autorizacion->puedeGestionarPqr($contextoApoyo, $pqr));
        $this->assertSame('hidden', $visibilidad->estado($contextoApoyo, $pqr, $reply));
        $pqr->update(['assigned_to_id' => $apoyo->id]);
        $this->assertTrue($autorizacion->puedeGestionarPqr($contextoApoyo, $pqr));
    }

    public function test_nombre_de_rol_conocido_sin_permiso_no_concede_capacidad(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $dueno = User::factory()->create();
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
            'user_id' => $dueno->id, 'assigned_to_id' => User::factory()->create()->id,
        ]);
        $reply = $pqr->replies()->create(['user_id' => $dueno->id, 'body' => 'Borrador ajeno', 'is_draft' => true]);
        $autorizacion = app(AutorizacionContextual::class);
        $visibilidad = app(VisibilidadBorradoresPqrs::class);

        // Se llama admin pero no tiene pqrs.ver_borradores: no ve borradores ajenos.
        $falsoAdmin = User::factory()->create();
        [, , $contextoAdmin] = $this->createContextualIdentity(
            $falsoAdmin, $organizacion, $copropiedad, 'admin', ['pqrs.ver_todas', 'pqrs.gestionar'],
        );
        $this->assertSame('hidden', $visibilidad->estado($contextoAdmin, $pqr, $reply));

        // Se llama apoyo pero no tiene pqrs.gestionar_asignadas: gestiona sin restricción.
        $falsoApoyo = User::factory()->create();
        [, , $contextoApoyo] = $this->createContextualIdentity(
            $falsoApoyo, $organizacion, $copropiedad, 'apoyo', ['pqrs.ver_todas', 'pqrs.gestionar'],
        );
        $this->assertTrue($autorizacion->puedeGestionarPqr($contextoApoyo, $pqr));
    }

    public function test_403_backend_sin_permiso_y_aislamiento_entre_copropiedades(): void
    {
        [$organizacion, $copropiedadA] = $this->createInstitutionalContext();
        $autorizacion = app(AutorizacionContextual::class);
        $sinPermiso = User::factory()->create();
        [, , $contextoSin] = $this->createContextualIdentity($sinPermiso, $organizacion, $copropiedadA, 'auditor', ['pqrs.ver_todas']);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedadA)->create(['user_id' => User::factory()->create()->id]);

        // Backend decide: sin pqrs.gestionar no hay gestión, aunque el rol se llame como sea.
        $this->assertFalse($autorizacion->puedeGestionarPqr($contextoSin, $pqr));

        // Aislamiento: la PQR de otra Copropiedad no es gestionable desde A.
        $otra = \App\Models\Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Otra', 'estado' => 'activa']);
        $pqrOtra = Pqr::factory()->paraContexto($organizacion, $otra)->create(['user_id' => User::factory()->create()->id]);
        $gestor = User::factory()->create();
        [, , $contextoGestor] = $this->createContextualIdentity(
            $gestor, $organizacion, $copropiedadA, 'coordinador', ['pqrs.ver_todas', 'pqrs.gestionar'],
        );
        $this->assertFalse($autorizacion->puedeGestionarPqr($contextoGestor, $pqrOtra));
        $this->assertFalse($autorizacion->puedeVerPqr($contextoGestor, $pqrOtra));
    }

    public function test_eliminacion_conserva_la_capacidad_administrativa_efectiva(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $capacidad = app(CapacidadAdministrativa::class);

        // Invariante por capacidad efectiva (no por nombre de rol): excluir al
        // único titular deja la Copropiedad sin administración; el mismo
        // servicio protege la revocación de roles y la eliminación de usuarios.
        $gestor = User::factory()->create();
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', ['usuarios.gestionar']);
        $nuevoTitular = User::factory()->create();
        [$membresiaTitular] = $this->createContextualIdentity($nuevoTitular, $organizacion, $copropiedad, 'administrador local', ['usuarios.gestionar']);
        $membresias = $capacidad->membresiasBloqueadas($membresiaTitular);

        $this->assertSame(2, $capacidad->titularesRestantes($membresias, $membresiaTitular));
        $this->assertSame(1, $capacidad->titularesRestantes($membresias, $membresiaTitular, null, (int) $gestor->id));
        $this->assertSame(0, $capacidad->titularesRestantes(
            $membresias->reject(fn ($m) => (int) $m->usuario_id === (int) $gestor->id)->values(),
            $membresiaTitular,
            null,
            (int) $nuevoTitular->id,
        ));
    }
}
