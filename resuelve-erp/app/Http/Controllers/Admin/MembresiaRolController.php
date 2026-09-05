<?php

namespace App\Http\Controllers\Admin;

use App\Application\Membresias\AsignarRolMembresia;
use App\Application\Membresias\RevocarRolMembresia;
use App\Models\MembresiaCopropiedad;
use App\Models\Rol;
use App\Models\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

final class MembresiaRolController extends Controller
{
    public function __construct(
        private readonly AsignarRolMembresia $asignarRol,
        private readonly RevocarRolMembresia $revocarRol,
    ) {}

    public function store(Request $request, MembresiaCopropiedad $membresia): RedirectResponse
    {
        $data = $request->validate([
            'rol_id' => ['required', 'integer', 'exists:roles,id'],
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after:vigente_desde'],
        ]);

        try {
            $this->asignarRol->ejecutar(
                $membresia,
                $data['rol_id'],
                $data['vigente_desde'],
                $data['vigente_hasta'] ?? null,
                $request->user()->id,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['rol_id' => $e->getMessage()])->withInput();
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'membresia.rol.asignar',
            'auditable_type' => MembresiaCopropiedad::class,
            'auditable_id' => $membresia->id,
            'ip_address' => $request->ip(),
            'metadata' => [
                'rol_id' => $data['rol_id'],
                'vigente_desde' => $data['vigente_desde'],
                'vigente_hasta' => $data['vigente_hasta'],
            ],
        ]);

        return back()->with('success', 'Rol asignado correctamente.');
    }

    public function destroy(Request $request, MembresiaCopropiedad $membresia, int $rolId): RedirectResponse
    {
        $this->revocarRol->ejecutar($membresia, $rolId);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'membresia.rol.revocar',
            'auditable_type' => MembresiaCopropiedad::class,
            'auditable_id' => $membresia->id,
            'ip_address' => $request->ip(),
            'metadata' => [
                'rol_id' => $rolId,
            ],
        ]);

        return back()->with('success', 'Rol revocado correctamente.');
    }
}
