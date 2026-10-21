<?php

namespace Tests\Feature;

use App\Application\Autorizacion\VerificadorEquivalenciaAutorizacionContextual;
use App\Application\Autorizacion\VerificadorEquivalenciaRbacSpatie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * Contrato aprobado del diagnóstico de equivalencia (Sprint 18):
 * normalización canónica, tolerancia multi-rol y clasificación por severidad.
 */
class VerificadorEquivalenciaContratoTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_la_normalizacion_canonica_no_reporta_variantes_de_mayusculas(): void
    {
        [$organizacion, $copropiedad] = $this->conEquivalenciaCompleta('gestor');

        // Deriva documentada: renombre de Roles y claves con otra capitalización.
        Role::query()->where('name', 'gestor')->update(['name' => 'Gestor']);
        DB::table('permissions')->where('name', 'pqrs.listar')->update(['name' => 'PQRS.Listar']);

        $this->assertSame([], app(VerificadorEquivalenciaRbacSpatie::class)->divergenciasClasificadas());
        $this->assertSame([], app(VerificadorEquivalenciaAutorizacionContextual::class)->divergencias());
    }

    public function test_el_multi_rol_no_es_error_y_los_permisos_adicionales_son_aviso(): void
    {
        [$organizacion, $copropiedad, $user] = $this->conEquivalenciaCompleta('gestor');

        // Segundo Rol asignado en la misma Copropiedad (unión de roles).
        setPermissionsTeamId($copropiedad->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->assignRole(Role::query()->where('name', 'admin')->firstOrFail());

        $divergencias = app(VerificadorEquivalenciaAutorizacionContextual::class)->divergencias();

        $this->assertCount(1, $divergencias);
        $this->assertSame(VerificadorEquivalenciaAutorizacionContextual::SEVERIDAD_AVISO, $divergencias[0]['severidad']);
        $this->assertStringContainsString('permisos adicionales por multi-rol o permisos directos', $divergencias[0]['motivo']);
    }

    public function test_la_severidad_distingue_perdidas_de_deriva_explicada(): void
    {
        [$organizacion, $copropiedad, $user] = $this->conEquivalenciaCompleta('gestor');

        // Pérdida efectiva: el Rol de Spatie deja de aportar un permiso heredado.
        $rolGestor = Role::query()->where('name', 'gestor')->firstOrFail();
        DB::table('role_has_permissions')
            ->where('role_id', $rolGestor->id)
            ->where('permission_id', DB::table('permissions')->where('name', 'pqrs.listar')->value('id'))
            ->delete();

        $divergencias = app(VerificadorEquivalenciaAutorizacionContextual::class)->divergencias();
        $this->assertSame(VerificadorEquivalenciaAutorizacionContextual::SEVERIDAD_ERROR, $divergencias[0]['severidad']);
        $this->assertStringContainsString('faltan pqrs.listar', $divergencias[0]['motivo']);

        // Usuario global sin Membresía: deriva explicada.
        User::factory()->create(['role' => 'residente']);
        $divergencias = app(VerificadorEquivalenciaAutorizacionContextual::class)->divergencias();
        $avisos = array_filter($divergencias, fn (array $d) => $d['severidad'] === VerificadorEquivalenciaAutorizacionContextual::SEVERIDAD_AVISO);
        $this->assertNotEmpty($avisos);
        $this->assertStringContainsString('no tiene Membresía', collect($avisos)->first()['motivo']);

        // users.role desconocido: error.
        User::factory()->create(['role' => 'superadmin']);
        $divergencias = app(VerificadorEquivalenciaAutorizacionContextual::class)->divergencias();
        $this->assertTrue(collect($divergencias)->contains(
            fn (array $d) => $d['severidad'] === VerificadorEquivalenciaAutorizacionContextual::SEVERIDAD_ERROR
                && str_contains($d['motivo'], 'no es reconocido'),
        ));
    }

    public function test_el_diagnostico_spatie_clasifica_faltantes_como_error_y_extras_como_aviso(): void
    {
        [$organizacion, $copropiedad, $user] = $this->conEquivalenciaCompleta('gestor');

        // Asignación de Spatie sin contraparte legado activa: aviso.
        setPermissionsTeamId($copropiedad->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->assignRole(Role::query()->where('name', 'admin')->firstOrFail());
        $clasificadas = app(VerificadorEquivalenciaRbacSpatie::class)->divergenciasClasificadas();
        $this->assertNotEmpty($clasificadas);
        $this->assertSame(VerificadorEquivalenciaRbacSpatie::SEVERIDAD_AVISO, $clasificadas[0]['severidad']);
        $this->assertStringContainsString('no corresponde a una asignación legado activa', $clasificadas[0]['mensaje']);

        // Asignación legado sin espejo en Spatie: error.
        DB::table('model_has_roles')->where('model_id', $user->id)->delete();
        $clasificadas = app(VerificadorEquivalenciaRbacSpatie::class)->divergenciasClasificadas();
        $errores = array_filter($clasificadas, fn (array $d) => $d['severidad'] === VerificadorEquivalenciaRbacSpatie::SEVERIDAD_ERROR);
        $this->assertNotEmpty($errores);
        $this->assertStringContainsString('Falta la asignación de Spatie', collect($errores)->first()['mensaje']);
    }

    public function test_el_comando_falla_solo_con_divergencias_de_severidad_error(): void
    {
        [$organizacion, $copropiedad, $user] = $this->conEquivalenciaCompleta('gestor');

        // Solo avisos (multi-rol): el gate no exige 0 divergencias.
        setPermissionsTeamId($copropiedad->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->assignRole(Role::query()->where('name', 'admin')->firstOrFail());
        $this->artisan('resuelve:verificar-equivalencia-autorizacion-contextual')->assertSuccessful();

        // Un error (pérdida heredada) sí detiene el gate.
        $rolGestor = Role::query()->where('name', 'gestor')->firstOrFail();
        DB::table('role_has_permissions')
            ->where('role_id', $rolGestor->id)
            ->where('permission_id', DB::table('permissions')->where('name', 'pqrs.listar')->value('id'))
            ->delete();
        $this->artisan('resuelve:verificar-equivalencia-autorizacion-contextual')->assertFailed();
    }

    /**
     * Catálogo legado auténtico, asignación legada y espejo Spatie equivalente.
     *
     * @return array{0: \App\Models\Organizacion, 1: \App\Models\Copropiedad, 2: User}
     */
    private function conEquivalenciaCompleta(string $rol): array
    {
        [$organizacion, $copropiedad] = $this->catalogoLegado();
        $user = User::factory()->create(['role' => $rol]);
        $this->asignacionLegado($user, $organizacion, $copropiedad, $rol);
        $this->artisan('resuelve:migrar-rbac-spatie')->assertSuccessful();

        $this->assertSame([], app(VerificadorEquivalenciaRbacSpatie::class)->divergenciasClasificadas());
        $this->assertSame([], app(VerificadorEquivalenciaAutorizacionContextual::class)->divergencias());

        return [$organizacion, $copropiedad, $user];
    }
}
