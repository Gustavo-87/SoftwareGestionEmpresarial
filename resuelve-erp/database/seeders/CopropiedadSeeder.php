<?php

namespace Database\Seeders;

use App\Models\Copropiedad;
use App\Models\Organizacion;
use Illuminate\Database\Seeder;

class CopropiedadSeeder extends Seeder
{
    public function run(): void
    {
        $organizacion = Organizacion::where(
            'identificacion_tributaria',
            '901888777-6'
        )->firstOrFail();

        $copropiedades = [
            [
                'nombre' => 'Conjunto Residencial Altos del Parque',
                'nit' => '901200001-1',
                'representante_legal' => 'Mariana Torres',
                'direccion' => 'Avenida del Parque # 18-45',
                'ciudad' => 'Pereira',
                'telefono' => '6065550201',
                'email' => 'administracion@altosdelparque.test',
                'estado' => 'activa',
            ],
            [
                'nombre' => 'Conjunto Residencial Bosques del Café',
                'nit' => '901200002-2',
                'representante_legal' => 'Carlos Ramírez',
                'direccion' => 'Carrera 12 # 25-40',
                'ciudad' => 'Pereira',
                'telefono' => '6065550202',
                'email' => 'administracion@bosquesdelcafe.test',
                'estado' => 'activa',
            ],
            [
                'nombre' => 'Unidad Residencial Mirador del Otún',
                'nit' => '901200003-3',
                'representante_legal' => 'Laura Gómez',
                'direccion' => 'Calle 30 # 14-25',
                'ciudad' => 'Pereira',
                'telefono' => '6065550203',
                'email' => 'administracion@miradordelotun.test',
                'estado' => 'activa',
            ],
            [
                'nombre' => 'Conjunto Residencial Senderos de Cerritos',
                'nit' => '901200004-4',
                'representante_legal' => 'Andrés Mejía',
                'direccion' => 'Vía Cerritos km 6',
                'ciudad' => 'Pereira',
                'telefono' => '6065550204',
                'email' => 'administracion@senderoscerritos.test',
                'estado' => 'activa',
            ],
            [
                'nombre' => 'Edificio Reserva Central',
                'nit' => '901200005-5',
                'representante_legal' => 'Daniela Ruiz',
                'direccion' => 'Carrera 7 # 19-32',
                'ciudad' => 'Pereira',
                'telefono' => '6065550205',
                'email' => 'administracion@reservacentral.test',
                'estado' => 'activa',
            ],
        ];

        foreach ($copropiedades as $copropiedad) {
            Copropiedad::updateOrCreate(
                [
                    'organizacion_id' => $organizacion->id,
                    'nit' => $copropiedad['nit'],
                ],
                array_merge(
                    $copropiedad,
                    ['organizacion_id' => $organizacion->id]
                )
            );
        }
    }
}
