<?php

namespace App\Application\Autorizacion;

use App\Application\Contexto\ContextResolver;
use App\Application\Roles\CatalogoPermisosBase;
use App\Application\Roles\CatalogoRolesBase;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Diagnóstico de equivalencia entre `users.role` y la autorización contextual
 * efectiva.
 *
 * Contrato de verificación aprobado (Sprint 18): normalización canónica de
 * claves (consistente con las colaciones `*_ci` de MySQL), tolerancia
 * multi-rol (no se exige un único Rol asignado) y clasificación por severidad.
 * Las divergencias se clasifican como `error` (contradicción o pérdida de
 * acceso efectivo) o `aviso` (deriva explicada sin pérdida: multi-rol,
 * permisos directos, usuarios globales o Rol activo seleccionado). El
 * diagnóstico es de solo lectura y no corrige divergencias.
 */
final class VerificadorEquivalenciaAutorizacionContextual
{
    public const SEVERIDAD_ERROR = 'error';
    public const SEVERIDAD_AVISO = 'aviso';

    private const PERMISOS_POR_ROL = [
        'admin' => ['mantenimiento.crear', 'mantenimiento.ver_propias', 'mantenimiento.ver_todas', 'mantenimiento.gestionar', 'pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'pqrs.ver_todas', 'pqrs.gestionar', 'pqrs.eliminar', 'informes.exportar', 'configuracion.gestionar', 'gestion.carga_ver', 'gestion.herramientas_gestionar', 'usuarios.gestionar', 'residentes.gestionar', 'auditoria.ver', 'documentos.consultar', 'documentos.gestionar', 'documentos.aprobar', 'documentos.archivar', 'notificaciones.consultar'],
        'gestor' => ['mantenimiento.crear', 'mantenimiento.ver_propias', 'mantenimiento.ver_todas', 'mantenimiento.gestionar', 'pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'pqrs.ver_todas', 'pqrs.gestionar', 'pqrs.eliminar', 'informes.exportar', 'configuracion.gestionar', 'gestion.carga_ver', 'gestion.herramientas_gestionar', 'documentos.consultar', 'documentos.gestionar', 'notificaciones.consultar'],
        'apoyo' => ['pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'pqrs.ver_todas', 'pqrs.gestionar', 'informes.exportar', 'notificaciones.consultar'],
        'auditor' => ['pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'pqrs.ver_todas', 'informes.exportar', 'notificaciones.consultar'],
        'residente' => ['mantenimiento.crear', 'mantenimiento.ver_propias', 'pqrs.listar', 'pqrs.crear', 'pqrs.ver_propias', 'informes.exportar', 'documentos.consultar', 'notificaciones.consultar'],
    ];

    public function __construct(private readonly ContextResolver $contextResolver) {}

    /** @return list<array{usuario_id:int,email:string,motivo:string,severidad:string}> */
    public function divergencias(): array
    {
        $siteSetting = SiteSetting::current();
        if (! $siteSetting->exists || $siteSetting->organizacion_id === null || $siteSetting->copropiedad_id === null) {
            throw new RuntimeException('No se puede verificar la equivalencia sin el contexto institucional inicial.');
        }

        $divergencias = [];
        User::query()->orderBy('id')->each(function (User $usuario) use (&$divergencias, $siteSetting): void {
            $rolHeredado = CatalogoRolesBase::normalizar((string) $usuario->role);
            $esperados = self::PERMISOS_POR_ROL[$rolHeredado] ?? null;
            if ($esperados === null) {
                $divergencias[] = $this->divergencia($usuario, 'users.role no es reconocido por el catálogo de compatibilidad.', self::SEVERIDAD_ERROR);
                return;
            }

            $contexto = $this->contextResolver->resolverExplicito($siteSetting->organizacion_id, $siteSetting->copropiedad_id, $usuario->id);
            if (! $contexto->tieneMembresiaContextual()) {
                $divergencias[] = $this->divergencia($usuario, 'no tiene Membresía de Copropiedad vigente.', self::SEVERIDAD_AVISO);
                return;
            }

            // Contrato multi-rol: el Rol heredado debe estar entre los Roles
            // realmente asignados (normalización canónica). No se exige que el
            // usuario tenga un único Rol.
            $rolesAsignados = array_map(
                static fn (string $clave) => CatalogoRolesBase::normalizar($clave),
                $contexto->clavesRolesAsignados(),
            );
            if (! in_array($rolHeredado, $rolesAsignados, true)) {
                $divergencias[] = $this->divergencia($usuario, 'el Rol heredado users.role no está asignado en el contexto.', self::SEVERIDAD_ERROR);
                return;
            }

            $actuales = array_map(
                static fn (string $clave) => CatalogoPermisosBase::normalizar($clave),
                array_values(array_diff($contexto->clavesPermisos(), VerificadorEquivalenciaRbacSpatie::PERMISOS_NUEVOS)),
            );
            $esperadosNorm = array_map(
                static fn (string $clave) => CatalogoPermisosBase::normalizar($clave),
                $esperados,
            );
            $faltantes = array_values(array_diff($esperadosNorm, $actuales));
            $adicionales = array_values(array_diff($actuales, $esperadosNorm));

            if ($faltantes !== []) {
                // Con Rol activo la restricción es intencional (segregación de
                // funciones): se clasifica como aviso y no como pérdida.
                $severidad = $contexto->tieneRolActivo() ? self::SEVERIDAD_AVISO : self::SEVERIDAD_ERROR;
                $divergencias[] = $this->divergencia(
                    $usuario,
                    'el vector de Permisos contextuales no es equivalente: faltan '.implode(', ', $faltantes).'.',
                    $severidad,
                );
            }
            if ($adicionales !== []) {
                $divergencias[] = $this->divergencia(
                    $usuario,
                    'el contexto aporta permisos adicionales por multi-rol o permisos directos: '.implode(', ', $adicionales).'.',
                    self::SEVERIDAD_AVISO,
                );
            }
        });

        foreach ($divergencias as $divergencia) {
            Log::warning('Divergencia de autorización contextual.', $divergencia);
        }

        return $divergencias;
    }

    private function divergencia(User $usuario, string $motivo, string $severidad): array
    {
        return [
            'usuario_id' => $usuario->id,
            'email' => $usuario->email,
            'motivo' => $motivo,
            'severidad' => $severidad,
        ];
    }
}
