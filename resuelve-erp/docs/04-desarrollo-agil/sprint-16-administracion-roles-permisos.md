# Sprint 16 — Administración funcional de roles y permisos

Estado: completado. Aprobación del Product Owner: 2 de octubre de 2026
(alcance y decisiones). Cierre técnico y documental: 3 de octubre de 2026
(Bloque 5).

Desarrolla HU-USR-03 (nueva) y refina HU-USR-02 del Product Backlog: catálogo
de roles vivo administrable desde la web, matriz de permisos por módulo,
asignación/revocación segura por Copropiedad, autorización verificada en
backend con 403, interfaz condicionada por permisos y auditoría, reduciendo la
dependencia de nombres de rol según ADR-011.

## Modelo de roles aprobado (híbrido)

- **Roles base globales** (`copropiedad_id` nulo en Spatie): los administra la
  autoridad de plataforma (`es_administrador_sistema`) desde `/admin`. Los cinco
  roles actuales son datos iniciales, no catálogo cerrado.
- **Roles personalizados por Copropiedad** (equipo Spatie): los crea el
  administrador contextual con el permiso `roles.gestionar`; visibles y
  asignables únicamente en su Copropiedad.
- **Asignaciones** siempre por Copropiedad (equipo = `copropiedad_id`).
- **Definiciones por Organización** quedan fuera de este sprint (evolución
  futura). La duplicación entre Copropiedades se mitiga con la creación de
  roles copiando permisos de un rol existente.

## Historias de Usuario

- **HU-USR-03 — Administración de Roles y Permisos (nueva):** como
  Administrador, quiero crear, editar y eliminar Roles y gestionar sus permisos
  desde la web, para adaptar el acceso sin modificar código.
- **HU-USR-02 (refinada) — Acceso según el Rol:** como Administrador, quiero
  asignar y revocar Roles a los usuarios de cada Copropiedad de forma segura y
  auditada.
- **HU-USR-02 (refinada) — Interfaz según permisos:** como Usuario, quiero ver
  solo las acciones autorizadas y recibir 403 ante operaciones no permitidas.

## Alcance funcional

Listado de roles (base y personalizados, con uso y permisos), crear, editar y
eliminar (solo cuando el rol no está en uso), edición de la matriz rol-permiso
agrupada por módulo, creación copiando permisos de un rol existente, asignación
y revocación de roles por Copropiedad, confirmaciones en operaciones
destructivas, mensajes de éxito/error, auditoría de cambios sensibles e
interfaz condicionada por permisos.

## Alcance técnico

Casos de uso en `app/Application/Roles/` sobre Spatie; permiso nuevo
`roles.gestionar` (ámbito Copropiedad; el catálogo global lo administra además
la autoridad de plataforma); consultas conscientes del equipo (globales y del
equipo activo); validaciones de unicidad, uso y anti-escalada; protección de la
última capacidad de administración basada en permisos; pruebas funcionales y de
seguridad PHPUnit; actualización de `docs/` y ADR del modelo de roles.

## Criterios de aceptación

1. CRUD completo de roles con validaciones y mensajes; eliminación bloqueada si
   el rol tiene asignaciones.
2. Matriz rol-permiso editable y presentada por módulos.
3. Los roles personalizados son visibles y asignables solo en su Copropiedad;
   los base, en todas.
4. Asignación y revocación por Copropiedad con confirmación, auditoría y 403
   ante operaciones no autorizadas.
5. Ninguna operación permite autoescalada ni conceder permisos fuera del
   conjunto que el operador posee.
6. Una Copropiedad no puede quedar sin al menos un usuario con capacidad
   efectiva de administración.
7. La interfaz oculta acciones sin permiso y el backend rechaza igualmente
   (pruebas de 403).
8. Las reglas de `apoyo` y de borradores se migran a
   `pqrs.gestionar_asignadas` y `pqrs.ver_borradores` sin cambio de
   comportamiento (caracterización por rol).
9. Suite completa sin regresiones y diagnóstico RBAC en 0 divergencias.

## Decisiones aprobadas (2 de octubre de 2026)

1. Modelo híbrido: roles base globales por plataforma y personalizados por
   Copropiedad; definiciones por Organización fuera de este sprint.
2. Capacidad efectiva de administración = permiso `usuarios.gestionar` (y
   `roles.gestionar` para el catálogo); sustituye la regla por nombre `admin`
   en la protección del último administrador.
3. Anti-escalada: un operador no puede otorgar permisos ni roles cuyos permisos
   no posee; el Administrador del sistema queda exento.
4. Retiro de roles: eliminación solo cuando no estén en uso; la desactivación
   queda como evolución.
5. Permiso nuevo `roles.gestionar` para la administración contextual del
   catálogo.

## Reducción de deuda RBAC planificada

- Migrar `tieneRol('apoyo')` a `pqrs.gestionar_asignadas` y
  `tieneRol('admin'|'gestor')` de borradores a `pqrs.ver_borradores` (permisos
  existentes).
- Basar la protección del último administrador en `usuarios.gestionar`.
- Sustituir las validaciones `in:admin,gestor,apoyo,auditor,residente` por el
  catálogo dinámico.
- Deprecar `canViewAllPqrs()`, `canManagePqrs()` e `isAdmin()`; `users.role` y
  la doble escritura se mantienen como compatibilidad temporal.

## Fuera de alcance

CRUD de permisos; definiciones de roles por Organización; retiro de
`users.role`, helpers y tablas legadas; activación de
`register_permission_check_method`; migración de `@can` a permisos directos;
soft delete y papelera; vocabulario ERP genérico; nuevos módulos; nuevas
dependencias.

## Riesgos

Escalada de privilegios (anti-escalada y verificación backend); borrado en
cascada de Spatie (prohibido eliminar roles en uso); proliferación de roles
(creación copiando permisos); divergencia entre interfaz y backend (pruebas de
403); desalineación semántica en la migración de reglas (caracterización por
rol); volumen de pruebas (fixtures compartidas).

## Bloques de implementación

- **B1 — Núcleo del catálogo:** casos de uso de roles y permisos,
  validaciones, eliminación segura, creación copiando permisos, permiso
  `roles.gestionar` y auditoría; pruebas unitarias.
- **B2 — Interfaz de administración:** CRUD visual con permisos agrupados por
  módulo, confirmaciones, mensajes, 403 y navegación.
- **B3 — Asignación segura:** asignación y revocación de roles por
  Copropiedad, anti-escalada y protección de la última capacidad de
  administración.
- **B4 — Semántica por permisos:** migración de reglas por nombre de rol y de
  la regla de administración; pruebas de caracterización y seguridad.
- **B5 — Cierre:** documentación (roles y permisos, arquitectura, ADR),
  evidencias, regresión completa y diagnóstico.

## Registro de ejecución

- **Bloque 1 — Núcleo del catálogo (2 de octubre de 2026):** casos de uso
  `app/Application/Roles/` (crear con modo copia, editar, eliminación segura,
  sincronizar permisos, consultas de roles gestionables y de permisos por
  módulo) con autoridad de plataforma vs. contextual, anti-escalada,
  validaciones, permiso `roles.gestionar` (catálogo: 25 permisos) y auditoría
  reutilizando `AuditLog`. Verificación: 26 pruebas específicas y suite
  completa de 524 (519 aprobadas, 5 omitidas, 2344 aserciones) sin regresiones.
  Sin interfaz (pendiente Bloque 2).
- **Bloque 2 — Interfaz de administración (2 de octubre de 2026):**
  `RolesCatalogoController` con rutas `/roles` (listado, crear, editar,
  permisos, eliminar) sobre los casos de uso del B1; vistas con permisos
  agrupados por módulo, creación con copia de permisos, confirmaciones en
  eliminación y mensajes de éxito/error; navegación condicionada por permisos
  (`Catálogo de roles` en la consola operativa y `Roles` en la consola
  administrativa); 403 verificado en backend para sin permiso y fuera de
  contexto. Auditoría específica sin duplicado del middleware. Verificación:
  14 pruebas de roles y suite completa de 530 (525 aprobadas, 5 omitidas,
  2401 aserciones) sin regresiones.
- **Bloque 3 — Asignación segura de roles por Copropiedad (3 de octubre de
  2026):** `AsignarRolUsuario` y `RevocarRolUsuario` transaccionales con
  espejo legado (solo roles con contraparte), auditoría
  `usuario.rol.asignar/revocar`, validación de membresía vigente, aislamiento
  tenant/team, anti-escalada y protección de la última capacidad
  administrativa (`usuarios.gestionar`) segura ante concurrencia mediante
  bloqueo de Membresías y lecturas bloqueantes (corregido un caso REPEATABLE
  READ detectado con concurrencia real en dos conexiones MySQL). Panel
  "Roles en esta Copropiedad" en la consola de usuarios con confirmaciones y
  mensajes. Verificación: 5 pruebas funcionales, prueba de concurrencia MySQL
  aprobada (pcntl, dos conexiones) y suite completa de 536 (530 aprobadas,
  6 omitidas, 2436 aserciones) sin regresiones.
- **Bloque 4 — Semántica por permisos (3 de octubre de 2026):** migración de
  las reglas por nombre de rol a capacidades efectivas: borradores ajenos →
  `pqrs.ver_borradores`; gestión restringida (semántica de apoyo) →
  `pqrs.gestionar_asignadas`; protección de eliminación de usuarios →
  capacidad `usuarios.gestionar` mediante `CapacidadAdministrativa`
  (reutilizado de B3, con bloqueos). Un rol personalizado con permisos
  equivalentes obtiene el comportamiento de un rol base; un nombre de rol sin
  permiso no concede capacidades. Permanecen como compatibilidad temporal las
  validaciones y helpers del campo legado `users.role` (no deciden
  autorización). Verificación: 12+17 pruebas específicas y suite completa de
  540 (534 aprobadas, 6 omitidas, 2450 aserciones) sin regresiones.
- **Bloque 5 — Cierre (3 de octubre de 2026):** documentación de cierre,
  ADR-012, actualización de roles y permisos, arquitectura y Product Backlog;
  registro de deuda y evidencias académicas; limpieza de residuos de pruebas
  en la base de desarrollo y garantía de limpieza en la prueba de concurrencia
  (`try/finally`).

## Estado de los criterios de aceptación

Los 9 criterios aprobados están **cumplidos**: CRUD validado con eliminación
protegida (1); matriz editable por módulos (2); personalizados aislados por
Copropiedad y base asignables en todas (3); asignación/revocación con
confirmación, auditoría y 403 (4); anti-escalada sin excepciones fuera de
plataforma (5); capacidad administrativa nunca en cero (6); UI condicionada y
backend autoritativo con pruebas de 403 (7); reglas migradas a
`pqrs.ver_borradores` y `pqrs.gestionar_asignadas` con caracterización por
rol (8); suite sin regresiones y diagnóstico en 0 divergencias (9).

## Resultados de cierre

- Pruebas específicas del Sprint 16: 65 pruebas, 65 aprobadas, 381 aserciones
  (catálogo, asignación, semántica por permisos, caracterización, identidad y
  eliminación de usuarios).
- Concurrencia real MySQL (dos conexiones, pcntl): aprobada.
- Suite completa: 540 pruebas, 534 aprobadas, 6 omitidas (5 preexistentes y 1
  de concurrencia que exige MySQL), 2450 aserciones.
- Diagnóstico `resuelve:verificar-equivalencia-autorizacion-contextual`:
  0 divergencias (users.role ↔ autorización efectiva y legado ↔ Spatie).

## Deuda registrada (post-Sprint 16)

1. Retiro futuro de `tieneRol()` (ya sin llamadores funcionales) y de los
   helpers `canViewAllPqrs()`, `canManagePqrs()` e `isAdmin()`.
2. Retiro futuro del campo legado `users.role`, sus validaciones
   `in:admin,gestor,...` y sus selects en la interfaz.
3. Eliminación futura del RBAC legado (`roles_contextuales`,
   `permisos_contextuales`, `rol_permiso_contextual`) y de la doble escritura
   cuando el rollback alternativo esté garantizado.
4. Integración controlada de Gate/`@can` con Spatie y evaluación de
   `register_permission_check_method` (sprint de autorización e interfaz).
5. Evoluciones fuera de alcance: desactivación de roles (hoy solo eliminación
   segura), CRUD de permisos y definiciones de roles por Organización.

## Evidencias para la entrega académica

Funcionalidades que deben demostrarse (las capturas visuales se realizarán
después y no son condición de este cierre técnico):

1. Crear un rol nuevo desde la web con permisos agrupados por módulo.
2. Editar el nombre de un rol y su matriz de permisos.
3. Eliminar un rol sin uso y observar el bloqueo cuando está asignado.
4. Crear un rol copiando permisos de otro existente.
5. Asignar y revocar un rol a un usuario en la Copropiedad activa, con
   mensajes de éxito y confirmación en la revocación.
6. Bloqueo de autoescalada (403) al intentar conceder permisos no poseidos.
7. Aislamiento: roles y asignaciones de una Copropiedad no afectan a otra.
8. Protección de la última capacidad administrativa (rol con
   `usuarios.gestionar`).
9. Un rol personalizado con permisos equivalentes gestiona PQR y borradores
   como los roles base; un nombre de rol sin permiso no obtiene acceso.
10. Auditoría de creación, edición, eliminación, permisos y asignaciones.
