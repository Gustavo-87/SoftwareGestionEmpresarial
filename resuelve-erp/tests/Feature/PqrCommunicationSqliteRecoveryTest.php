<?php

namespace Tests\Feature;

use App\Models\Pqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PqrCommunicationSqliteRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_critical_failure_is_recoverable_without_losing_or_duplicating_rows(): void
    {
        $migration = $this->migration();
        $migration->down();
        $author = User::factory()->create();
        $pqr = Pqr::factory()->create(['estado' => 'respondida']);
        DB::table('pqr_replies')->insert([
            ['pqr_id' => $pqr->id, 'user_id' => $author->id, 'body' => 'uno', 'is_draft' => 0, 'attachments' => '[{"size":1}]', 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['pqr_id' => $pqr->id, 'user_id' => $author->id, 'body' => 'dos', 'is_draft' => 1, 'attachments' => '[]', 'sent_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $expected = DB::table('pqr_replies')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        foreach (['after_rename', 'before_create', 'after_create', 'before_copy', 'during_copy', 'after_copy', 'before_drop_backup'] as $point) {
            $migration->injectSqliteFailureAt($point);
            try {
                $migration->up();
                $this->fail("Debía fallar en {$point}.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString($point, $exception->getMessage());
            }
            $this->assertTrue(Schema::hasTable('pqr_replies_c351a_backup'));
            $this->assertSame(2, DB::table('pqr_replies_c351a_backup')->count());
            $this->assertSame(1, (int) DB::scalar('PRAGMA foreign_keys'));

            $migration->up();
            $this->assertFalse(Schema::hasTable('pqr_replies_c351a_backup'), "El respaldo persistió después de reintentar {$point}.");
            $this->assertTrue(Schema::hasColumn('pqr_replies', 'official_response_slot'));
            $actual = DB::table('pqr_replies')->select(array_keys($expected[0]))->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
            $this->assertEquals($expected, $actual);
            $this->assertSame(2, DB::table('pqr_replies')->distinct()->count('id'));
            $migration->down();
        }
    }

    public function test_ambiguous_dual_tables_and_missing_both_abort_without_dropping_either(): void
    {
        $migration = $this->migration();
        $migration->down();
        DB::statement('CREATE TABLE pqr_replies_c351a_backup AS SELECT * FROM pqr_replies');
        $author = User::factory()->create();
        $pqr = Pqr::factory()->create();
        DB::table('pqr_replies')->insert(['pqr_id' => $pqr->id, 'user_id' => $author->id, 'body' => 'ambigua', 'is_draft' => 1]);
        try {
            $migration->up();
            $this->fail('El estado ambiguo debía abortar.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ambigua', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('pqr_replies'));
        $this->assertTrue(Schema::hasTable('pqr_replies_c351a_backup'));

        DB::statement('DROP TABLE pqr_replies');
        DB::statement('DROP TABLE pqr_replies_c351a_backup');
        $this->expectException(RuntimeException::class);
        $migration->up();
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_08_13_120000_secure_pqr_replies_and_create_communication_operations.php');
    }
}
