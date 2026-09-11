<?php

namespace Database\Seeders;

use App\Models\Organizacion;
use App\Models\Persona;
use Illuminate\Database\Seeder;

class PersonaSeeder extends Seeder
{
    public function run(): void
    {
        $organizacion = Organizacion::where(
            'identificacion_tributaria',
            '901888777-6'
        )->firstOrFail();

        $personas = [
            [
                'tipo_persona' => 'natural',
                'nombre_razon_social' => 'Daniela Ramírez López',
                'identificacion' => '1000000001',
                'email' => 'daniela.ramirez@example.test',
                'telefono' => '3005550101',
                'estado' => 'activa',
            ],
            [
                'tipo_persona' => 'natural',
                'nombre_razon_social' => 'Carlos Andrés Gómez',
                'identificacion' => '1000000002',
                'email' => 'carlos.gomez@example.test',
                'telefono' => '3005550102',
                'estado' => 'activa',
            ],
            [
                'tipo_persona' => 'natural',
                'nombre_razon_social' => 'Laura Marcela Torres',
                'identificacion' => '1000000003',
                'email' => 'laura.torres@example.test',
                'telefono' => '3005550103',
                'estado' => 'activa',
            ],
            [
                'tipo_persona' => 'natural',
                'nombre_razon_social' => 'Andrés Felipe Mejía',
                'identificacion' => '1000000004',
                'email' => 'andres.mejia@example.test',
                'telefono' => '3005550104',
                'estado' => 'activa',
            ],
            [
                'tipo_persona' => 'natural',
                'nombre_razon_social' => 'Natalia Ruiz Castro',
                'identificacion' => '1000000005',
                'email' => 'natalia.ruiz@example.test',
                'telefono' => '3005550105',
                'estado' => 'activa',
            ],
        ];

        foreach ($personas as $datos) {
            $persona = Persona::firstOrNew([
                'organizacion_id' => $organizacion->id,
                'identificacion' => $datos['identificacion'],
            ]);

            $persona->organizacion_id = $organizacion->id;
            $persona->fill($datos);
            $persona->save();
        }
    }
}
