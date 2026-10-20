<?php

namespace Tests\Feature;

use App\Domain\GestionDocumental\Enums\EstadoDocumentoVersionEnum;
use App\Models\Copropiedad;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\Mantenimiento;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\TipoPqr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * Dashboard del administrador de organización: encabezado de una fila,
 * prioridades compactas, resumen por módulo con barras por estados y
 * Top 5 de PQRS por tipo. Actividad reciente y accesos rápidos no se
 * presentan en esta vista; sus capacidades siguen disponibles en el producto.
 */
class PanelIndicadoresTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    private const PERMISOS_TODOS = [
        'pqrs.listar', 'pqrs.ver_todas',
        'mantenimiento.ver_todas',
        'documentos.consultar',
    ];

    private function sembrarModulos($organizacion, Copropiedad $copropiedad, User $user): void
    {
        $tipo = TipoPqr::factory()->create();
        foreach (['radicada', 'en_revision', 'respondida'] as $estado) {
            Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
                'user_id' => $user->id,
                'tipo_pqr_id' => $tipo->id,
                'estado' => $estado,
            ]);
        }

        foreach (['pendiente', 'en_proceso', 'finalizado'] as $estado) {
            Mantenimiento::forceCreate([
                'organizacion_id' => $organizacion->id,
                'copropiedad_id' => $copropiedad->id,
                'solicitante_id' => $user->id,
                'titulo' => 'Mantenimiento '.$estado,
                'descripcion' => 'Descripción de prueba',
                'fecha_programada' => now(),
                'estado' => $estado,
            ]);
        }

        $conVersionAprobada = Documento::factory()->paraContexto($organizacion, $copropiedad)->create();
        DocumentoVersion::factory()->create([
            'documento_id' => $conVersionAprobada->id,
            'estado' => EstadoDocumentoVersionEnum::APROBADA,
        ]);
        foreach ([EstadoDocumentoVersionEnum::PENDIENTE_APROBACION, EstadoDocumentoVersionEnum::BORRADOR] as $estado) {
            $documento = Documento::factory()->paraContexto($organizacion, $copropiedad)->create();
            DocumentoVersion::factory()->create(['documento_id' => $documento->id, 'estado' => $estado]);
        }
    }

    public function test_el_panel_muestra_prioridades_modulos_y_top_cinco_con_datos_reales(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'admin', self::PERMISOS_TODOS);
        $this->sembrarModulos($organizacion, $copropiedad, $user);

        $contenido = $this->actingAsContextual($user)->get(route('panel'))->assertOk()->content();

        // Encabezado, prioridades, resumen por módulo y Top 5.
        $this->assertStringContainsString($copropiedad->nombre, $contenido);
        $this->assertStringContainsString('PQRS vencidas', $contenido);
        $this->assertStringContainsString('Próximas a vencer', $contenido);
        $this->assertStringContainsString('PQRS pendientes', $contenido);
        $this->assertStringContainsString('Top 5 de PQRS por tipo', $contenido);

        // Módulos: total y distribución por estados con cantidades legibles.
        $this->assertMatchesRegularExpression('/operativo-total-pqrs">3 solicitudes</', $contenido);
        $this->assertMatchesRegularExpression('/operativo-total-mantenimiento">3 registros</', $contenido);
        $this->assertMatchesRegularExpression('/operativo-total-documentos">3 documentos</', $contenido);
        $this->assertMatchesRegularExpression('/modulo-estado-label">Radicadas<\/span>.*?modulo-estado-cantidad">1<\/span>/s', $contenido);
        $this->assertMatchesRegularExpression('/modulo-estado-label">En revisión<\/span>.*?modulo-estado-cantidad">1<\/span>/s', $contenido);
        $this->assertMatchesRegularExpression('/modulo-estado-label">Cerradas<\/span>.*?modulo-estado-cantidad">0<\/span>/s', $contenido);
        $this->assertMatchesRegularExpression('/modulo-estado-label">Pendientes<\/span>.*?modulo-estado-cantidad">1<\/span>/s', $contenido);
        $this->assertMatchesRegularExpression('/modulo-estado-label">Finalizados<\/span>.*?modulo-estado-cantidad">1<\/span>/s', $contenido);
        $this->assertMatchesRegularExpression('/modulo-estado-label">En trámite<\/span>.*?modulo-estado-cantidad">2<\/span>/s', $contenido);
        $this->assertMatchesRegularExpression('/modulo-estado-label">Aprobados<\/span>.*?modulo-estado-cantidad">1<\/span>/s', $contenido);

        // Top 5 por tipo, con una fila de barra por tipo.
        $this->assertMatchesRegularExpression('/class="stats-bar-row/', $contenido);

        // Accesos al módulo, según la autorización existente.
        $this->assertStringContainsString('Ir a PQRS', $contenido);
        $this->assertStringContainsString('Ir a Mantenimiento', $contenido);
        $this->assertStringContainsString('Ir a Documentos', $contenido);

        // Fuera de esta vista: donas, actividad reciente y accesos rápidos.
        $this->assertStringNotContainsString('stats-donut', $contenido);
        $this->assertStringNotContainsString('Actividad reciente', $contenido);
        $this->assertStringNotContainsString('Accesos rápidos', $contenido);
    }

    public function test_los_modulos_sin_acceso_no_se_muestran(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $user = User::factory()->create(['role' => 'gestor']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'gestor', ['pqrs.listar', 'pqrs.ver_todas']);
        $this->sembrarModulos($organizacion, $copropiedad, $user);

        $contenido = $this->actingAsContextual($user)->get(route('panel'))->assertOk()->content();

        $this->assertMatchesRegularExpression('/<h2>PQRS<\/h2>/', $contenido);
        $this->assertStringNotContainsString('<h2>Mantenimiento</h2>', $contenido);
        $this->assertStringNotContainsString('<h2>Documentos</h2>', $contenido);
        $this->assertStringNotContainsString('Ir a Mantenimiento', $contenido);
        $this->assertStringNotContainsString('Ir a Documentos', $contenido);
    }

    public function test_los_indicadores_solo_cuentan_registros_del_contexto_activo(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $otraOrganizacion = Organizacion::create(['nombre' => 'Otra organización', 'estado' => 'activa']);
        $otraCopropiedad = Copropiedad::create([
            'organizacion_id' => $otraOrganizacion->id,
            'nombre' => 'Otra copropiedad',
            'estado' => 'activa',
        ]);
        $user = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'admin', self::PERMISOS_TODOS);

        $this->sembrarModulos($organizacion, $copropiedad, $user);
        $tipo = TipoPqr::factory()->create();
        foreach (['radicada', 'en_revision'] as $estado) {
            Pqr::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create([
                'user_id' => $user->id,
                'tipo_pqr_id' => $tipo->id,
                'estado' => $estado,
            ]);
        }
        foreach (['pendiente', 'finalizado'] as $estado) {
            Mantenimiento::forceCreate([
                'organizacion_id' => $otraOrganizacion->id,
                'copropiedad_id' => $otraCopropiedad->id,
                'solicitante_id' => $user->id,
                'titulo' => 'Otro mantenimiento',
                'descripcion' => 'Descripción de prueba',
                'fecha_programada' => now(),
                'estado' => $estado,
            ]);
        }
        $otroDocumento = Documento::factory()->paraContexto($otraOrganizacion, $otraCopropiedad)->create();
        DocumentoVersion::factory()->create([
            'documento_id' => $otroDocumento->id,
            'estado' => EstadoDocumentoVersionEnum::APROBADA,
        ]);

        $contenido = $this->actingAsContextual($user)->get(route('panel'))->assertOk()->content();

        // Solo los registros del contexto activo (3 por módulo).
        $this->assertMatchesRegularExpression('/operativo-total-pqrs">3 solicitudes</', $contenido);
        $this->assertMatchesRegularExpression('/operativo-total-mantenimiento">3 registros</', $contenido);
        $this->assertMatchesRegularExpression('/operativo-total-documentos">3 documentos</', $contenido);
    }
}
