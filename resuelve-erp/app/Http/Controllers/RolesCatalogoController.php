<?php

namespace App\Http\Controllers;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Roles\AutorizacionGestionRoles;
use App\Application\Roles\ConsultaPermisosCatalogo;
use App\Application\Roles\ConsultaRolesGestionables;
use App\Application\Roles\CrearRol;
use App\Application\Roles\EditarRol;
use App\Application\Roles\EliminarRol;
use App\Application\Roles\SincronizarPermisosRol;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Role as SpatieRole;

class RolesCatalogoController extends Controller
{
    public function __construct(
        private readonly ConsultaRolesGestionables $consultaRoles,
        private readonly ConsultaPermisosCatalogo $consultaPermisos,
        private readonly AutorizacionGestionRoles $autorizacion,
        private readonly CrearRol $crear,
        private readonly EditarRol $editar,
        private readonly EliminarRol $eliminar,
        private readonly SincronizarPermisosRol $sincronizar,
    ) {}

    public function index(): View
    {
        $roles = $this->consultaRoles->ejecutar(auth()->user(), app(ContextoOperativo::class));

        return view('roles.index', compact('roles'));
    }

    public function create(): View
    {
        $contexto = app(ContextoOperativo::class);
        $origenes = $this->consultaRoles->origenesDeCopia(auth()->user(), $contexto);
        $permisosPorModulo = $this->consultaPermisos->porModulo(auth()->user(), $contexto);

        return view('roles.create', compact('origenes', 'permisosPorModulo'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->crear->ejecutar(auth()->user(), app(ContextoOperativo::class), $request->only('nombre', 'copiar_desde_rol_id', 'permisos'));
        $request->attributes->set('auditoria_especifica_registrada', true);

        return redirect()->route('roles.index')->with('success', 'Rol creado correctamente.');
    }

    public function edit(int $rol): View
    {
        $contexto = app(ContextoOperativo::class);
        $operador = auth()->user();
        $rol = SpatieRole::query()->findOrFail($rol);
        $this->autorizacion->autorizarGestion($operador, $contexto, $rol);

        $permisosPorModulo = $this->consultaPermisos->porModulo($operador, $contexto);
        $seleccionados = $rol->permissions()->pluck('name')->all();
        $asignaciones = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('role_id', $rol->id)
            ->count();

        return view('roles.edit', compact('rol', 'permisosPorModulo', 'seleccionados', 'asignaciones'));
    }

    public function update(Request $request, int $rol): RedirectResponse
    {
        $this->editar->ejecutar(auth()->user(), app(ContextoOperativo::class), $rol, $request->only('nombre'));
        $request->attributes->set('auditoria_especifica_registrada', true);

        return redirect()->route('roles.edit', $rol)->with('success', 'Rol actualizado correctamente.');
    }

    public function permisos(Request $request, int $rol): RedirectResponse
    {
        $this->sincronizar->ejecutar(
            auth()->user(),
            app(ContextoOperativo::class),
            $rol,
            $request->input('permisos') ?? [],
        );
        $request->attributes->set('auditoria_especifica_registrada', true);

        return redirect()->route('roles.edit', $rol)->with('success', 'Permisos del rol actualizados correctamente.');
    }

    public function destroy(Request $request, int $rol): RedirectResponse
    {
        $this->eliminar->ejecutar(auth()->user(), app(ContextoOperativo::class), $rol);
        $request->attributes->set('auditoria_especifica_registrada', true);

        return redirect()->route('roles.index')->with('success', 'Rol eliminado correctamente.');
    }
}
