<?php

namespace App\Application\Autorizacion;

use App\Application\Contexto\ContextResolver;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class VerificadorEquivalenciaAutorizacionContextual
{
    private const PERMISOS_POR_ROL = [
        'admin' => ['pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'pqrs.ver_todas', 'pqrs.gestionar', 'pqrs.eliminar', 'informes.exportar', 'configuracion.gestionar', 'gestion.carga_ver', 'gestion.herramientas_gestionar', 'usuarios.gestionar', 'residentes.gestionar', 'auditoria.ver', 'documentos.consultar', 'documentos.gestionar', 'documentos.aprobar', 'documentos.archivar', 'notificaciones.consultar'],
        'gestor' => ['pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'pqrs.ver_todas', 'pqrs.gestionar', 'pqrs.eliminar', 'informes.exportar', 'configuracion.gestionar', 'gestion.carga_ver', 'gestion.herramientas_gestionar', 'documentos.consultar', 'documentos.gestionar', 'notificaciones.consultar'],
        'apoyo' => ['pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'pqrs.ver_todas', 'pqrs.gestionar', 'informes.exportar', 'notificaciones.consultar'],
        'auditor' => ['pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'pqrs.ver_todas', 'informes.exportar', 'notificaciones.consultar'],
        'residente' => ['pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'informes.exportar', 'documentos.consultar', 'notificaciones.consultar'],
    ];

    public function __construct(private readonly ContextResolver $contextResolver) {}

    /** @return list<array{usuario_id:int,email:string,motivo:string}> */
    public function divergencias(): array
    {
        $siteSetting = SiteSetting::current();
        if (! $siteSetting->exists || $siteSetting->organizacion_id === null || $siteSetting->copropiedad_id === null) {
            throw new RuntimeException('No se puede verificar la equivalencia sin el contexto institucional inicial.');
        }

        $divergencias = [];
        User::query()->orderBy('id')->each(function (User $usuario) use (&$divergencias, $siteSetting): void {
            $esperados = self::PERMISOS_POR_ROL[$usuario->role] ?? null;
            if ($esperados === null) {
                $divergencias[] = $this->divergencia($usuario, 'users.role no es reconocido por el catálogo de compatibilidad.');
                return;
            }

            $contexto = $this->contextResolver->resolverExplicito($siteSetting->organizacion_id, $siteSetting->copropiedad_id, $usuario->id);
            if (! $contexto->tieneMembresiaContextual()) {
                $divergencias[] = $this->divergencia($usuario, 'no tiene Membresía de Copropiedad vigente.');
                return;
            }
            if ($contexto->clavesRoles() !== [$usuario->role]) {
                $divergencias[] = $this->divergencia($usuario, 'el Rol contextual vigente no coincide exactamente con users.role.');
                return;
            }
            $actuales = $contexto->clavesPermisos(); sort($actuales); sort($esperados);
            if ($actuales !== $esperados) {
                $divergencias[] = $this->divergencia($usuario, 'el vector de Permisos contextuales no es equivalente.');
            }
        });

        foreach ($divergencias as $divergencia) {
            Log::warning('Divergencia de autorización contextual.', $divergencia);
        }

        return $divergencias;
    }

    private function divergencia(User $usuario, string $motivo): array
    {
        return ['usuario_id' => $usuario->id, 'email' => $usuario->email, 'motivo' => $motivo];
    }
}
