<?php

namespace App\Application\Documentos;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Models\Documento;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CrearDocumento
{
    public function __construct(private readonly AutorizacionContextual $autorizacion, private readonly RegistrarActuacionDocumento $actuaciones) {}

    public function ejecutar(ContextoOperativo $contexto, User $actor, array $datos): Documento
    {
        if (! $this->autorizacion->tienePermiso($contexto, 'documentos.gestionar')) throw new AuthorizationException();
        $this->validarPropietario($contexto, $datos['propietario_documental_user_id']);

        return DB::transaction(function () use ($contexto, $actor, $datos): Documento {
            $documento = new Documento($datos);
            $documento->forceFill([
                'organizacion_id' => $contexto->organizacion->id,
                'copropiedad_id' => $contexto->copropiedad->id,
                'ambito' => 'copropiedad',
                'creado_por_user_id' => $actor->id,
            ])->save();
            $this->actuaciones->registrar($documento, null, $actor, 'documento_creado', 'Creó el Documento.');
            return $documento;
        });
    }

    private function validarPropietario(ContextoOperativo $contexto, int $userId): void
    {
        $vigente = MembresiaCopropiedad::query()->where('usuario_id', $userId)->where('organizacion_id', $contexto->organizacion->id)->where('copropiedad_id', $contexto->copropiedad->id)->where('estado', 'activa')->where('vigente_desde', '<=', now())->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', now()))->exists();
        if (! $vigente) throw ValidationException::withMessages(['propietario_documental_user_id' => ['El propietario documental seleccionado no es válido.']]);
    }
}
