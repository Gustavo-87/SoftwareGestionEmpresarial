# ADR-011 — Adopción de spatie/laravel-permission como motor RBAC contextual

## Control documental

- **Identificador:** ADR-011
- **Título:** Adopción de spatie/laravel-permission como motor RBAC contextual
- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha:** 2 de octubre de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner (Sprint 15)

## Contexto

Un requisito académico obligatorio exige utilizar `spatie/laravel-permission`
como motor de roles y permisos. El proyecto ya poseía un RBAC contextual propio
(`roles`, `permisos`, `rol_permiso` y asignación por `membresia_copropiedad_rol`)
activo como fuente de autorización desde el Sprint 5, con aislamiento por
Organización y Copropiedad, vigencia de membresías y 511 pruebas aprobadas.

## Problema

Spatie y el RBAC legado compiten por el nombre de la tabla `roles` y por la
pregunta "quién decide la autorización". Migrar de golpe habría roto las reglas
de vigencia y tenancy, que Spatie no modela, y la superficie completa de
consumidores de `ContextoOperativo`.

## Alternativas evaluadas

1. Mantener la implementación propia (descartada: el requisito académico es
   obligatorio).
2. Migración big-bang que sustituyera el contexto por Spatie puro (descartada:
   perdería la validación de Membresía vigente y exigiría reescribir políticas,
   casos de uso y pruebas).
3. Integración incremental: Spatie como fuente de datos de roles/permisos,
   `ContextoOperativo` como snapshot y el RBAC legado como espejo temporal
   (elegida).

## Decisión

Adoptar `spatie/laravel-permission: ^8.3` con la funcionalidad Teams:

- `teams = true` y `team_foreign_key = 'copropiedad_id'`: la Copropiedad activa
  es el equipo de asignación de roles; la Organización permanece como frontera
  de tenant.
- Catálogo global: los 5 roles base y los 24 permisos viven sin equipo
  (`copropiedad_id` nulo); las asignaciones (`model_has_roles`) son siempre por
  Copropiedad.
- `ContextResolver::resolverExplicito()` fija el equipo activo y construye
  `roles[]` y `permisos[]` desde Spatie; `ContextoOperativo` conserva su API y
  opera como snapshot de autorización por resolución.
- `AutorizacionContextual` y las Policies se conservan como fachada estable; la
  Membresía vigente sigue siendo requisito obligatorio y una fila de
  `model_has_roles` nunca sustituye esa validación.
- El RBAC legado (`roles_contextuales`, `permisos_contextuales`,
  `rol_permiso_contextual`, `membresia_copropiedad_rol`) pasa a ser espejo
  temporal de compatibilidad y diagnóstico, con doble escritura mientras dure
  la transición. `users.role` queda como campo legado.
- `register_permission_check_method = false`: el Gate de Spatie no participa en
  `can()`/`@can`; la integración con la interfaz se hará de forma controlada en
  el sprint de autorización y UI.

## Justificación

- Conservar `ContextoOperativo` y `AutorizacionContextual` mantiene intactos a
  todos los consumidores, las reglas de vigencia/tenancy y la red de pruebas.
- Mantener el RBAC legado permite rollback, diagnóstico de equivalencia
  (`resuelve:verificar-equivalencia-autorizacion-contextual`) y comparación
  controlada durante la transición.
- Teams con `copropiedad_id` reproduce exactamente la granularidad de
  `membresia_copropiedad_rol` (un rol distinto por Copropiedad para un mismo
  usuario).

## Consecuencias positivas

- Roles y permisos administrables desde datos (base para el CRUD futuro).
- Aislamiento por Copropiedad garantizado por la estructura de Spatie.
- Equivalencia verificable: 0 divergencias entre legado y Spatie.
- Retiro gradual del legado sin reescrituras ni paradas.

## Consecuencias negativas

- Doble escritura temporal y dos esquemas RBAC coexistentes hasta el retiro.
- La vigencia de la asignación de roles permanece en la Membresía; una
  asignación legada futura se activa en Spatie con
  `resuelve:migrar-rbac-spatie` (idempotente, sirve de reconciliador).
- `register_permission_check_method = false` limita el uso directo de `@can`
  con permisos hasta el sprint de autorización y UI.

## Riesgos

- Deriva entre espejo y fuente si se escribe fuera de los servicios de doble
  escritura (mitigado con el diagnóstico de equivalencia).
- Roles con nombre igual a abilities de Policy si se activara el Gate de Spatie
  (mitigado por la decisión de mantenerlo desactivado).
- Escalada de privilegios cuando exista CRUD de roles (mitigaciones definidas
  para ese sprint: permiso dedicado, ámbito por organización, protección del
  último administrador).

## Estrategia de retiro del legado

1. Migrar las reglas por nombre de rol a permisos
   (`pqrs.gestionar_asignadas`, `pqrs.ver_borradores` y los usos de
   `tieneRol('admin')` en consola y borradores).
2. Retirar las validaciones `in:admin,gestor,...` y los helpers de `User`.
3. Suspender la doble escritura y dejar el legado en solo lectura.
4. Retirar `users.role` y las tablas `roles_contextuales`, `permisos_contextuales`
   y `rol_permiso_contextual` en modo degradación.
5. Integrar Gate/`@can` con Spatie de forma controlada y habilitar, si procede,
   `register_permission_check_method`.

## Evidencia

- `config/permission.php`, `database/migrations/2026_10_05_000000_rename_legacy_rbac_tables.php`,
  `database/migrations/2026_10_05_000100_create_permission_tables.php`.
- `app/Application/Contexto/ContextResolver.php`,
  `app/Application/Autorizacion/VerificadorEquivalenciaRbacSpatie.php`,
  `app/Console/Commands/MigrarRbacSpatie.php`.
- `tests/Feature/RbacSpatieTest.php`, `tests/Feature/RbacSpatieFuenteTest.php`,
  `tests/Feature/RbacSpatieCaracterizacionTest.php`.
- `docs/04-desarrollo-agil/sprint-15-integracion-spatie-rbac.md`.
