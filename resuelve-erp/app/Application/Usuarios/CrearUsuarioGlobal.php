<?php

namespace App\Application\Usuarios;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

final class CrearUsuarioGlobal
{
    public function ejecutar(
        string $nombre,
        string $email,
        string $password,
        ?string $tower = null,
        ?string $unit = null,
        bool $esAdministradorSistema = false,
    ): User {
        return DB::transaction(function () use (
            $nombre,
            $email,
            $password,
            $tower,
            $unit,
            $esAdministradorSistema,
        ) {
            if (blank($nombre)) {
                throw new InvalidArgumentException('El nombre del usuario es obligatorio.');
            }

            if (blank($email)) {
                throw new InvalidArgumentException('El email del usuario es obligatorio.');
            }

            $existe = User::where('email', strtolower($email))->exists();
            if ($existe) {
                throw new InvalidArgumentException("Ya existe un usuario con el email '{$email}'.");
            }

            if (strlen($password) < 8) {
                throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
            }

            $user = User::create([
                'name' => $nombre,
                'email' => strtolower($email),
                'password' => Hash::make($password),
                'role' => 'residente',
                'tower' => $tower,
                'unit' => $unit,
                'email_verified_at' => now(),
                'es_administrador_sistema' => $esAdministradorSistema,
                'estado' => 'activo',
            ]);

            return $user;
        });
    }
}
