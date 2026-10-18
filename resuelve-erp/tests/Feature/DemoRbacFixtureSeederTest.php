<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoRbacFixtureSeeder;
use ReflectionClass;
use Tests\TestCase;

/**
 * HU-USR-09 (Sprint 18): el fixture de demostración RBAC es OPT-IN.
 * Estas pruebas verifican el contrato del fixture sin ejecutarlo (no se siembra
 * automáticamente contra ninguna base de datos).
 */
class DemoRbacFixtureSeederTest extends TestCase
{
    public function test_el_fixture_es_opt_in_y_no_esta_registrado_en_database_seeder(): void
    {
        $this->assertTrue(class_exists(DemoRbacFixtureSeeder::class));

        $fuenteDatabaseSeeder = file_get_contents((new ReflectionClass(DatabaseSeeder::class))->getFileName());
        $this->assertStringNotContainsString('DemoRbacFixtureSeeder', $fuenteDatabaseSeeder);
    }

    public function test_el_fixture_declara_dos_roles_y_un_permiso_directo_por_equipo(): void
    {
        $this->assertSame(['gestor', 'auditor'], DemoRbacFixtureSeeder::ROLES_DEMO);
        $this->assertSame('auditoria.ver', DemoRbacFixtureSeeder::PERMISO_DIRECTO_DEMO);
        $this->assertNotContains(
            DemoRbacFixtureSeeder::PERMISO_DIRECTO_DEMO,
            ['pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'pqrs.ver_todas', 'pqrs.gestionar', 'informes.exportar', 'notificaciones.consultar'],
            'El permiso directo de demostración no debe pertenecer a la matriz de los roles demo.',
        );
    }
}
