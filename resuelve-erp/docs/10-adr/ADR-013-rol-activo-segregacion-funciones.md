# ADR-013 — Rol activo, permisos directos y ciclo de vida de usuarios

## Control documental

- **Identificador:** ADR-013
- **Título:** Rol activo, permisos directos y ciclo de vida de usuarios
- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha:** 17 de octubre de 2026
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
4. El ciclo de vida de las cuentas se basa en la eliminación y en reglas
   puntuales: no existe un estado de desactivación que bloquee el acceso sin
   perder la trazabilidad histórica.

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
   legado ↔ Spatie se mantiene en 0 divergencias y
   `register_permission_check_method` permanece en `false`.
5. **Ciclo de vida de usuarios:** desactivar en vez de eliminar
   (`users.estado` y `users.desactivado_at`). Un usuario desactivado no
   inicia sesión (bloqueo en `AuthController@store` con mensaje inequívoco),
   su sesión abierta se invalida en la siguiente petición (middleware de
   expulsión) y no cuenta para la protección "≥1 miembro con
   `usuarios.gestionar`". No se desactiva la propia cuenta ni al último
   administrador efectivo. Cada mutación se audita
   (`usuario.desactivar`/`usuario.activar`).
6. **Nota de adaptación académica:** la guía de Clase 10 se adapta al
   proyecto: los permisos usan la forma "modulo.accion"; los roles son los
   cinco roles base (`admin`, `gestor`, `apoyo`, `auditor`, `residente`)
   más los personalizados por Copropiedad; la regla "≥1 administrador" se
   interpreta como "≥1 miembro con `usuarios.gestionar`"; y las rutas
   permanecen en español.

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
  cada historia; base de 546 pruebas).
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

> **Alcance de este ADR:** aprueba decisiones de diseño para el Sprint 18. Las
> capacidades descritas se encuentran en implementación y no se presentan como
> parte del sistema actual.
