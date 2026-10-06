<?php

namespace App\Http\Controllers;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Documentos\ArchivarDocumento;
use App\Application\Documentos\CargarVersionDocumento;
use App\Application\Documentos\ConsultaDocumentosContextuales;
use App\Application\Documentos\CrearDocumento;
use App\Models\Documento;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentoController extends Controller
{
    public function index(ContextoOperativo $contexto, ConsultaDocumentosContextuales $consulta): View
    {
        $this->authorize('viewAny', Documento::class);

        $query = $consulta->para($contexto);

        $totalDocumentos = (clone $query)->count();
        $documentosActivos = (clone $query)->where('estado', 'activo')->count();
        $documentosArchivados = (clone $query)->where('estado', 'archivado')->count();

        $documentos = $query->with(['propietarioDocumental', 'versiones'])->latest()->paginate(15);

        return view('documentos.index', compact('documentos', 'totalDocumentos', 'documentosActivos', 'documentosArchivados'));
    }

    public function create(ContextoOperativo $contexto): View
    {
        $this->authorize('create', Documento::class);

        $usuariosPropietario = MembresiaCopropiedad::query()
            ->where('organizacion_id', $contexto->organizacion->id)
            ->where('copropiedad_id', $contexto->copropiedad->id)
            ->where('estado', 'activa')
            ->where('vigente_desde', '<=', now())
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', now()))
            ->with('usuario:id,name,email')
            ->get()
            ->pluck('usuario')
            ->filter()
            ->sortBy('name')
            ->values();

        return view('documentos.create', compact('usuariosPropietario'));
    }

    public function store(Request $request, ContextoOperativo $contexto, CrearDocumento $crear, CargarVersionDocumento $cargar): RedirectResponse
    {
        $data = $request->validate(['tipo' => ['required', Rule::in(['documento_general', 'reglamento', 'manual_convivencia', 'acta'])], 'categoria' => ['required', Rule::in(['normativo', 'administrativo', 'gobierno_copropiedad', 'contractual', 'financiero', 'comunicaciones', 'otro'])], 'titulo' => ['required', 'string', 'max:180'], 'descripcion' => ['nullable', 'string'], 'nivel_acceso' => ['required', Rule::in(['administrativo', 'interno', 'comunidad'])], 'propietario_documental_user_id' => ['required', 'integer'], 'archivo' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,docx']]);
        $archivo = $data['archivo'] ?? null;
        unset($data['archivo']);
        $documento = DB::transaction(function () use ($request, $contexto, $crear, $cargar, $data, $archivo): Documento {
            $documento = $crear->ejecutar($contexto, $request->user(), $data);
            if ($archivo) {
                $cargar->ejecutar($contexto, $request->user(), $documento, $archivo, 'usuario', null);
            }
            return $documento;
        });
        return redirect()->route('documentos.show', $documento)->with('success', 'Documento creado correctamente.');
    }

    public function show(Documento $documento): View
    {
        $this->authorize('view', $documento);
        $documento->load(['propietarioDocumental', 'versiones.sustituyeVersion', 'actuaciones.actor']);
        return view('documentos.show', compact('documento'));
    }

    public function archive(Documento $documento, ContextoOperativo $contexto, ArchivarDocumento $archivar): RedirectResponse
    {
        $archivar->ejecutar($contexto, request()->user(), $documento);
        return back()->with('success', 'Documento archivado correctamente.');
    }
}
