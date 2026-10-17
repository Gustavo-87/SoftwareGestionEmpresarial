# Sprint 18 — Seguridad de acceso: rol activo, permisos directos y ciclo de vida de usuarios

Estado: en curso (alcance y decisiones aprobados; capacidades en implementación).
Aprobación del Product Owner: 17 de octubre de 2026.

Desarrolla HU-USR-04 a HU-USR-09 (nuevas) de la Épica Usuarios y Seguridad del
Product Backlog: rol activo con segregación de funciones, permisos directos por
usuario, matriz global de permisos "ver como usuario", administración de
permisos, ciclo de vida de usuarios por desactivación y endurecimiento de las
reglas de roles con fixture de demostración.

## Control documental

- **Versión:** v1.0
- **Estado:** En curso
- **Fecha de aprobación:** 17 de octubre de 2026
- **Aprobación:** Product Owner (17 de octubre de 2026)
- **Responsable:** Equipo de desarrollo de Resuelve

## Objetivo

Cerrar las brechas de seguridad de acceso identificadas en la revisión de la
guía académica "Roles, Permisos y Rol Activo" (Clase 10) y en la deuda
registrada en ADR-012, sin romper las fachadas de autorización ni retirar la
compatibilidad temporal del legado: segregar funciones mediante rol activo,
admitir permisos directos por usuario con anti-escalada y origen visible,
ofrecer una matriz global de permisos, administrar el catálogo de permisos,
desactivar cuentas en vez de eliminarlas y endurecer las reglas de roles,
contando con un fixture de demostración para la entrega académica.

## Justificación

- **Guía académica Clase 10:** la revisión de cumplimiento realizada el 17 de
  octubre de 2026 (ver [Integración académica](../integracion-academica.md))
  identificó brechas en rol activo, permisos directos, origen de los permisos,
  administración de permisos y ciclo de vida de usuarios. Las brechas fueron
  aprobadas por el Product Owner para implementarse en este sprint.
- **Deuda de ADR-012:** el rol activo y los permisos directos amplían el
  modelo de capacidades efectivas manteniendo la regla de fachadas estables
  (ver [ADR-013](../10-adr/ADR-013-rol-activo-segregacion-funciones.md)) y la
  verificación de equivalencia legado ↔ Spatie.
- **Épica Usuarios y Seguridad:** HU-USR-04 a HU-USR-09 fueron aprobadas por
  el Product Owner el 17 de octubre de 2026 (ver
  [Product Backlog](product-backlog.md)).

## Alcance aprobado

Seis historias de usuario de la Épica Usuarios y Seguridad, con sus criterios
de aceptación. Las capacidades descritas se encuentran **en implementación** y
no se presentan como parte del sistema actual.

### HU-USR-04 — Rol activo con segregación de funciones

Criterios de aceptación:

1. ContextoOperativo deja de resolver la unión de roles cuando hay rol activo:
   permisos efectivos = rol activo + permisos directos; sin selección explícita
   se conserva el comportamiento actual.
2. Integración con /cambiar-contexto: el rol activo se elige y persiste por
   Membresía/Copropiedad; cambiar de Copropiedad reinicia o revalida la
   selección.
3. AutorizacionContextual y Policies conservan su API; diagnóstico
   legado↔Spatie sin divergencias.
4. Interfaz: selector de rol activo visible, con el rol actual indicado en la
   navegación.
5. Pruebas: multi-rol con y sin rol activo, cambio de contexto, concurrencia.

### HU-USR-05 — Permisos directos por usuario

Criterios de aceptación:

1. Flujo UI sobre model_has_permissions con team = Copropiedad activa; alta,
   baja y listado.
2. Anti-escalada idéntica a roles (autoridad de plataforma exenta).
3. Origen visible: cada permiso efectivo muestra "rol activo / rol X / directo".
4. Auditoría de cada mutación y 403 en backend; el permiso usuarios.gestionar
   no puede revocarse si deja a la Copropiedad sin capacidad administrativa.
5. Pruebas: asignación, revocación, anti-escalada, 403, auditoría y
   concurrencia.

### HU-USR-06 — Matriz global de permisos "ver como usuario"

Criterios de aceptación:

1. Vista reutilizando ConsultaPermisosCatalogo::porModulo(), agrupada por
   módulo "modulo.accion".
2. Cada celda indica el origen: rol activo, rol asignado (nombre) o permiso
   directo.
3. Respeta membresía vigente, Copropiedad activa y (si aplica) el rol activo
   del usuario consultado.
4. Solo visible para usuarios.gestionar; 403 en backend; solo lectura.

### HU-USR-07 — Administración de permisos (CRUD)

Criterios de aceptación:

1. Alta con clave "modulo.accion", nombre, descripción y módulo; validación de
   unicidad y formato.
2. No se elimina un permiso asignado a roles o usuarios (mensaje que identifica
   el uso); eliminación lógica/auditada.
3. El rol admin base hereda los permisos nuevos (invariante del catálogo base).
4. Solo autoridad de plataforma; auditoría completa.
5. Pruebas: creación, herencia al admin, bloqueo de eliminación en uso, 403 y
   actualización de la matriz por módulo.

### HU-USR-08 — Usuarios inactivos

Criterios de aceptación:

1. Flujo activar/desactivar sobre users.estado/desactivado_at; desactivar en
   vez de eliminar.
2. Bloqueo en AuthController@store: un usuario desactivado no inicia sesión
   (mensaje inequívoco).
3. Middleware de expulsión: la sesión abierta de un usuario desactivado se
   invalida en la siguiente petición.
4. Un usuario desactivado no cuenta para la protección "≥1 miembro con
   usuarios.gestionar"; no se desactiva la propia cuenta ni al último
   administrador efectivo.
5. Auditoría usuario.desactivar/usuario.activar; pruebas de bloqueo, expulsión,
   reactivación y protección administrativa.

### HU-USR-09 — Endurecimiento de reglas de roles y fixture de demostración

Criterios de aceptación:

1. EliminarRol: no se elimina un rol con usuarios asignados (mensaje con
   conteo) ni un rol base del catálogo inicial.
2. EditarRol: rol admin base inmutable en permisos; anti-escalada en la edición
   de la matriz (verificar y endurecer lo de S16).
3. Resolver el hallazgo §9-5 de roles-y-permisos.md (cambio de rol sin
   protección "≥1 admin"): corregirlo o documentar la regla aprobada.
4. Fixture/Seeder de demostración: usuario con 2 roles + 1 permiso directo,
   para evidencia académica.
5. Pruebas de cada regla; suite completa sin regresiones.

## Decisiones de diseño

Las decisiones de este sprint están formalizadas en
[ADR-013 — Rol activo, permisos directos y ciclo de vida de
usuarios](../10-adr/ADR-013-rol-activo-segregacion-funciones.md) (aprobado el
17 de octubre de 2026): rol activo por Membresía/Copropiedad con compatibilidad
por defecto, permisos directos con anti-escalada y origen visible, integración
con `/cambiar-contexto` sin romper las fachadas
(`ContextoOperativo`, `AutorizacionContextual` y Policies), ciclo de vida de
usuarios por desactivación y nota de adaptación académica de la guía de Clase
10.

## Fuera de alcance

- Desactivación de roles y definiciones de roles por Organización.
- Retiro del legado (`users.role`, `tieneRol()`, RBAC espejo y doble
  escritura).
- Integración de Gate/`@can`: `register_permission_check_method` permanece
  en `false` salvo decisión expresa.
- CRUD de roles base fuera de lo implementado en el Sprint 16.
- Módulos nuevos y cambios de esquema no previstos.
- Documentación P3 salvo lo indicado.

## Orden de implementación

1. **HU-USR-09** — endurecimiento de reglas de roles y fixture de demostración.
2. **HU-USR-08** — usuarios inactivos.
3. **HU-USR-04** — rol activo con segregación de funciones (hito intermedio
   del sprint; decisión formalizada en ADR-013).
4. **HU-USR-05** — permisos directos por usuario.
5. **HU-USR-06** — matriz global de permisos "ver como usuario".
6. **HU-USR-07** — administración de permisos (CRUD).

Después de cada paso debe mantenerse el diagnóstico
`resuelve:verificar-equivalencia-autorizacion-contextual` en 0 divergencias y
la suite completa sin regresiones.

## Documentación a crear/actualizar

- `docs/10-adr/ADR-013-rol-activo-segregacion-funciones.md` (creado con la
  aprobación de este sprint).
- `docs/02-funcional/roles-y-permisos.md`: reglas de roles endurecidas,
  permisos directos, origen de los permisos, ciclo de vida de usuarios y
  resolución del hallazgo §9-5.
- `docs/03-tecnica/modelo-de-datos.md`: `users.estado`/`desactivado_at` y
  asignaciones de permisos directos.
- `docs/integracion-academica.md`: evidencias de la guía de Clase 10.
- `docs/04-desarrollo-agil/product-backlog.md`: estados de las HU al cierre.
- `docs/README.md`: estado del sprint y referencias.
- Este documento: registro de ejecución y acta de cierre.

## Riesgos y estimación

Riesgos:

- Regresión de autorización (base: 546 pruebas) → suite completa y diagnóstico
  RBAC por cada HU.
- El rol activo rompe las fachadas → compatibilidad de API como criterio de
  aceptación.
- Anti-escalada incompleta con permisos directos → reutilizar
  `CapacidadAdministrativa` del Sprint 16.
- Doble escritura/espejo divergente → no retirar el legado y verificar la
  equivalencia.
- Middleware de expulsión con sesiones "recordarme" → pruebas explícitas.
- Alcance excesivo → HU-USR-07 recortable a "crear + eliminar con reglas".

Estimación: HU-USR-09 S; HU-USR-08 S–M; HU-USR-04 M–A; HU-USR-05 M; HU-USR-06
S–M; HU-USR-07 M. Sprint de 2 a 3 semanas con hito intermedio tras HU-USR-04
(comparable al Sprint 16: 65 pruebas específicas).

## Registro de ejecución

- **Aprobación del alcance (17 de octubre de 2026):** el Product Owner aprobó
  las 6 HU (HU-USR-04 a HU-USR-09), el orden de implementación, la estimación,
  las decisiones formalizadas en ADR-013 y el plan de documentación. Las
  capacidades se encuentran en implementación: este documento no registra
  ninguna capacidad del sprint como implementada.

## Cierre pendiente

El cierre del Sprint 18 sigue el patrón de los sprints anteriores (ver
[Sprint 17 — Eliminación segura de Copropiedades](sprint-17-eliminacion-copropiedades.md))
y queda pendiente de la ejecución. Al terminar la implementación se registrará:

1. Estado de los criterios de aceptación de las 6 HU.
2. Pruebas específicas del sprint y suite completa sin regresiones.
3. Diagnóstico `resuelve:verificar-equivalencia-autorizacion-contextual` en 0
   divergencias.
4. Deuda resultante y evidencias para la entrega académica (incluido el
   fixture de demostración de HU-USR-09).
5. Acta de cierre con fecha y aprobación del Product Owner.

Hasta entonces, ninguna capacidad de este sprint se presenta como
implementada.
