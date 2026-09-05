<?php

namespace App\Application\Documentos;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\DocumentoActuacion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CargarVersionDocumento
{
    private const MIME_PERMITIDOS = ['application/pdf', 'image/jpeg', 'image/png', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    private const EXTENSIONES_PERMITIDAS = ['pdf', 'jpg', 'jpeg', 'png', 'docx'];

    public function __construct(private readonly AutorizacionContextual $autorizacion, private readonly ConsultaDocumentosContextuales $consulta, private readonly RegistrarActuacionDocumento $actuaciones) {}

    public function ejecutar(ContextoOperativo $contexto, User $actor, Documento $documento, UploadedFile $archivo, string $origen = 'usuario', ?DocumentoVersion $sustituye = null): DocumentoVersion
    {
        $documento = $this->consulta->resolver($contexto, $documento->id);
        if (! $this->autorizacion->tienePermiso($contexto, 'documentos.gestionar')) throw new AuthorizationException();
        $this->validarArchivo($archivo);
        $disk = Storage::disk('local');
        $temporal = 'documentos/staging/'.Str::uuid().'.'.$archivo->extension();
        $disk->putFileAs('documentos/staging', $archivo, basename($temporal));
        $hash = hash_file('sha256', $disk->path($temporal));
        $version = null;
        try {
            $version = DB::transaction(function () use ($contexto, $actor, $documento, $archivo, $origen, $temporal, $hash, $sustituye): DocumentoVersion {
                $bloqueado = Documento::query()->whereKey($documento->id)->lockForUpdate()->firstOrFail();
                $numero = ((int) $bloqueado->versiones()->max('numero')) + 1;
                $ruta = "documentos/{$contexto->organizacion->id}/{$contexto->copropiedad->id}/{$bloqueado->id}/{$numero}/".basename($temporal);
                if ($sustituye && ($sustituye->documento_id !== $bloqueado->id || $sustituye->organizacion_id !== $contexto->organizacion->id || $sustituye->copropiedad_id !== $contexto->copropiedad->id)) throw ValidationException::withMessages(['sustituye_version_id' => ['La Versión sustituida no pertenece al Documento.']]);
                $version = new DocumentoVersion([
                    'numero' => $numero, 'estado' => 'borrador', 'origen' => $origen,
                    'nombre_original' => $archivo->getClientOriginalName(), 'ruta_archivo' => $ruta,
                    'mime_type' => $archivo->getMimeType() ?: 'application/octet-stream', 'extension' => strtolower($archivo->extension()),
                    'tamano_bytes' => $archivo->getSize(), 'hash_sha256' => $hash,
                    'cargada_por_user_id' => $actor->id, 'nombre_cargador' => $actor->name, 'sustituye_version_id' => $sustituye?->id,
                ]);
                $version->forceFill(['documento_id' => $bloqueado->id, 'organizacion_id' => $contexto->organizacion->id, 'copropiedad_id' => $contexto->copropiedad->id])->save();
                $this->actuaciones->registrar($bloqueado, $version, $actor, 'version_cargada', 'Cargó una nueva Versión en borrador.');
                return $version;
            });
            if (! $disk->move($temporal, $version->ruta_archivo)) throw new \RuntimeException('No fue posible mover el archivo a su almacenamiento definitivo.');
            return $version;
        } catch (\Throwable $exception) {
            if ($version?->exists) DB::transaction(function () use ($version): void {
                DocumentoActuacion::query()->where('documento_version_id', $version->id)->delete();
                $version->delete();
            });
            $disk->delete($temporal);
            if ($version) $disk->delete($version->ruta_archivo);
            throw $exception;
        }
    }

    private function validarArchivo(UploadedFile $archivo): void
    {
        if (! in_array($archivo->getMimeType(), self::MIME_PERMITIDOS, true) || ! in_array(strtolower($archivo->extension()), self::EXTENSIONES_PERMITIDAS, true) || $archivo->getSize() > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['archivo' => ['El archivo seleccionado no es válido.']]);
        }
    }
}
