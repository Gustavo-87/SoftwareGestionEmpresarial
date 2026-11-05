<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Copropiedad;
use App\Models\UnidadPrivada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesInstitutionalContext;
use Tests\TestCase;

/**
 * HU-RYP-03 — Importación masiva de Unidades Privadas desde XLSX (Sprint 22).
 */
class UnidadesPrivadasImportacionTest extends TestCase
{
    use CreatesInstitutionalContext, RefreshDatabase;

    public function test_importacion_valida_crea_las_unidades_en_el_contexto_activo(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $operador = $this->operador($organizacion, $copropiedad);

        $respuesta = $this->actingAs($operador)->post(route('management.unidades.importar.store'), [
            'archivo' => $this->archivoXlsx(
                ['Código', 'Número / Nombre', 'Torre / Bloque', 'Tipo'],
                [
                    ['A-101', 'Apto 101', 'Torre A', 'apartamento'],
                    ['A-102', 'Apto 102', 'Torre A', 'apartamento'],
                    ['B-201', 'Local 201', 'Torre B', 'local'],
                ],
            ),
        ]);

        $respuesta->assertRedirect(route('management.unidades.importar'));
        $respuesta->assertSessionHas('importacion_unidades_resultado');
        $resultado = session('importacion_unidades_resultado');
        $this->assertSame('exito', $resultado['estado']);
        $this->assertSame(3, $resultado['leidas']);
        $this->assertSame(3, $resultado['creadas']);
        $this->assertSame(0, $resultado['duplicadas']);
        $this->assertSame(0, $resultado['invalidas']);
        $this->assertDatabaseHas('unidades_privadas', [
            'codigo' => 'A-101',
            'numero_nombre' => 'Apto 101',
            'organizacion_id' => $organizacion->id,
            'copropiedad_id' => $copropiedad->id,
        ]);
        $this->assertDatabaseCount('unidades_privadas', 3);
    }

    public function test_filas_invalidas_no_impiden_crear_las_validas(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $operador = $this->operador($organizacion, $copropiedad);

        $respuesta = $this->actingAs($operador)->post(route('management.unidades.importar.store'), [
            'archivo' => $this->archivoXlsx(
                ['codigo', 'numero_nombre', 'torre_bloque', 'tipo'],
                [
                    ['A-101', 'Válida', 'Torre A', 'casa'],
                    ['', 'Sin código', null, null],
                    [str_repeat('X', 60), 'Código muy largo', null, null],
                    ['A-103', 'Tipo inválido', null, 'vehiculo'],
                ],
            ),
        ]);

        $respuesta->assertSessionHas('importacion_unidades_resultado');
        $resultado = session('importacion_unidades_resultado');
        $this->assertSame('parcial', $resultado['estado']);
        $this->assertSame(4, $resultado['leidas']);
        $this->assertSame(1, $resultado['creadas']);
        $this->assertSame(3, $resultado['invalidas']);
        $this->assertCount(3, $resultado['errores']);
        $this->assertSame(3, $resultado['errores'][0]['fila']);
        $this->assertSame('codigo', $resultado['errores'][0]['campo']);
        $this->assertSame(5, $resultado['errores'][2]['fila']);
        $this->assertSame('tipo', $resultado['errores'][2]['campo']);
        $this->assertDatabaseCount('unidades_privadas', 1);
    }

    public function test_duplicados_se_omitien_y_reportan_sin_distincion_de_caja(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $operador = $this->operador($organizacion, $copropiedad);
        $this->crearUnidad($organizacion, $copropiedad, 'B-201', 'Existente');

        $respuesta = $this->actingAs($operador)->post(route('management.unidades.importar.store'), [
            'archivo' => $this->archivoXlsx(
                ['codigo', 'numero_nombre'],
                [
                    ['A-101', 'Primera'],
                    ['a-101', 'Repetida en el archivo'],
                    ['b-201', 'Ya existe en la Copropiedad'],
                ],
            ),
        ]);

        $respuesta->assertSessionHas('importacion_unidades_resultado');
        $resultado = session('importacion_unidades_resultado');
        $this->assertSame('parcial', $resultado['estado']);
        $this->assertSame(1, $resultado['creadas']);
        $this->assertSame(2, $resultado['duplicadas']);
        $this->assertSame(0, $resultado['invalidas']);
        $this->assertStringContainsString('repetido en el archivo', $resultado['errores'][0]['motivo']);
        $this->assertStringContainsString('Ya existe una unidad', $resultado['errores'][1]['motivo']);
        $this->assertDatabaseCount('unidades_privadas', 2);
    }

    public function test_mismo_codigo_en_otra_copropiedad_no_es_duplicado(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $operador = $this->operador($organizacion, $copropiedad);

        $otra = Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Otra', 'estado' => 'activa']);
        $this->crearUnidad($organizacion, $otra, 'X-1', 'De la otra Copropiedad');

        $this->actingAs($operador)->post(route('management.unidades.importar.store'), [
            'archivo' => $this->archivoXlsx(['codigo'], [['X-1']]),
        ])->assertRedirect(route('management.unidades.importar'));

        $this->assertDatabaseHas('unidades_privadas', [
            'codigo' => 'X-1',
            'copropiedad_id' => $copropiedad->id,
        ]);
        $this->assertDatabaseCount('unidades_privadas', 2);
    }

    public function test_solo_altas_no_modifica_unidades_existentes(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $operador = $this->operador($organizacion, $copropiedad);
        $this->crearUnidad($organizacion, $copropiedad, 'C-1', 'Nombre original');

        $respuesta = $this->actingAs($operador)->post(route('management.unidades.importar.store'), [
            'archivo' => $this->archivoXlsx(['codigo', 'numero_nombre'], [['C-1', 'Nombre pretendido']]),
        ]);

        $respuesta->assertSessionHas('importacion_unidades_resultado');
        $resultado = session('importacion_unidades_resultado');
        $this->assertSame(0, $resultado['creadas']);
        $this->assertSame(1, $resultado['duplicadas']);
        $this->assertDatabaseHas('unidades_privadas', ['codigo' => 'C-1', 'numero_nombre' => 'Nombre original']);
        $this->assertDatabaseCount('unidades_privadas', 1);
    }

    public function test_exceso_de_filas_rechaza_el_archivo_completo_sin_inserciones(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $operador = $this->operador($organizacion, $copropiedad);

        $filas = [];
        for ($i = 1; $i <= 1001; $i++) {
            $filas[] = ['F-'.$i];
        }

        $this->actingAs($operador)->post(route('management.unidades.importar.store'), [
            'archivo' => $this->archivoXlsx(['codigo'], $filas),
        ])->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('unidades_privadas', 0);
    }

    public function test_exceso_de_tamano_rechaza_el_archivo_sin_inserciones(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $operador = $this->operador($organizacion, $copropiedad);

        $this->actingAs($operador)->post(route('management.unidades.importar.store'), [
            'archivo' => UploadedFile::fake()->create('unidades.xlsx', 3000),
        ])->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('unidades_privadas', 0);
    }

    public function test_encabezados_invalidos_rechazan_el_archivo_sin_inserciones(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $operador = $this->operador($organizacion, $copropiedad);

        $this->actingAs($operador)->post(route('management.unidades.importar.store'), [
            'archivo' => $this->archivoXlsx(['foo', 'bar'], [['a', 'b']]),
        ])->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('unidades_privadas', 0);
    }

    public function test_sin_permiso_no_puede_importar(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $sinPermiso = $this->operador($organizacion, $copropiedad, []);

        $this->actingAs($sinPermiso)->get(route('management.unidades.importar'))->assertStatus(403);

        $this->actingAs($sinPermiso)->post(route('management.unidades.importar.store'), [
            'archivo' => $this->archivoXlsx(['codigo'], [['A-1']]),
        ])->assertStatus(403);

        $this->assertDatabaseCount('unidades_privadas', 0);
    }

    public function test_el_contexto_nunca_se_toma_del_archivo(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $operador = $this->operador($organizacion, $copropiedad);

        $otra = Copropiedad::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Otra', 'estado' => 'activa']);

        // El archivo incluye columnas desconocidas con identificadores ajenos:
        // se ignoran y el contexto se fija en backend.
        $this->actingAs($operador)->post(route('management.unidades.importar.store'), [
            'archivo' => $this->archivoXlsx(
                ['codigo', 'copropiedad_id', 'organizacion_id'],
                [['A-1', (string) $otra->id, (string) $organizacion->id]],
            ),
        ])->assertRedirect(route('management.unidades.importar'));

        $this->assertDatabaseHas('unidades_privadas', ['codigo' => 'A-1', 'copropiedad_id' => $copropiedad->id]);
    }

    public function test_la_importacion_queda_auditada_con_sus_totales(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $operador = $this->operador($organizacion, $copropiedad);

        $this->actingAs($operador)->post(route('management.unidades.importar.store'), [
            'archivo' => $this->archivoXlsx(['codigo'], [['A-1'], [str_repeat('Y', 60)]]),
        ]);

        $auditoria = AuditLog::query()
            ->where('action', 'residencia.unidad.importar')
            ->where('user_id', $operador->id)
            ->firstOrFail();
        $this->assertSame(2, $auditoria->metadata['leidas']);
        $this->assertSame(1, $auditoria->metadata['creadas']);
        $this->assertSame(1, $auditoria->metadata['invalidas']);
        $this->assertSame($copropiedad->id, $auditoria->metadata['contexto']['copropiedad_id']);
    }

    public function test_el_reporte_de_errores_es_descargable_en_xlsx(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $operador = $this->operador($organizacion, $copropiedad);

        $this->actingAs($operador)->post(route('management.unidades.importar.store'), [
            'archivo' => $this->archivoXlsx(['codigo'], [[str_repeat('Y', 60)]]),
        ]);

        $this->actingAs($operador)->get(route('management.unidades.importar.reporte'))
            ->assertOk()
            ->assertDownload();
    }

    public function test_sin_reporte_la_descarga_vuelve_a_importar(): void
    {
        [$organizacion, $copropiedad] = $this->createInstitutionalContext();
        $operador = $this->operador($organizacion, $copropiedad);

        $this->actingAs($operador)->get(route('management.unidades.importar.reporte'))
            ->assertRedirect(route('management.unidades.importar'));
    }

    private function operador($organizacion, $copropiedad, array $permisos = ['residentes.gestionar']): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->createContextualIdentity($user, $organizacion, $copropiedad, 'admin', $permisos);

        return $user;
    }

    private function crearUnidad($organizacion, $copropiedad, string $codigo, ?string $nombre = null): UnidadPrivada
    {
        $unidad = new UnidadPrivada(['codigo' => $codigo, 'numero_nombre' => $nombre]);
        $unidad->forceFill(['organizacion_id' => $organizacion->id, 'copropiedad_id' => $copropiedad->id]);
        $unidad->save();

        return $unidad;
    }

    private function archivoXlsx(array $encabezados, array $filas): UploadedFile
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray($encabezados, null, 'A1');
        $fila = 2;
        foreach ($filas as $filaDatos) {
            $sheet->fromArray(array_values($filaDatos), null, 'A'.$fila);
            $fila++;
        }

        $ruta = tempnam(sys_get_temp_dir(), 'importacion').'.xlsx';
        (new Xlsx($book))->save($ruta);
        $book->disconnectWorksheets();

        return new UploadedFile(
            $ruta,
            'unidades.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }
}
