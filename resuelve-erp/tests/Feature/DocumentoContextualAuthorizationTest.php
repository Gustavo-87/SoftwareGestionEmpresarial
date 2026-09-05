<?php

namespace Tests\Feature;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Documentos\ConsultaDocumentosContextuales;
use App\Domain\GestionDocumental\Enums\NivelAccesoDocumentoEnum;
use App\Models\Copropiedad;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\Organizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

class DocumentoContextualAuthorizationTest extends TestCase
{
    use CreatesInstitutionalContext;
    use RefreshDatabase;

    public function test_contextual_query_returns_only_documents_from_the_active_copropiedad_and_hides_external_documents_like_missing_ones(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$otraOrganizacion, $otraCopropiedad] = $this->createOtherContext();
        $actor = User::factory()->create();
        [, , $contexto] = $this->createContextualIdentity($actor, $organizacion, $copropiedad, 'gestor', ['documentos.consultar']);
        $local = $this->documento($organizacion, $copropiedad, $actor);
        $external = $this->documento($otraOrganizacion, $otraCopropiedad, $actor, 'Documento externo');
        $consulta = app(ConsultaDocumentosContextuales::class);

        $this->assertSame([$local->id], $consulta->para($contexto)->pluck('id')->all());
        $this->assertTrue($consulta->resolver($contexto, $local->id)->is($local));

        foreach ([$external->id, 999999] as $id) {
            try {
                $consulta->resolver($contexto, $id);
                $this->fail('El Documento debía ser indistinguible de uno inexistente.');
            } catch (ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_version_is_resolved_only_as_a_child_of_the_contextual_document(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        [$otraOrganizacion, $otraCopropiedad] = $this->createOtherContext();
        $actor = User::factory()->create();
        [, , $contexto] = $this->createContextualIdentity($actor, $organizacion, $copropiedad, 'gestor', ['documentos.consultar']);
        $local = $this->documento($organizacion, $copropiedad, $actor);
        $externo = $this->documento($otraOrganizacion, $otraCopropiedad, $actor);
        $versionLocal = $this->version($local, 1);
        $versionExterna = $this->version($externo, 1);
        $consulta = app(ConsultaDocumentosContextuales::class);

        $this->assertTrue($consulta->resolverVersion($contexto, $local, $versionLocal->id)->is($versionLocal));

        foreach ([$versionExterna->id, 999999] as $id) {
            try {
                $consulta->resolverVersion($contexto, $local, $id);
                $this->fail('La Versión debía ser indistinguible de una inexistente.');
            } catch (ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_inactive_membership_and_insufficient_permission_cannot_view_documents(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $owner = User::factory()->create();
        $documento = $this->documento($organizacion, $copropiedad, $owner);
        $inactive = User::factory()->create(['role' => 'admin']);
        [, , $inactiveContext] = $this->createContextualIdentity($inactive, $organizacion, $copropiedad, 'gestor', ['documentos.consultar'], 'inactiva');
        $withoutPermission = User::factory()->create(['role' => 'admin']);
        [, , $withoutPermissionContext] = $this->createContextualIdentity($withoutPermission, $organizacion, $copropiedad, 'gestor', []);

        $this->setContext($inactiveContext);
        $this->assertFalse(Gate::forUser($inactive)->allows('view', $documento));
        $this->setContext($withoutPermissionContext);
        $this->assertFalse(Gate::forUser($withoutPermission)->allows('view', $documento));
    }

    public function test_access_level_restricts_without_granting_access_by_itself(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $owner = User::factory()->create();
        $administrativo = $this->documento($organizacion, $copropiedad, $owner, 'Administrativo', 'administrativo');
        $interno = $this->documento($organizacion, $copropiedad, $owner, 'Interno', 'interno');
        $comunidad = $this->documento($organizacion, $copropiedad, $owner, 'Comunidad', 'comunidad');
        $consultor = User::factory()->create(['role' => 'residente']);
        [, , $consultorContext] = $this->createContextualIdentity($consultor, $organizacion, $copropiedad, 'residente', ['documentos.consultar']);
        $gestor = User::factory()->create(['role' => 'residente']);
        [, , $gestorContext] = $this->createContextualIdentity($gestor, $organizacion, $copropiedad, 'residente', ['documentos.gestionar']);

        $this->setContext($consultorContext);
        $this->assertFalse(Gate::forUser($consultor)->allows('view', $administrativo));
        $this->assertTrue(Gate::forUser($consultor)->allows('view', $interno));
        $this->assertTrue(Gate::forUser($consultor)->allows('view', $comunidad));

        $this->setContext($gestorContext);
        $this->assertTrue(Gate::forUser($gestor)->allows('view', $administrativo));
    }

    public function test_owner_does_not_receive_access_and_organization_scope_has_no_operational_query(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $owner = User::factory()->create(['role' => 'admin']);
        [, , $contexto] = $this->createContextualIdentity($owner, $organizacion, $copropiedad, 'admin', []);
        $documento = $this->documento($organizacion, $copropiedad, $owner);
        $organizacionDocumento = new Documento([
            'ambito' => 'organizacion', 'propietario_documental_user_id' => $owner->id,
            'tipo' => 'documento_general', 'categoria' => 'administrativo',
            'titulo' => 'Documento de organización', 'nivel_acceso' => 'interno', 'estado' => 'activo',
        ]);
        $organizacionDocumento->forceFill(['organizacion_id' => $organizacion->id, 'copropiedad_id' => null])->save();

        $this->setContext($contexto);
        $this->assertFalse(Gate::forUser($owner)->allows('view', $documento));
        $this->assertSame([$documento->id], app(ConsultaDocumentosContextuales::class)->para($contexto)->pluck('id')->all());
    }

    public function test_authorized_actor_uses_contextual_permissions_and_document_policy_is_registered(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $owner = User::factory()->create();
        $documento = $this->documento($organizacion, $copropiedad, $owner);
        $actor = User::factory()->create(['role' => 'residente']);
        [, , $contexto] = $this->createContextualIdentity($actor, $organizacion, $copropiedad, 'residente', ['documentos.consultar']);

        $this->setContext($contexto);
        $this->assertTrue(Gate::forUser($actor)->allows('view', $documento));
        $this->assertTrue(Gate::forUser($actor)->allows('viewAny', Documento::class));
    }

    private function setContext(ContextoOperativo $contexto): void
    {
        $this->app->instance(ContextoOperativo::class, $contexto);
    }

    private function documento(Organizacion $organizacion, Copropiedad $copropiedad, User $owner, string $titulo = 'Documento', string $nivel = 'interno'): Documento
    {
        $documento = new Documento([
            'ambito' => 'copropiedad', 'propietario_documental_user_id' => $owner->id,
            'tipo' => 'documento_general', 'categoria' => 'administrativo',
            'titulo' => $titulo, 'nivel_acceso' => $nivel, 'estado' => 'activo',
        ]);
        $documento->forceFill(['organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id])->save();

        return $documento;
    }

    private function version(Documento $documento, int $numero): DocumentoVersion
    {
        $version = new DocumentoVersion([
            'numero' => $numero, 'estado' => 'borrador', 'origen' => 'usuario',
            'nombre_original' => "documento-{$numero}.pdf", 'ruta_archivo' => "privado/{$numero}.pdf",
            'mime_type' => 'application/pdf', 'extension' => 'pdf', 'tamano_bytes' => 100,
            'hash_sha256' => hash('sha256', "{$documento->id}-{$numero}"),
        ]);
        $version->forceFill(['documento_id' => $documento->id, 'organizacion_id' => $documento->organizacion_id, 'copropiedad_id' => $documento->copropiedad_id])->save();

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
