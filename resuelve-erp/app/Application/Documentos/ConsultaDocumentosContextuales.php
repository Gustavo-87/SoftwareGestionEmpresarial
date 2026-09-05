<?php

namespace App\Application\Documentos;

use App\Application\Contexto\ContextoOperativo;
use App\Domain\GestionDocumental\Enums\AmbitoDocumentoEnum;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

final class ConsultaDocumentosContextuales
{
    public function para(ContextoOperativo $contexto): Builder
    {
        return Documento::query()->tap(fn (Builder $query) => $this->restringir($query, $contexto));
    }

    public function restringir(Builder $query, ContextoOperativo $contexto): Builder
    {
        $this->validarContexto($contexto);

        return $query
            ->where($query->qualifyColumn('organizacion_id'), $contexto->organizacion->id)
            ->where($query->qualifyColumn('copropiedad_id'), $contexto->copropiedad->id)
            ->where($query->qualifyColumn('ambito'), AmbitoDocumentoEnum::COPROPIEDAD->value);
    }

    public function resolver(ContextoOperativo $contexto, int|string $identificador): Documento
    {
        $modelo = new Documento();

        return $this->para($contexto)
            ->where($modelo->getRouteKeyName(), $identificador)
            ->firstOrFail();
    }

    public function resolverVersion(ContextoOperativo $contexto, Documento $documento, int|string $identificador): DocumentoVersion
    {
        $documento = $this->resolver($contexto, $documento->getKey());
        $modelo = new DocumentoVersion();

        return DocumentoVersion::query()
            ->where($modelo->getRouteKeyName(), $identificador)
            ->where('documento_id', $documento->id)
            ->where('organizacion_id', $contexto->organizacion->id)
            ->where('copropiedad_id', $contexto->copropiedad->id)
            ->firstOrFail();
    }

    private function validarContexto(ContextoOperativo $contexto): void
    {
        if ($contexto->copropiedad->organizacion_id !== $contexto->organizacion->id) {
            throw new RuntimeException('El contexto institucional de Documentos es inconsistente.');
        }
    }
}
