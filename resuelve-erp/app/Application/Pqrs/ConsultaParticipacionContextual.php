<?php

namespace App\Application\Pqrs;

use App\Application\Contexto\ContextoOperativo;
use App\Models\Persona;
use App\Models\User;
use App\Models\VinculoUnidad;
use Illuminate\Support\Collection;
use RuntimeException;

final class ConsultaParticipacionContextual
{
    /**
     * @return array{persona: ?Persona, vinculos: Collection<int, VinculoUnidad>, unidadesPrivadas: Collection}
     */
    public function para(User $usuario, ContextoOperativo $contexto): array
    {
        $this->validarContexto($contexto);

        $persona = Persona::query()
            ->where('organizacion_id', $contexto->organizacion->id)
            ->where('usuario_id', $usuario->id)
            ->first();

        if ($persona === null) {
            return $this->vacio();
        }

        $vinculos = VinculoUnidad::query()
            ->where('organizacion_id', $contexto->organizacion->id)
            ->where('copropiedad_id', $contexto->copropiedad->id)
            ->where('persona_id', $persona->id)
            ->vigentes()
            ->with('unidadPrivada')
            ->get();

        return [
            'persona' => $persona,
            'vinculos' => $vinculos,
            'unidadesPrivadas' => $vinculos
                ->pluck('unidadPrivada')
                ->filter()
                ->unique('id')
                ->values(),
        ];
    }

    /**
     * @return array{persona: null, vinculos: Collection<int, VinculoUnidad>, unidadesPrivadas: Collection}
     */
    private function vacio(): array
    {
        return [
            'persona' => null,
            'vinculos' => collect(),
            'unidadesPrivadas' => collect(),
        ];
    }

    private function validarContexto(ContextoOperativo $contexto): void
    {
        if ($contexto->copropiedad->organizacion_id !== $contexto->organizacion->id) {
            throw new RuntimeException('El contexto institucional de participación PQRS es inconsistente.');
        }
    }
}
