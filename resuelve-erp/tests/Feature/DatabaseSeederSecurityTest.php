<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_is_rejected_in_production_before_writing_data(): void
    {
        $this->app['env'] = 'production';
        config(['resuelve.demo_password' => 'ClaveDemoSegura2026']);

        try {
            app(DatabaseSeeder::class)->run();
            $this->fail('El seeder debió rechazar el entorno production.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('production', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_demo_seeder_fails_clearly_when_password_is_missing(): void
    {
        config(['resuelve.demo_password' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('RESUELVE_DEMO_PASSWORD');

        $this->seed(DatabaseSeeder::class);
    }

    public function test_demo_seeder_uses_configured_password_and_preserves_unrelated_users(): void
    {
        $password = 'ClaveDemoSegura2026';
        config(['resuelve.demo_password' => $password]);
        $unrelated = User::factory()->create([
            'name' => 'Usuario externo',
            'email' => 'externo@example.com',
            'role' => 'auditor',
            'password' => 'ClaveOriginalSegura',
        ]);
        $originalPasswordHash = $unrelated->password;

        $this->seed(DatabaseSeeder::class);

        foreach ([
            'gestionpqrs7@gmail.com',
            'residentepqrs@gmail.com',
            'carlos.mejia@example.com',
            'andrea.ruiz@example.com',
        ] as $email) {
            $this->assertTrue(Hash::check($password, User::query()->where('email', $email)->firstOrFail()->password));
        }

        $unrelated->refresh();
        $this->assertSame('Usuario externo', $unrelated->name);
        $this->assertSame('auditor', $unrelated->role);
        $this->assertSame($originalPasswordHash, $unrelated->password);
    }
}
