<?php

namespace App\Console\Commands;

use App\Application\Autorizacion\VerificadorEquivalenciaAutorizacionContextual;
use Illuminate\Console\Command;
use Throwable;

class VerificarEquivalenciaAutorizacionContextual extends Command
{
    protected $signature = 'resuelve:verificar-equivalencia-autorizacion-contextual';
    protected $description = 'Verifica que users.role y la autorización contextual sean equivalentes antes de activarla';

    public function handle(VerificadorEquivalenciaAutorizacionContextual $verificador): int
    {
        try { $divergencias = $verificador->divergencias(); } catch (Throwable $exception) {
            $this->error($exception->getMessage()); return self::FAILURE;
        }
        if ($divergencias !== []) {
            $this->error('No se activa la autorización contextual: se detectaron '.count($divergencias).' divergencias.');
            foreach ($divergencias as $divergencia) $this->line("Usuario {$divergencia['usuario_id']} ({$divergencia['email']}): {$divergencia['motivo']}");
            return self::FAILURE;
        }
        $this->info('Equivalencia completa verificada: 0 divergencias. La autorización contextual puede activarse.');
        return self::SUCCESS;
    }
}
