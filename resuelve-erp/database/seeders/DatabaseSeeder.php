<?php

namespace Database\Seeders;

use App\Application\Contexto\ContextResolver;
use App\Application\Pqrs\ConsultaPqrsContextuales;
use App\Models\Organizacion;
use App\Models\Pqr;
use App\Models\SiteSetting;
use App\Models\TipoPqr;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'Los usuarios de demostración no pueden crearse en el entorno production.'
            );
        }

        $demoPassword = config('resuelve.demo_password');

        if (! is_string($demoPassword) || strlen($demoPassword) < 12) {
            throw new RuntimeException(
                'Define RESUELVE_DEMO_PASSWORD con al menos 12 caracteres antes de ejecutar el seeder.'
            );
        }

        $siteSetting = SiteSetting::firstOrCreate([], SiteSetting::defaults());

        $organizacion = Organizacion::firstOrCreate(
            ['nombre' => 'Gestión Urbana de Copropiedades S.A.S.'],
            [
                'identificacion_tributaria' => '901888777-6',
                'email' => 'contacto@gestionurbana.test',
                'telefono' => '6065550100',
                'estado' => 'activa',
            ]
        );
        $siteSetting->forceFill([
            'nombre_conjunto' => 'Conjunto Residencial Altos del Parque',
            'nit' => '901999999-9',
            'representante_legal' => 'Mariana Torres',
            'direccion' => 'Avenida del Parque # 18-45',
            'ciudad' => 'Pereira',
            'telefono' => '6065550187',
            'email' => 'administracion@altosdelparque.test',
            'color_principal' => '#1e3a5f',
            'dias_respuesta' => 15,
            'organizacion_id' => $organizacion->id,
        ])->save();

        if ($siteSetting->organizacion_id === null || $siteSetting->copropiedad_id === null) {
            $exitCode = Artisan::call('resuelve:crear-contexto-inicial');
            if ($exitCode !== 0) {
                throw new RuntimeException('No fue posible crear el contexto inicial para los datos de demostración.');
            }
            $siteSetting->refresh();
        }
        $contexto = app(ContextResolver::class)->resolverExplicito(
            $siteSetting->organizacion_id,
            $siteSetting->copropiedad_id
        );
        $consultaPqrs = app(ConsultaPqrsContextuales::class);

        $user = User::updateOrCreate([
            'email' => 'gestionpqrs7@gmail.com',
        ], [
            'name' => 'Gestión PQRS',
            'role' => 'admin',
            'email_verified_at' => now(),
            'password' => $demoPassword,
        ]);

        $resident = User::updateOrCreate([
            'email' => 'residentepqrs@gmail.com',
        ], [
            'name' => 'Laura Gómez',
            'role' => 'residente',
            'tower' => 'B',
            'unit' => '204',
            'email_verified_at' => now(),
            'password' => $demoPassword,
        ]);

        $residentTwo = User::updateOrCreate(['email' => 'carlos.mejia@example.com'], [
            'name' => 'Carlos Mejía', 'role' => 'residente', 'tower' => 'A', 'unit' => '105',
            'email_verified_at' => now(), 'password' => $demoPassword,
        ]);
        $residentThree = User::updateOrCreate(['email' => 'andrea.ruiz@example.com'], [
            'name' => 'Andrea Ruiz', 'role' => 'residente', 'tower' => 'C', 'unit' => '302',

            'email_verified_at' => now(), 'password' => $demoPassword,
        ]);

        if (Artisan::call('resuelve:crear-identidad-contextual-inicial') !== 0) {
            throw new RuntimeException('No fue posible sincronizar la identidad contextual de los usuarios de demostración.');
        }

        $tipos = collect([
            ['nombre' => 'Petición', 'descripcion' => 'Solicitud formal presentada por un residente.'],
            ['nombre' => 'Queja', 'descripcion' => 'Manifestación de inconformidad frente a una situación.'],
            ['nombre' => 'Reclamo', 'descripcion' => 'Solicitud relacionada con una posible afectación o incumplimiento.'],
            ['nombre' => 'Sugerencia', 'descripcion' => 'Propuesta de mejora para la administración del conjunto.'],
            ['nombre' => 'Solicitud', 'descripcion' => 'Requerimiento general de información o trámite.'],
        ])->map(function ($tipo) {
            return TipoPqr::firstOrCreate(['nombre' => $tipo['nombre']], $tipo);
        });

        $tipos = $tipos->keyBy('nombre');

        // Retira únicamente el lote ficticio original generado por Faker.
        $legacyDemoIds = $consultaPqrs->para($contexto)->whereBetween('id', [1, 30])
            ->whereHas('user', fn ($query) => $query->where('email', 'admin@pqrs.com'))
            ->pluck('id');
        Pqr::destroy($legacyDemoIds);

        $cases = [
            ['asunto' => 'Luminaria apagada en el pasillo de la torre B', 'descripcion' => 'La lámpara del segundo piso no enciende desde hace tres noches y el pasillo queda oscuro.', 'tipo' => 'Reclamo', 'estado' => 'radicada', 'resident' => $resident, 'radicada' => -2, 'limite' => 2, 'month' => 0],
            ['asunto' => 'Ruido recurrente después de las 11 p. m.', 'descripcion' => 'Se presentan reuniones con música alta los fines de semana en el apartamento A-304.', 'tipo' => 'Queja', 'estado' => 'en_revision', 'resident' => $residentTwo, 'radicada' => -7, 'limite' => 1, 'month' => 0],
            ['asunto' => 'Certificado de paz y salvo para trámite bancario', 'descripcion' => 'Solicito el certificado de paz y salvo correspondiente al apartamento C-302.', 'tipo' => 'Petición', 'estado' => 'respondida', 'resident' => $residentThree, 'radicada' => -12, 'limite' => 3, 'month' => 0],
            ['asunto' => 'Humedad en el muro del parqueadero 18', 'descripcion' => 'Después de las lluvias aparece una filtración que alcanza el espacio de estacionamiento.', 'tipo' => 'Reclamo', 'estado' => 'en_revision', 'resident' => $resident, 'radicada' => -18, 'limite' => -3, 'month' => 1],
            ['asunto' => 'Instalación de bicicletero cubierto', 'descripcion' => 'Propongo adecuar un espacio cubierto para bicicletas junto al salón comunal.', 'tipo' => 'Sugerencia', 'estado' => 'radicada', 'resident' => $residentTwo, 'radicada' => -25, 'limite' => 8, 'month' => 1],
            ['asunto' => 'Actualización de datos para ingreso de visitante', 'descripcion' => 'Requiero registrar a una persona autorizada para acompañar a un adulto mayor.', 'tipo' => 'Solicitud', 'estado' => 'cerrada', 'resident' => $residentThree, 'radicada' => -32, 'limite' => -20, 'month' => 2],
            ['asunto' => 'Intermitencia en el citófono del apartamento B-204', 'descripcion' => 'El citófono recibe llamadas pero no permite abrir la puerta desde el apartamento.', 'tipo' => 'Reclamo', 'estado' => 'respondida', 'resident' => $resident, 'radicada' => -48, 'limite' => -34, 'month' => 2],
            ['asunto' => 'Revisión de árboles junto a la zona infantil', 'descripcion' => 'Una rama seca está inclinada hacia los juegos y requiere revisión preventiva.', 'tipo' => 'Petición', 'estado' => 'en_revision', 'resident' => $residentTwo, 'radicada' => -64, 'limite' => 6, 'month' => 3],
            ['asunto' => 'Mejora de señalización para reciclaje', 'descripcion' => 'Sugiero instalar avisos claros para separar residuos aprovechables y ordinarios.', 'tipo' => 'Sugerencia', 'estado' => 'cerrada', 'resident' => $residentThree, 'radicada' => -80, 'limite' => -65, 'month' => 3],
            ['asunto' => 'Cobro duplicado de parqueadero en la factura', 'descripcion' => 'La cuota de parqueadero aparece dos veces en el estado de cuenta del mes.', 'tipo' => 'Reclamo', 'estado' => 'respondida', 'resident' => $resident, 'radicada' => -105, 'limite' => -90, 'month' => 4],
            ['asunto' => 'Reserva del salón comunal para reunión familiar', 'descripcion' => 'Solicito disponibilidad y requisitos para reservar el salón el próximo sábado.', 'tipo' => 'Solicitud', 'estado' => 'radicada', 'resident' => $residentTwo, 'radicada' => -130, 'limite' => 12, 'month' => 4],
            ['asunto' => 'Reparación de fuga en zona de lavado común', 'descripcion' => 'Se observa goteo constante en la llave principal y acumulación de agua en el piso.', 'tipo' => 'Queja', 'estado' => 'cerrada', 'resident' => $residentThree, 'radicada' => -155, 'limite' => -140, 'month' => 5],
        ];

        foreach ($cases as $case) {
            $pqr = $consultaPqrs->para($contexto)
                ->where('asunto', $case['asunto'])
                ->first() ?? new Pqr;
            $pqr->fill([
                'asunto' => $case['asunto'],
                'descripcion' => $case['descripcion'],
                'fecha_radicacion' => today()->addDays($case['radicada']),
                'fecha_limite_respuesta' => today()->addDays($case['limite']),
                'estado' => $case['estado'],
                'user_id' => $case['resident']->id,
                'assigned_to_id' => $user->id,
                'tipo_pqr_id' => $tipos[$case['tipo']]->id,
            ]);
            $pqr->organizacion()->associate($contexto->organizacion);
            $pqr->copropiedad()->associate($contexto->copropiedad);
            $pqr->save();
            $createdAt = now()->subMonths($case['month'])->subDays(($pqr->id % 18) + 1);
            $pqr->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
        }
        $this->call([
            OrganizacionSeeder::class,
            CopropiedadSeeder::class,
            PersonaSeeder::class,
            ]);
    }
}
