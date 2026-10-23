<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class DrogueriaRolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $matriz = [
            'admin' => [
                'ver-productos', 'crear-productos',
                'editar-productos', 'eliminar-productos',
                'ver-compras', 'crear-compras', 'editar-compras',
                'ver-ventas', 'ver-inventario',
                'ver-reportes', 'gestionar-usuarios',
            ],
            'regente' => [
                'ver-productos', 'ver-compras',
                'recibir-lotes', 'ver-inventario',
                'ver-ventas', 'crear-ventas',
                'dispensar-control-especial',
            ],
            'cajero' => [
                'ver-productos', 'ver-inventario',
                'ver-ventas', 'crear-ventas',
            ],
            'auxiliar-bodega' => [
                'ver-productos', 'ver-compras',
                'ver-inventario', 'ubicar-mercancia',
            ],
        ];

        foreach ($matriz as $rol => $permisos) {
            foreach ($permisos as $permiso) {
                Permission::findOrCreate($permiso, 'web');
            }

            Role::findOrCreate($rol, 'web')
                ->syncPermissions($permisos);
        }
    }
}
