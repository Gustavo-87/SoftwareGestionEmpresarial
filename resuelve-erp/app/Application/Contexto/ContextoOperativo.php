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
     */
    public function __construct(
        public ?User $usuario,
        public Organizacion $organizacion,
        public Copropiedad $copropiedad,
        public ?MembresiaCopropiedad $membresiaCopropiedad,
        public array $roles,
        public array $permisos,
        public string $identificadorCorrelacion,
    ) {}

    public function tieneMembresiaContextual(): bool
    {
        return $this->membresiaCopropiedad !== null;
    }

    /** @return list<string> */
    public function clavesRoles(): array
    {
        return array_values(array_map(fn (Rol $rol) => $rol->clave, $this->roles));
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
