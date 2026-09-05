<?php

namespace App\Models;

use App\Domain\GestionDocumental\Enums\EstadoDocumentoVersionEnum;
use App\Domain\GestionDocumental\Enums\OrigenDocumentoVersionEnum;
use Database\Factories\DocumentoVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentoVersion extends Model
{
    /** @use HasFactory<DocumentoVersionFactory> */
    use HasFactory;

    protected $table = 'documento_versiones';

    protected $fillable = [
        'numero', 'estado', 'origen', 'nombre_original', 'ruta_archivo',
        'mime_type', 'extension', 'tamano_bytes', 'hash_sha256', 'vigente_desde',
        'vigente_hasta', 'sustituye_version_id', 'cargada_por_user_id',
        'nombre_cargador', 'sometida_por_user_id', 'nombre_sometedor',
        'sometida_at', 'aprobada_por_user_id', 'nombre_aprobador', 'aprobada_at',
        'rechazada_por_user_id', 'nombre_rechazador', 'rechazada_at',
        'observacion_rechazo',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoDocumentoVersionEnum::class,
            'origen' => OrigenDocumentoVersionEnum::class,
            'vigente_desde' => 'date', 'vigente_hasta' => 'date',
            'sometida_at' => 'datetime', 'aprobada_at' => 'datetime', 'rechazada_at' => 'datetime',
        ];
    }

    public function documento(): BelongsTo { return $this->belongsTo(Documento::class); }
    public function sustituyeVersion(): BelongsTo { return $this->belongsTo(self::class, 'sustituye_version_id'); }
    public function sucesora(): HasMany { return $this->hasMany(self::class, 'sustituye_version_id'); }
    public function actuaciones(): HasMany { return $this->hasMany(DocumentoActuacion::class); }
}
