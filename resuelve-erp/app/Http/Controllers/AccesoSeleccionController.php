<?php

namespace App\Http\Controllers;

use App\Application\Roles\GestionarRolActivo;
use App\Models\MembresiaCopropiedad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Selección de Copropiedad y Rol activo al ingresar (ampliación del Sprint 18).
 *
 * Después de validar las credenciales se resuelven la Copropiedad y los Roles
 * autorizados del usuario: con una sola Copropiedad y un solo Rol se seleccionan
 * automáticamente; con varios se exige elegir antes de acceder a la operación.
 * No muestra información de la cuenta antes de la validación (las rutas son de
 * usuario autenticado) y reutiliza `GestionarRolActivo` con su revalidación
 * contextual.
 */
final class AccesoSeleccionController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $usuario = $request->user();
        $membresias = $this->membresiasVigentes($usuario->id);

        if ($usuario->esAdministradorSistema() || $membresias->isEmpty()) {
            return $this->completar($request);
        }

        $copropiedadId = (int) $request->session()->get('copropiedad_activa_id');
        $membresia = $membresias->firstWhere('copropiedad_id', $copropiedadId);

        if ($membresia === null && $membresias->count() === 1) {
            $membresia = $membresias->first();
            $request->session()->put('copropiedad_activa_id', $membresia->copropiedad_id);
        }

        if ($membresia === null) {
            return view('auth.seleccion', [
                'paso' => 'copropiedad',
                'membresias' => $membresias,
                'roles' => collect(),
            ]);
        }

        $roles = collect(app(GestionarRolActivo::class)->rolesAsignados($usuario, $membresia));

        if ($roles->count() <= 1) {
            $this->seleccionarUnicoRol($usuario, $membresia, $roles);

            return $this->completar($request);
        }

        return view('auth.seleccion', [
            'paso' => 'rol',
            'membresias' => collect([$membresia]),
            'roles' => $roles,
        ]);
    }

    public function copropiedad(Request $request): RedirectResponse
    {
        $datos = $request->validate(['copropiedad_id' => ['required', 'integer']]);
        $usuario = $request->user();
        $membresia = $this->membresiasVigentes($usuario->id)
            ->firstWhere('copropiedad_id', (int) $datos['copropiedad_id']);

        if ($membresia === null) {
            throw ValidationException::withMessages([
                'copropiedad_id' => 'Selecciona una Copropiedad en la que tengas membresía vigente.',
            ]);
        }

        $request->session()->put('copropiedad_activa_id', $membresia->copropiedad_id);

        $roles = collect(app(GestionarRolActivo::class)->rolesAsignados($usuario, $membresia));
        if ($roles->count() <= 1) {
            $this->seleccionarUnicoRol($usuario, $membresia, $roles);

            return $this->completar($request);
        }

        return redirect()->route('acceso.seleccion');
    }

    public function rol(Request $request): RedirectResponse
    {
        $datos = $request->validate(['rol_id' => ['required', 'integer']]);
        $usuario = $request->user();
        $membresia = $this->membresiaActiva($request, $usuario->id);

        if ($membresia === null) {
            return redirect()->route('acceso.seleccion');
        }

        $roles = collect(app(GestionarRolActivo::class)->rolesAsignados($usuario, $membresia));
        if (! $roles->contains('id', (int) $datos['rol_id'])) {
            throw ValidationException::withMessages([
                'rol_id' => 'Selecciona uno de tus Roles autorizados en esta Copropiedad.',
            ]);
        }

        app(GestionarRolActivo::class)->ejecutar($usuario, $membresia, (int) $datos['rol_id']);

        return $this->completar($request);
    }

    private function seleccionarUnicoRol($usuario, MembresiaCopropiedad $membresia, Collection $roles): void
    {
        if ($roles->isEmpty()) {
            return;
        }

        $rol = $roles->first();
        if ((int) $membresia->rol_activo_id !== (int) $rol->id) {
            app(GestionarRolActivo::class)->ejecutar($usuario, $membresia, (int) $rol->id);
        }
    }

    private function completar(Request $request): RedirectResponse
    {
        $request->session()->forget('acceso_seleccion_pendiente');

        return redirect()->intended(route('panel'));
    }

    private function membresiaActiva(Request $request, int $usuarioId): ?MembresiaCopropiedad
    {
        $membresias = $this->membresiasVigentes($usuarioId);

        return $membresias->firstWhere(
            'copropiedad_id',
            (int) $request->session()->get('copropiedad_activa_id'),
        ) ?? ($membresias->count() === 1 ? $membresias->first() : null);
    }

    /** @return Collection<int, MembresiaCopropiedad> */
    private function membresiasVigentes(int $usuarioId): Collection
    {
        return MembresiaCopropiedad::query()
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'activa')
            ->where('vigente_desde', '<=', now())
            ->where(fn ($query) => $query
                ->whereNull('vigente_hasta')
                ->orWhere('vigente_hasta', '>', now()))
            ->with('copropiedad')
            ->orderBy('id')
            ->get();
    }
}
