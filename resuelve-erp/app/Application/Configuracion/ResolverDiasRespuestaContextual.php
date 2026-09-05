<?php

namespace App\Application\Configuracion;

use App\Application\Contexto\ContextoOperativo;
use App\Models\ConfiguracionCopropiedad;
use App\Models\SiteSetting;

final class ResolverDiasRespuestaContextual
{
    public function resolver(ContextoOperativo $contexto): int
    {
        return ConfiguracionCopropiedad::query()
            ->where('organizacion_id', $contexto->organizacion->id)
            ->where('copropiedad_id', $contexto->copropiedad->id)
            ->value('dias_respuesta')
            ?? (int) SiteSetting::defaults()['dias_respuesta'];
    }
}
