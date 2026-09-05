<?php

namespace App\Http\Controllers\Admin;

use App\Application\Usuarios\CrearUsuarioGlobal;
use App\Application\Usuarios\DesactivarUsuarioGlobal;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class UsuarioGlobalController extends Controller
{
    public function __construct(
        private readonly CrearUsuarioGlobal $crearUsuario,
        private readonly DesactivarUsuarioGlobal $desactivarUsuario,
    ) {}

    public function index(Request $request): View
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('es_administrador_sistema')) {
            $query->where('es_administrador_sistema', $request->boolean('es_administrador_sistema'));
        }

        $usuarios = $query->orderBy('name')->paginate(20);

        return view('admin.usuarios-globales.index', compact('usuarios'));
    }

    public function create(): View
    {
        return view('admin.usuarios-globales.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'tower' => ['nullable', 'string', 'max:50'],
            'unit' => ['nullable', 'string', 'max:50'],
            'es_administrador_sistema' => ['nullable', 'boolean'],
        ]);

        try {
            $usuario = $this->crearUsuario->ejecutar(
                $data['name'],
                $data['email'],
                $data['password'],
                $data['tower'] ?? null,
                $data['unit'] ?? null,
                $data['es_administrador_sistema'] ?? false,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput();
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'usuario_global.store',
            'auditable_type' => User::class,
            'auditable_id' => $usuario->id,
            'ip_address' => $request->ip(),
            'metadata' => ['after' => ['name' => $usuario->name, 'email' => $usuario->email]],
        ]);

        return redirect()->route('admin.usuarios-globales.show', $usuario)
            ->with('success', 'Usuario creado correctamente.');
    }

    public function show(User $usuario): View
    {
        $usuario->load(['membresiasCopropiedad.copropiedad', 'membresiasCopropiedad.organizacion']);

        return view('admin.usuarios-globales.show', compact('usuario'));
    }

    public function edit(User $usuario): View
    {
        return view('admin.usuarios-globales.edit', compact('usuario'));
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $before = $usuario->only(['name', 'email', 'tower', 'unit', 'es_administrador_sistema']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,' . $usuario->id],
            'tower' => ['nullable', 'string', 'max:50'],
            'unit' => ['nullable', 'string', 'max:50'],
            'es_administrador_sistema' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($data['password']);
        }

        $data['email'] = strtolower($data['email']);
        $data['es_administrador_sistema'] = $data['es_administrador_sistema'] ?? false;

        $usuario->update($data);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'usuario_global.update',
            'auditable_type' => User::class,
            'auditable_id' => $usuario->id,
            'ip_address' => $request->ip(),
            'metadata' => ['before' => $before, 'after' => $usuario->fresh()->only(['name', 'email', 'tower', 'unit', 'es_administrador_sistema'])],
        ]);

        return redirect()->route('admin.usuarios-globales.show', $usuario)
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function desactivar(Request $request, User $usuario): RedirectResponse
    {
        try {
            $this->desactivarUsuario->ejecutar($usuario, $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['estado' => $e->getMessage()])->withInput();
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'usuario_global.deactivate',
            'auditable_type' => User::class,
            'auditable_id' => $usuario->id,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.usuarios-globales.show', $usuario)
            ->with('success', 'Usuario desactivado correctamente.');
    }

    public function reactivar(Request $request, User $usuario): RedirectResponse
    {
        try {
            $this->desactivarUsuario->reactivar($usuario);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['estado' => $e->getMessage()])->withInput();
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'usuario_global.reactivate',
            'auditable_type' => User::class,
            'auditable_id' => $usuario->id,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.usuarios-globales.show', $usuario)
            ->with('success', 'Usuario reactivado correctamente.');
    }
}
