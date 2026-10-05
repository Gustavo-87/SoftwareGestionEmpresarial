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

        // Desglose por estado real: una consulta agregada por entidad.
        $organizacionEstados = Organizacion::query()
            ->selectRaw('estado, COUNT(*) total')->groupBy('estado')->pluck('total', 'estado');
        $organizacionesActivas = (int) $organizacionEstados->get('activa', 0);
        $organizacionesInactivas = (int) $organizacionEstados->get('inactiva', 0);

        $copropiedadEstados = Copropiedad::query()
            ->selectRaw('estado, COUNT(*) total')->groupBy('estado')->pluck('total', 'estado');
        $copropiedadesActivas = (int) $copropiedadEstados->get('activa', 0);
        $copropiedadesInactivas = (int) $copropiedadEstados->get('inactiva', 0);

        $usuarioEstados = User::query()
            ->selectRaw('estado, COUNT(*) total')->groupBy('estado')->pluck('total', 'estado');
        $usuariosActivos = (int) $usuarioEstados->get('activo', 0);
        $usuariosInactivos = (int) $usuarioEstados->get('inactivo', 0);

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
            'organizacionesInactivas',
            'copropiedadesActivas',
            'copropiedadesInactivas',
            'usuariosActivos',
            'usuariosInactivos',
            'usuariosNuevosHoy',
            'membresiasNuevasHoy',
            'membresiasNuevasSemana',
            'usuariosNuevosSemana',
            'copropiedadesPorOrganizacion',
            'maxCopropiedades',
        ));
    }
}
