<?php

namespace Tests\Feature;

use App\Application\Roles\CrearRol;
use App\Application\Roles\RevocarRolUsuario;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * Concurrencia real de la protección de última capacidad administrativa.
 * Requiere MySQL y pcntl (patrón de PqrCommunicationMysqlConcurrencyTest):
 * dos revocaciones simultáneas sobre la misma Copropiedad no pueden dejarla
 * sin ningún miembro vigente con el permiso `usuarios.gestionar`.
 */
class RolesAsignacionMysqlConcurrencyTest extends TestCase
{
    use CreatesInstitutionalContext;

    public function test_dos_revocaciones_simultaneas_no_dejan_la_copropiedad_sin_capacidad_administrativa(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requiere MySQL y pcntl.');
        }

        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $plataforma = User::factory()->create(['es_administrador_sistema' => true]);
        \Spatie\Permission\Models\Permission::findOrCreate('usuarios.gestionar', 'web');
        $sufijo = \Illuminate\Support\Str::random(6);
        $rolUno = app(CrearRol::class)->ejecutar($plataforma, null, ['nombre' => 'Capacidad uno '.$sufijo, 'permisos' => ['usuarios.gestionar']]);
        $rolDos = app(CrearRol::class)->ejecutar($plataforma, null, ['nombre' => 'Capacidad dos '.$sufijo, 'permisos' => ['usuarios.gestionar']]);

        $usuarios = User::factory()->count(2)->create();
        $membresias = $usuarios->map(fn (User $usuario) => MembresiaCopropiedad::create([
            'usuario_id' => $usuario->id,
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
            'estado' => 'activa',
            'vigente_desde' => now()->subMinute(),
        ]));
        setPermissionsTeamId((int) $copropiedad->id);
        $usuarios[0]->assignRole($rolUno);
        $usuarios[1]->assignRole($rolDos);

        [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        try {
            DB::beginTransaction();
            app(RevocarRolUsuario::class)->ejecutar($plataforma, null, $membresias[0], (int) $rolUno->id);

            $pid = pcntl_fork();
            if ($pid === 0) {
                fclose($parentSocket);
                config(['database.connections.mysql_child' => config('database.connections.mysql')]);
                fwrite($childSocket, DB::connection('mysql_child')->selectOne('SELECT CONNECTION_ID() id')->id."\n");
                fflush($childSocket);
                DB::setDefaultConnection('mysql_child');
                try {
                    app(RevocarRolUsuario::class)->ejecutar($plataforma, null, $membresias[1], (int) $rolDos->id);
                    fwrite($childSocket, "permitido\n");
                } catch (ValidationException) {
                    fwrite($childSocket, "bloqueado\n");
                } catch (\Throwable $exception) {
                    fwrite($childSocket, 'error: '.$exception->getMessage()."\n");
                }
                fclose($childSocket);
                exit(0);
            }

            fclose($childSocket);
            $childConnectionId = (int) trim(fgets($parentSocket));
            $this->assertTrue($this->waitUntilChildIsBlocked($childConnectionId));
            DB::commit();
            $resultado = trim(fgets($parentSocket));
            pcntl_waitpid($pid, $status);
            fclose($parentSocket);
            DB::purge('mysql');
            DB::reconnect('mysql');

            $this->assertSame('bloqueado', $resultado);
            $this->assertDatabaseMissing('model_has_roles', ['model_id' => $usuarios[0]->id, 'role_id' => $rolUno->id]);
            $this->assertDatabaseHas('model_has_roles', ['model_id' => $usuarios[1]->id, 'role_id' => $rolDos->id]);
        } finally {
            // Limpieza garantizada incluso ante fallos: evita residuos en la BD.
            DB::table('model_has_roles')->whereIn('model_id', $usuarios->pluck('id')->all())->delete();
            DB::table('membresias_copropiedad')->whereIn('id', $membresias->pluck('id')->all())->delete();
            DB::table('audit_logs')->where('user_id', $plataforma->id)->where('action', 'usuario.rol.revocar')->delete();
            $rolUno->delete();
            $rolDos->delete();
            $usuarios->each->delete();
            $plataforma->delete();
            DB::table('site_settings')->where('organizacion_id', $organizacion->id)->delete();
            DB::table('copropiedades')->where('id', $copropiedad->id)->delete();
            DB::table('organizaciones')->where('id', $organizacion->id)->delete();
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
                if (str_contains($normalized, 'lock') || str_contains($normalized, 'updat')) {
                    return true;
                }
            }
            if (is_string($process?->INFO) && str_contains(strtolower($process->INFO), 'for update')) {
                return true;
            }
        } while (hrtime(true) < $deadline);

        return false;
    }
}
