<?php

namespace App\Http\Controllers;

use App\Application\Roles\GestionarRolActivo;
use App\Models\MembresiaCopropiedad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ContextoSelectorController extends Controller
{
    public function cambiar(Request $request)
    {
        $request->validate([
            'copropiedad_id' => ['required', 'integer'],
            'rol_activo_id' => ['nullable', 'integer'],
        ]);

        $usuario = Auth::user();

        $membresia = MembresiaCopropiedad::query()
            ->where('usuario_id', $usuario->id)
            ->where('copropiedad_id', $request->input('copropiedad_id'))
            ->where('estado', 'activa')
            ->where('vigente_desde', '<=', now())
            ->where(fn ($query) => $query
                ->whereNull('vigente_hasta')
                ->orWhere('vigente_hasta', '>', now()))
            ->first();

        if ($membresia === null) {
            return back()->withErrors([
                'copropiedad_id' => 'No tienes una membresía vigente para esa copropiedad.',
            ]);
        }

        $request->session()->put('copropiedad_activa_id', $membresia->copropiedad_id);

        // HU-USR-04: el Rol activo se elige y persiste por Membresía/Copropiedad
        // junto con el cambio de contexto. Sin el campo, la selección de la
        // Membresía destino se conserva (y se revalida al resolver el contexto).
        if ($request->has('rol_activo_id')) {
            $rolId = $request->input('rol_activo_id');
            app(GestionarRolActivo::class)->ejecutar(
                $usuario,
                $membresia,
                $rolId === null || $rolId === '' ? null : (int) $rolId,
            );
        }

        return redirect()->route('panel')->with('success', 'Contexto cambiado a '.$membresia->copropiedad->nombre.'.');
    }
}
