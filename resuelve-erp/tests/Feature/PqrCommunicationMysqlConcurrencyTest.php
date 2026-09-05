<?php

namespace Tests\Feature;

use App\Application\Pqrs\Idempotency\PqrCommunicationOperationConflict;
use App\Application\Pqrs\Idempotency\PqrCommunicationOperationRepository;
use App\Application\Pqrs\GestionarCicloRespuestaPqrs;
use App\Application\Contexto\ContextResolver;
use App\Application\Notificaciones\ReconciliarNotificacionRespuestaPqrs;
use App\Enums\PqrCommunicationNotificationStatus;
use App\Enums\PqrCommunicationResultCode;
use App\Enums\PqrCommunicationOperation as OperationType;
use App\Models\Pqr;
use App\Models\PqrCommunicationOperation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Concerns\CreatesInstitutionalContext;

class PqrCommunicationMysqlConcurrencyTest extends TestCase
{
    use CreatesInstitutionalContext;

    public function test_repository_claim_is_deterministic_with_two_mysql_connections_and_rejects_all_scope_changes(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requiere MySQL y pcntl.');
        }
        $repository = app(PqrCommunicationOperationRepository::class);
        $actor = User::factory()->create();
        $otherActor = User::factory()->create();
        $pqr = Pqr::factory()->create();
        $otherPqr = Pqr::factory()->create();
        $key = (string) Str::uuid();
        $hash = hash('sha256', 'concurrent-payload');
        [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        DB::beginTransaction();
        $first = $repository->claim($pqr, $actor, OperationType::CreateReply, $key, $hash);
        $this->assertTrue($first->claimed);
        $pid = pcntl_fork();
        if ($pid === 0) {
            fclose($parentSocket);
            config(['database.connections.mysql_child' => config('database.connections.mysql')]);
            fwrite($childSocket, DB::connection('mysql_child')->selectOne('SELECT CONNECTION_ID() id')->id."\n");
            fflush($childSocket);
            $claim = app(PqrCommunicationOperationRepository::class)->claim($pqr, $actor, OperationType::CreateReply, $key, $hash, 'mysql_child');
            fwrite($childSocket, json_encode(['claimed' => $claim->claimed, 'id' => $claim->operation->id], JSON_THROW_ON_ERROR)."\n");
            fclose($childSocket);
            exit(0);
        }

        fclose($childSocket);
        $childConnectionId = (int) trim(fgets($parentSocket));
        $this->assertTrue($this->waitUntilChildIsBlocked($childConnectionId));
        DB::commit();
        $second = json_decode(trim(fgets($parentSocket)), true, flags: JSON_THROW_ON_ERROR);
        pcntl_waitpid($pid, $status);
        fclose($parentSocket);
        DB::purge('mysql');
        DB::reconnect('mysql');

        $this->assertFalse($second['claimed']);
        $this->assertSame($first->operation->id, $second['id']);
        $this->assertSame(1, PqrCommunicationOperation::where('idempotency_key', $key)->count());
        foreach ([
            [$otherPqr, $actor, OperationType::CreateReply, $hash],
            [$pqr, $otherActor, OperationType::CreateReply, $hash],
            [$pqr, $actor, OperationType::CreateInternalComment, $hash],
            [$pqr, $actor, OperationType::CreateReply, hash('sha256', 'different')],
        ] as [$candidatePqr, $candidateActor, $candidateOperation, $candidateHash]) {
            try {
                $repository->claim($candidatePqr, $candidateActor, $candidateOperation, $key, $candidateHash);
                $this->fail('El ámbito diferente debía producir conflicto.');
            } catch (PqrCommunicationOperationConflict) {
                $this->assertTrue(true);
            }
        }

        $invalidPqr = new Pqr;
        $invalidPqr->setAttribute('id', PHP_INT_MAX);
        $this->expectException(QueryException::class);
        try {
            $repository->claim($invalidPqr, $actor, OperationType::CreateReply, (string) Str::uuid(), $hash);
        } finally {
            PqrCommunicationOperation::where('idempotency_key', $key)->delete();
        }
    }

    public function test_send_draft_waits_for_concurrent_update_and_preserves_committed_body(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requiere MySQL y pcntl.');
        }
        [$organization, $property] = $this->createInstitutionalContext();
        $actor = User::factory()->create();
        $this->createContextualIdentity($actor, $organization, $property, 'gestor', ['pqrs.ver_todas', 'pqrs.gestionar']);
        $pqr = Pqr::factory()->paraContexto($organization, $property)->create(['estado' => 'radicada']);
        $draft = $pqr->replies()->create(['user_id' => $actor->id, 'body' => 'antes', 'is_draft' => true, 'attachments' => []]);
        [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        DB::beginTransaction();
        Pqr::query()->lockForUpdate()->findOrFail($pqr->id);
        $lockedDraft = $pqr->replies()->lockForUpdate()->findOrFail($draft->id);
        $pid = pcntl_fork();
        if ($pid === 0) {
            fclose($parentSocket);
            config(['database.connections.mysql_child' => config('database.connections.mysql')]);
            DB::purge('mysql_child');
            DB::setDefaultConnection('mysql_child');
            fwrite($childSocket, DB::connection()->selectOne('SELECT CONNECTION_ID() id')->id."\n");
            fflush($childSocket);
            $context = app(ContextResolver::class)->resolverExplicito($organization->id, $property->id, $actor->id);
            $result = app(GestionarCicloRespuestaPqrs::class)->send($context, $actor, $pqr, $draft, (string) Str::uuid());
            fwrite($childSocket, json_encode(['body' => $result->body, 'draft' => $result->is_draft], JSON_THROW_ON_ERROR)."\n");
            fclose($childSocket);
            exit(0);
        }
        fclose($childSocket);
        $childConnectionId = (int) trim(fgets($parentSocket));
        $this->assertTrue($this->waitUntilChildIsBlocked($childConnectionId));
        DB::table('pqr_replies')->where('id', $lockedDraft->id)->update(['body' => 'edición confirmada', 'updated_at' => now()]);
        DB::commit();
        $result = json_decode(trim(fgets($parentSocket)), true, flags: JSON_THROW_ON_ERROR);
        pcntl_waitpid($pid, $status);
        fclose($parentSocket);
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
        DB::reconnect('mysql');

        $this->assertSame('edición confirmada', $result['body']);
        $this->assertFalse($result['draft']);
        $this->assertSame('edición confirmada', $draft->fresh()->body);
        $this->assertFalse($draft->fresh()->is_draft);
    }

    public function test_notification_reconciliation_lock_creates_one_notification_and_one_job(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requiere MySQL y pcntl.');
        }
        [$organization, $property] = $this->createInstitutionalContext();
        $actor = User::factory()->create();
        $resident = User::factory()->create();
        $this->createContextualIdentity($actor, $organization, $property, 'manager_b3_concurrency', ['pqrs.gestionar']);
        $this->createContextualIdentity($resident, $organization, $property, 'resident_b3_concurrency', ['notificaciones.consultar']);
        $pqr = Pqr::factory()->paraContexto($organization, $property)->create(['user_id' => $resident->id]);
        $reply = $pqr->replies()->create(['user_id' => $actor->id, 'body' => 'Respuesta', 'is_draft' => false, 'sent_at' => now()]);
        $repository = app(PqrCommunicationOperationRepository::class);
        $claim = $repository->claim($pqr, $actor, OperationType::SendReply, (string) Str::uuid(), hash('sha256', 'notification-concurrency'), null, PqrCommunicationNotificationStatus::Pending);
        $operation = $repository->complete($claim->operation, PqrCommunicationResultCode::ReplySent, $reply->id);
        [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        DB::beginTransaction();
        PqrCommunicationOperation::query()->lockForUpdate()->findOrFail($operation->id);
        $pid = pcntl_fork();
        if ($pid === 0) {
            fclose($parentSocket);
            config(['database.connections.mysql_child' => config('database.connections.mysql')]);
            DB::purge('mysql_child');
            DB::setDefaultConnection('mysql_child');
            fwrite($childSocket, DB::connection()->selectOne('SELECT CONNECTION_ID() id')->id."\n");
            fflush($childSocket);
            $status = app(ReconciliarNotificacionRespuestaPqrs::class)->ejecutar($operation->id);
            fwrite($childSocket, $status->value."\n");
            fclose($childSocket);
            exit(0);
        }
        fclose($childSocket);
        $childConnectionId = (int) trim(fgets($parentSocket));
        $this->assertTrue($this->waitUntilChildIsBlocked($childConnectionId));
        $parentStatus = app(ReconciliarNotificacionRespuestaPqrs::class)->ejecutar($operation->id);
        DB::commit();
        $childStatus = trim(fgets($parentSocket));
        pcntl_waitpid($pid, $status);
        fclose($parentSocket);
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
        DB::reconnect('mysql');

        $this->assertSame('completed', $parentStatus->value);
        $this->assertSame('completed', $childStatus);
        $this->assertSame(1, $resident->notifications()->count());
        $this->assertSame(1, DB::table('jobs')->count());
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
