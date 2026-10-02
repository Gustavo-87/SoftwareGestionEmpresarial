# Sprint 15 — Integración de Spatie y migración controlada del RBAC

Estado: completado. Aprobación del Product Owner: 2 de octubre de 2026
(diseño técnico y revisión final de riesgos aprobados formalmente). Cierre
técnico y documental: 2 de octubre de 2026.

Integra `spatie/laravel-permission` como fuente progresiva de verdad de roles
y permisos, preservando la arquitectura contextual existente: ContextResolver,
ContextoOperativo, aislamiento multi-organización/multi-copropiedad, Policies,
`app/Application` y la auditoría existente.

## Alcance aprobado

1. Instalación de `spatie/laravel-permission:^8.3` con funcionalidad Teams
   habilitada (`teams = true`, `team_foreign_key = 'copropiedad_id'`, guard
   `web`). La Copropiedad activa es el equipo (team) de asignación de roles.
2. Renombre del bloque RBAC legado: `roles` → `roles_contextuales`,
   `permisos` → `permisos_contextuales`, `rol_permiso` → `rol_permiso_contextual`.
   Spatie adopta los nombres estándar de sus tablas. Las claves foráneas
   compuestas se reescriben automáticamente (verificado en MySQL 8.4).
3. Ajuste mínimo de modelos y pivotes legados (`App\Models\Rol`,
   `App\Models\Permiso` y referencias directas) hacia las tablas renombradas.
4. Migración de datos controlada e idempotente hacia Spatie (permisos, los
   cinco roles base con su matriz y las asignaciones por Copropiedad), con
   verificación de equivalencia frente al catálogo legado.
5. `ContextResolver::resolverExplicito()` como único punto de fijación del
   equipo activo (`setPermissionsTeamId`) y limpieza de relaciones de Spatie
   al cambiar de contexto.
6. `App\Models\User` incorpora `HasRoles`; `AutorizacionContextual` se
   conserva como fachada/adapter sin cambios de comportamiento de autorización.
7. Compatibilidad temporal con `users.role` mediante doble escritura en
   `SincronizarIdentidadContextualUsuario`.

## Fuera de alcance

- CRUD visual de roles, papelera, soft delete.
- Generalización del vocabulario de propiedad horizontal.
- Nuevos módulos ERP.
- Retiro de comparaciones de nombres de rol en código (siguiente sprint).

## Decisiones técnicas aprobadas

- Versión: `spatie/laravel-permission:^8.3` (compatible con Laravel 13 y
  PHP ^8.3 del proyecto).
- Colisión de tablas resuelta renombrando el bloque legado; Spatie conserva
  sus nombres estándar (`roles`, `permissions`, `model_has_roles`,
  `model_has_permissions`, `role_has_permissions`).
- Teams con `team_foreign_key = 'copropiedad_id'`: la asignación de roles se
  aísla por Copropiedad, equivalente a `membresia_copropiedad_rol`.
- Fijación del team en `ContextResolver` (no en middleware), por ser el único
  punto común a HTTP, bindings, policies, comandos y pruebas.
- `unsetRelation('roles')` y `unsetRelation('permissions')` al cambiar el
  equipo activo, para evitar contaminación entre contextos.
- La vigencia de la asignación de roles permanece en la Membresía.
- `roles`/`permissions` globales para el catálogo; asignaciones siempre por
  Copropiedad; roles personalizados por Copropiedad reservados a futuro.

## Registro de ejecución

- **Bloque 1 — Instalación y estructura de base de datos (2 de octubre de
  2026):** dependencia instalada; configuración de Spatie publicada con Teams
  (`copropiedad_id`); migración de renombre del bloque RBAC legado aplicada y
  verificada (FK antes/después, conteos, drill de rollback y reaplicación);
  modelos y pivotes legados ajustados a las tablas renombradas. Sin cambios de
  comportamiento de autorización.
- **Bloque 1.5 — Validación de infraestructura RBAC (2 de octubre de 2026):**
  inventario de referencias al bloque legado y corrección de tres defectos del
  rename (migración portable a SQLite, literales de tabla en `ContextResolver`
  y regla `exists` en `MembresiaRolController`). Suite completa en verde
  (498 pruebas, 493 aprobadas, 5 omitidas, 2190 aserciones).
- **Bloque 2A — Integración paralela y migración de datos a Spatie (2 de
  octubre de 2026):** `HasRoles` en `User` (guard `web`); fijación del equipo en
  `ContextResolver`; comando idempotente `resuelve:migrar-rbac-spatie`
  (24 permisos, 5 roles, 63 relaciones rol-permiso y asignaciones activas por
  Copropiedad); diagnóstico legado ↔ Spatie
  (`VerificadorEquivalenciaRbacSpatie`); doble escritura en
  `SincronizarIdentidadContextualUsuario`; espejo automático en el helper de
  pruebas. Spatie quedó poblado en modo paralelo sin decidir autorización.
  Decisión: `register_permission_check_method => false`.
- **Bloque 2B — Spatie como fuente efectiva (2 de octubre de 2026):**
  `ContextResolver` resuelve `roles[]` y `permisos[]` desde Spatie por
  Copropiedad (equipo activo), conservando la API de `ContextoOperativo`,
  `AutorizacionContextual` y las Policies. La Membresía vigente sigue siendo
  requisito obligatorio. Doble escritura extendida a `AsignarRolMembresia`,
  `RevocarRolMembresia` y la eliminación de usuarios. Tablas legadas:
  compatibilidad y diagnóstico.

### Cambios de pruebas con trazabilidad

- `IdentidadMembresiasRelationshipsTest::test_active_authorization_still_uses_users_role`
  fue **reemplazada** por
  `test_effective_authorization_uses_spatie_and_users_role_is_legacy_compatibility`
  (Bloque 2B): la premisa anterior dejó de ser válida. Nueva regla documentada:
  la autorización efectiva usa el RBAC de Spatie; `users.role` y sus helpers son
  compatibilidad legada temporal.
- `ContextResolverTest::test_recognized_user_resolves_current_membership_roles_and_permissions`
  fue **actualizada** (Bloque 2B): su fixture creaba únicamente el espejo legado;
  ahora crea también el equivalente de Spatie, fuente efectiva desde este bloque.
- Se agregaron `RbacSpatieTest`, `RbacSpatieFuenteTest` y
  `RbacSpatieCaracterizacionTest` (equivalencia funcional de los cinco roles,
  reglas sensibles de `apoyo`, borradores, aislamiento entre Copropiedades,
  recursos ajenos y membresías no vigentes).

### Decisiones del Bloque 2B

- `register_permission_check_method` permanece en `false`: la auditoría de
  `Gate`/`can`/`@can` muestra que todas las verificaciones resuelven por
  Policies (abilities `view`, `viewAny`, `create`, `update`, `delete`,
  `archive`, `approve`) y un Gate propio (`administrar-sistema`), ninguna con
  nombres de permisos; activarlo añadiría consultas por cada `can()` y
  podría sombrear abilities. La integración de Spatie con Gate/@can se hará de
  forma controlada en el sprint de autorización y UI.
- Los permisos efectivos del contexto se derivan de los roles del equipo activo
  (equivalente al modelo legado). Los permisos directos de Spatie
  (`model_has_permissions`) quedan reservados y no se consultan todavía.
- El catálogo de Spatie (roles y permisos) es global; las asignaciones son
  siempre por Copropiedad.
- La vigencia de la asignación de roles permanece en la Membresía; una
  asignación legada futura se activa en Spatie mediante
  `resuelve:migrar-rbac-spatie` (idempotente, también sirve de reconciliador).

## Verificación final (2 de octubre de 2026)

- Pruebas específicas RBAC/Spatie/contexto: 27 pruebas aprobadas
  (`RbacSpatieTest`, `RbacSpatieFuenteTest`, `RbacSpatieCaracterizacionTest` e
  `IdentidadMembresiasRelationshipsTest`).
- Pruebas de identidad/autorización: 119 pruebas aprobadas, 434 aserciones.
- Suite completa: 516 pruebas, 511 aprobadas, 5 omitidas (preexistentes),
  2302 aserciones.
- `resuelve:verificar-equivalencia-autorizacion-contextual`: 0 divergencias en
  ambas secciones (users.role ↔ autorización efectiva y legado ↔ Spatie).
- Sin regresiones ni problemas de seguridad detectados. La autorización
  efectiva conserva exactamente el comportamiento funcional previo.

## Código residual clasificado

- **Compatibilidad temporal válida:** `SincronizarIdentidadContextualUsuario`
  (doble escritura), `MigrarRbacSpatie` y `VerificadorEquivalencia*`
  (diagnóstico sobre el legado), `CrearIdentidadContextualInicial`
  (instalaciones limpias: debe seguirlo `resuelve:migrar-rbac-spatie`),
  modelos `Rol`/`Permiso` y la relación `roles()` de `MembresiaCopropiedad`
  (espejo), y `users.role` con sus helpers.
- **Deuda planificada para el Sprint 16:** reglas por nombre de rol
  (`tieneRol('apoyo')` en `AutorizacionContextual`,
  `tieneRol('admin'|'gestor')` en `VisibilidadBorradoresPqrs` y
  `UserManagementController`), validaciones `in:admin,gestor,...`, helpers
  `canViewAllPqrs()`/`canManagePqrs()`/`isAdmin()`, visualización del espejo en
  la consola de membresías e integración de Gate/`@can`.
- **Referencias incorrectas:** ninguna.

## Estrategia de transición futura

Descrita en [ADR-011](../10-adr/ADR-011-spatie-rbac-contextual.md): migración
de reglas por nombre de rol a permisos, retirada de validaciones y helpers,
suspensión de la doble escritura, retiro degradado de `users.role` y de las
tablas legadas, e integración controlada de Gate/`@can`.
