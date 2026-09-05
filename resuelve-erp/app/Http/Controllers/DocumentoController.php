<?php

namespace App\Http\Controllers;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Documentos\ArchivarDocumento;
use App\Application\Documentos\ConsultaDocumentosContextuales;
use App\Application\Documentos\CrearDocumento;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function create(): View { $this->authorize('create', Documento::class); return view('documentos.create'); }

    public function store(Request $request, ContextoOperativo $contexto, CrearDocumento $crear): RedirectResponse
    {
        $data = $request->validate(['tipo' => ['required', Rule::in(['documento_general', 'reglamento', 'manual_convivencia', 'acta'])], 'categoria' => ['required', Rule::in(['normativo', 'administrativo', 'gobierno_copropiedad', 'contractual', 'financiero', 'comunicaciones', 'otro'])], 'titulo' => ['required', 'string', 'max:180'], 'descripcion' => ['nullable', 'string'], 'nivel_acceso' => ['required', Rule::in(['administrativo', 'interno', 'comunidad'])], 'propietario_documental_user_id' => ['required', 'integer']]);
        $documento = $crear->ejecutar($contexto, $request->user(), $data);
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
