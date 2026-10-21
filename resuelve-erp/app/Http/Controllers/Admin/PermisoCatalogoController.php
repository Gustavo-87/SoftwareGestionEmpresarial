<?php

namespace App\Http\Controllers\Admin;

use App\Application\Roles\AutorizacionGestionRoles;
use App\Application\Roles\GestionarCatalogoPermisos;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

/**
 * Administración del catálogo de permisos (HU-USR-07, Sprint 18).
 * Solo autoridad de plataforma (middleware `admin.sistema` + validación del
 * servicio). La eliminación es lógica y auditada: la fila nunca se borra.
 */
final class PermisoCatalogoController extends Controller
{
    public function __construct(private readonly GestionarCatalogoPermisos $gestionar) {}

    public function index(): View
    {
        $permisos = Permission::query()
            ->where('guard_name', AutorizacionGestionRoles::GUARD)
            ->orderBy('name')
            ->get();

        $usos = [];
        foreach ($permisos as $permiso) {
            $usos[$permiso->id] = $this->gestionar->usoDe($permiso);
        }

        return view('admin.permisos.index', ['permisos' => $permisos, 'usos' => $usos]);
    }

    public function create(): View
    {
        return view('admin.permisos.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'clave' => ['required', 'string', 'max:150', 'regex:'.GestionarCatalogoPermisos::FORMATO_CLAVE],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'modulo' => ['required', 'string', 'max:100'],
        ]);

        $this->gestionar->crear(
            $request->user(),
            $data['clave'],
            $data['nombre'],
            $data['descripcion'] ?? null,
            $data['modulo'],
        );

        return redirect()->route('admin.permisos.index')->with('success', 'Permiso creado correctamente. El rol admin base lo hereda automáticamente.');
    }

    public function desactivar(Request $request, Permission $permiso): RedirectResponse
    {
        $this->gestionar->desactivar($request->user(), $permiso);
        $request->attributes->set('auditoria_especifica_registrada', true);

        return back()->with('success', 'Permiso desactivado. El registro se conserva y no concede acceso.');
    }

    public function reactivar(Request $request, Permission $permiso): RedirectResponse
    {
        $this->gestionar->reactivar($request->user(), $permiso);
        $request->attributes->set('auditoria_especifica_registrada', true);

        return back()->with('success', 'Permiso reactivado y heredado de nuevo por el rol admin base.');
    }
}
