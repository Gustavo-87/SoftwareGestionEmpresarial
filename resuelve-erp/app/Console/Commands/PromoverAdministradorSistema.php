<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;

class PromoverAdministradorSistema extends Command
{
    protected $signature = 'resuelve:promover-admin-sistema {email : Correo electrónico del usuario}';

    protected $description = 'Asigna es_administrador_sistema=true a un usuario existente';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));

        $usuario = User::query()->where('email', $email)->first();

        if ($usuario === null) {
            $this->error("No existe un usuario con el correo '{$email}'.");

            return self::FAILURE;
        }

        if ($usuario->es_administrador_sistema) {
            $this->warn("El usuario '{$usuario->name}' ({$email}) ya es administrador del sistema.");

            return self::SUCCESS;
        }

        $usuario->es_administrador_sistema = true;
        $usuario->save();

        AuditLog::create([
            'user_id' => $usuario->id,
            'action' => 'system_admin.promoted',
            'auditable_type' => 'user',
            'auditable_id' => $usuario->id,
            'ip_address' => null,
            'metadata' => [
                'email' => $email,
                'metodo' => 'comando artisan',
            ],
        ]);

        $this->info("✓ Usuario '{$usuario->name}' ({$email}) promovido a administrador del sistema.");

        return self::SUCCESS;
    }
}
