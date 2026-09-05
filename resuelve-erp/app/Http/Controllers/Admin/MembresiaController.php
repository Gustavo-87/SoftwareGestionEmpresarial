<?php

namespace App\Http\Controllers\Admin;

use App\Application\Membresias\CrearMembresia;
use App\Application\Membresias\EditarMembresia;
use App\Application\Membresias\SuspenderMembresia;
use App\Application\Membresias\FinalizarMembresia;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use App\Models\Organizacion;
use App\Models\Copropiedad;
use App\Models\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

final class MembresiaController extends Controller
{
    public function __construct(
        private readonly CrearMembresia $crearMembresia,
        private readonly EditarMembresia $editarMembresia,
        private readonly SuspenderMembresia $suspenderMembresia,
        private readonly FinalizarMembresia $finalizarMembresia,
    ) {}

    public function index(Request $request): View
    {
        $query = MembresiaCopropiedad::query()
            ->with(['usuario', 'copropiedad', 'organizacion']);

        if ($request->filled('copropiedad_id')) {
            $query->where('copropiedad_id', $request->input('copropiedad_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('usuario', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $membresias = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.membresias.index', compact('membresias'));
    }

    public function create(): View
    {
        $usuarios = User::orderBy('name')->get();
        $organizaciones = Organizacion::orderBy('nombre')->get();
        $copropiedades = Copropiedad::orderBy('nombre')->get();

        return view('admin.membresias.create', compact('usuarios', 'organizaciones', 'copropiedades'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'usuario_id' => ['required', 'integer', 'exists:users,id'],
            'organizacion_id' => ['required', 'integer', 'exists:organizaciones,id'],
            'copropiedad_id' => ['required', 'integer', 'exists:copropiedades,id'],
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after:vigente_desde'],
        ]);

        try {
            $membresia = $this->crearMembresia->ejecutar(
                $data['usuario_id'],
                $data['organizacion_id'],
                $data['copropiedad_id'],
                $data['vigente_desde'],
                $data['vigente_hasta'] ?? null,
                $request->user()->id,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['usuario_id' => $e->getMessage()])->withInput();
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'membresia.store',
            'auditable_type' => MembresiaCopropiedad::class,
            'auditable_id' => $membresia->id,
            'ip_address' => $request->ip(),
            'metadata' => [
                'before' => null,
                'after' => $membresia->toArray(),
            ],
        ]);

        return redirect()->route('admin.membresias.index')
            ->with('success', 'Membresía creada correctamente.');
    }

    public function show(MembresiaCopropiedad $membresia): View
    {
        $membresia->load(['usuario', 'copropiedad', 'organizacion', 'roles']);

        return view('admin.membresias.show', compact('membresia'));
    }

    public function edit(MembresiaCopropiedad $membresia): View
    {
        $membresia->load(['usuario', 'copropiedad', 'organizacion']);

        return view('admin.membresias.edit', compact('membresia'));
    }

    public function update(Request $request, MembresiaCopropiedad $membresia): RedirectResponse
    {
        $before = $membresia->toArray();

        $data = $request->validate([
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after:vigente_desde'],
        ]);

        $this->editarMembresia->ejecutar(
            $membresia,
            $data['vigente_desde'],
            $data['vigente_hasta'] ?? null,
        );

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'membresia.update',
            'auditable_type' => MembresiaCopropiedad::class,
            'auditable_id' => $membresia->id,
            'ip_address' => $request->ip(),
            'metadata' => [
                'before' => $before,
                'after' => $membresia->fresh()->toArray(),
            ],
        ]);

        return redirect()->route('admin.membresias.show', $membresia)
            ->with('success', 'Membresía actualizada correctamente.');
    }

    public function suspender(Request $request, MembresiaCopropiedad $membresia): RedirectResponse
    {
        $before = $membresia->toArray();

        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        $this->suspenderMembresia->ejecutar($membresia, $data['motivo']);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'membresia.suspend',
            'auditable_type' => MembresiaCopropiedad::class,
            'auditable_id' => $membresia->id,
            'ip_address' => $request->ip(),
            'metadata' => [
                'before' => $before,
                'after' => $membresia->fresh()->toArray(),
                'motivo' => $data['motivo'],
            ],
        ]);

        return redirect()->route('admin.membresias.show', $membresia)
            ->with('success', 'Membresía suspendida correctamente.');
    }

    public function finalizar(Request $request, MembresiaCopropiedad $membresia): RedirectResponse
    {
        $before = $membresia->toArray();

        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        $this->finalizarMembresia->ejecutar($membresia, $data['motivo']);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'membresia.finalize',
            'auditable_type' => MembresiaCopropiedad::class,
            'auditable_id' => $membresia->id,
            'ip_address' => $request->ip(),
            'metadata' => [
                'before' => $before,
                'after' => $membresia->fresh()->toArray(),
                'motivo' => $data['motivo'],
            ],
        ]);

        return redirect()->route('admin.membresias.show', $membresia)
            ->with('success', 'Membresía finalizada correctamente.');
    }

    public function destroy(Request $request, MembresiaCopropiedad $membresia): RedirectResponse
    {
        abort(405, 'Use suspender o finalizar en lugar de eliminar.');
    }
}
