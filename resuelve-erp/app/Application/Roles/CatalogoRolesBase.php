<?php

namespace App\Application\Roles;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Catálogo inicial de Roles (HU-USR-09, Sprint 18).
 *
 * Los Roles base del catálogo inicial son los Roles globales
 * (`copropiedad_id` nulo) cuyo nombre coincide con una clave del catálogo
 * creado por `resuelve:crear-identidad-contextual-inicial` y migrado a Spatie
 * por `resuelve:migrar-rbac-spatie`.
 *
 * La comparación de nombres usa normalización canónica (recorte + minúsculas
 * Unicode) para ser consistente con las colaciones `*_ci` de MySQL: `Admin`,
 * `ADMIN` y `admin` designan el mismo Rol base aunque PHP compare cadenas con
 * sensibilidad a mayúsculas (y SQLite, en las pruebas, también).
 */
final class CatalogoRolesBase
{
    /** Claves del catálogo inicial de Roles. */
    public const CLAVES = ['admin', 'gestor', 'apoyo', 'auditor', 'residente'];

    /** Clave del Rol base inmutable (matriz y nombre). */
    public const CLAVE_ADMIN = 'admin';

    /** Normalización canónica de nombres de Rol (consistente con colación *_ci). */
    public static function normalizar(string $nombre): string
    {
        return mb_strtolower(trim($nombre), 'UTF-8');
    }

    /** ¿El Rol es un Rol base del catálogo inicial? */
    public static function esBase(SpatieRole $rol): bool
    {
        return $rol->copropiedad_id === null
            && in_array(self::normalizar($rol->name), self::CLAVES, true);
    }

    /** ¿El Rol es el Rol base `admin` (inmutable)? */
    public static function esAdminBase(SpatieRole $rol): bool
    {
        return $rol->copropiedad_id === null
            && self::normalizar($rol->name) === self::CLAVE_ADMIN;
    }
}
