<?php
namespace App\Http\Controllers;
use App\Application\Identidad\SincronizarIdentidadContextualUsuario;
use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextResolver;
use App\Application\Contexto\ContextoOperativo;
use App\Models\MembresiaCopropiedad;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    public function edit(Request $request,User $user): View { $this->autorizar(); return view('users.edit',compact('user')); }
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
        $autorizacion = app(AutorizacionContextual::class);
        $contextoObjetivo = $resolver->resolverExplicito(
            $contexto->organizacion->id,
            $contexto->copropiedad->id,
            $user->id,
        );
        abort_unless($contextoObjetivo->tieneMembresiaContextual(), 404);

        if ($autorizacion->tieneRol($contextoObjetivo, 'admin')) {
            $administradores = MembresiaCopropiedad::query()
                ->where('organizacion_id', $contexto->organizacion->id)
                ->where('copropiedad_id', $contexto->copropiedad->id)
                ->where('estado', 'activa')
                ->where('vigente_desde', '<=', now())
                ->where(fn ($query) => $query
                    ->whereNull('vigente_hasta')
                    ->orWhere('vigente_hasta', '>', now()))
                ->get()
                ->filter(fn (MembresiaCopropiedad $membresia) => $autorizacion->tieneRol(
                    $resolver->resolverExplicito(
                        $contexto->organizacion->id,
                        $contexto->copropiedad->id,
                        $membresia->usuario_id,
                    ),
                    'admin',
                ))
                ->count();
            abort_if($administradores <= 1,422,'Debe existir al menos un administrador.');
        }

        abort_if($user->pqrs()->exists(),422,'No se puede eliminar porque tiene PQRS asociadas. Puedes cambiar su rol para conservar el historial.');
        abort_if(Documento::query()->where('propietario_documental_user_id', $user->id)->where('estado', 'activo')->exists(),422,'No se puede eliminar porque es propietario documental de Documentos activos.');
        DB::transaction(function () use ($contextoObjetivo, $user): void {
            DB::table('membresia_copropiedad_rol')
                ->where('membresia_copropiedad_id', $contextoObjetivo->membresiaCopropiedad->id)
                ->delete();
            $contextoObjetivo->membresiaCopropiedad->delete();
            $user->delete();
        });
        return redirect()->route('users.index')->with('success','Usuario eliminado correctamente.');
    }
}
