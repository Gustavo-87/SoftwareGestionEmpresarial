<?php

namespace App\Http\Controllers;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Documentos\CargarVersionDocumento;
use App\Application\Documentos\SometerVersionDocumento;
use App\Application\Documentos\AprobarVersionDocumento;
use App\Application\Documentos\RechazarVersionDocumento;
use App\Application\Documentos\DescargarVersionDocumento;
use App\Application\Documentos\ConsultaDocumentosContextuales;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoVersionController extends Controller
{
    public function store(Request $request, Documento $documento, ContextoOperativo $contexto, CargarVersionDocumento $cargar): RedirectResponse
    {
        $data = $request->validate(['archivo' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,docx']]);
        $sustituye = $request->filled('sustituye_version_id') ? app(ConsultaDocumentosContextuales::class)->resolverVersion($contexto, $documento, $request->integer('sustituye_version_id')) : null;
        $cargar->ejecutar($contexto, $request->user(), $documento, $data['archivo'], 'usuario', $sustituye);
        return back()->with('success', 'Versión cargada correctamente.');
    }
    public function submit(Documento $documento, DocumentoVersion $documentoVersion, ContextoOperativo $contexto, SometerVersionDocumento $someter): RedirectResponse { $someter->ejecutar($contexto, request()->user(), $documento, $documentoVersion); return back()->with('success','Versión sometida correctamente.'); }
    public function approve(Request $request, Documento $documento, DocumentoVersion $documentoVersion, ContextoOperativo $contexto, AprobarVersionDocumento $aprobar): RedirectResponse { $data=$request->validate(['vigente_desde'=>['required','date'],'vigente_hasta'=>['nullable','date','after_or_equal:vigente_desde']]);$aprobar->ejecutar($contexto,$request->user(),$documento,$documentoVersion,$data['vigente_desde'],$data['vigente_hasta']??null);return back()->with('success','Versión aprobada correctamente.'); }
    public function reject(Request $request, Documento $documento, DocumentoVersion $documentoVersion, ContextoOperativo $contexto, RechazarVersionDocumento $rechazar): RedirectResponse { $data=$request->validate(['observacion_rechazo'=>['required','string','max:2000']]);$rechazar->ejecutar($contexto,$request->user(),$documento,$documentoVersion,$data['observacion_rechazo']);return back()->with('success','Versión rechazada correctamente.'); }
    public function download(Documento $documento, DocumentoVersion $documentoVersion, ContextoOperativo $contexto, DescargarVersionDocumento $descargar) { return $descargar->ejecutar($contexto, request()->user(), $documento, $documentoVersion); }

    public function preview(Documento $documento, DocumentoVersion $documentoVersion, ContextoOperativo $contexto, ConsultaDocumentosContextuales $consulta): StreamedResponse
    {
        $user = request()->user();

        // Reutilizar la misma lógica de autorización que DescargarVersionDocumento
        $documento = $consulta->resolver($contexto, $documento->id);
        $documentoVersion = $consulta->resolverVersion($contexto, $documento, $documentoVersion->id);

        if (! $user->can('view', $documento)) {
            abort(403, 'No tienes permiso para ver este documento.');
        }

        $h = now()->toDateString();
        if ($documentoVersion->estado->value !== 'aprobada'
            || ($documentoVersion->vigente_desde && $documentoVersion->vigente_desde->toDateString() > $h)
            || ($documentoVersion->vigente_hasta && $documentoVersion->vigente_hasta->toDateString() < $h)
        ) {
            abort(404, 'La versión no está disponible.');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($documentoVersion->ruta_archivo)) {
            abort(404, 'El archivo no existe.');
        }

        $mime = $documentoVersion->mime_type ?? 'application/octet-stream';
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];

        if (! in_array($mime, $allowedMimes)) {
            abort(400, 'Este tipo de archivo no soporta previsualización.');
        }

        return $disk->response($documentoVersion->ruta_archivo, $documentoVersion->nombre_original, [
            'Content-Disposition' => 'inline',
            'Content-Type' => $mime,
        ]);
    }
}
