<?php

namespace Tests\Feature;

use App\Application\Contexto\ContextResolver;
use App\Application\Contexto\ContextoOperativo;
use App\Application\Mantenimiento\GenerarMantenimientoDesdePqr;
use App\Models\Mantenimiento;
use App\Models\Pqr;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * Prueba REAL de concurrencia en MySQL (HU-PQRS-03, Sprint 19).
 *
 * Demuestra con dos conexiones MySQL reales y `pcntl_fork` que dos solicitudes
 * simultáneas con la misma clave de idempotencia producen **un único
 * mantenimiento**, y que reutilizar esa clave con otra PQRS o con datos
 * incompatibles no devuelve ni modifica un recurso incorrecto.
 *
 * Se ejecuta únicamente con MySQL y pcntl disponibles (base aislada de
 * pruebas); con SQLite en memoria queda omitida.
 */
class MantenimientoIdempotencyMysqlConcurrencyTest extends TestCase
{
    use CreatesInstitutionalContext;

    public function test_dos_conexiones_mysql_simultaneas_con_la_misma_clave_producen_un_unico_mantenimiento(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requiere MySQL y pcntl.');
        }

        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $gestor = User::factory()->create();
        $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'gestor', [
            'pqrs.listar', 'pqrs.ver_todas', 'pqrs.gestionar',
            'mantenimiento.crear', 'mantenimiento.ver_todas', 'mantenimiento.gestionar',
        ]);
        $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['user_id' => $gestor->id]);
        $clave = 'concurrencia-'.bin2hex(random_bytes(8));
        [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        try {
            // El padre inserta la solicitud dentro de una transacción abierta:
            // la segunda conexión queda bloqueada por el índice único hasta el
            // commit y, al despertar, debe resolver la misma creación.
            DB::beginTransaction();
            $contexto = app(ContextResolver::class)->resolverExplicito($organizacion->id, $copropiedad->id, $gestor->id);
            app()->instance(ContextoOperativo::class, $contexto);
            $primero = app(GenerarMantenimientoDesdePqr::class)->ejecutar(
                $gestor,
                $contexto,
                $pqr,
                'Reparación de fugas',
                'Revisar tubería del sótano',
                '2026-11-01',
                $clave,
            );

            $pid = pcntl_fork();
            if ($pid === 0) {
                fclose($parentSocket);
                config(['database.connections.mysql_child' => config('database.connections.mysql')]);
                DB::purge('mysql_child');
                DB::setDefaultConnection('mysql_child');
                fwrite($childSocket, DB::connection()->selectOne('SELECT CONNECTION_ID() id')->id."\n");
                fflush($childSocket);
                try {
                    $contextoHijo = app(ContextResolver::class)->resolverExplicito($organizacion->id, $copropiedad->id, $gestor->id);
                    app()->instance(ContextoOperativo::class, $contextoHijo);
                    $segundo = app(GenerarMantenimientoDesdePqr::class)->ejecutar(
                        $gestor,
                        $contextoHijo,
                        $pqr,
                        'Reparación de fugas',
                        'Revisar tubería del sótano',
                        '2026-11-01',
                        $clave,
                    );
                    fwrite($childSocket, json_encode(['id' => $segundo->id], JSON_THROW_ON_ERROR)."\n");
                } catch (\Throwable $exception) {
                    fwrite($childSocket, json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n");
                }
                fclose($childSocket);
                exit(0);
            }

            fclose($childSocket);
            $childConnectionId = (int) trim(fgets($parentSocket));
            $this->assertTrue($this->waitUntilChildIsBlocked($childConnectionId), 'La segunda conexión debía quedar bloqueada por la clave única.');
            DB::commit();
            $segundo = json_decode(trim(fgets($parentSocket)), true, flags: JSON_THROW_ON_ERROR);
            pcntl_waitpid($pid, $status);
            fclose($parentSocket);
            DB::purge('mysql');
            DB::reconnect('mysql');

            $this->assertArrayNotHasKey('error', $segundo, 'La segunda conexión no debía fallar: '.($segundo['error'] ?? ''));

            // Una sola creación: ambas solicitudes simultáneas devuelven el
            // mismo recurso, sin duplicados y sin estados intermedios.
            $this->assertSame($primero->id, $segundo['id']);
            $this->assertSame(1, Mantenimiento::query()->where('idempotency_key', $clave)->count());

            $original = Mantenimiento::query()->where('idempotency_key', $clave)->firstOrFail();

            // Reutilizar la clave con otra PQRS o con datos incompatibles no
            // devuelve ni modifica el recurso original.
            foreach ([[$pqr, 'Otro título', 'Revisar tubería del sótano'], [$pqr, 'Reparación de fugas', 'Otra descripción']] as [$pqrCandidata, $titulo, $descripcion]) {
                try {
                    app(GenerarMantenimientoDesdePqr::class)->ejecutar(
                        $gestor,
                        $contexto,
                        $pqrCandidata,
                        $titulo,
                        $descripcion,
                        '2026-11-01',
                        $clave,
                    );
                    $this->fail('La reutilización de la clave con datos incompatibles debía ser rechazada.');
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    $this->assertArrayHasKey('idempotency_key', $exception->errors());
                }
            }

            $pqrAjena = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create(['user_id' => $gestor->id]);
            try {
                app(GenerarMantenimientoDesdePqr::class)->ejecutar(
                    $gestor,
                    $contexto,
                    $pqrAjena,
                    'Reparación de fugas',
                    'Revisar tubería del sótano',
                    '2026-11-01',
                    $clave,
                );
                $this->fail('La reutilización de la clave con otra PQRS debía ser rechazada.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $this->assertArrayHasKey('idempotency_key', $exception->errors());
            }

            $this->assertSame(1, Mantenimiento::query()->where('idempotency_key', $clave)->count());
            $this->assertSame($pqr->id, (int) $original->fresh()->pqr_id);
            $this->assertSame('Reparación de fugas', $original->fresh()->titulo);
            $this->assertSame(0, Mantenimiento::query()->where('pqr_id', $pqrAjena->id)->count());
        } finally {
            Mantenimiento::query()->where('idempotency_key', $clave)->delete();
        }
    }

    private function waitUntilChildIsBlocked(int $connectionId): bool
    {
        $deadline = hrtime(true) + 5_000_000_000;
        do {
            $process = DB::table('information_schema.PROCESSLIST')->where('ID', $connectionId)->first(['STATE', 'INFO']);
            $state = $process?->STATE;
            if (is_string($state)) {
                $normalized = strtolower($state);
                if (str_contains($normalized, 'lock') || str_contains($normalized, 'updat') || str_contains($normalized, 'send')) {
                    return true;
                }
            }
            if (is_string($process?->INFO) && str_contains(strtolower($process->INFO), 'insert into `mantenimientos`')) {
                return true;
            }
        } while (hrtime(true) < $deadline);

        return false;
    }
}
