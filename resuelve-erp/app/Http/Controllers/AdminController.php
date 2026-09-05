<?php

namespace App\Http\Controllers;

use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\Organizacion;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        // Métricas principales
        $totalUsuarios = User::count();
        $totalOrganizaciones = Organizacion::count();
        $totalCopropiedades = Copropiedad::count();
        $totalMembresias = MembresiaCopropiedad::count();

        // Desglose de membresías
        $membresiasActivas = MembresiaCopropiedad::where('estado', 'activa')->count();
        $membresiasSuspendidas = MembresiaCopropiedad::where('estado', 'suspendida')->count();
        $membresiasFinalizadas = MembresiaCopropiedad::where('estado', 'finalizada')->count();

        // Organizaciones y copropiedades activas
        $organizacionesActivas = Organizacion::where('estado', 'activa')->count();
        $copropiedadesActivas = Copropiedad::where('estado', 'activa')->count();

        // Métricas de actividad reciente
        $usuariosNuevosHoy = User::whereDate('created_at', today())->count();
        $membresiasNuevasHoy = MembresiaCopropiedad::whereDate('created_at', today())->count();
        $membresiasNuevasSemana = MembresiaCopropiedad::where('created_at', '>=', now()->subWeek())->count();
        $usuariosNuevosSemana = User::where('created_at', '>=', now()->subWeek())->count();

        // Copropiedades por organización (top 8)
        $copropiedadesPorOrganizacion = Organizacion::withCount('copropiedades')
            ->orderByDesc('copropiedades_count')
            ->limit(8)
            ->get()
            ->map(fn ($org) => [
                'nombre' => $org->nombre,
                'cantidad' => $org->copropiedades_count,
            ]);

        // Máximo para escalar barras
        $maxCopropiedades = $copropiedadesPorOrganizacion->max('cantidad') ?: 1;

        return view('admin.index', compact(
            'totalUsuarios',
            'totalOrganizaciones',
            'totalCopropiedades',
            'totalMembresias',
            'membresiasActivas',
            'membresiasSuspendidas',
            'membresiasFinalizadas',
            'organizacionesActivas',
            'copropiedadesActivas',
            'usuariosNuevosHoy',
            'membresiasNuevasHoy',
            'membresiasNuevasSemana',
            'usuariosNuevosSemana',
            'copropiedadesPorOrganizacion',
            'maxCopropiedades',
        ));
    }
}
