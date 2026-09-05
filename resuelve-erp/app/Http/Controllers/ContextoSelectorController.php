<?php

namespace App\Http\Controllers;

use App\Models\MembresiaCopropiedad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ContextoSelectorController extends Controller
{
    public function cambiar(Request $request)
    {
        $request->validate([
            'copropiedad_id' => ['required', 'integer'],
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

        return redirect()->route('panel')->with('success', 'Contexto cambiado a '.$membresia->copropiedad->nombre.'.');
    }
}
