<?php

namespace Tests\Feature;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Pqrs\VisibilidadBorradoresPqrs;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * Caracterización de equivalencia funcional tras el cambio de fuente (Sprint 15,
 * Bloque 2B): las reglas sensibles conservan exactamente su comportamiento,
 * ahora resuelto desde el RBAC de Spatie a través de AutorizacionContextual.
 */
class RbacSpatieCaracterizacionTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    private const PERMISOS_POR_ROL = [
        'admin' => ['pqrs.ver_todas', 'pqrs.gestionar', 'pqrs.ver_borradores'],
        'gestor' => ['pqrs.ver_todas', 'pqrs.gestionar', 'pqrs.ver_borradores'],
        'apoyo' => ['pqrs.ver_todas', 'pqrs.gestionar', 'pqrs.gestionar_asignadas'],
        'auditor' => ['pqrs.ver_todas'],
        'residente' => ['pqrs.ver_propias'],
    ];

    public function test_caracterizacion_admin_gestor_apoyo_auditor_residente_conservan_su_comportamiento(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $dueno = User::factory()->create();
        $asignado = User::factory()->create();
        $pqrAjena = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
            'user_id' => $dueno->id,
            'assigned_to_id' => $asignado->id,
        ]);
        $autorizacion = app(AutorizacionContextual::class);

        $contextos = [];
        foreach (self::PERMISOS_POR_ROL as $rol => $permisos) {
            $usuario = User::factory()->create();
            [, , $contextos[$rol]] = $this->createContextualIdentity($usuario, $organizacion, $copropiedad, $rol, $permisos);
        }

        // admin y gestor: ven y gestionan PQR ajenas.
        foreach (['admin', 'gestor'] as $rol) {
            $this->assertTrue($autorizacion->puedeVerPqr($contextos[$rol], $pqrAjena), $rol);
            $this->assertTrue($autorizacion->puedeGestionarPqr($contextos[$rol], $pqrAjena), $rol);
        }

        // apoyo: ve todas pero solo gestiona la asignada o la sin asignar.
        $this->assertTrue($autorizacion->puedeVerPqr($contextos['apoyo'], $pqrAjena));
        $this->assertFalse($autorizacion->puedeGestionarPqr($contextos['apoyo'], $pqrAjena));
        $pqrAjena->update(['assigned_to_id' => $contextos['apoyo']->usuario->id]);
        $this->assertTrue($autorizacion->puedeGestionarPqr($contextos['apoyo'], $pqrAjena));
        $pqrAjena->update(['assigned_to_id' => null]);
        $this->assertTrue($autorizacion->puedeGestionarPqr($contextos['apoyo'], $pqrAjena));

        // auditor: solo consulta.
        $this->assertTrue($autorizacion->puedeVerPqr($contextos['auditor'], $pqrAjena));
        $this->assertFalse($autorizacion->puedeGestionarPqr($contextos['auditor'], $pqrAjena));

        // residente: solo sus propias solicitudes y sin gestión.
        $pqrPropia = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['user_id' => $contextos['residente']->usuario->id]);
        $this->assertTrue($autorizacion->puedeVerPqr($contextos['residente'], $pqrPropia));
        $this->assertFalse($autorizacion->puedeVerPqr($contextos['residente'], $pqrAjena));
        $this->assertFalse($autorizacion->puedeGestionarPqr($contextos['residente'], $pqrPropia));
    }

    public function test_caracterizacion_borradores_visibles_para_admin_y_gestor(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $dueno = User::factory()->create();
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['user_id' => $dueno->id]);
        $reply = $pqr->replies()->create(['user_id' => $dueno->id, 'body' => 'Borrador ajeno', 'is_draft' => true]);
        $visibilidad = app(VisibilidadBorradoresPqrs::class);

        foreach (['admin' => true, 'gestor' => true, 'apoyo' => false, 'auditor' => false, 'residente' => false] as $rol => $visible) {
            $usuario = User::factory()->create();
            [, , $contexto] = $this->createContextualIdentity($usuario, $organizacion, $copropiedad, $rol, self::PERMISOS_POR_ROL[$rol]);
            $this->assertSame($visible ? 'visible' : 'hidden', $visibilidad->estado($contexto, $pqr, $reply), $rol);
        }
    }

    public function test_caracterizacion_aislamiento_entre_copropiedades_y_contextos_externos(): void
    {
        [$organizacion, $copropiedadA] = $this->createInstitutionalContext();
        $copropiedadB = Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Copropiedad B', 'estado' => 'activa']);
        $pqrB = Pqr::factory()->paraContexto($organizacion, $copropiedadB)->create(['user_id' => User::factory()->create()->id]);

        $organizacionExterna = Organizacion::create(['nombre' => 'Externa', 'estado' => 'activa']);
        $copropiedadExterna = Copropiedad::create(['organizacion_id' => $organizacionExterna->id, 'nombre' => 'Externa', 'estado' => 'activa']);
        $pqrExterna = Pqr::factory()->paraContexto($organizacionExterna, $copropiedadExterna)->create(['user_id' => User::factory()->create()->id]);

        $admin = User::factory()->create();
        [, , $contextoA] = $this->createContextualIdentity($admin, $organizacion, $copropiedadA, 'admin', self::PERMISOS_POR_ROL['admin']);
        $autorizacion = app(AutorizacionContextual::class);

        // Recurso de otra Copropiedad de la misma Organización.
        $this->assertFalse($autorizacion->puedeVerPqr($contextoA, $pqrB));
        $this->assertFalse($autorizacion->puedeGestionarPqr($contextoA, $pqrB));

        // Recurso de otra Organización.
        $this->assertFalse($autorizacion->puedeVerPqr($contextoA, $pqrExterna));
        $this->assertFalse($autorizacion->puedeGestionarPqr($contextoA, $pqrExterna));
    }

    public function test_caracterizacion_membresia_vencida_o_inactiva_no_autoriza(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $autorizacion = app(AutorizacionContextual::class);

        $inactivo = User::factory()->create();
        [, , $contextoInactivo] = $this->createContextualIdentity(
            $inactivo, $organizacion, $copropiedad, 'gestor', self::PERMISOS_POR_ROL['gestor'], 'inactiva'
        );
        $vencido = User::factory()->create();
        [, , $contextoVencido] = $this->createContextualIdentity(
            $vencido, $organizacion, $copropiedad, 'gestor', self::PERMISOS_POR_ROL['gestor'], 'activa', null, now()->subDay()
        );

        foreach ([$contextoInactivo, $contextoVencido] as $contexto) {
            $this->assertFalse($contexto->tieneMembresiaContextual());
            $this->assertFalse($autorizacion->tienePermiso($contexto, 'pqrs.gestionar'));
            $this->assertFalse($autorizacion->tieneRol($contexto, 'gestor'));
        }
    }
}
