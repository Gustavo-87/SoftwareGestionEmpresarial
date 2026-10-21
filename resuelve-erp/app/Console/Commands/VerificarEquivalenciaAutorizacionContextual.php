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
        $errores = 0;
        $avisos = 0;

        try {
            $divergencias = $verificador->divergencias();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        if ($divergencias !== []) {
            $this->warn('Equivalencia users.role ↔ autorización efectiva: '.count($divergencias).' divergencias (contrato: normalización canónica + multi-rol + severidad).');
            foreach ($divergencias as $divergencia) {
                if ($divergencia['severidad'] === VerificadorEquivalenciaAutorizacionContextual::SEVERIDAD_ERROR) {
                    $errores++;
                    $this->error("Usuario {$divergencia['usuario_id']} ({$divergencia['email']}): {$divergencia['motivo']} [error]");
                } else {
                    $avisos++;
                    $this->warn("Usuario {$divergencia['usuario_id']} ({$divergencia['email']}): {$divergencia['motivo']} [aviso]");
                }
            }
        }

        try {
            $divergenciasSpatie = $verificadorSpatie->divergenciasClasificadas();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        if ($divergenciasSpatie !== []) {
            $this->warn('RBAC de Spatie frente al RBAC contextual legado: '.count($divergenciasSpatie).' divergencias.');
            foreach ($divergenciasSpatie as $divergencia) {
                if ($divergencia['severidad'] === VerificadorEquivalenciaRbacSpatie::SEVERIDAD_ERROR) {
                    $errores++;
                    $this->error($divergencia['mensaje'].' [error]');
                } else {
                    $avisos++;
                    $this->warn($divergencia['mensaje'].' [aviso]');
                }
            }
        }

        $this->info("Resumen de severidad: {$errores} error(es), {$avisos} aviso(s).");
        $this->info('El diagnóstico es de solo lectura: no corrige divergencias.');

        if ($errores > 0) {
            $this->error('Se detectaron divergencias de severidad error. El gate no exige 0 divergencias: los avisos corresponden a deriva explicada.');
            return self::FAILURE;
        }

        $this->info('Fuente efectiva de autorización: RBAC de Spatie por Copropiedad (equipo = Copropiedad activa).');
        $this->info('Espejo temporal conservado: RBAC contextual legado (roles_contextuales, permisos_contextuales, rol_permiso_contextual, membresia_copropiedad_rol) para rollback y diagnóstico.');
        $this->info('Equivalencia verificada con el contrato aprobado: normalización canónica, tolerancia multi-rol y severidad.');
        return self::SUCCESS;
    }
}
