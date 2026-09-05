<?php

namespace App\Http\Controllers\Admin;

use App\Application\Copropiedades\CrearCopropiedad;
use App\Application\Copropiedades\DesactivarCopropiedad;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CopropiedadController extends Controller
{
    public function __construct(
        private readonly CrearCopropiedad $crearCopropiedad,
        private readonly DesactivarCopropiedad $desactivarCopropiedad,
    ) {}

    public function index(Request $request): View
    {
        $query = Copropiedad::query()->with(['organizacion']);

        if ($request->filled('organizacion_id')) {
            $query->where('organizacion_id', $request->input('organizacion_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('nit', 'like', "%{$search}%")
                  ->orWhere('ciudad', 'like', "%{$search}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $copropiedades = $query->orderBy('nombre')->paginate(20);
        $organizaciones = Organizacion::where('estado', 'activa')->orderBy('nombre')->get();

        return view('admin.copropiedades.index', compact('copropiedades', 'organizaciones'));
    }

    public function create(): View
    {
        $organizaciones = Organizacion::where('estado', 'activa')->orderBy('nombre')->get();

        return view('admin.copropiedades.create', compact('organizaciones'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organizacion_id' => ['required', 'integer', 'exists:organizaciones,id'],
            'nombre' => ['required', 'string', 'max:200'],
            'nit' => ['nullable', 'string', 'max:40'],
            'representante_legal' => ['nullable', 'string', 'max:200'],
            'direccion' => ['nullable', 'string', 'max:300'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
        ]);

        try {
            $copropiedad = $this->crearCopropiedad->ejecutar(
                $data['organizacion_id'],
                $data['nombre'],
                $data['nit'] ?? null,
                $data['representante_legal'] ?? null,
                $data['direccion'] ?? null,
                $data['ciudad'] ?? null,
                $data['telefono'] ?? null,
                $data['email'] ?? null,
                $request->user()->id,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['nombre' => $e->getMessage()])->withInput();
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'copropiedad.store',
            'auditable_type' => Copropiedad::class,
            'auditable_id' => $copropiedad->id,
            'ip_address' => $request->ip(),
            'metadata' => ['after' => $copropiedad->toArray()],
        ]);

        return redirect()->route('admin.copropiedades.show', $copropiedad)
            ->with('success', 'Copropiedad creada correctamente.');
    }

    public function show(Copropiedad $copropiedad): View
    {
        $copropiedad->load(['organizacion', 'configuracion', 'membresiasCopropiedad.usuario']);

        return view('admin.copropiedades.show', compact('copropiedad'));
    }

    public function edit(Copropiedad $copropiedad): View
    {
        $organizaciones = Organizacion::where('estado', 'activa')->orderBy('nombre')->get();

        return view('admin.copropiedades.edit', compact('copropiedad', 'organizaciones'));
    }

    public function update(Request $request, Copropiedad $copropiedad): RedirectResponse
    {
        $before = $copropiedad->toArray();

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'nit' => ['nullable', 'string', 'max:40'],
            'representante_legal' => ['nullable', 'string', 'max:200'],
            'direccion' => ['nullable', 'string', 'max:300'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
        ]);

        $copropiedad->update($data);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'copropiedad.update',
            'auditable_type' => Copropiedad::class,
            'auditable_id' => $copropiedad->id,
            'ip_address' => $request->ip(),
            'metadata' => ['before' => $before, 'after' => $copropiedad->fresh()->toArray()],
        ]);

        return redirect()->route('admin.copropiedades.show', $copropiedad)
            ->with('success', 'Copropiedad actualizada correctamente.');
    }

    public function desactivar(Request $request, Copropiedad $copropiedad): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->desactivarCopropiedad->ejecutar($copropiedad, $data['motivo']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()])->withInput();
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'copropiedad.deactivate',
            'auditable_type' => Copropiedad::class,
            'auditable_id' => $copropiedad->id,
            'ip_address' => $request->ip(),
            'metadata' => ['motivo' => $data['motivo']],
        ]);

        return redirect()->route('admin.copropiedades.show', $copropiedad)
            ->with('success', 'Copropiedad desactivada correctamente.');
    }

    public function reactivar(Request $request, Copropiedad $copropiedad): RedirectResponse
    {
        try {
            $this->desactivarCopropiedad->reactivar($copropiedad);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['estado' => $e->getMessage()])->withInput();
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'copropiedad.reactivate',
            'auditable_type' => Copropiedad::class,
            'auditable_id' => $copropiedad->id,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.copropiedades.show', $copropiedad)
            ->with('success', 'Copropiedad reactivada correctamente.');
    }
}
