<?php

namespace App\Application\Documentos;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ArchivarDocumento
{
    public function __construct(private readonly AutorizacionContextual $autorizacion, private readonly ConsultaDocumentosContextuales $consulta, private readonly RegistrarActuacionDocumento $actuaciones) {}
    public function ejecutar(ContextoOperativo $contexto, User $actor, Documento $documento): void
    {
        $documento = $this->consulta->resolver($contexto, $documento->id);
        if (! $this->autorizacion->tienePermiso($contexto, 'documentos.archivar')) throw new AuthorizationException();
        DB::transaction(function () use ($documento, $actor): void {
            $documento->update(['estado' => 'archivado', 'archivado_por_user_id' => $actor->id, 'archivado_at' => now()]);
            $this->actuaciones->registrar($documento, null, $actor, 'documento_archivado', 'Archivó el Documento.');
        });
    }
}
