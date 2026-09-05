<?php

namespace App\Http\Controllers\Admin;

use App\Application\Organizaciones\CrearOrganizacion;
use App\Application\Organizaciones\DesactivarOrganizacion;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organizacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OrganizacionController extends Controller
{
    public function __construct(
        private readonly CrearOrganizacion $crearOrganizacion,
        private readonly DesactivarOrganizacion $desactivarOrganizacion,
    ) {}

    public function index(Request $request): View
    {
        $query = Organizacion::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('identificacion_tributaria', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $organizaciones = $query->orderBy('nombre')->paginate(20);

        return view('admin.organizaciones.index', compact('organizaciones'));
    }

    public function create(): View
    {
        return view('admin.organizaciones.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'identificacion_tributaria' => ['nullable', 'string', 'max:40', 'unique:organizaciones,identificacion_tributaria'],
            'email' => ['nullable', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $organizacion = $this->crearOrganizacion->ejecutar(
                $data['nombre'],
                $data['identificacion_tributaria'] ?? null,
                $data['email'] ?? null,
                $data['telefono'] ?? null,
                $request->user()->id,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['nombre' => $e->getMessage()])->withInput();
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'organizacion.store',
            'auditable_type' => Organizacion::class,
            'auditable_id' => $organizacion->id,
            'ip_address' => $request->ip(),
            'metadata' => ['after' => $organizacion->toArray()],
        ]);

        return redirect()->route('admin.organizaciones.show', $organizacion)
            ->with('success', 'Organización creada correctamente.');
    }

    public function show(Organizacion $organizacion): View
    {
        $organizacion->load(['copropiedades', 'configuracion']);

        return view('admin.organizaciones.show', compact('organizacion'));
    }

    public function edit(Organizacion $organizacion): View
    {
        return view('admin.organizaciones.edit', compact('organizacion'));
    }

    public function update(Request $request, Organizacion $organizacion): RedirectResponse
    {
        $before = $organizacion->toArray();

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'identificacion_tributaria' => ['nullable', 'string', 'max:40', 'unique:organizaciones,identificacion_tributaria,' . $organizacion->id],
            'email' => ['nullable', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:40'],
        ]);

        $organizacion->update($data);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'organizacion.update',
            'auditable_type' => Organizacion::class,
            'auditable_id' => $organizacion->id,
            'ip_address' => $request->ip(),
            'metadata' => ['before' => $before, 'after' => $organizacion->fresh()->toArray()],
        ]);

        return redirect()->route('admin.organizaciones.show', $organizacion)
            ->with('success', 'Organización actualizada correctamente.');
    }

    public function desactivar(Request $request, Organizacion $organizacion): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->desactivarOrganizacion->ejecutar($organizacion, $data['motivo']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()])->withInput();
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'organizacion.deactivate',
            'auditable_type' => Organizacion::class,
            'auditable_id' => $organizacion->id,
            'ip_address' => $request->ip(),
            'metadata' => ['motivo' => $data['motivo']],
        ]);

        return redirect()->route('admin.organizaciones.show', $organizacion)
            ->with('success', 'Organización desactivada correctamente.');
    }

    public function reactivar(Request $request, Organizacion $organizacion): RedirectResponse
    {
        try {
            $this->desactivarOrganizacion->reactivar($organizacion);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['estado' => $e->getMessage()])->withInput();
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'organizacion.reactivate',
            'auditable_type' => Organizacion::class,
            'auditable_id' => $organizacion->id,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.organizaciones.show', $organizacion)
            ->with('success', 'Organización reactivada correctamente.');
    }
}
