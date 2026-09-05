<?php

namespace Tests\Feature;

use App\Domain\GestionDocumental\Enums\AmbitoDocumentoEnum;
use App\Domain\GestionDocumental\Enums\EstadoDocumentoVersionEnum;
use App\Models\Copropiedad;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\Organizacion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class DocumentoSchemaTest extends TestCase
{
    use CreatesInstitutionalContext;
    use RefreshDatabase;

    public function test_document_models_cast_the_approved_catalogs_and_keep_context_out_of_fillable_attributes(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $owner = User::factory()->create();
        $documento = $this->documento($organizacion, $copropiedad, $owner);

        $this->assertSame(AmbitoDocumentoEnum::COPROPIEDAD, $documento->ambito);
        $this->assertNotContains('organizacion_id', $documento->getFillable());
        $this->assertNotContains('copropiedad_id', $documento->getFillable());
        $this->assertFalse(Schema::hasColumn('documento_actuaciones', 'updated_at'));
    }

    public function test_context_and_catalog_checks_reject_invalid_documents(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $owner = User::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('documentos')->insert([
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => null,
            'ambito' => 'copropiedad',
            'propietario_documental_user_id' => $owner->id,
            'tipo' => 'reglamento',
            'categoria' => 'normativo',
            'titulo' => 'Reglamento inválido',
            'nivel_acceso' => 'comunidad',
            'estado' => 'activo',
        ]);
    }

    public function test_composite_context_foreign_key_rejects_a_document_with_an_external_copropiedad(): void
    {
        [$organizacion] = $this->createInstitutionalContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create(['organizacion_id' => $otraOrganizacion->id, 'nombre' => 'Otra copropiedad', 'estado' => 'activa']);
        $owner = User::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('documentos')->insert([
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $otraCopropiedad->id,
            'ambito' => 'copropiedad',
            'propietario_documental_user_id' => $owner->id,
            'tipo' => 'acta',
            'categoria' => 'gobierno_copropiedad',
            'titulo' => 'Acta externa',
            'nivel_acceso' => 'interno',
            'estado' => 'activo',
        ]);
    }

    public function test_pending_generated_key_allows_only_one_pending_version_per_document_and_preserves_other_states(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $owner = User::factory()->create();
        $documento = $this->documento($organizacion, $copropiedad, $owner);
        $this->version($documento, 1, 'pendiente_aprobacion');
        $this->version($documento, 2, 'borrador');

        $this->expectException(QueryException::class);
        $this->version($documento, 3, 'pendiente_aprobacion');
    }

    public function test_version_constraints_enforce_hash_scope_and_contextual_successor(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$otraOrganizacion, $otraCopropiedad] = $this->createOtherContext();
        $owner = User::factory()->create();
        $documento = $this->documento($organizacion, $copropiedad, $owner);
        $otroDocumento = $this->documento($organizacion, $copropiedad, $owner, 'Otro documento');
        $externo = $this->documento($otraOrganizacion, $otraCopropiedad, $owner, 'Documento externo');
        $hash = hash('sha256', 'mismo archivo');
        $primera = $this->version($documento, 1, 'borrador', $hash);
        $this->version($otroDocumento, 1, 'borrador', $hash);

        try {
            $this->version($documento, 2, 'borrador', $hash);
            $this->fail('El hash duplicado dentro del mismo Documento debía rechazarse.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        $this->version($externo, 1, 'borrador', hash('sha256', 'externo'), $primera->id);
    }

    public function test_owner_documental_is_restricted_while_historical_version_actors_remain_nullable(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $documento = $this->documento($organizacion, $copropiedad, $owner);
        $version = $this->version($documento, 1, 'borrador');
        $version->forceFill(['cargada_por_user_id' => $actor->id, 'nombre_cargador' => $actor->name])->save();

        $actor->delete();
        $this->assertNull($version->fresh()->cargada_por_user_id);
        $this->assertSame($actor->name, $version->fresh()->nombre_cargador);

        $this->expectException(QueryException::class);
        $owner->delete();
    }

    private function documento(Organizacion $organizacion, Copropiedad $copropiedad, User $owner, string $titulo = 'Documento'): Documento
    {
        $documento = new Documento([
            'ambito' => 'copropiedad', 'propietario_documental_user_id' => $owner->id,
            'tipo' => 'documento_general', 'categoria' => 'administrativo',
            'titulo' => $titulo, 'nivel_acceso' => 'interno', 'estado' => 'activo',
        ]);
        $documento->forceFill(['organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id])->save();

        return $documento;
    }

    private function version(Documento $documento, int $numero, string $estado, ?string $hash = null, ?int $sustituye = null): DocumentoVersion
    {
        $version = new DocumentoVersion([
            'numero' => $numero, 'estado' => $estado, 'origen' => 'usuario',
            'nombre_original' => "documento-{$numero}.pdf", 'ruta_archivo' => "privado/{$numero}.pdf",
            'mime_type' => 'application/pdf', 'extension' => 'pdf', 'tamano_bytes' => 100,
            'hash_sha256' => $hash ?? hash('sha256', "{$documento->id}-{$numero}"),
            'sustituye_version_id' => $sustituye,
        ]);
        $version->forceFill([
            'documento_id' => $documento->id,
            'organizacion_id' => $documento->organizacion_id,
            'copropiedad_id' => $documento->copropiedad_id,
        ])->save();

        return $version;
    }

    /** @return array{Organizacion, Copropiedad} */
    private function createOtherContext(): array
    {
        $organizacion = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $copropiedad = Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Otra copropiedad', 'estado' => 'activa']);

        return [$organizacion, $copropiedad];
    }
}
