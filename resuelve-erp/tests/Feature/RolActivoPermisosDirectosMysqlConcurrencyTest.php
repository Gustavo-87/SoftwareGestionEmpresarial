<?php

namespace Tests\Feature;

use App\Application\Roles\CrearRol;
use App\Application\Roles\GestionarPermisosDirectos;
use App\Application\Roles\GestionarRolActivo;
use App\Application\Roles\RevocarRolUsuario;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * Concurrencia real de HU-USR-04 y HU-USR-05 (Sprint 18). Requiere MySQL y
 * pcntl (patrón de RolesAsignacionMysqlConcurrencyTest):
 *
 * - dos revocaciones simultáneas del permiso directo `usuarios.gestionar` no
 *   pueden dejar a la Copropiedad sin capacidad administrativa;
 * - la selección del Rol activo es consistente con la revocación concurrente
 *   del Rol: la revalidación nunca deja permisos de un Rol ya revocado.
 *
 * Nota de arnés: MySQL 8 informa `executing` durante la espera de una lectura
 * bloqueante y las vistas de `information_schema` no devuelven filas con
 * binding en el WHERE, por lo que la detección de bloqueo consulta las vistas
 * completas y también `performance_schema.data_lock_waits`.
 */
class RolActivoPermisosDirectosMysqlConcurrencyTest extends TestCase
{
    use CreatesInstitutionalContext;

    public function test_dos_revocaciones_simultaneas_de_permiso_directo_conservan_capacidad(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requiere MySQL y pcntl.');
        }

        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        Permission::findOrCreate('usuarios.gestionar', 'web');

        $usuarios = User::factory()->count(2)->create();
        $membresias = $usuarios->map(fn (User $usuario) => MembresiaCopropiedad::create([
            'usuario_id' => $usuario->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subMinute(),
        ]));

        $directos = app(GestionarPermisosDirectos::class);
        $directos->asignar($plataforma, null, $membresias[0], 'usuarios.gestionar');
        $directos->asignar($plataforma, null, $membresias[1], 'usuarios.gestionar');

        [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        try {
            DB::beginTransaction();
            $directos->revocar($plataforma, null, $membresias[0], 'usuarios.gestionar');

            $pid = pcntl_fork();
            if ($pid === 0) {
                fclose($parentSocket);
                config(['database.connections.mysql_child' => config('database.connections.mysql')]);
                fwrite($childSocket, DB::connection('mysql_child')->selectOne('SELECT CONNECTION_ID() id')->id."\n");
                fflush($childSocket);
                DB::setDefaultConnection('mysql_child');
                try {
                    app(GestionarPermisosDirectos::class)->revocar($plataforma, null, $membresias[1], 'usuarios.gestionar');
                    fwrite($childSocket, "permitido\n");
                } catch (ValidationException) {
                    fwrite($childSocket, "bloqueado\n");
                } catch (\RuntimeException) {
                    fwrite($childSocket, "bloqueado\n");
                } catch (\Throwable $exception) {
                    fwrite($childSocket, 'error: '.$exception->getMessage()."\n");
                }
                fclose($childSocket);
                exit(0);
            }

            fclose($childSocket);
            $childConnectionId = (int) trim(fgets($parentSocket));
            if (! $this->waitUntilChildIsBlocked($childConnectionId)) {
                stream_set_timeout($parentSocket, 5);
                $salidaHijo = trim((string) stream_get_contents($parentSocket));
                $this->fail('El proceso hijo no entró en espera de bloqueo. Salida: '.($salidaHijo !== '' ? $salidaHijo : 'sin salida'));
            }
            DB::commit();
            $resultado = trim((string) fgets($parentSocket));
            pcntl_waitpid($pid, $status);
            fclose($parentSocket);
            DB::purge('mysql');
            DB::reconnect('mysql');

            // El hijo se bloquea en las Membresías y, al reanudar, evalúa el
            // estado confirmado: solo una de las dos revocaciones queda permitida.
            $this->assertSame('bloqueado', $resultado);
            $this->assertDatabaseMissing('model_has_permissions', [
                'model_id' => $usuarios[0]->id,
                'permission_id' => Permission::where('name', 'usuarios.gestionar')->value('id'),
            ]);
        } finally {
            $this->limpiarTransaccionAbierta();
            DB::table('model_has_permissions')->whereIn('model_id', $usuarios->pluck('id')->all())->delete();
            DB::table('membresias_copropiedad')->whereIn('id', $membresias->pluck('id')->all())->delete();
            DB::table('audit_logs')->where('user_id', $plataforma->id)->where('action', 'usuario.permiso_directo.revocar')->delete();
            DB::table('audit_logs')->where('user_id', $plataforma->id)->where('action', 'usuario.permiso_directo.asignar')->delete();
            $usuarios->each->delete();
            $plataforma->delete();
            DB::table('site_settings')->where('organizacion_id', $organizacion->id)->delete();
            DB::table('copropiedades')->where('id', $copropiedad->id)->delete();
            DB::table('organizaciones')->where('id', $organizacion->id)->delete();
        }
    }

    public function test_la_seleccion_del_rol_activo_se_revalida_con_revocaciones_concurrentes(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requiere MySQL y pcntl.');
        }

        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        Permission::findOrCreate('pqrs.listar', 'web');
        Permission::findOrCreate('usuarios.gestionar', 'web');
        $sufijo = Str::random(6);
        $rol = app(CrearRol::class)->ejecutar($plataforma, null, ['nombre' => 'Rol activo '.$sufijo, 'permisos' => ['pqrs.listar']]);
        $usuario = User::factory()->create();
        $membresia = MembresiaCopropiedad::create([
            'usuario_id' => $usuario->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subMinute(),
        ]);
        setPermissionsTeamId((int) $copropiedad->id);
        $usuario->assignRole($rol);

        // Segundo titular de la capacidad administrativa: la revocación del Rol
        // activo no debe ser bloqueada por la protección "≥1".
        $rolCapacidad = app(CrearRol::class)->ejecutar($plataforma, null, ['nombre' => 'Rol capacidad '.$sufijo, 'permisos' => ['usuarios.gestionar']]);
        $titular = User::factory()->create();
        $membresiaTitular = MembresiaCopropiedad::create([
            'usuario_id' => $titular->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subMinute(),
        ]);
        setPermissionsTeamId((int) $copropiedad->id);
        $titular->assignRole($rolCapacidad);

        [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        try {
            DB::beginTransaction();
            app(GestionarRolActivo::class)->ejecutar($usuario, $membresia, (int) $rol->id);

            $pid = pcntl_fork();
            if ($pid === 0) {
                fclose($parentSocket);
                config(['database.connections.mysql_child' => config('database.connections.mysql')]);
                fwrite($childSocket, DB::connection('mysql_child')->selectOne('SELECT CONNECTION_ID() id')->id."\n");
                fflush($childSocket);
                DB::setDefaultConnection('mysql_child');
                try {
                    app(RevocarRolUsuario::class)->ejecutar($plataforma, null, $membresia, (int) $rol->id);
                    fwrite($childSocket, "permitido\n");
                } catch (\Throwable $exception) {
                    fwrite($childSocket, 'error: '.$exception->getMessage()."\n");
                }
                fclose($childSocket);
                exit(0);
            }

            fclose($childSocket);
            $childConnectionId = (int) trim(fgets($parentSocket));
            if (! $this->waitUntilChildIsBlocked($childConnectionId)) {
                stream_set_timeout($parentSocket, 5);
                $salidaHijo = trim((string) stream_get_contents($parentSocket));
                $this->fail('El proceso hijo no entró en espera de bloqueo. Salida: '.($salidaHijo !== '' ? $salidaHijo : 'sin salida'));
            }
            DB::commit();
            $resultado = trim((string) fgets($parentSocket));
            pcntl_waitpid($pid, $status);
            fclose($parentSocket);
            DB::purge('mysql');
            DB::reconnect('mysql');

            $this->assertSame('permitido', $resultado);

            // Invariante: la selección caducada nunca aporta permisos.
            $contexto = app(\App\Application\Contexto\ContextResolver::class)
                ->resolverExplicito($organizacion->id, $copropiedad->id, $usuario->id);
            $this->assertNull($contexto->rolActivoClave);
            $this->assertNotContains($rol->name, $contexto->clavesRoles());
            $this->assertNull($membresia->fresh()->rol_activo_id);
        } finally {
            $this->limpiarTransaccionAbierta();
            DB::table('model_has_roles')->whereIn('model_id', [$usuario->id, $titular->id])->delete();
            DB::table('membresias_copropiedad')->whereIn('id', [$membresia->id, $membresiaTitular->id])->delete();
            DB::table('audit_logs')->where('user_id', $plataforma->id)->delete();
            $rol->delete();
            $rolCapacidad->delete();
            $usuario->delete();
            $titular->delete();
            $plataforma->delete();
            DB::table('site_settings')->where('organizacion_id', $organizacion->id)->delete();
            DB::table('copropiedades')->where('id', $copropiedad->id)->delete();
            DB::table('organizaciones')->where('id', $organizacion->id)->delete();
        }
    }

    /**
     * El hijo comparte el socket heredado de `mysql`: se descarta la transacción
     * abierta y se reconecta antes de limpiar para no heredar bloqueos ni una
     * conexión ya cerrada.
     */
    private function limpiarTransaccionAbierta(): void
    {
        if (DB::transactionLevel() > 0) {
            try {
                DB::rollBack();
            } catch (\Throwable) {
            }
        }
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    private function waitUntilChildIsBlocked(int $connectionId): bool
    {
        $deadline = hrtime(true) + 30_000_000_000;
        do {
            foreach (DB::select('SELECT REQUESTING_THREAD_ID FROM performance_schema.data_lock_waits') as $espera) {
                if ((int) $espera->REQUESTING_THREAD_ID === $connectionId) {
                    return true;
                }
            }
            // Las vistas de `information_schema` no devuelven filas con binding
            // en el WHERE, por lo que se consultan completas y se filtran aquí.
            foreach (DB::select('SELECT trx_mysql_thread_id, trx_state FROM information_schema.INNODB_TRX') as $trx) {
                if ((int) $trx->trx_mysql_thread_id === $connectionId
                    && strtoupper(trim((string) $trx->trx_state)) === 'LOCK WAIT') {
                    return true;
                }
            }
            $process = DB::table('information_schema.PROCESSLIST')->where('ID', $connectionId)->first(['STATE', 'INFO']);
            $state = $process?->STATE;
            $info = $process?->INFO;
            if (is_string($state)) {
                $normalized = strtolower($state);
                if (str_contains($normalized, 'lock') || str_contains($normalized, 'updat')) {
                    return true;
                }
                // MySQL 8 informa `executing` durante la espera de una lectura
                // bloqueante; el texto de la sentencia confirma el bloqueo.
                if (str_contains($normalized, 'execut') && is_string($info) && str_contains(strtolower($info), 'for update')) {
                    return true;
                }
            }
            usleep(20_000);
        } while (hrtime(true) < $deadline);

        return false;
    }
}
