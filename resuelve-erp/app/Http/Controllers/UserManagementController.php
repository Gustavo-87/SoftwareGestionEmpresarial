<?php
namespace App\Http\Controllers;
use App\Application\Identidad\SincronizarIdentidadContextualUsuario;
use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextResolver;
use App\Application\Contexto\ContextoOperativo;
use App\Application\Roles\AsignarRolUsuario;
use App\Application\Roles\ConsultaRolesGestionables;
use App\Application\Roles\RevocarRolUsuario;
use App\Models\MembresiaCopropiedad;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
class UserManagementController extends Controller {
    public function __construct(private readonly SincronizarIdentidadContextualUsuario $sincronizarIdentidad) {}
    private function autorizar(): void { abort_unless(app(AutorizacionContextual::class)->tienePermiso(app(ContextoOperativo::class), 'usuarios.gestionar'), 403); }
    public function index(Request $request): View { $this->autorizar(); return view('users.index',['users'=>User::orderBy('name')->paginate(20)]); }
    public function store(Request $request): RedirectResponse {
        $this->autorizar();
        $data=$request->validate([
            'name'=>['required','string','max:150'],
            'email'=>['required','email','max:150','unique:users,email'],
            'role'=>['required','in:admin,gestor,apoyo,auditor,residente'],
            'password'=>['required','string','min:8','confirmed'],
            'tower'=>['nullable','string','max:50'],
            'unit'=>['nullable','string','max:50'],
        ]);
        $data['email']=strtolower($data['email']);
        $data['email_verified_at']=now();
        $this->sincronizarIdentidad->crearUsuario($data);
        return redirect()->route('users.index')->with('success','Usuario creado correctamente.');
    }
    public function edit(Request $request,User $user): View {
        $this->autorizar();
        $contexto = app(ContextoOperativo::class);
        $equipoId = (int) $contexto->copropiedad->id;
        setPermissionsTeamId($equipoId);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $asignados = $user->roles()->get();
        $disponibles = app(ConsultaRolesGestionables::class)->asignablesEn($equipoId)
            ->reject(fn ($rol) => $asignados->contains('id', $rol->id))
            ->values();
        return view('users.edit',compact('user','asignados','disponibles'));
    }

    public function asignarRol(Request $request, User $user): RedirectResponse {
        $this->autorizar();
        $data = $request->validate(['rol_id' => ['required', 'integer']]);
        app(AsignarRolUsuario::class)->ejecutar(
            $request->user(),
            app(ContextoOperativo::class),
            $this->membresiaDelContexto($user),
            (int) $data['rol_id'],
        );
        $request->attributes->set('auditoria_especifica_registrada', true);
        return back()->with('success', 'Rol asignado correctamente.');
    }

    public function revocarRol(Request $request, User $user, int $rol): RedirectResponse {
        $this->autorizar();
        app(RevocarRolUsuario::class)->ejecutar(
            $request->user(),
            app(ContextoOperativo::class),
            $this->membresiaDelContexto($user),
            $rol,
        );
        $request->attributes->set('auditoria_especifica_registrada', true);
        return back()->with('success', 'Rol revocado correctamente.');
    }

    private function membresiaDelContexto(User $user): MembresiaCopropiedad {
        $contexto = app(ContextoOperativo::class);
        $membresia = MembresiaCopropiedad::query()
            ->where('usuario_id', $user->id)
            ->where('organizacion_id', $contexto->organizacion->id)
            ->where('copropiedad_id', $contexto->copropiedad->id)
            ->first();
        if ($membresia === null) {
            throw ValidationException::withMessages(['usuario' => 'El usuario no tiene Membresía en la Copropiedad activa.']);
        }
        return $membresia;
    }
    public function update(Request $request,User $user): RedirectResponse {
        $this->autorizar();
        $data=$request->validate([
            'name'=>['required','string','max:150'],
            'email'=>['required','email','max:150','unique:users,email,'.$user->id],
            'role'=>['required','in:admin,gestor,apoyo,auditor,residente'],
            'tower'=>['nullable','string','max:50'],
            'unit'=>['nullable','string','max:50'],
            'password'=>['nullable','string','min:8','confirmed'],
        ]);
        abort_if($user->is($request->user())&&$data['role']!=='admin',422,'No puedes retirar tu propio rol de administrador.');
        $data['email']=strtolower($data['email']);
        if(blank($data['password']??null)) unset($data['password']);
        $this->sincronizarIdentidad->actualizarUsuario($user,$data);
        return redirect()->route('users.index')->with('success','Usuario actualizado correctamente.');
    }
    public function updateRole(Request $request,User $user): RedirectResponse { $this->autorizar(); $data=$request->validate(['role'=>['required','in:admin,gestor,apoyo,auditor,residente']]); abort_if($user->is($request->user())&&$data['role']!=='admin',422,'No puedes retirar tu propio rol de administrador.'); $this->sincronizarIdentidad->actualizarUsuario($user,$data); return back()->with('success','Rol actualizado.'); }
    public function destroy(Request $request,User $user): RedirectResponse {
        $this->autorizar();
        abort_if($user->is($request->user()),422,'No puedes eliminar tu propia cuenta.');
        $contexto = app(ContextoOperativo::class);
        $resolver = app(ContextResolver::class);
        $contextoObjetivo = $resolver->resolverExplicito(
            $contexto->organizacion->id,
            $contexto->copropiedad->id,
            $user->id,
        );
        abort_unless($contextoObjetivo->tieneMembresiaContextual(), 404);

        abort_if($user->pqrs()->exists(),422,'No se puede eliminar porque tiene PQRS asociadas. Puedes cambiar su rol para conservar el historial.');
        abort_if(Documento::query()->where('propietario_documental_user_id', $user->id)->where('estado', 'activo')->exists(),422,'No se puede eliminar porque es propietario documental de Documentos activos.');
        DB::transaction(function () use ($contextoObjetivo, $user): void {
            // Protección por capacidad efectiva (usuarios.gestionar), no por
            // nombres de rol. Reutiliza el mecanismo seguro de asignación de
            // roles con bloqueo de Membresías ante concurrencia.
            $capacidad = app(\App\Application\Roles\CapacidadAdministrativa::class);
            $membresias = $capacidad->membresiasBloqueadas($contextoObjetivo->membresiaCopropiedad);
            abort_if(
                $capacidad->titularesRestantes($membresias, $contextoObjetivo->membresiaCopropiedad, null, (int) $user->id) === 0,
                422,
                'Debe existir al menos un usuario con capacidad de administración (permiso usuarios.gestionar).'
            );
            DB::table('membresia_copropiedad_rol')
                ->where('membresia_copropiedad_id', $contextoObjetivo->membresiaCopropiedad->id)
                ->delete();
            DB::table('model_has_roles')
                ->where('model_type', \App\Models\User::class)
                ->where('model_id', $user->id)
                ->delete();
            DB::table('model_has_permissions')
                ->where('model_type', \App\Models\User::class)
                ->where('model_id', $user->id)
                ->delete();
            $contextoObjetivo->membresiaCopropiedad->delete();
            $user->delete();
        });
        return redirect()->route('users.index')->with('success','Usuario eliminado correctamente.');
    }
}
