<?php

namespace App\Application\Roles;

use Spatie\Permission\Models\Permission;

/**
 * Catálogo inicial de Permisos (HU-USR-07, Sprint 18).
 *
 * Los permisos base son los creados por
 * `resuelve:crear-identidad-contextual-inicial` (22 claves) más los que
 * incorporó la administración dinámica de roles (ADR-012). Son necesarios para
 * las funcionalidades del sistema y quedan protegidos contra eliminación
 * lógica (decisión del Product Owner, 20 de octubre de 2026).
 *
 * La comparación de claves usa normalización canónica consistente con las
 * colaciones `*_ci` de MySQL, igual que `CatalogoRolesBase`.
 */
final class CatalogoPermisosBase
{
    public const CLAVES = [
        'mantenimiento.crear',
        'mantenimiento.ver_propias',
        'mantenimiento.ver_todas',
        'mantenimiento.gestionar',
        'pqrs.listar',
        'pqrs.crear',
        'pqrs.ver_propias',
        'pqrs.ver_todas',
        'pqrs.gestionar',
        'pqrs.eliminar',
        'pqrs.gestionar_asignadas',
        'pqrs.ver_borradores',
        'roles.gestionar',
        'informes.exportar',
        'configuracion.gestionar',
        'gestion.carga_ver',
        'gestion.herramientas_gestionar',
        'usuarios.gestionar',
        'residentes.gestionar',
        'auditoria.ver',
        'notificaciones.consultar',
        'documentos.consultar',
        'documentos.gestionar',
        'documentos.aprobar',
        'documentos.archivar',
    ];

    /** Normalización canónica de claves de Permiso (consistente con colación *_ci). */
    public static function normalizar(string $clave): string
    {
        return mb_strtolower(trim($clave), 'UTF-8');
    }

    /** ¿El Permiso pertenece al catálogo base protegido? */
    public static function esBase(Permission $permiso): bool
    {
        return in_array(self::normalizar((string) $permiso->name), self::CLAVES, true);
    }
}
