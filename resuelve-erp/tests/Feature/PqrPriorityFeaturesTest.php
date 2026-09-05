<?php
namespace Tests\Feature;
use App\Models\Pqr;
use App\Models\TipoPqr;
use App\Models\User;
use App\Notifications\PqrEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;
class PqrPriorityFeaturesTest extends TestCase {
    use CreatesInstitutionalContext, RefreshDatabase;
    public function test_creating_a_pqr_notifies_managers_and_records_activity(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        Notification::fake(); $manager = User::factory()->create(['role'=>'gestor']); $resident = User::factory()->create(['role'=>'residente']); $type = TipoPqr::factory()->create();
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['pqrs.gestionar', 'notificaciones.consultar']);
        $this->createContextualIdentity($resident, $organizacion, $copropiedad, 'residente', ['pqrs.crear']);
        $this->actingAs($resident)->post(route('pqrs.store'), ['asunto'=>'Ruido nocturno','descripcion'=>'Se presenta ruido frecuente en la noche.','fecha_radicacion'=>now()->format('Y-m-d'),'tipo_pqr_id'=>$type->id])->assertRedirect();
        $pqr = Pqr::firstOrFail(); $this->assertDatabaseHas('pqr_activities',['pqr_id'=>$pqr->id,'action'=>'created']); Notification::assertSentTo($manager,PqrEventNotification::class);
    }
    public function test_manager_can_assign_and_change_status_with_traceability(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        Notification::fake(); $manager = User::factory()->create(['role'=>'gestor']); $resident = User::factory()->create(['role'=>'residente']); $type = TipoPqr::factory()->create(); $pqr = Pqr::factory()->create(['user_id'=>$resident->id,'tipo_pqr_id'=>$type->id,'estado'=>'radicada']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['pqrs.gestionar']);
        $this->createContextualIdentity($resident, $organizacion, $copropiedad, 'residente', ['notificaciones.consultar']);
        $this->actingAs($manager)->put(route('pqrs.update',$pqr),['asunto'=>$pqr->asunto,'descripcion'=>$pqr->descripcion,'fecha_radicacion'=>$pqr->fecha_radicacion->format('Y-m-d'),'fecha_limite_respuesta'=>$pqr->fecha_limite_respuesta?->format('Y-m-d'),'tipo_pqr_id'=>$type->id,'estado'=>'en_revision','assigned_to_id'=>$manager->id])->assertRedirect();
        $this->assertDatabaseHas('pqrs',['id'=>$pqr->id,'assigned_to_id'=>$manager->id,'estado'=>'en_revision']); $this->assertDatabaseHas('pqr_activities',['pqr_id'=>$pqr->id,'action'=>'updated']); Notification::assertSentTo($resident,PqrEventNotification::class);
    }
    public function test_manager_can_send_a_reply_and_notify_resident(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        Notification::fake(); $manager = User::factory()->create(['role'=>'gestor']); $resident = User::factory()->create(['role'=>'residente']); $pqr = Pqr::factory()->create(['user_id'=>$resident->id, 'estado'=>'radicada']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['pqrs.gestionar']);
        $this->createContextualIdentity($resident, $organizacion, $copropiedad, 'residente', ['notificaciones.consultar']);
        $this->actingAs($manager)->post(route('pqrs.replies.store',$pqr),['body'=>'La solicitud fue revisada y solucionada.','action'=>'send','send_reply_key'=>(string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pqr_replies',['pqr_id'=>$pqr->id,'is_draft'=>false]); $this->assertSame('respondida',$pqr->fresh()->estado); Notification::assertSentTo($resident,PqrEventNotification::class);
    }
    public function test_advanced_search_finds_resident_name_and_type_filter(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $manager = User::factory()->create(['role'=>'gestor']); $resident = User::factory()->create(['name'=>'María Especial','role'=>'residente']); $type = TipoPqr::factory()->create(); Pqr::factory()->create(['user_id'=>$resident->id,'tipo_pqr_id'=>$type->id,'asunto'=>'Caso único']);
        $this->createContextualIdentity($manager, $organizacion, $copropiedad, 'gestor', ['pqrs.listar', 'pqrs.ver_todas']);
        $this->actingAs($manager)->get(route('pqrs.index',['buscar'=>'María Especial','tipo_pqr_id'=>$type->id]))->assertOk()->assertSee('Caso único');
    }
}
