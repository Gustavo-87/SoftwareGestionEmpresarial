<?php

namespace App\Application\Roles;

use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoOperativo;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Autorización de la administración del catálogo de Roles (Sprint 16, B1).
 *
 * Autoridad de plataforma (`es_administrador_sistema`): gestiona el catálogo
 * global y supervisa los Roles de cualquier Copropiedad.
 * Autoridad contextual (`roles.gestionar`): gestiona únicamente los Roles
 * personalizados de la Copropiedad activa y solo puede conceder permisos que
 * ya posee (anti-escalada).
 */
final class AutorizacionGestionRoles
{
    public const GUARD = 'web';

    public function esAutoridadPlataforma(User $operador): bool
    {
        return $operador->esAdministradorSistema();
    }

    public function autorizarGestion(User $operador, ?ContextoOperativo $contexto, ?SpatieRole $rol = null): void
    {
        if ($this->esAutoridadPlataforma($operador)) {
            return;
        }

        if ($contexto === null || ! $contexto->tieneMembresiaContextual()) {
            throw new AuthorizationException('Se requiere una Membresía vigente para gestionar Roles.');
        }

        if (! app(AutorizacionContextual::class)->tienePermiso($contexto, 'roles.gestionar')) {
            throw new AuthorizationException('No tienes permiso para gestionar Roles.');
        }

        if ($rol !== null && (int) $rol->copropiedad_id !== (int) $contexto->copropiedad->id) {
            throw new AuthorizationException('El Rol pertenece a otro contexto.');
        }
    }

    /**
     * Claves de permiso que el operador puede conceder.
     * `null` indica conjunto sin límite (autoridad de plataforma).
     * Cada flujo autoriza antes su propia capacidad; aquí solo se exige
     * contexto con Membresía vigente para conocer el conjunto efectivo.
     *
     * @return list<string>|null
     */
    public function permisosOtorgables(User $operador, ?ContextoOperativo $contexto): ?array
    {
        if ($this->esAutoridadPlataforma($operador)) {
            return null;
        }

        if ($contexto === null || ! $contexto->tieneMembresiaContextual()) {
            throw new AuthorizationException('Se requiere una Membresía vigente para gestionar Roles.');
        }

        return array_values($contexto->clavesPermisos());
    }

    /** @param list<string> $claves */
    public function autorizarPermisosOtorgables(User $operador, ?ContextoOperativo $contexto, array $claves): void
    {
        $otorgables = $this->permisosOtorgables($operador, $contexto);
        if ($otorgables === null) {
            return;
        }

        $noAutorizados = array_values(array_diff($claves, $otorgables));
        if ($noAutorizados !== []) {
            throw new AuthorizationException(
                'No puedes conceder permisos que no posees: '.implode(', ', $noAutorizados).'.'
            );
        }
    }
}
