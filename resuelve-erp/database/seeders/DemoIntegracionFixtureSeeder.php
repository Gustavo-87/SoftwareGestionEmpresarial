<?php

namespace Database\Seeders;

use App\Domain\GestionDocumental\Enums\EstadoDocumentoVersionEnum;
use App\Domain\GestionDocumental\Enums\NivelAccesoDocumentoEnum;
use App\Models\Copropiedad;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\Mantenimiento;
use App\Models\MantenimientoDocumentoEvidencia;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\PqrActivity;
use App\Models\PqrReply;
use App\Models\TipoPqr;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fixture de demostración de la integración de módulos — Sprint 19.
 *
 * Crea datos reproducibles para la demostración del segundo corte con registros
 * reales por módulo principal (≥10 PQRS, ≥10 mantenimientos y ≥10 documentos),
 * sin tablas ni entidades artificiales:
 * - PQRS radicadas con tipos, estados, responsables y actuaciones reales;
 * - mantenimientos vinculados e independientes en todo el ciclo de estados,
 *   con resultado público/interno separado y finalización con evidencia;
 * - documentos activos con versiones aprobadas y niveles de acceso variados,
 *   vinculados como evidencia exacta de mantenimientos.
 *
 * OPT-IN ESTRICTO: requiere RESUELVE_DEMO_FIXTURE=1 además de entorno distinto
 * de production. No se registra en DatabaseSeeder ni se ejecuta en pruebas
 * automáticas, y debe ejecutarse únicamente contra una base aislada de
 * demostración (nunca contra la BD local de desarrollo).
 */
class DemoIntegracionFixtureSeeder extends Seeder
{
    public const PQR_MINIMAS = 12;

    public const MANTENIMIENTOS_MINIMOS = 12;

    public const DOCUMENTOS_MINIMOS = 12;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('El fixture de integración no puede crearse en el entorno production.');
        }

        if (env('RESUELVE_DEMO_FIXTURE') !== '1') {
            throw new RuntimeException(
                'Define RESUELVE_DEMO_FIXTURE=1 y usa una base aislada de demostración antes de ejecutar este fixture.'
            );
        }

        $organizacion = Organizacion::query()->firstOrFail();
        $copropiedad = Copropiedad::query()->firstOrFail();
        $gestor = User::query()->where('email', 'gestionpqrs7@gmail.com')->firstOrFail();
        $residentes = User::query()
            ->whereIn('email', ['residentepqrs@gmail.com', 'carlos.mejia@example.com', 'andrea.ruiz@example.com'])
            ->get();

        if ($residentes->count() < 2) {
            throw new RuntimeException('Ejecuta primero DatabaseSeeder en la base aislada: faltan usuarios de demostración.');
        }

        $this->crearPqrsYMantenimientos($organizacion, $copropiedad, $gestor, $residentes);
        $this->crearDocumentosYEvidencias($organizacion, $copropiedad, $gestor);
        $this->registrarResultadosYFinalizar($gestor);

        $this->command?->info(sprintf(
            'Fixture de integración: %d PQRS, %d mantenimientos, %d documentos.',
            Pqr::query()->count(),
            Mantenimiento::query()->count(),
            Documento::query()->count(),
        ));
    }

    private function crearPqrsYMantenimientos(Organizacion $organizacion, Copropiedad $copropiedad, User $gestor, $residentes): void
    {
        $tipos = TipoPqr::query()->pluck('id')->all();
        $pqrBase = [
            ['Fuga de agua en sótano 2', 'Se presenta una fuga continua en el estacionamiento del sótano 2 que afecta varios puestos.', 'radicada'],
            ['Ruido nocturno en torre A', 'Los residentes reportan ruido excesivo después de la medianoche en el apartamento 304.', 'en_revision'],
            ['Luminaria apagada en pasillo', 'El pasillo del cuarto piso permanece a oscuras desde hace varios días.', 'respondida'],
            ['Ascensor detenido entre pisos', 'El ascensor de la torre B quedó detenido entre el tercero y el cuarto piso.', 'en_revision'],
            ['Basurero desbordado en zona común', 'El área de basuras de la zona común se encuentra desbordada.', 'cerrada'],
            ['Goteo en tanque de agua', 'El tanque de agua de la azotea presenta goteo en la base.', 'radicada'],
            ['Puerta del gimnasio averiada', 'La puerta corrediza del gimnasio no cierra correctamente.', 'en_revision'],
            ['Humedad en muro de la terraza', 'Aparece humedad en el muro contiguo a la terraza comunal.', 'radicada'],
            ['Iluminación intermitente en parqueadero', 'Las luces del parqueadero parpadean de forma intermitente.', 'respondida'],
            ['Pintura descarapelada en lobby', 'La pintura del lobby se está descarapelando en varios sectores.', 'cerrada'],
            ['Falla en cerradura de portería', 'La cerradura electrónica de portería falla con frecuencia.', 'en_revision'],
            ['Ruido de bomba de agua', 'La bomba de agua genera ruido constante en el cuarto técnico.', 'radicada'],
        ];

        $estadosMantenimiento = ['pendiente', 'en_proceso', 'finalizado'];
        $pqrsCreadas = collect();
        $mantenimientosCreados = collect();

        foreach ($pqrBase as $indice => [$asunto, $descripcion, $estado]) {
            $radicador = $residentes[$indice % $residentes->count()];
            $pqr = Pqr::factory()->paraContexto($organizacion, $copropiedad)->create([
                'asunto' => $asunto,
                'descripcion' => $descripcion,
                'estado' => $estado,
                'user_id' => $radicador->id,
                'assigned_to_id' => $gestor->id,
                'tipo_pqr_id' => $tipos[$indice % count($tipos)] ?? null,
                'fecha_radicacion' => now()->subDays(30 - $indice),
            ]);
            $pqrsCreadas->push($pqr);

            PqrActivity::create([
                'pqr_id' => $pqr->id,
                'user_id' => $radicador->id,
                'action' => 'created',
                'description' => 'Radica la solicitud «'.$asunto.'».',
            ]);

            if (in_array($estado, ['respondida', 'cerrada'], true)) {
                PqrReply::create([
                    'pqr_id' => $pqr->id,
                    'user_id' => $gestor->id,
                    'body' => 'Damos respuesta a su solicitud «'.$asunto.'». La gestión correspondiente fue programada con el área de mantenimiento.',
                    'is_draft' => false,
                    'sent_at' => now()->subDays(5),
                ]);
            }

            // Dos mantenimientos por cada tres PQRS: uno vinculado desde el
            // expediente y trabajos legítimos adicionales con clave explícita.
            if ($indice % 3 !== 2) {
                $mantenimiento = Mantenimiento::create([
                    'organizacion_id' => $organizacion->id,
                    'copropiedad_id' => $copropiedad->id,
                    'pqr_id' => $pqr->id,
                    'solicitante_id' => $radicador->id,
                    'responsable_id' => $gestor->id,
                    'titulo' => 'Atender: '.$asunto,
                    'descripcion' => 'Intervención derivada de la PQRS PQR-'.str_pad($pqr->id, 4, '0', STR_PAD_LEFT).'.',
                    'fecha_programada' => now()->addDays(($indice % 5) + 1),
                    'estado' => $estadosMantenimiento[$indice % 3],
                    'idempotency_key' => 'demo-integracion-'.($indice + 1),
                ]);
                $mantenimientosCreados->push($mantenimiento);
            }
        }

        // Mantenimientos independientes (sin PQRS de origen) para demostrar la
        // vinculación posterior y los trabajos planificados.
        foreach (range(1, 4) as $numero) {
            $mantenimientosCreados->push(Mantenimiento::create([
                'organizacion_id' => $organizacion->id,
                'copropiedad_id' => $copropiedad->id,
                'solicitante_id' => $gestor->id,
                'responsable_id' => $gestor->id,
                'titulo' => 'Mantenimiento preventivo #'.$numero,
                'descripcion' => 'Plan preventivo trimestral de instalaciones (jornada '.$numero.').',
                'fecha_programada' => now()->addDays($numero * 3),
                'estado' => 'pendiente',
                'idempotency_key' => 'demo-preventivo-'.$numero,
            ]));
        }
    }

    private function registrarResultadosYFinalizar(User $gestor): void
    {
        // Resultados y finalizaciones reales sobre algunos mantenimientos (tras
        // vincular su evidencia: la finalización válida exige evidencia).
        foreach (Mantenimiento::query()->whereNotNull('pqr_id')->orderBy('id')->take(6)->get() as $indice => $mantenimiento) {
            $mantenimiento->update([
                'resultado_publico' => 'La intervención fue completada y el área quedó en condiciones normales.',
                'resultado_interno' => 'Se repusieron elementos del inventario técnico del taller.',
                'resultado_registrado_por_user_id' => $gestor->id,
                'resultado_registrado_at' => now()->subDays(2),
            ]);
            if ($indice < 3) {
                $mantenimiento->update(['estado' => 'finalizado', 'finalizado_at' => now()->subDay()]);
            }
        }
    }

    private function crearDocumentosYEvidencias(Organizacion $organizacion, Copropiedad $copropiedad, User $gestor): void
    {
        $documentosBase = [
            ['Informe de inspección de ascensores', NivelAccesoDocumentoEnum::COMUNIDAD],
            ['Protocolo de mantenimiento preventivo', NivelAccesoDocumentoEnum::COMUNIDAD],
            ['Cotización de repuestos para bombas', NivelAccesoDocumentoEnum::INTERNO],
            ['Acta de visita técnica de humedades', NivelAccesoDocumentoEnum::INTERNO],
            ['Informe financiero mensual', NivelAccesoDocumentoEnum::ADMINISTRATIVO],
            ['Manual del sistema de vigilancia', NivelAccesoDocumentoEnum::INTERNO],
            ['Registro de inspección eléctrica', NivelAccesoDocumentoEnum::COMUNIDAD],
            ['Política de gestión documental', NivelAccesoDocumentoEnum::COMUNIDAD],
            ['Informe de calidad del agua', NivelAccesoDocumentoEnum::COMUNIDAD],
            ['Contrato de servicio de ascensores', NivelAccesoDocumentoEnum::ADMINISTRATIVO],
            ['Plan de contingencia del conjunto', NivelAccesoDocumentoEnum::INTERNO],
            ['Informe de terminación de obra menor', NivelAccesoDocumentoEnum::COMUNIDAD],
        ];

        $documentos = collect();
        foreach ($documentosBase as $indice => [$titulo, $nivel]) {
            $documento = Documento::factory()->paraContexto($organizacion, $copropiedad)->create([
                'titulo' => $titulo,
                'nivel_acceso' => $nivel,
                'propietario_documental_user_id' => $gestor->id,
            ]);
            DocumentoVersion::factory()->create([
                'documento_id' => $documento->id,
                'estado' => EstadoDocumentoVersionEnum::APROBADA,
            ]);
            $documentos->push($documento);
        }

        // Evidencia exacta en mantenimientos: versiones aprobadas, incluida una
        // restringida (nivel administrativo) que no debe filtrar metadatos.
        $mantenimientos = Mantenimiento::query()->whereNotNull('pqr_id')->take(6)->get();
        foreach ($mantenimientos as $indice => $mantenimiento) {
            $documento = $documentos[$indice % $documentos->count()];
            $version = $documento->versiones()->firstOrFail();
            MantenimientoDocumentoEvidencia::create([
                'mantenimiento_id' => $mantenimiento->id,
                'documento_id' => $documento->id,
                'documento_version_id' => $version->id,
                'vinculado_por_user_id' => $gestor->id,
            ]);
        }
    }
}
