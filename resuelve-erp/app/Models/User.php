<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'role', 'password', 'tower', 'unit', 'email_verified_at', 'estado', 'desactivado_at', 'es_administrador_sistema'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'es_administrador_sistema' => 'boolean',
            'desactivado_at' => 'datetime',
        ];
    }

    public function pqrs()
    {
        return $this->hasMany(Pqr::class);
    }

    public function assignedPqrs()
    {
        return $this->hasMany(Pqr::class, 'assigned_to_id');
    }

    public function pqrCommunicationOperations(): HasMany
    {
        return $this->hasMany(PqrCommunicationOperation::class, 'actor_id');
    }

    public function canViewAllPqrs(): bool
    {
        return in_array($this->role, ['admin', 'gestor', 'auditor', 'apoyo'], true);
    }

    public function canManagePqrs(): bool
    {
        return in_array($this->role, ['admin', 'gestor', 'apoyo'], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function esAdministradorSistema(): bool
    {
        return $this->es_administrador_sistema === true;
    }

    public function membresiasOrganizacion(): HasMany
    {
        return $this->hasMany(MembresiaOrganizacion::class, 'usuario_id');
    }

    public function membresiasCopropiedad(): HasMany
    {
        return $this->hasMany(MembresiaCopropiedad::class, 'usuario_id');
    }

    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class, 'usuario_id');
    }
}
