# Roles y permisos

## 1. Propósito

Este documento describe el control de acceso implementado en Resuelve. La
matriz se deriva del modelo `User`, la política de PQR, las validaciones de los
controladores y las condiciones de las vistas.

## 2. Implementación general

Desde el Sprint 15 la fuente efectiva de roles y permisos es el RBAC de
`spatie/laravel-permission` (ver [ADR-011](../10-adr/ADR-011-spatie-rbac-contextual.md)):

- **Copropiedad como equipo (team):** `team_foreign_key = 'copropiedad_id'`.
  Las asignaciones de roles son siempre por Copropiedad activa.
- **Organización como frontera de tenant:** el aislamiento
  Organización/Copropiedad se conserva en consultas, claves foráneas y
  políticas.
- **`MembresiaCopropiedad` como requisito de vigencia:** sin membresía activa y
  vigente no se resuelven roles ni permisos, aunque existan asignaciones en
  Spatie.
- **`ContextoOperativo` como snapshot de autorización:** `roles[]` y
  `permisos[]` se resuelven por petición desde Spatie y se exponen mediante
  `clavesRoles()` y `clavesPermisos()`.
- **`AutorizacionContextual` como fachada estable:** `tienePermiso()`,
  `tieneRol()`, `puedeVerPqr()` y `puedeGestionarPqr()` conservan su API y las
  Policies delegan en ella.

Catálogo inicial: **5 roles base** (`admin`, `gestor`, `apoyo`, `auditor`,
`residente`), **24 permisos** y **63 relaciones rol-permiso**. Dos permisos
nuevos participan como datos y se usarán en el sprint de autorización:
`pqrs.gestionar_asignadas` (rol `apoyo`) y `pqrs.ver_borradores` (roles
`admin` y `gestor`).

Compatibilidad temporal conservada:

- `users.role` es un campo legado que ya no decide autorización; se mantiene
  con doble escritura para rollback y diagnóstico.
- El RBAC contextual legado (`roles_contextuales`, `permisos_contextuales`,
  `rol_permiso_contextual`, `membresia_copropiedad_rol`) es espejo temporal de
  compatibilidad y diagnóstico.
- `resuelve:migrar-rbac-spatie` sincroniza de forma idempotente y
  `resuelve:verificar-equivalencia-autorizacion-contextual` detecta divergencias
  sin corregirlas.
- `register_permission_check_method` permanece en `false`: el Gate de Spatie no
  interviene en `can()`/`@can` hasta el sprint de autorización y UI.

Los valores aceptados al crear, editar o cambiar el rol de un usuario
permanecen siendo `admin`, `gestor`, `apoyo`, `auditor` y `residente` mientras
dure la transición.

### Administración de roles y permisos (Sprint 16)

Modelo híbrido aprobado (ver [ADR-012](../10-adr/ADR-012-administracion-dinamica-roles-permisos.md)):

- **Roles base globales:** catálogo inicial (5 roles) administrado por la
  autoridad de plataforma desde la consola administrativa. Son datos
  iniciales, no un catálogo cerrado.
- **Roles personalizados por Copropiedad:** los crea y administra el usuario
  con el permiso `roles.gestionar` dentro de su Copropiedad (equipo). No
  afectan a otras Copropiedades.
- **Permisos por módulo:** el catálogo se presenta agrupado por módulo
  (`pqrs.*`, `documentos.*`, `mantenimiento.*`, `informes.*`, `roles.*`,
  `usuarios.*`, …) y se asigna a los roles desde la web.
- **Asignación y revocación por Copropiedad:** desde la consola de usuarios,
  sobre la Membresía vigente, con confirmación, mensajes y auditoría.
- **Anti-escalada:** un operador no puede conceder permisos ni roles cuyos
  permisos no posee; la autoridad de plataforma está exenta.
- **Capacidad administrativa efectiva:** una Copropiedad nunca queda sin al
  menos un miembro vigente con el permiso `usuarios.gestionar`, tanto al
  revocar roles como al eliminar usuarios. La protección evalúa capacidades
  efectivas, nunca nombres de rol.
- **Roles personalizados:** un rol con los permisos adecuados obtiene el mismo
  comportamiento que un rol base equivalente; el nombre del rol no concede
  capacidades. Las capacidades semánticas son `pqrs.ver_borradores` (borradores
  ajenos) y `pqrs.gestionar_asignadas` (gestión restringida a la PQR sin
  asignar o propia).

## 3. Capacidades auxiliares del usuario

| Método | Roles incluidos |
| --- | --- |
| `canViewAllPqrs()` | Administrador, gestor, auditor y apoyo. |
| `canManagePqrs()` | Administrador, gestor y apoyo. |
| `isAdmin()` | Administrador. |

Estas funciones son **compatibilidad legada** sobre `users.role` y ya no
deciden autorización; se retirarán al terminar la transición (ver ADR-011).
Estas funciones no expresan por sí solas todos los permisos. Algunas acciones
usan la política y otras consultan directamente el rol.

## 4. Política de PQR

| Acción de política | Regla |
| --- | --- |
| `viewAny` | Permitida para todo usuario autenticado. |
| `view` | Permitida si el usuario ve todas las PQR o si es el radicador. |
| `create` | Permitida para todo usuario autenticado. |
| `update` | Administrador y gestor; apoyo si no hay responsable o si está asignada a él. |
| `delete` | Administrador y gestor. |

Laravel descubre `PqrPolicy` por convención; no existe registro manual de la
política.

## 5. Matriz funcional

Leyenda:

- **Sí:** permitido por el código.
- **Propias:** permitido solo sobre PQR radicadas por el usuario.
- **Condicionado:** sujeto a asignación u otra regla indicada.
- **No:** rechazado por el backend o no expuesto como capacidad.

| Capacidad | Administrador | Gestor | Apoyo | Auditor | Residente |
| --- | --- | --- | --- | --- | --- |
| Iniciar y cerrar sesión | Sí | Sí | Sí | Sí | Sí |
| Recuperar contraseña | Sí | Sí | Sí | Sí | Sí |
| Actualizar perfil propio | Sí | Sí | Sí | Sí | Sí |
| Consultar listado de PQR | Sí | Sí | Sí | Sí | Propias |
| Consultar detalle de PQR | Sí | Sí | Sí | Sí | Propias |
| Radicar PQR | Sí | Sí | Sí | Sí | Sí |
| Editar PQR mediante formulario | Sí | Sí | Condicionado | No | No |
| Aplicar acción rápida | Sí | Sí | Condicionado | No | No |
| Eliminar PQR | Sí | Sí | No | No | No |
| Descargar adjunto principal | Sí | Sí | Sí | Sí | Propias |
| Sincronizar etiquetas | Sí | Sí | Condicionado | No | No |
| Crear borrador o enviar respuesta directa | Sí | Sí | Condicionado | No | No |
| Editar, enviar o retirar borrador propio | Propios con gestión efectiva | Propios con gestión efectiva | Propios con gestión efectiva | No | No |
| Descargar adjunto de respuesta | Sí | Sí | Oficiales y borradores propios con gestión efectiva | Oficiales | Oficiales propias |
| Agregar comentario interno | Sí | Sí | Condicionado | No | No |
| Ver borradores | Sí | Sí | Propios, con gestión efectiva | No | No |
| Ver comentarios internos | Sí | Sí | Condicionado | No | No |
| Registrar encuesta | Si es radicador | Si es radicador | Si es radicador | Si es radicador | Propias |
| Consultar notificaciones propias | Sí | Sí | Sí | Sí | Sí |
| Exportar informes | Sí | Sí | Sí | Sí | Propias |
| Gestionar configuración | Sí | Sí | No | No | No |
| Consultar carga de trabajo | Sí | Sí | No | No | No |
| Gestionar herramientas | Sí | Sí | No | No | No |
| Administrar usuarios y roles | Sí | No | No | No | No |
| Gestionar unidad de residentes | Sí | No | No | No | No |
| Consultar auditoría general | Sí | No | No | No | No |

## 6. Detalle por rol

### 6.1 Administrador

Es el único rol que puede:

- crear, editar y eliminar usuarios;
- cambiar roles;
- actualizar torre y unidad de residentes;
- consultar el registro general de auditoría.

También comparte con el gestor:

- gestión completa de PQR;
- eliminación de PQR;
- configuración de la copropiedad;
- herramientas operativas;
- carga de trabajo.

### 6.2 Gestor

Puede consultar y gestionar todas las PQR, eliminarlas, responderlas y usar las
funciones operativas. No puede administrar usuarios, residentes ni consultar
la auditoría general.

### 6.3 Apoyo

Puede consultar todas las PQR. La política permite actualizar una PQR cuando:

- no tiene responsable; o
- el responsable es el propio usuario de apoyo.

Los flujos de respuesta y comentario aplican esa misma gestión efectiva antes
de validar el payload. En el expediente, apoyo efectivo solo recibe sus propios
borradores; un borrador ajeno permanece invisible y su descarga responde `404`.

### 6.4 Auditor

Puede consultar el listado, el detalle y los archivos de todas las PQR. No
puede modificar PQR ni ver contenido interno condicionado por
`canManagePqrs()`.

El nombre del rol no implica acceso al módulo “Registro de auditoría”: dicho
módulo exige `isAdmin()`.

### 6.5 Residente

Puede:

- radicar PQR;
- consultar sus propias PQR;
- descargar archivos de sus propias PQR;
- ver respuestas enviadas;
- registrar una encuesta en sus PQR respondidas o cerradas;
- exportar informes limitados a sus PQR;
- gestionar su perfil y notificaciones.

No puede editar o eliminar una PQR después de radicarla.

## 7. Administración de cuentas

### 7.1 Creación

Solo el administrador puede crear usuarios. Se exige:

- nombre;
- correo único;
- rol válido;
- contraseña confirmada de mínimo ocho caracteres;
- torre y unidad opcionales.

La cuenta se crea con `email_verified_at` establecido en la fecha actual.

### 7.2 Edición y cambio de rol

Solo el administrador puede editar cuentas o cambiar roles. Un administrador
no puede retirarse a sí mismo el rol de administrador.

La regla protege el rol de la cuenta actual, pero no impide cambiar el rol de
otro administrador aunque sea el último. La protección de “al menos un
administrador” existe únicamente en la eliminación.

### 7.3 Eliminación

La eliminación es **irreversible** y está reservada a registros que no
deberían existir (duplicados, datos de prueba, cuentas erróneas). No se
permite:

- eliminar la propia cuenta;
- eliminar un usuario con PQR radicadas asociadas;
- eliminar a un usuario que sea propietario documental de Documentos activos;
- eliminar si la Copropiedad queda sin usuarios con el permiso
  `usuarios.gestionar` (protección por capacidad administrativa efectiva, no
  por nombre de rol).

La validación de PQR consulta la relación `user.pqrs`, no las PQR donde el
usuario sea responsable. La clave `assigned_to_id` usa `nullOnDelete`, por lo
que la base de datos permite eliminar un responsable sin PQR radicadas. La
eliminación limpia las asignaciones del usuario por equipo (roles y permisos
directos) y su Membresía en la Copropiedad, dentro de una transacción.

### 7.4 Desactivar frente a eliminar

**DESACTIVAR — camino estándar para retirar accesos.** Es reversible (el
usuario puede reactivarse), conserva el historial y la trazabilidad, expulsa
la sesión y bloquea el inicio de sesión, suspende las Membresías y no cuenta
para la capacidad administrativa mientras permanezca inactivo. La
desactivación global ya está implementada (`DesactivarUsuarioGlobal`:
`users.estado = 'inactivo'` y `users.desactivado_at`, con las prohibiciones de
la propia cuenta y del último administrador del sistema) y el flujo contextual
de HU-USR-08 (Sprint 18) cierra los huecos: bloqueo de inicio de sesión y
expulsión de la sesión para `users.estado ≠ 'activo'`, integración con
`usuarios.gestionar`/`CapacidadAdministrativa` y reactivación que restaura
exclusivamente las Membresías suspendidas por la desactivación (motivo
`desactivacion_cuenta`).

**ELIMINAR — irreversible y excepcional.** Queda reservado para registros que
no deberían existir (duplicados, datos de prueba, cuentas erróneas) y está
sujeto a las guardas de §7.3.

**Regla de coexistencia:** ante cualquier baja de personal se desactiva; la
eliminación requiere justificación explícita.

**Vocabulario de auditoría:** el flujo global conserva
`usuario_global.deactivate`/`usuario_global.reactivate`; el flujo contextual
(HU-USR-08) usará `usuario.desactivar`/`usuario.activar` (convención registrada
en [ADR-013](../10-adr/ADR-013-rol-activo-segregacion-funciones.md)).

### 7.4.1 Reactivación de Membresías suspendidas (decisión del Product Owner, 21 de octubre de 2026)

Regla de reactivación de Membresías, por caso de suspensión:

1. **Suspendida manualmente** (Administración → Membresías → Suspender, motivo
   libre): puede reactivarse desde la propia ficha de la Membresía. La
   suspensión manual corta `vigente_hasta`; al reactivar se conserva la
   vigencia si sigue siendo válida y, si fue cortada, se restituye la vigencia
   previa conocida por la auditoría (`membresia.suspend` → `before`) o se
   reabre de forma indefinida.
2. **Suspendida por desactivación de la cuenta** (motivo canónico
   `desactivacion_cuenta`): se restaura al reactivar la cuenta (regla HU-USR-08)
   y también puede reactivarse desde la ficha de la Membresía con el usuario ya
   activo. Su vigencia no se alteró al suspender y se conserva tal cual.
3. **Finalizada o vencida**: nunca se convierte automáticamente en activa. La
   ficha explica el motivo y no ofrece la acción.

La reactivación exige autoridad de plataforma, usuario activo, coherencia de
contexto (la Membresía pertenece a la Organización de su Copropiedad),
Organización y Copropiedad activas y vigencia no finalizada. Conserva los
Roles asignados, limpia `motivo_terminacion` y se audita como
`membresia.reactivate`. Cuando la operación se bloquea, el motivo concreto se
muestra en la interfaz. La reactivación de una cuenta **no** restaura
suspensiones manuales ni convierte finalizadas o vencidas en activas; su
resultado informa cuántas Membresías se restauraron y por qué se conservaron
las demás.

## 7.5 Rol activo con segregación de funciones (HU-USR-04)

Los permisos efectivos de un usuario en la Copropiedad activa son los permisos
del **Rol activo** más sus **permisos directos**. Sin selección explícita se
conserva la compatibilidad por defecto: la unión de los Roles asignados más los
permisos directos. La selección persiste en
`membresias_copropiedad.rol_activo_id` (una por Membresía/Copropiedad), se
elige desde `/cambiar-contexto` y se revalida al resolver el contexto: si el
Rol ya no está asignado, la selección se descarta y la Membresía recupera la
unión de Roles. El usuario solo puede seleccionar su propio Rol activo; el rol
efectivo se indica en la navegación.

**Al inicio de sesión (ampliación del Sprint 18):** después de validar las
credenciales se resuelven la Copropiedad y los Roles autorizados. Con varias
Copropiedades se selecciona primero una (Paso 1 de 2); con varios Roles se
exige elegir uno antes de acceder a la operación (Paso 2 de 2); con un único
Rol se selecciona automáticamente. El middleware `acceso.pendiente` impide
operar omitiendo la selección. El administrador de plataforma y los usuarios
sin membresía operativa conservan sus flujos específicos.

## 7.6 Permisos directos por usuario (HU-USR-05)

Se administran sobre `model_has_permissions` con `team` igual a la Copropiedad
activa (alta, baja y listado) desde la edición del usuario. Aplican la misma
anti-escalada que los Roles: ningún operador concede permisos que no posee y la
autoridad de plataforma queda exenta. Cada mutación se audita
(`usuario.permiso_directo.asignar`/`.revocar`) y exige `usuarios.gestionar` del
contexto (403 en backend). El permiso `usuarios.gestionar` no puede revocarse
si deja a la Copropiedad sin ningún miembro vigente que conserve la capacidad
administrativa efectiva (`CapacidadAdministrativa`, que contabiliza Roles y
permisos directos conforme al Rol activo). Un permiso desactivado no admite
nuevas asignaciones.

## 7.7 Matriz global de permisos "ver como usuario" (HU-USR-06)

`GET /usuarios/{user}/permisos` presenta, agrupada por módulo, la matriz del
catálogo con el origen de cada permiso: `rol activo`, `rol {nombre}` o
`directo`. Respeta la Membresía vigente del usuario consultado, la Copropiedad
activa y su Rol activo si está seleccionado (con Rol activo solo son efectivos
el Rol activo y los permisos directos). Es de solo lectura, requiere
`usuarios.gestionar` o autoridad de plataforma y responde 403 en backend. La
agrupación reutiliza la consulta del catálogo de `ConsultaPermisosCatalogo`
(`catalogoAgrupado()`), la misma que expone `porModulo()` con su autorización
de `roles.gestionar`.

## 7.8 Catálogo de permisos (HU-USR-07)

Solo la autoridad de plataforma administra el catálogo desde
`admin.permisos.*`. El alta exige clave `modulo.accion` (minúsculas, unicidad),
nombre, descripción y módulo coherente con el prefijo de la clave; el Rol base
`admin` hereda los permisos nuevos (invariante del catálogo base,
`CatalogoPermisosBase`). La eliminación es **lógica y auditada** (decisión del
Product Owner, 20 de octubre de 2026):

1. los permisos base del catálogo inicial están protegidos contra eliminación;
2. un permiso personalizado no puede desactivarse mientras esté asignado a
   otros Roles o directamente a usuarios, y el mensaje identifica su uso;
3. la asignación automática al Rol base `admin` no cuenta como uso;
4. si únicamente conserva esa asignación, se desactiva y se retira en la misma
   transacción;
5. el registro y su auditoría (`permiso.crear`, `permiso.desactivar`,
   `permiso.activar`) se conservan: la fila nunca se borra;
6. un permiso inactivo no concede acceso ni admite nuevas asignaciones.

## 8. Protección de rutas y recursos

### 8.1 Invitados

Las rutas de inicio y recuperación de contraseña usan middleware `guest`. El
resto de funciones de negocio se encuentra dentro del middleware `auth`.

### 8.2 Adjuntos principales

La descarga exige que el usuario pueda ver la PQR asociada al adjunto.

### 8.3 Notificaciones

La bandeja exige Membresía vigente y `notificaciones.consultar`. Todas las
consultas combinan destinatario, Organización y Copropiedad activas. La
apertura revalida además la visibilidad contextual de la PQR; una notificación
ajena, externa, revocada o inexistente produce la misma respuesta `404` y no se
marca como leída. El marcado masivo no afecta otros contextos.

### 8.4 Visibilidad de interfaz

La navegación oculta opciones según el rol, pero la seguridad efectiva depende
de los controladores y la política. Las condiciones de la vista no sustituyen
la autorización del backend.

### 8.5 Comunicaciones internas

La visibilidad de borradores se resuelve mediante roles y permisos del contexto
vigente, nunca mediante `users.role`. Administrador y gestor efectivos pueden
consultar borradores propios y ajenos; apoyo efectivo solo los propios. El
autor que pierde gestión efectiva recibe `403` al solicitar su adjunto, mientras
un borrador ajeno invisible produce `404`.

La mutación es más restrictiva que la consulta: solo el autor con gestión
efectiva puede editar, enviar o retirar lógicamente su borrador. Un borrador
visible de otro autor responde `403`; uno invisible o externo al contexto
responde `404`. La autorización precede a la validación del UUID y del payload.

Comentarios internos y actuaciones no públicas se filtran antes de construir
la vista. Para actores sin gestión solo son públicas `created`, `updated`,
`quick_action`, `sent_reply` y `tags_updated`; todo identificador desconocido
permanece privado por defecto.

## 9. Hallazgos de autorización

1. El auditor consulta PQR, pero no el módulo de auditoría general.
2. Cualquier rol autenticado puede radicar PQR.
3. Cualquier rol puede registrar una encuesta si es el radicador y la PQR
   cumple el estado requerido.
4. El backend permite exportaciones para todos los roles autenticados.
5. El cambio de `users.role` está protegido por capacidad administrativa
   efectiva (Sprint 18, HU-USR-09, corregido el 17 de octubre de 2026):
   `UserManagementController` bloquea con 422 la operación —dentro de la
   transacción que escribe— si la Copropiedad activa quedaría sin ningún
   miembro vigente con el permiso `usuarios.gestionar` (protección "≥1" por
   capacidad efectiva y no por nombre de rol, reutilizando
   `CapacidadAdministrativa`). La regla mide el estado final real tras la
   sincronización —no predice el resultado del nuevo rol—, se aplica solo
   cuando el rol cambia y no afecta a usuarios sin Membresía en el contexto
   activo ni a otras Copropiedades.

Los puntos 1 a 4 describen el código actual y no establecen el comportamiento
deseado. El punto 5 describe el comportamiento implementado y verificado.

## 10. Evidencias utilizadas

### Autorización y dominio

- `app/Models/User.php`
- `app/Models/Pqr.php`
- `app/Policies/PqrPolicy.php`
- `app/Application/Pqrs/VisibilidadBorradoresPqrs.php`
- `database/migrations/2026_07_24_170000_add_role_to_users_table.php`
- `database/migrations/2026_07_24_210000_add_assignee_to_pqrs_table.php`

### Controladores

- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/PqrController.php`
- `app/Http/Controllers/PqrQuickActionController.php`
- `app/Http/Controllers/PqrReplyController.php`
- `app/Http/Controllers/PqrInternalCommentController.php`
- `app/Http/Controllers/ComplementaryController.php`
- `app/Http/Controllers/SettingsController.php`
- `app/Http/Controllers/UserManagementController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/NotificationController.php`

### Rutas, vistas y pruebas

- `routes/web.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/pqrs/show.blade.php`
- `resources/views/users/`
- `tests/Feature/PqrPermissionsTest.php`
- `tests/Feature/PqrCommunicationPrivacyAndRetentionTest.php`
- `tests/Feature/PqrSafeDraftLifecycleTest.php`
- `tests/Feature/PqrMediumFeaturesTest.php`
- `tests/Feature/PqrComplementaryFeaturesTest.php`
- `tests/Feature/SettingsTest.php`

## Control documental

- **Versión:** v1.4
- **Fecha de creación:** 30 de julio de 2026
- **Fecha de última actualización:** 17 de octubre de 2026 (regla desactivar
  frente a eliminar y vocabulario de auditoría; ver ADR-013; §9-5 corregido
  con la protección de capacidad administrativa del cambio de rol)

## Gestión de mantenimiento — Sprint 14

| Rol contextual | Registrar | Consultar propias | Consultar todas | Gestionar |
| --- | --- | --- | --- | --- |
| Administrador | Sí | Sí | Sí | Sí |
| Gestor | Sí | Sí | Sí | Sí |
| Residente | Sí | Sí | No | No |
| Apoyo | No | No | No | No |
| Auditor | No | No | No | No |

Cada operación exige membresía vigente y pertenencia al contexto activo.
Los permisos son `mantenimiento.crear`, `mantenimiento.ver_propias`,
`mantenimiento.ver_todas` y `mantenimiento.gestionar`.
Evidencia: MantenimientoPolicy, ConsultaMantenimientos, migración de
mantenimientos y CrearIdentidadContextualInicial.
