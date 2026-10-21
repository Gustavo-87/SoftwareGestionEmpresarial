# Sprint 18 — Seguridad de acceso: rol activo, permisos directos y ciclo de vida de usuarios

Estado: completado (alcance, ampliaciones y aceptación visual aprobados por el
Product Owner el 21 de octubre de 2026).
Aprobación del Product Owner: 17 de octubre de 2026 (alcance); 21 de octubre de
2026 (implementación, ampliaciones y cierre).

Desarrolla HU-USR-04 a HU-USR-09 (nuevas) de la Épica Usuarios y Seguridad del
Product Backlog: rol activo con segregación de funciones, permisos directos por
usuario, matriz global de permisos "ver como usuario", administración de
permisos, ciclo de vida de usuarios por desactivación y endurecimiento de las
reglas de roles con fixture de demostración.

## Control documental

- **Versión:** v1.4
- **Estado:** Completado (aprobación y cierre formal del Product Owner, 21 de octubre de 2026)
- **Fecha de aprobación:** 17 de octubre de 2026 (alcance); 21 de octubre de 2026 (cierre formal)
- **Errata:** 17 de octubre de 2026 — correcciones documentales D1–D4
  (alcance de HU-USR-08, vocabulario de auditoría y baseline real de pruebas y
  divergencias RBAC)
- **Actualización:** 20 de octubre de 2026 — aclaración del Product Owner sobre
  eliminación lógica de permisos (HU-USR-07) y registro de ejecución completo
- **Ampliaciones:** 21 de octubre de 2026 — experiencia de inicio de sesión
  (rol activo al ingresar, protección contra intentos fallidos y recuperación
  de contraseña) y reactivación de Membresías suspendidas
- **Aprobación:** Product Owner (17 y 21 de octubre de 2026)
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
   legado↔Spatie ejecutado con el criterio de verificación corregido (el gate
   no exige 0 divergencias artificiales; ver «Baseline real y divergencias RBAC
   conocidas»).
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

Criterios de aceptación (redactados con la aclaración del Product Owner del
20 de octubre de 2026 sobre eliminación lógica):

1. Alta con clave "modulo.accion", nombre, descripción y módulo; validación de
   unicidad y formato (claves canónicas en minúsculas; el módulo debe coincidir
   con el prefijo de la clave).
2. Eliminación lógica y auditada: los permisos base del catálogo inicial están
   protegidos; un permiso personalizado no puede desactivarse mientras esté
   asignado a otros Roles o directamente a usuarios (el mensaje identifica su
   uso); la asignación automática al Rol base `admin` no cuenta como uso; si
   únicamente conserva esa asignación se desactiva y se retira en la misma
   transacción; el registro y su auditoría se conservan (la fila nunca se
   borra); un permiso inactivo no concede acceso ni admite nuevas asignaciones.
3. El rol admin base hereda los permisos nuevos (invariante del catálogo base;
   la reactivación restablece la herencia).
4. Solo autoridad de plataforma; auditoría completa (`permiso.crear`,
   `permiso.desactivar`, `permiso.activar`).
5. Pruebas: creación, herencia al admin, bloqueo de eliminación en uso (roles y
   usuarios), eliminación lógica con retiro de la herencia, permiso inactivo
   sin acceso ni asignaciones, 403 y actualización de la matriz por módulo.

### HU-USR-08 — Usuarios inactivos

Alcance: **no es crear un flujo nuevo**. La desactivación global ya existe
(`app/Application/Usuarios/DesactivarUsuarioGlobal.php` pone
`users.estado = 'inactivo'` y `users.desactivado_at`, suspende las Membresías
activas y prohíbe desactivar la propia cuenta o al último administrador del
sistema; rutas `admin.usuarios-globales.desactivar`/`reactivar`; auditoría
`usuario_global.deactivate`/`usuario_global.reactivate`; pruebas en
`tests/Feature/Admin/UsuarioGlobalCrudTest.php`). Esta historia cierra los
huecos restantes:

Criterios de aceptación:

1. Bloqueo en AuthController@store: un usuario con `users.estado ≠ 'activo'`
   no inicia sesión (mensaje inequívoco).
2. Middleware de expulsión de sesión: se invalidan la sesión abierta y el token
   "recordarme" del usuario desactivado en la siguiente petición.
3. Integración contextual con `usuarios.gestionar` y `CapacidadAdministrativa`:
   no se desactiva la propia cuenta ni al último administrador efectivo de la
   Copropiedad (el flujo global conserva su protección del último administrador
   del sistema) y un usuario desactivado no cuenta para la protección "≥1
   miembro con usuarios.gestionar".
4. Auditoría `usuario.desactivar`/`usuario.activar` en el flujo contextual (el
   flujo global conserva `usuario_global.deactivate`/`usuario_global.reactivate`,
   sin renombrar; ver la convención de vocabulario en ADR-013).
5. Regla de reactivación de las Membresías suspendidas por la desactivación
   (decisión del Product Owner, 17 de octubre de 2026): al reactivar se
   restauran exclusivamente las Membresías suspendidas como consecuencia de la
   desactivación (motivo `desactivacion_cuenta` registrado en
   `motivo_terminacion`); las suspensiones por otros motivos y las anteriores
   a la regla, sin motivo demostrable, se conservan.
6. Pruebas de bloqueo, expulsión, reactivación y protección administrativa.

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

Después de cada paso debe ejecutarse el diagnóstico
`resuelve:verificar-equivalencia-autorizacion-contextual` y la suite completa
debe permanecer sin regresiones. El gate no exige 0 divergencias artificiales:
primero se corrige el criterio de verificación (ver «Baseline real y
divergencias RBAC conocidas») y las divergencias conocidas ya están clasificadas.

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

## Baseline real y divergencias RBAC conocidas (17 de octubre de 2026)

Estado inicial verificado en la auditoría preventiva del 17 de octubre de 2026
(sustituye a las cifras anteriores de "546 pruebas" y "diagnóstico en 0
divergencias"):

- **Suite de pruebas (estado inicial):** 594 pruebas — 588 aprobadas, 6
  omitidas (las 6 requieren MySQL real) y 2698 aserciones.
- **Baseline actualizado tras HU-USR-09 (commit `de5b241`, 17 de octubre de
  2026):** 611 pruebas — 605 aprobadas, 6 omitidas (las 6 que requieren MySQL
  real) y 2757 aserciones. Las 32 divergencias se mantienen sin incremento
  (10 + 22) tras el diagnóstico de solo lectura.
- **Divergencias RBAC conocidas en la BD local:** 32 (10 entre `users.role` y el
  contexto, 22 entre Spatie y el legado), con 3 causas raíz: (1) renombre de
  roles desde la interfaz en el Sprint 16 (`audit_logs` #59–63), amplificado por
  el defecto de sensibilidad del verificador; (2) ampliación de la matriz de
  Apoyo (#144); y (3) bloque de datos de prueba del 3 de octubre (usuarios
  21–26, orgs 19–24, copropiedades 17–21 y roles `rol-fk-…`).
- **Regla del Product Owner:** el gate no exige 0 divergencias artificiales.
  Primero se corrige el criterio de verificación —el verificador actual compara
  en PHP con sensibilidad a mayúsculas mientras SQL usa colación `*_ci` y exige
  un solo rol— con el contrato propuesto por Arquitectura (normalización
  canónica + multi-rol + severidad). Las divergencias actuales están clasificadas
  como deriva de datos locales documentada, con plan de saneamiento en
  preparación (sin ejecutar).

## Riesgos y estimación

Riesgos:

- Regresión de autorización (base tras HU-USR-09 del 17 de octubre de 2026:
  611 pruebas, 605 aprobadas y 6 omitidas que requieren MySQL real; estado
  inicial del sprint: 594 pruebas, 588 aprobadas y 6 omitidas) → suite completa
  y diagnóstico RBAC por cada HU.
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
- **HU-USR-09 (17 de octubre de 2026, commit `de5b241`):** endurecimiento de
  reglas de roles (eliminación con conteo, protección del catálogo inicial con
  normalización canónica, admin base inmutable, anti-escalada verificada) y
  fixture de demostración `DemoRbacFixtureSeeder` (usuario con 2 Roles y 1
  permiso directo).
- **HU-USR-08 (17 de octubre de 2026, commit `68418de`):** bloqueo de inicio de
  sesión para `users.estado ≠ 'activo'`, expulsión de sesión y token
  "recordarme", integración contextual con `usuarios.gestionar` /
  `CapacidadAdministrativa`, auditoría `usuario.desactivar`/`usuario.activar` y
  reactivación que restaura exclusivamente las Membresías suspendidas por la
  desactivación.
- **HU-USR-04 (20 de octubre de 2026):** rol activo por Membresía/Copropiedad
  persistido en `membresias_copropiedad.rol_activo_id`, revalidado al resolver
  el contexto (una selección caducada se descarta y recupera la unión de
  Roles), integrado en `/cambiar-contexto`, con selector visible y rol actual
  indicado en la navegación. `ContextoOperativo`, `AutorizacionContextual` y
  Policies conservan su API (se añaden `rolActivoClave`, `rolesAsignados` y
  `tieneRolActivo()`).
- **HU-USR-05 (20 de octubre de 2026):** permisos directos sobre
  `model_has_permissions` con `team` = Copropiedad activa (alta, baja y
  listado) mediante `GestionarPermisosDirectos`, anti-escalada idéntica a
  Roles (autoridad de plataforma exenta), auditoría
  `usuario.permiso_directo.asignar`/`.revocar` y protección de
  `usuarios.gestionar` al revocar mediante `CapacidadAdministrativa`, que
  ahora evalúa la capacidad efectiva incluyendo permisos directos y el Rol
  activo. Los permisos desactivados no admiten nuevas asignaciones.
- **HU-USR-06 (20 de octubre de 2026):** matriz global "ver como usuario" en
  `GET /usuarios/{user}/permisos` con `ConsultaMatrizPermisosUsuario`,
  reutilizando la agrupación por módulo de `ConsultaPermisosCatalogo`
  (`catalogoAgrupado()`, misma consulta que expone `porModulo()` con su
  autorización de `roles.gestionar`). Cada celda indica el origen (`rol
  activo`, `rol {nombre}` o `directo`), respeta la Membresía vigente, la
  Copropiedad activa y el Rol activo del usuario consultado; solo lectura,
  requiere `usuarios.gestionar` o autoridad de plataforma (403 en backend).
- **HU-USR-07 (20 de octubre de 2026):** catálogo de permisos en
  `admin.permisos.*` (`PermisoCatalogoController` +
  `GestionarCatalogoPermisos`), solo autoridad de plataforma, con la
  eliminación lógica y auditada aclarada por el Product Owner (ver criterios).
  La invariante del catálogo base se mantiene con `CatalogoPermisosBase`
  (25 claves protegidas).
- **Diagnóstico de equivalencia (20 de octubre de 2026):** se aplicó
  únicamente el contrato aprobado (normalización canónica + multi-rol +
  severidad) a `VerificadorEquivalenciaAutorizacionContextual`,
  `VerificadorEquivalenciaRbacSpatie` y al comando
  `resuelve:verificar-equivalencia-autorizacion-contextual`, que ahora falla
  solo ante divergencias de severidad `error` y clasifica como `aviso` la
  deriva explicada (usuarios globales, multi-rol, permisos directos,
  ampliaciones de matriz y asignaciones extra). Resultado sobre la BD de
  desarrollo (solo lectura, 20 de octubre de 2026): **10 divergencias (3 error,
  7 aviso)** frente a las **32** del criterio anterior, sin divergencias nuevas
  respecto de las 3 causas raíz documentadas. Los 3 `error` son los Roles
  `rol-fk-…` del bloque de datos de prueba del 3 de octubre (causa 3); los
  avisos corresponden a usuarios globales sin Membresía (4), permisos
  adicionales por multi-rol (1), la ampliación de la matriz de Apoyo (1) y una
  asignación de Spatie sin contraparte legado activa (1). No se ejecutaron
  saneamientos ni resincronizaciones.

### Verificación del 20 de octubre de 2026

- Pruebas específicas del sprint: `RolActivoHuUsr04Test` (9),
  `PermisosDirectosHuUsr05Test` (8), `MatrizPermisosHuUsr06Test` (6),
  `CatalogoPermisosHuUsr07Test` (11), `VerificadorEquivalenciaContratoTest`
  (5) y las de HU-USR-08/09 ya cerradas: **39 pruebas nuevas, 0 fallos**.
- Suite completa: **667 pruebas — 659 aprobadas, 8 omitidas, 3.003 aserciones,
  0 fallos**. Las 8 omitidas son las que requieren MySQL real.
- Concurrencia MySQL sobre base de pruebas aislada
  (`resuelve_concurrencia_test`, migrada por separado):
  `RolActivoPermisosDirectosMysqlConcurrencyTest` 2/2 (dos revocaciones
  simultáneas de permiso directo con capacidad conservada; selección de Rol
  activo revalidada tras revocación concurrente), `RolesAsignacionMysqlConcurrencyTest`
  1/1, `PqrCommunicationMysqlConcurrencyTest` 3/3 y
  `PqrCommunicationMysqlRollbackTest` 1/1. Nota previa: la prueba de rollback
  activa su guarda de protección ("el ledger contiene operaciones reales") si
  se ejecuta después de la de concurrencia sobre la misma base; aislada con
  ledger limpio aprueba sus 18 aserciones.
- `npm run build` y `git diff --check`: correctos. El techo de memoria del
  proceso de pruebas se elevó a 512M solo en `phpunit.xml` (la exportación XLSX
  de `Sprint3RemainingConsumersTest` supera el límite de 128M al ejecutar la
  suite completa; aislada aprueba).
- Las migraciones `2026_10_20_000100` y `2026_10_20_000200` (aditivas) se
  aplicaron a la base de desarrollo; no se ejecutaron seeders ni saneamientos.

## Cierre formal — Acta de cierre (21 de octubre de 2026)

El Product Owner aprobó el 21 de octubre de 2026 los cambios revisados del
Sprint 18 y su aceptación visual, incluidas las ampliaciones de la experiencia
de inicio de sesión y de la reactivación de Membresías. Cumplimiento de los
cinco puntos de cierre:

1. **Criterios de las 6 HU (HU-USR-04 a HU-USR-09):** implementados y
   verificados, con experiencia web completa, autorización en backend,
   aislamiento por Organización y Copropiedad, anti-escalada y protecciones de
   última capacidad administrativa. Ampliaciones aprobadas incorporadas:
   experiencia de inicio de sesión (rol activo al ingresar, protección contra
   intentos fallidos y recuperación de contraseña) y reactivación de
   Membresías suspendidas (§7.4.1 de roles-y-permisos).
2. **Pruebas:** 88 pruebas específicas del sprint (419 aserciones) y suite
   completa **699 pruebas — 691 aprobadas, 8 omitidas (MySQL), 3.201
   aserciones, 0 fallos**. Concurrencia MySQL verificada sobre base de pruebas
   aislada (7 pruebas).
3. **Diagnóstico RBAC** con el criterio corregido (normalización canónica +
   multi-rol + severidad), de solo lectura, sin regresiones frente a las causas
   raíz documentadas: **3 errores y 6 avisos** (ver «Pendientes explícitos»).
4. **Deuda resultante y evidencias:** registradas en ADR-012/ADR-013 y en los
   pendientes de este acta; fixture de demostración disponible (HU-USR-09) y
   evidencia visual en `storage/evidence/acceso-sprint18/`.
5. **Aprobación del Product Owner:** concedida el 21 de octubre de 2026.

### Pendientes explícitos (no presentados como resueltos)

- **Envío real de la recuperación de contraseña:** el entorno usa
  `MAIL_MAILER=log` (sin proveedor SMTP/API ni credenciales) y el remitente es
  `hello@example.com`. El flujo está verificado con correo de pruebas
  (`MAIL_MAILER=array`) y con el transporte `log`; la entrega real **no está
  verificada**. Falta un proveedor efectivo, un remitente verificado y un buzón
  de recepción.
- **Divergencias de datos conocidas (BD de desarrollo):** 3 errores (Roles
  `rol-fk-…` del bloque de datos de prueba del 3 de octubre) y 6 avisos
  (usuarios globales sin Membresía vigente, permisos adicionales por multi-rol
  —incluido el usuario de demostración con dos Roles usado en la verificación—
  y la ampliación de la matriz de Apoyo). El plan de saneamiento permanece en
  preparación y **no fue ejecutado** por no estar autorizado.
- **Pruebas MySQL:** 8 omitidas en la suite por requerir MySQL real; sus
  equivalentes se verificaron sobre base de pruebas aislada. La prueba de
  rollback de la comunicación conserva su guarda de protección si se ejecuta
  después de la de concurrencia sobre la misma base (interacción previa, no
  bloqueante).

## Ampliación — Experiencia de inicio de sesión (21 de octubre de 2026)

Ampliación autorizada del Sprint 18 (no es una HU nueva): completa la
experiencia de inicio de sesión sobre las capacidades ya implementadas de rol
activo, permisos directos y revalidación contextual. **El sprint permanece en
curso y su cierre formal aguarda la aprobación del Product Owner.**

### 1. Rol activo al ingresar

- Se conserva el inicio de sesión por correo único y contraseña
  (`AuthController@store`, con normalización del correo).
- Después de validar las credenciales se resuelven la Copropiedad y los Roles
  autorizados (`AccesoSeleccionController`, rutas `acceso.seleccion`,
  `acceso.copropiedad`, `acceso.rol`):
  - varias Copropiedades → se solicita primero seleccionar una (Paso 1 de 2);
  - varios Roles → se exige seleccionar uno antes de acceder a la operación
    (Paso 2 de 2);
  - un único Rol → se selecciona automáticamente mediante
    `GestionarRolActivo`, reutilizando su persistencia por Membresía y su
    revalidación contextual.
- No se muestran Roles ni información de la cuenta antes de validar las
  credenciales: las pantallas de selección son rutas autenticadas.
- El middleware `SeleccionAccesoPendiente` (alias `acceso.pendiente`) impide
  acceder a rutas operativas omitiendo la selección (bandera de sesión del
  inicio de ingreso y, como refuerzo, varios Roles sin activo válido —por
  ejemplo, tras revocarlo—). Reutiliza `ContextoOperativo` memoizado, sin
  consultas adicionales.
- Se conservan los flujos específicos del administrador de plataforma
  (`admin.index`) y de los usuarios sin membresía operativa (contexto
  institucional).

### 2. Protección contra intentos fallidos

- Tres intentos fallidos por cuenta y bloqueo temporal de 15 minutos
  (`RateLimiter` de Laravel: `INTENTOS_CUENTA = 3`, `BLOQUEO_SEGUNDOS = 900`).
- Control combinado por cuenta e IP (`INTENTOS_IP = 20`) para reducir intentos
  distribuidos sin bloquear a toda una red por un único error.
- Los ingresos correctos no acumulan intentos y limpian los contadores.
- El mensaje informa cuándo se puede reintentar («Podrás intentarlo de nuevo en
  N minutos») y es idéntico exista o no la cuenta.
- Verificado mediante solicitudes HTTP directas al endpoint (sin interfaz).

### 3. Recuperación de contraseña

- Se reutiliza el broker de contraseñas de Laravel (`Password::sendResetLink`
  y `Password::reset`) con sus vistas existentes (`auth.forgot-password`,
  `auth.reset-password`) y `password_reset_tokens`.
- Flujo completo: solicitud, envío del enlace, formulario y confirmación. El
  enlace caduca a los 60 minutos (`config/auth.php`) y queda inutilizable tras
  usarse (el token se elimina al restablecer).
- Limitación de solicitudes: 3 por cuenta y 10 por IP en 15 minutos, además del
  throttle interno del broker (60 s). Las respuestas son siempre neutras («Si
  existe una cuenta con ese correo…»), tanto para la solicitud como para el
  formulario, y no revelan si la cuenta existe.
- **Configuración de correo comprobada (sin mostrar credenciales):** el entorno
  de desarrollo usa `MAIL_MAILER=log`, sin usuario SMTP; el mensaje se escribe
  en `storage/logs/laravel.log` (comprobado con una solicitud real). Las
  pruebas usan `MAIL_MAILER=array` (correo de pruebas) y verifican la
  notificación `ResetPassword`. **Falta para verificar una entrega real:** un
  proveedor de correo efectivo (SMTP con host/puerto/credenciales o API), un
  remitente verificado (hoy `MAIL_FROM_ADDRESS` es un placeholder) y un buzón
  real donde confirmar la recepción. No se afirma que el envío real funcione.

### 4. Presentación

- Se conserva la identidad visual del login (`auth.partials.shell`); la
  selección de Copropiedad y Rol se presenta como pasos numerados y compactos
  («Paso 1 de 2 · Copropiedad», «Paso 2 de 2 · Rol activo») con opciones de
  radio accesibles, «Continuar» y «Cerrar sesión».
- Errores, bloqueo temporal y recuperación se presentan con mensajes
  comprensibles en el propio formulario.
- Verificación visual en navegador (Chrome, capturas en
  `storage/evidence/acceso-sprint18/`): escritorio 1366×768 y móvil 390 px
  reales, sin desbordamiento horizontal. Navegación por teclado verificada por
  orden de foco natural (7 controles, `tabindex=0`) y contorno `:focus-visible`
  de 3 px en cada control.

### 5. Verificación

- Pruebas: `AccesoSeleccionTest` (9), `AccesoIntentosFallidosTest` (6),
  `RecuperacionContrasenaTest` (6) y `AuthenticationTest` (actualizado a la
  política de 3 intentos/15 minutos con aislamiento por cuenta e IP).
- Cobertura: cuentas con uno y varios Roles, varias Copropiedades, Rol activo
  revocado, usuarios inactivos (HU-USR-08), administrador de plataforma,
  usuarios sin membresía operativa, tercer intento fallido, expiración del
  bloqueo a los 15 minutos, caducidad y uso único del enlace de recuperación,
  límite de solicitudes y ausencia de revelación de cuentas.
- Suite completa: **688 pruebas — 680 aprobadas, 8 omitidas (MySQL), 3.139
  aserciones, 0 fallos**. `npm run build` y `git diff --check` correctos.
- Pruebas de ajuste de una prueba previa (`RolesAsignacionTest`): el actor
  multi-rol completa ahora la selección antes de operar, sin cambiar el
  comportamiento verificado de la protección «≥1 administrador».

### Pendientes de esta ampliación

- Aceptación visual y funcional del Product Owner (incluida la verificación
  manual de teclado en su entorno).
- Configuración de un proveedor de correo real para verificar la entrega de los
  enlaces de recuperación (ver punto 3).
- El cierre formal del Sprint 18 continúa pendiente de la aprobación final.

## Asignación de varios Roles a un usuario (21 de octubre de 2026)

La función ya estaba implementada desde el Sprint 16 (sin duplicar
funcionalidad) y su acceso visible se reetiquetó para hacerla encontrable:

- **Ruta:** Usuarios → botón «Editar» del usuario → sección 02 «Roles de la
  Copropiedad activa» (`GET /usuarios/{user}/editar`, vista `users/edit`).
- **Permiso exigido:** `usuarios.gestionar` en la Copropiedad activa (403 en
  backend sin él).
- **Acciones:** «Asignar rol» (selector con los Roles globales y del equipo
  disponibles, `POST /usuarios/{user}/roles`) y «Revocar» por cada Rol
  asignado (`DELETE /usuarios/{user}/roles/{rol}`). Backend:
  `AsignarRolUsuario` / `RevocarRolUsuario`, con anti-escalada (solo Roles
  cuyos permisos posee el operador), protección de la última capacidad
  administrativa, exigencia de Membresía activa y vigente y auditoría
  (`usuario.rol.asignar` / `usuario.rol.revocar`).
- **Rótulo actualizado:** «Roles en esta Copropiedad» → «Roles de la
  Copropiedad activa», con la indicación de que los Roles se agregan y revocan
  individualmente. La sección 01 conserva el campo «Rol» legado
  (`users.role`), que no administra los Roles contextuales.

### Verificación real en navegador (21 de octubre de 2026)

Sobre la aplicación real (`localhost:8083`), con las cuentas de demostración
restauradas mediante el flujo de recuperación de contraseña:

1. alta del Rol «Auditor» a un usuario con «Residente» en Usuarios → Editar →
   Roles de la Copropiedad activa (`302` de éxito, aviso «Rol asignado
   correctamente», ambos Roles listados con «Revocar» y auditoría
   `usuario.rol.asignar {"rol":"Auditor","rol_id":3,"copropiedad_id":1}`);
2. cierre de sesión y nuevo inicio de sesión de ese usuario: redirige a
   `/seleccionar-acceso` con el selector «Paso 2 de 2 · Rol activo», mostrando
   «Auditor — Rol autorizado» y «Residente — Rol activo anterior».
- El intento sobre un usuario cuya Membresía está suspendida devuelve «La
  Membresía del usuario no está activa y vigente en esta Copropiedad»
  (validación de backend correcta).
- Evidencia visual: `storage/evidence/acceso-sprint18/10-usuarios-editar-roles.png`
  (Usuarios → Editar con dos Roles asignados) y
  `11-selector-rol-ingreso-movil.png` (selector al reingresar).

## Reactivación de Membresías suspendidas (21 de octubre de 2026)

Diagnóstico del fallo reportado («no puedo reactivar membresías suspendidas»):
no existía acción de reactivación para Membresías (`MembresiaController`
exponía solo `suspender` y `finalizar`) y la única restauración era la de la
regla HU-USR-08 al reactivar la cuenta. Tres casos distinguibles:

1. **Suspensión manual** (`SuspenderMembresia`): `estado=suspendida`, motivo
   libre y `vigente_hasta` cortado al suspender. Sin acción de reactivación.
2. **Suspensión por desactivación de la cuenta** (motivo
   `desactivacion_cuenta`): se restaura al reactivar la cuenta; su vigencia no
   se altera.
3. **Finalizada** (`FinalizarMembresia`) **o vencida** (`vigente_hasta`
   pasado): el diseño aprobado no permite convertirlas automáticamente en
   activas.

Decisión del Product Owner (21 de octubre de 2026): **ampliar la reactivación
también a las suspensiones manuales**, con la regla de vigencia definida en
[roles-y-permisos.md §7.4.1](../02-funcional/roles-y-permisos.md). Complementos
aplicados sin alterar lo aprobado: la reactivación de cuenta ya no convierte en
activas las Membresías vencidas y su resultado informa las restauradas y los
motivos concretos de las conservadas.

### Implementación y verificación

- `ReactivarMembresia` (nuevo caso de uso) con validaciones y motivos
  concretos: usuario activo, coherencia de contexto, Organización y
  Copropiedad activas y vigencia no finalizada; conserva los Roles asignados.
  `MembresiaController::reactivar`, ruta `admin.membresias.reactivate` y
  acción visible «▶ Reactivar membresía» en la ficha de Membresías, con
  indicación del tipo de suspensión. Las Membresías finalizadas muestran la
  explicación y el motivo, sin acción. Auditoría `membresia.reactivate`.
- `DesactivarUsuarioGlobal::reactivar` conserva la regla HU-USR-08 (no
  restaura suspensiones manuales), excluye las vencidas y devuelve un resumen
  visible («Membresías restauradas: N. No restauradas: …»).
- Verificación en la app real (navegador): la membresía suspendida manualmente
  de `carlos.mejia` muestra el botón, el motivo y los Roles conservados; al
  reactivarla queda `activa` conservando su vigencia 31/12/2026; una Membresía
  finalizada rechaza la reactivación con «La membresía está finalizada
  (motivo: …). El diseño aprobado no permite reactivar membresías finalizadas.»
- Pruebas: `MembresiaReactivacionTest` (9) cubren los tres casos, la
  restauración de vigencia, las validaciones con motivos, conservación de
  Roles, autoridad de plataforma, visibilidad de la acción y la exclusión de
  suspensiones manuales en la reactivación de cuenta. Suite completa:
  **699 pruebas — 691 aprobadas, 8 omitidas (MySQL), 3.201 aserciones, 0
  fallos**. Evidencia visual:
  `storage/evidence/acceso-sprint18/12-membresia-suspendida-con-reactivar.png`,
  `13-membresia-reactivada.png` y `14-membresia-finalizada-bloqueada.png`.
- El estado de la membresía de demostración se restauró al final de la
  verificación (suspendida, motivo y vigencia originales).

### Acta de cierre — aprobación registrada (21 de octubre de 2026)

- **Alcance:** HU-USR-04 a HU-USR-09, con experiencia web completa,
  autorización en backend, aislamiento por Organización y Copropiedad y
  protecciones anti-escalada y de última capacidad administrativa.
- **Ampliaciones aprobadas:** experiencia de inicio de sesión y reactivación
  de Membresías suspendidas.
- **Evidencia final:** 88 pruebas específicas del sprint y suite completa de
  699 (691 aprobadas, 8 omitidas que requieren MySQL, 3.201 aserciones,
  0 fallos); concurrencia MySQL sobre base aislada; diagnóstico RBAC de solo
  lectura con 3 errores y 6 avisos clasificados (ver «Pendientes explícitos»).
- **Deuda resultante:** la registrada en ADR-012/ADR-013 (retiro futuro del
  legado, integración controlada de Gate, definiciones de Roles por
  Organización) y los pendientes explícitos de este acta.
- **Aprobación del Product Owner:** concedida el 21 de octubre de 2026
  (cambios revisados y aceptación visual).
