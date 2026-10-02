<?php

namespace App\Console\Commands;

use App\Application\Autorizacion\VerificadorEquivalenciaAutorizacionContextual;
use App\Application\Autorizacion\VerificadorEquivalenciaRbacSpatie;
use Illuminate\Console\Command;
use Throwable;

class VerificarEquivalenciaAutorizacionContextual extends Command
{
    protected $signature = 'resuelve:verificar-equivalencia-autorizacion-contextual';
    protected $description = 'Verifica que users.role y la autorización contextual sean equivalentes antes de activarla';

    public function handle(VerificadorEquivalenciaAutorizacionContextual $verificador, VerificadorEquivalenciaRbacSpatie $verificadorSpatie): int
    {
        $fallas = false;
        try { $divergencias = $verificador->divergencias(); } catch (Throwable $exception) {
            $this->error($exception->getMessage()); return self::FAILURE;
        }
        if ($divergencias !== []) {
            $fallas = true;
            $this->error('No se activa la autorización contextual: se detectaron '.count($divergencias).' divergencias.');
            foreach ($divergencias as $divergencia) $this->line("Usuario {$divergencia['usuario_id']} ({$divergencia['email']}): {$divergencia['motivo']}");
        }

        try { $divergenciasSpatie = $verificadorSpatie->divergencias(); } catch (Throwable $exception) {
            $this->error($exception->getMessage()); return self::FAILURE;
        }
        if ($divergenciasSpatie !== []) {
            $fallas = true;
            $this->error('RBAC de Spatie frente al RBAC contextual legado: '.count($divergenciasSpatie).' divergencias.');
            foreach ($divergenciasSpatie as $divergencia) $this->line($divergencia);
        }

        if ($fallas) {
            return self::FAILURE;
        }
        $this->info('Fuente efectiva de autorización: RBAC de Spatie por Copropiedad (equipo = Copropiedad activa).');
        $this->info('Espejo temporal conservado: RBAC contextual legado (roles_contextuales, permisos_contextuales, rol_permiso_contextual, membresia_copropiedad_rol) para rollback y diagnóstico.');
        $this->info('Equivalencia users.role ↔ autorización efectiva: 0 divergencias.');
        $this->info('Equivalencia legado ↔ Spatie: 0 divergencias.');
        $this->info('El diagnóstico es de solo lectura: no corrige divergencias.');
        return self::SUCCESS;
    }
}
