<?php

namespace App\Models;

use App\Domain\GestionDocumental\Enums\AmbitoDocumentoEnum;
use App\Domain\GestionDocumental\Enums\CategoriaDocumentoEnum;
use App\Domain\GestionDocumental\Enums\EstadoDocumentoEnum;
use App\Domain\GestionDocumental\Enums\NivelAccesoDocumentoEnum;
use App\Domain\GestionDocumental\Enums\TipoDocumentoEnum;
use Database\Factories\DocumentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Documento extends Model
{
    /** @use HasFactory<DocumentoFactory> */
    use HasFactory;

    protected $table = 'documentos';

    protected $fillable = [
        'ambito', 'tipo', 'categoria', 'titulo', 'descripcion', 'nivel_acceso',
        'estado', 'propietario_documental_user_id', 'creado_por_user_id',
        'archivado_por_user_id', 'archivado_at',
    ];

    protected function casts(): array
    {
        return [
            'ambito' => AmbitoDocumentoEnum::class,
            'tipo' => TipoDocumentoEnum::class,
            'categoria' => CategoriaDocumentoEnum::class,
            'nivel_acceso' => NivelAccesoDocumentoEnum::class,
            'estado' => EstadoDocumentoEnum::class,
            'archivado_at' => 'datetime',
        ];
    }

    public function organizacion(): BelongsTo { return $this->belongsTo(Organizacion::class); }
    public function copropiedad(): BelongsTo { return $this->belongsTo(Copropiedad::class); }
    public function propietarioDocumental(): BelongsTo { return $this->belongsTo(User::class, 'propietario_documental_user_id'); }
    public function creador(): BelongsTo { return $this->belongsTo(User::class, 'creado_por_user_id'); }
    public function archivador(): BelongsTo { return $this->belongsTo(User::class, 'archivado_por_user_id'); }
    public function versiones(): HasMany { return $this->hasMany(DocumentoVersion::class); }

    public function versionVigente(): ?DocumentoVersion
    {
        return $this->versiones->first(fn (DocumentoVersion $version) =>
            $version->estado->value === 'aprobada'
            && (! $version->vigente_desde || $version->vigente_desde->isToday() || $version->vigente_desde->isPast())
            && (! $version->vigente_hasta || $version->vigente_hasta->isToday() || $version->vigente_hasta->isFuture())
        );
    }
    public function actuaciones(): HasMany { return $this->hasMany(DocumentoActuacion::class); }
}
