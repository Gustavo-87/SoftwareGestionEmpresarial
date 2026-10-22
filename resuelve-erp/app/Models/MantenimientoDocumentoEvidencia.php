<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evidencia documental de un mantenimiento (HU-DOC-03, Sprint 19).
 *
 * Fija la versión exacta utilizada (`documento_version_id`) sin alterar el
 * ciclo documental del documento. La clave única impide vínculos duplicados
 * por doble clic, reenvío o concurrencia.
 */
class MantenimientoDocumentoEvidencia extends Model
{

    protected $table = 'mantenimiento_documento_evidencia';

    protected $fillable = [
        'mantenimiento_id',
        'documento_id',
        'documento_version_id',
        'vinculado_por_user_id',
    ];

    public function mantenimiento(): BelongsTo
    {

        return $this->belongsTo(Mantenimiento::class, 'mantenimiento_id');
    }

    public function documento(): BelongsTo
    {

        return $this->belongsTo(Documento::class, 'documento_id');
    }

    public function version(): BelongsTo
    {

        return $this->belongsTo(DocumentoVersion::class, 'documento_version_id');
    }

    public function vinculador(): BelongsTo
    {

        return $this->belongsTo(User::class, 'vinculado_por_user_id');
    }
}
