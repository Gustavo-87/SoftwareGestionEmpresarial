<?php

namespace Database\Seeders;

use App\Models\Organizacion;
use Illuminate\Database\Seeder;

class OrganizacionSeeder extends Seeder
{
    public function run(): void
    {
        $organizaciones = [
            [
                'nombre' => 'Gestión Urbana de Copropiedades S.A.S.',
                'identificacion_tributaria' => '901888777-6',
                'email' => 'contacto@gestionurbana.test',
                'telefono' => '6065550100',
                'estado' => 'activa',
            ],
            [
                'nombre' => 'Administraciones del Eje S.A.S.',
                'identificacion_tributaria' => '901100001-1',
                'email' => 'contacto@admineje.test',
                'telefono' => '6065550101',
                'estado' => 'activa',
            ],
            [
                'nombre' => 'Soluciones Residenciales S.A.S.',
                'identificacion_tributaria' => '901100002-2',
                'email' => 'contacto@solucionesresidenciales.test',
                'telefono' => '6065550102',
                'estado' => 'activa',
            ],
            [
                'nombre' => 'Gestión PH Integral S.A.S.',
                'identificacion_tributaria' => '901100003-3',
                'email' => 'contacto@gestionph.test',
                'telefono' => '6065550103',
                'estado' => 'activa',
            ],
            [
                'nombre' => 'Administración y Comunidad S.A.S.',
                'identificacion_tributaria' => '901100004-4',
                'email' => 'contacto@admincomunidad.test',
                'telefono' => '6065550104',
                'estado' => 'activa',
            ],
        ];

        foreach ($organizaciones as $organizacion) {
            Organizacion::updateOrCreate(
                ['identificacion_tributaria' => $organizacion['identificacion_tributaria']],
                $organizacion
            );
        }
    }
}
