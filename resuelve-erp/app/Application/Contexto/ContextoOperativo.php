<?php

namespace App\Application\Contexto;

use App\Models\Copropiedad;
use App\Models\MembresiaCopropiedad;
use App\Models\Organizacion;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;

final readonly class ContextoOperativo
{
    /**
     * @param  list<Rol>  $roles
     * @param  list<Permiso>  $permisos
     * @param  list<Rol>  $rolesAsignados
     */
    public function __construct(
        public ?User $usuario,
        public Organizacion $organizacion,
        public Copropiedad $copropiedad,
        public ?MembresiaCopropiedad $membresiaCopropiedad,
        public array $roles,
        public array $permisos,
        public string $identificadorCorrelacion,
        public ?string $rolActivoClave = null,
        public array $rolesAsignados = [],
    ) {}

    public function tieneMembresiaContextual(): bool
    {
        return $this->membresiaCopropiedad !== null;
    }

    public function tieneRolActivo(): bool
    {
        return $this->rolActivoClave !== null;
    }

    /** @return list<string> */
    public function clavesRoles(): array
    {
        return array_values(array_map(fn (Rol $rol) => $rol->clave, $this->roles));
    }

    /** Claves de todos los Roles asignados, con o sin Rol activo seleccionado. */
    public function clavesRolesAsignados(): array
    {
        return array_values(array_map(fn (Rol $rol) => $rol->clave, $this->rolesAsignados));
    }

    /** @return list<string> */
    public function clavesPermisos(): array
    {
        return array_values(array_map(fn (Permiso $permiso) => $permiso->clave, $this->permisos));
    }

    public function rolHeredadoCoincideParaDiagnostico(): ?bool
    {
        if ($this->usuario === null || $this->membresiaCopropiedad === null) {
            return null;
        }

        return in_array($this->usuario->role, $this->clavesRoles(), true);
    }
}
