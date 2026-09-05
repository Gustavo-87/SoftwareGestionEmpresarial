<?php

namespace App\Models;

use Database\Factories\DocumentoActuacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoActuacion extends Model
{
    /** @use HasFactory<DocumentoActuacionFactory> */
    use HasFactory;

    protected $table = 'documento_actuaciones';
    public $timestamps = false;

    protected $fillable = ['documento_version_id', 'organizacion_id', 'copropiedad_id', 'accion', 'detalle', 'actor_user_id', 'nombre_actor'];

    public function documento(): BelongsTo { return $this->belongsTo(Documento::class); }
    public function version(): BelongsTo { return $this->belongsTo(DocumentoVersion::class, 'documento_version_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
}
