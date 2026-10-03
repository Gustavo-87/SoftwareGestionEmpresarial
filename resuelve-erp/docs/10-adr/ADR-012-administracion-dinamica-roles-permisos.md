# ADR-012 — Administración dinámica de roles y semántica por capacidades

## Control documental

- **Identificador:** ADR-012
- **Título:** Administración dinámica de roles y semántica por capacidades
- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha:** 3 de octubre de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner (Sprint 16)

## Contexto

Tras adoptar `spatie/laravel-permission` como motor RBAC (ADR-011), el
producto requería administrar roles y permisos desde la aplicación —sin
modificar código— y eliminar la dependencia de comparaciones por nombres de
rol en las decisiones de autorización. Los requisitos académicos exigen roles
configurables y permisos por módulo; el producto exige además que un ERP real
permita adaptar el acceso a cada organización.

## Problema

Las reglas de autorización aún comparaban claves de rol (`apoyo`, `admin`,
`gestor`), lo que impedía que un rol personalizado obtuviera el comportamiento
correspondiente a sus permisos y convertía al catálogo de roles en un
conjunto cerrado quemado en código.

## Alternativas evaluadas

1. Mantener las reglas por nombre de rol y solo exponer su edición (descartada:
   el nombre no es una capacidad; un rol renombrado perdería comportamiento).
2. Reglas por permisos sin administración web (descartada: no cumple el
   requisito de configurabilidad).
3. Administración web de roles con semántica por capacidades efectivas
   (elegida).

## Decisión

1. **Modelo híbrido de roles:** catálogo de roles base globales (administrados
   por la autoridad de plataforma) y roles personalizados por Copropiedad
   (equipo Spatie), administrables desde la web con permisos agrupados por
   módulo. Los roles base son datos iniciales, no catálogo cerrado.
2. **Semántica por capacidades efectivas:** las decisiones de autorización
   consultan permisos, no nombres de rol:
   - `pqrs.ver_borradores` sustituye la regla admin/gestor de borradores;
   - `pqrs.gestionar_asignadas` sustituye la gestión restringida de apoyo;
   - `usuarios.gestionar` define la capacidad efectiva de administración.
3. **Anti-escalada:** ningún operador concede permisos ni roles cuyos permisos
   no posee; la autoridad de plataforma queda exenta.
4. **Protección administrativa:** una Copropiedad nunca queda sin un miembro
   vigente con `usuarios.gestionar` (revocación de roles y eliminación de
   usuarios), evaluada por capacidades y segura ante concurrencia
   (`CapacidadAdministrativa`, con bloqueo de Membresías y lecturas
   bloqueantes).
5. **Fachada estable:** `ContextoOperativo` (snapshot), `AutorizacionContextual`
   y las Policies conservan su API; `register_permission_check_method`
   permanece en `false` hasta la integración controlada de Gate/`@can`.

## Consecuencias positivas

- Acceso configurable por Organización/Copropiedad sin desarrollo.
- Roles personalizados equivalentes a los base por composición de permisos.
- Reglas de autorización auditables como catálogo de datos.
- Protecciones basadas en invariantes de capacidad, no en convenciones de
  nombres.

## Consecuencias negativas

- Los fixtures y emulaciones deben incluir las capacidades semánticas para
  reproducir comportamientos de roles base.
- Cambio semántico aprobado: eliminar al último usuario con rol `admin` está
  permitido si la Copropiedad conserva `usuarios.gestionar`.
- Persiste la compatibilidad temporal con `users.role` y el RBAC legado.

## Riesgos

- Un rol personalizado demasiado amplio (mitigado por anti-escalada y por el
  alcance del operador).
- Deriva del espejo legado (mitigado con el diagnóstico de equivalencia).
- Proliferación de roles (mitigado con creación copiando permisos).

## Deuda asociada

Retiro futuro de `tieneRol()` y helpers legados, de `users.role` y del RBAC
legado/doble escritura; integración de Gate/`@can`; desactivación de roles,
CRUD de permisos y definiciones por Organización como evoluciones.

## Evidencia

- `app/Application/Roles/` (catálogo, asignación, revocación, capacidad).
- `app/Application/Autorizacion/AutorizacionContextual.php`,
  `app/Application/Pqrs/VisibilidadBorradoresPqrs.php`.
- `resources/views/roles/`, `resources/views/users/edit.blade.php`.
- `tests/Feature/RolesCatalogoTest.php`, `RolesCatalogoWebTest.php`,
  `RolesAsignacionTest.php`, `RolesSemanticaPermisosTest.php`,
  `RolesAsignacionMysqlConcurrencyTest.php`,
  `RbacSpatieCaracterizacionTest.php`.
- `docs/04-desarrollo-agil/sprint-16-administracion-roles-permisos.md`.
