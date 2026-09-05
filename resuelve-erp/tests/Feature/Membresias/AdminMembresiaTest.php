<?php

namespace Tests\Feature\Membresias;

use App\Models\User;
use App\Models\MembresiaCopropiedad;
use App\Models\Organizacion;
use App\Models\Copropiedad;
use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMembresiaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organizacion $organizacion;
    private Copropiedad $copropiedad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'es_administrador_sistema' => true,
        ]);

        $this->organizacion = Organizacion::factory()->create();
        $this->copropiedad = Copropiedad::factory()->create([
            'organizacion_id' => $this->organizacion->id,
        ]);
    }

    public function test_index_membresias(): void
    {
        MembresiaCopropiedad::factory()->create([
            'organizacion_id' => $this->organizacion->id,
            'copropiedad_id' => $this->copropiedad->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.membresias.index'));

        $response->assertOk();
    }

    public function test_create_membresia_form(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.membresias.create'));

        $response->assertOk();
    }

    public function test_store_membresia_exitosa(): void
    {
        $usuario = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.membresias.store'), [
                'usuario_id' => $usuario->id,
                'organizacion_id' => $this->organizacion->id,
                'copropiedad_id' => $this->copropiedad->id,
                'vigente_desde' => now()->format('Y-m-d'),
                'vigente_hasta' => now()->addYear()->format('Y-m-d'),
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('membresias_copropiedad', [
            'usuario_id' => $usuario->id,
            'copropiedad_id' => $this->copropiedad->id,
            'estado' => 'activa',
        ]);
    }

    public function test_store_membresia_validacion_falla(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.membresias.store'), []);

        $response->assertSessionHasErrors();
    }

    public function test_store_membresia_duplicada_falla(): void
    {
        $usuario = User::factory()->create();

        MembresiaCopropiedad::factory()->create([
            'usuario_id' => $usuario->id,
            'organizacion_id' => $this->organizacion->id,
            'copropiedad_id' => $this->copropiedad->id,
            'estado' => 'activa',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.membresias.store'), [
                'usuario_id' => $usuario->id,
                'organizacion_id' => $this->organizacion->id,
                'copropiedad_id' => $this->copropiedad->id,
                'vigente_desde' => now()->format('Y-m-d'),
            ]);

        $response->assertSessionHasErrors();
    }

    public function test_show_membresia(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'organizacion_id' => $this->organizacion->id,
            'copropiedad_id' => $this->copropiedad->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.membresias.show', $membresia));

        $response->assertOk();
    }

    public function test_edit_membresia_form(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'organizacion_id' => $this->organizacion->id,
            'copropiedad_id' => $this->copropiedad->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.membresias.edit', $membresia));

        $response->assertOk();
    }

    public function test_update_membresia_exitosa(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'organizacion_id' => $this->organizacion->id,
            'copropiedad_id' => $this->copropiedad->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.membresias.update', $membresia), [
                'vigente_desde' => now()->format('Y-m-d'),
                'vigente_hasta' => now()->addYears(2)->format('Y-m-d'),
            ]);

        $response->assertRedirect();
    }

    public function test_suspender_membresia(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'organizacion_id' => $this->organizacion->id,
            'copropiedad_id' => $this->copropiedad->id,
            'estado' => 'activa',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.membresias.suspend', $membresia), [
                'motivo' => 'Motivo de prueba',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('membresias_copropiedad', [
            'id' => $membresia->id,
            'estado' => 'suspendida',
        ]);
    }

    public function test_finalizar_membresia(): void
    {
        $membresia = MembresiaCopropiedad::factory()->create([
            'organizacion_id' => $this->organizacion->id,
            'copropiedad_id' => $this->copropiedad->id,
            'estado' => 'activa',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.membresias.finalize', $membresia), [
                'motivo' => 'Finalización de prueba',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('membresias_copropiedad', [
            'id' => $membresia->id,
            'estado' => 'finalizada',
        ]);
    }

    public function test_admin_sistema_requerido(): void
    {
        $usuarioNormal = User::factory()->create([
            'es_administrador_sistema' => false,
        ]);

        $response = $this->actingAs($usuarioNormal)
            ->get(route('admin.membresias.index'));

        $response->assertForbidden();
    }

    public function test_auditoria_registra_acciones(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.membresias.store'), [
                'usuario_id' => $usuario->id,
                'organizacion_id' => $this->organizacion->id,
                'copropiedad_id' => $this->copropiedad->id,
                'vigente_desde' => now()->format('Y-m-d'),
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'membresia.store',
        ]);
    }
}
