<?php

namespace App\Policies;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Domain\GestionDocumental\Enums\AmbitoDocumentoEnum;
use App\Domain\GestionDocumental\Enums\NivelAccesoDocumentoEnum;
use App\Models\Documento;
use App\Models\User;

class DocumentoPolicy
{
    public function viewAny(User $user): bool
    {
        $contexto = app(ContextoOperativo::class);
        $autorizacion = app(AutorizacionContextual::class);

        return $autorizacion->tienePermiso($contexto, 'documentos.consultar')
            || $this->tieneAccesoAdministrativo($autorizacion, $contexto);
    }

    public function view(User $user, Documento $documento): bool
    {
        $contexto = app(ContextoOperativo::class);
        $autorizacion = app(AutorizacionContextual::class);

        if (! $this->perteneceAlContexto($contexto, $documento)) {
            return false;
        }

        return match ($documento->nivel_acceso) {
            NivelAccesoDocumentoEnum::ADMINISTRATIVO => $this->tieneAccesoAdministrativo($autorizacion, $contexto),
            NivelAccesoDocumentoEnum::INTERNO,
            NivelAccesoDocumentoEnum::COMUNIDAD => $autorizacion->tienePermiso($contexto, 'documentos.consultar')
                || $this->tieneAccesoAdministrativo($autorizacion, $contexto),
        };
    }

    public function create(User $user): bool
    {
        return app(AutorizacionContextual::class)->tienePermiso(app(ContextoOperativo::class), 'documentos.gestionar');
    }

    public function update(User $user, Documento $documento): bool
    {
        return $this->perteneceAlContexto(app(ContextoOperativo::class), $documento)
            && app(AutorizacionContextual::class)->tienePermiso(app(ContextoOperativo::class), 'documentos.gestionar');
    }

    public function approve(User $user, Documento $documento): bool
    {
        return $this->perteneceAlContexto(app(ContextoOperativo::class), $documento)
            && app(AutorizacionContextual::class)->tienePermiso(app(ContextoOperativo::class), 'documentos.aprobar');
    }

    public function archive(User $user, Documento $documento): bool
    {
        return $this->perteneceAlContexto(app(ContextoOperativo::class), $documento)
            && app(AutorizacionContextual::class)->tienePermiso(app(ContextoOperativo::class), 'documentos.archivar');
    }

    private function perteneceAlContexto(ContextoOperativo $contexto, Documento $documento): bool
    {
        return $documento->ambito === AmbitoDocumentoEnum::COPROPIEDAD
            && $documento->organizacion_id === $contexto->organizacion->id
            && $documento->copropiedad_id === $contexto->copropiedad->id;
    }

    private function tieneAccesoAdministrativo(AutorizacionContextual $autorizacion, ContextoOperativo $contexto): bool
    {
        return $autorizacion->tienePermiso($contexto, 'documentos.gestionar')
            || $autorizacion->tienePermiso($contexto, 'documentos.aprobar')
            || $autorizacion->tienePermiso($contexto, 'documentos.archivar');
    }
}
