# ADR-013 — Rol activo, permisos directos y ciclo de vida de usuarios

## Control documental

- **Identificador:** ADR-013
- **Título:** Rol activo, permisos directos y ciclo de vida de usuarios
- **Versión:** v1.1
- **Estado:** Aprobado
- **Fecha:** 17 de octubre de 2026
- **Errata:** 17 de octubre de 2026 — correcciones documentales D1–D4
  (funcionalidad existente de desactivación, vocabulario de auditoría, baseline
  real de pruebas y divergencias RBAC, y regla desactivar frente a eliminar).
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner (Sprint 18)

## Contexto

Tras la administración dinámica de roles (ADR-012, Sprint 16) y la eliminación
segura de Copropiedades (Sprint 17), la revisión de cumplimiento de la guía
académica "Roles, Permisos y Rol Activo" (Clase 10), realizada el 17 de
octubre de 2026, identificó brechas entre el modelo implementado y el modelo
exigido: segregación de funciones mediante rol activo, asignación de permisos
directos por usuario, visibilidad del origen de cada permiso y ciclo de vida de
usuarios mediante desactivación. La deuda registrada en ADR-012 (fachadas
estables, compatibilidad temporal del legado y evoluciones pendientes) y la
Épica Usuarios y Seguridad del Product Backlog enmarcan esta decisión.

## Problema

1. Un usuario con varios roles en una Copropiedad opera con la unión de los
   permisos de todos sus roles, lo que impide segregar funciones y contradice
   la guía de Clase 10.
2. Los permisos solo se conceden mediante roles: no existen permisos directos
   por usuario para excepciones puntuales.
3. Ni el usuario ni el administrador pueden observar el origen de cada permiso
   efectivo (qué rol lo aporta o si es un permiso directo).
4. El ciclo de vida de las cuentas ya dispone de desactivación global:
   `app/Application/Usuarios/DesactivarUsuarioGlobal.php` pone
   `users.estado = 'inactivo'` y `users.desactivado_at`, suspende las Membresías
   activas y prohíbe desactivar la propia cuenta o al último administrador del
   sistema; expone las rutas `admin.usuarios-globales.desactivar`/`reactivar`,
   audita `usuario_global.deactivate`/`usuario_global.reactivate` y tiene
   pruebas en `tests/Feature/Admin/UsuarioGlobalCrudTest.php`. La brecha real no
   es un flujo de desactivación nuevo, sino huecos sin cerrar: el inicio de
   sesión no consulta `users.estado` (`AuthController@store`), no existe
   expulsión de la sesión abierta (ni del token "recordarme"), no hay
   integración contextual con `usuarios.gestionar`/`CapacidadAdministrativa`, y
   no está definida la regla de reactivación de las Membresías suspendidas por
   la desactivación.

## Alternativas evaluadas

1. Mantener la unión de roles como única semántica y solo documentar la guía
   (descartada: no cumple la segregación de funciones exigida).
2. Limitar cada Membresía a un único rol y modelar las excepciones con roles
   "comodín" (descartada: destruye la composición de roles personalizados
   aprobada en ADR-012 y oculta las excepciones como roles).
3. Rol activo por Membresía/Copropiedad con permisos directos por usuario,
   origen visible de cada permiso y compatibilidad por defecto (elegida).
4. Eliminar usuarios para bloquear su acceso (descartada: pierde la
   trazabilidad y choca con las restricciones de eliminación existentes).

## Decisión

1. **Rol activo por Membresía/Copropiedad:** los permisos efectivos del
   usuario son los permisos del rol activo más sus permisos directos, dentro
   de la Copropiedad activa (equipo Spatie). Sin selección explícita de rol
   activo se conserva el comportamiento actual (unión de los roles asignados)
   por compatibilidad.
2. **Permisos directos por usuario:** se administran sobre
   `model_has_permissions` con `team` igual a la Copropiedad activa (alta,
   baja y listado), con la misma anti-escalada que los roles: ningún operador
   concede permisos que no posee; la autoridad de plataforma queda exenta.
3. **Origen visible de cada permiso:** cada permiso efectivo expone su origen
   (rol activo, rol asignado que lo aporta o permiso directo), tanto en la
   interfaz como en la matriz "ver como usuario".
4. **Integración sin romper APIs:** `ContextoOperativo` (snapshot),
   `AutorizacionContextual` y las Policies conservan su API, según la regla
   fijada en ADR-012. El rol activo se elige y persiste por
   Membresía/Copropiedad en `/cambiar-contexto`; cambiar de Copropiedad
   reinicia o revalida la selección. El diagnóstico de equivalencia
   legado ↔ Spatie se ejecuta en cada paso, pero el gate no exige 0
   divergencias artificiales: primero se corrige el criterio de verificación
   (ver «Baseline real y divergencias RBAC conocidas») y
   `register_permission_check_method` permanece en `false`.
5. **Ciclo de vida de usuarios:** desactivar en vez de eliminar. El flujo
   global ya existente se conserva sin cambios
   (`DesactivarUsuarioGlobal`: `users.estado` y `users.desactivado_at`,
   suspensión de Membresías y prohibiciones de la propia cuenta y del último
   administrador del sistema). HU-USR-08 no crea un flujo nuevo: cierra los
   huecos restantes — bloqueo en `AuthController@store` para
   `users.estado ≠ 'activo'` con mensaje inequívoco, middleware de expulsión de
   sesión (invalida la sesión y el token "recordarme"), integración contextual
   con `usuarios.gestionar`/`CapacidadAdministrativa` (no desactivar al último
   administrador efectivo de la Copropiedad) y la regla de reactivación de las
   Membresías suspendidas por la desactivación (decisión pendiente del Product
   Owner; opción recomendada: restaurar las suspendidas por ese motivo). La
   regla desactivar frente a eliminar se define en
   `docs/02-funcional/roles-y-permisos.md` §7.4: desactivar es el camino
   estándar y reversible para retirar accesos; eliminar queda reservado a
   registros que no deberían existir, sujeto a las guardas existentes. Cada
   mutación se audita con el vocabulario unificado (ver «Convención de
   vocabulario de auditoría»).
6. **Nota de adaptación académica:** la guía de Clase 10 se adapta al
   proyecto: los permisos usan la forma "modulo.accion"; los roles son los
   cinco roles base (`admin`, `gestor`, `apoyo`, `auditor`, `residente`)
   más los personalizados por Copropiedad; la regla "≥1 administrador" se
   interpreta como "≥1 miembro con `usuarios.gestionar`"; y las rutas
   permanecen en español.

## Convención de vocabulario de auditoría (errata alineada)

- El flujo global (consola administrativa) conserva las acciones existentes
  `usuario_global.deactivate`/`usuario_global.reactivate`; no se renombran.
- El flujo contextual por Copropiedad (HU-USR-08) usará
  `usuario.desactivar`/`usuario.activar`.
- La convención se aplica a toda mención posterior de este vocabulario en la
  documentación del proyecto.

## Baseline real y divergencias RBAC conocidas (17 de octubre de 2026)

Estado inicial real al iniciar el Sprint 18 (corrige las cifras anteriores de
"546 pruebas" y "diagnóstico en 0 divergencias"):

- **Suite de pruebas (estado inicial):** 594 pruebas — 588 aprobadas, 6
  omitidas (las 6 requieren MySQL real) y 2698 aserciones.
- **Baseline actualizado tras HU-USR-09 (commit `de5b241`, 17 de octubre de
  2026):** 611 pruebas — 605 aprobadas, 6 omitidas (las 6 que requieren MySQL
  real) y 2757 aserciones. Las 32 divergencias se mantienen sin incremento
  (10 + 22) tras el diagnóstico de solo lectura.
- **Divergencias RBAC conocidas en la BD local:** 32 (10 entre `users.role` y el
  contexto, 22 entre Spatie y el legado), con 3 causas raíz:
  1. Renombre de roles desde la interfaz en el Sprint 16 (`audit_logs` #59–63),
     amplificado por el defecto de sensibilidad del verificador.
  2. Ampliación de la matriz de Apoyo (#144).
  3. Bloque de datos de prueba del 3 de octubre (usuarios 21–26, orgs 19–24,
     copropiedades 17–21 y roles `rol-fk-…`).

Regla del Product Owner: el gate **no** exige 0 divergencias artificiales.
Primero se corrige el criterio de verificación —el verificador actual compara
en PHP con sensibilidad a mayúsculas mientras SQL usa colación `*_ci` y exige un
solo rol— con el contrato propuesto por Arquitectura (normalización canónica +
multi-rol + severidad). Las divergencias actuales están clasificadas como deriva
de datos locales documentada, con un plan de saneamiento en preparación (sin
ejecutar).

## Consecuencias positivas

- Segregación de funciones real sin retirar la composición de roles.
- Excepciones puntuales mediante permisos directos, auditables y con origen
  visible.
- Bloqueo de cuentas sin eliminar datos: la trazabilidad histórica se
  conserva.
- Compatibilidad: sin selección de rol activo el comportamiento no cambia y
  las fachadas mantienen su API.
- La guía académica queda satisfecha con adaptaciones declaradas, sin
  renombrar permisos ni rutas del producto.

## Consecuencias negativas

- La resolución de permisos adquiere dos semánticas (rol activo + permisos
  directos frente a unión de roles) que deben explicarse y probarse en
  conjunto.
- El rol activo introduce persistencia por Membresía/Copropiedad y
  revalidación al cambiar de contexto.
- Se mantiene la compatibilidad temporal del legado (`users.role`, RBAC espejo
  y doble escritura), lo que amplía la superficie de verificación.
- Los usuarios desactivados deben excluirse de conteos, protecciones y
  asignaciones, lo que comparte lógica con `CapacidadAdministrativa`.
- La protección administrativa se expresa por capacidades
  (`usuarios.gestionar`) y no por el nombre del rol `admin`.

## Riesgos

- Regresión de autorización (mitigado: suite completa y diagnóstico RBAC en
  cada historia; base tras HU-USR-09 del 17 de octubre de 2026: 611 pruebas,
  605 aprobadas y 6 omitidas que requieren MySQL real; estado inicial del
  sprint: 594 pruebas, 588 aprobadas y 6 omitidas).
- El rol activo rompe las fachadas (mitigado: la compatibilidad de API es
  criterio de aceptación).
- Anti-escalada incompleta con permisos directos (mitigado: reutilizar
  `CapacidadAdministrativa` y las reglas anti-escalada del Sprint 16).
- Divergencia entre la doble escritura y el espejo legado (mitigado: no
  retirar el legado y verificar la equivalencia en cada paso).
- El middleware de expulsión con sesiones "recordarme" (mitigado: pruebas
  explícitas de bloqueo y expulsión).

## Deuda asociada

Permanece la deuda registrada en ADR-012: retiro futuro de `tieneRol()` y de
los helpers legados, de `users.role` y del RBAC legado/doble escritura;
integración controlada de Gate/`@can` (`register_permission_check_method` en
`false`); desactivación de roles y definiciones por Organización como
evoluciones. Se añade la deuda de coherencia entre las dos semánticas de
permisos mientras dure la compatibilidad por defecto.

## Evidencia

- Guía académica "Roles, Permisos y Rol Activo" (Clase 10), revisada el 17 de
  octubre de 2026 (ver `docs/integracion-academica.md`).
- `docs/04-desarrollo-agil/sprint-18-seguridad-acceso.md` (alcance aprobado:
  HU-USR-04 a HU-USR-09).
- `docs/04-desarrollo-agil/product-backlog.md` (Épica Usuarios y Seguridad).
- `docs/02-funcional/roles-y-permisos.md` (§7 Administración de cuentas y
  §9.5 hallazgo del cambio de rol sin protección administrativa).
- `docs/10-adr/ADR-012-administracion-dinamica-roles-permisos.md` (fachadas
  estables, `CapacidadAdministrativa`, anti-escalada y deuda asociada).
- Puntos de integración previstos, extendidos en el Sprint 18 sin romper su
  API: `ContextoOperativo`, `AutorizacionContextual`, las Policies, la ruta
  `/cambiar-contexto` y `CapacidadAdministrativa`.

> **Alcance de este ADR:** aprueba decisiones de diseño para el Sprint 18. Con
> la excepción de la desactivación global ya existente
> (`DesactivarUsuarioGlobal` con sus rutas, auditoría y pruebas, ver Problema
> #4), las capacidades descritas se encuentran en implementación y no se
> presentan como parte del sistema actual.
