# Roles y permisos

## 1. Propósito

Este documento describe el control de acceso implementado en Resuelve. La
matriz se deriva del modelo `User`, la política de PQR, las validaciones de los
controladores y las condiciones de las vistas.

## 2. Implementación general

El rol se almacena como texto en `users.role`. La migración establece
`residente` como valor predeterminado. Los valores aceptados al crear, editar o
cambiar el rol de un usuario son:

- `admin`;
- `gestor`;
- `apoyo`;
- `auditor`;
- `residente`.

No existe una tabla de roles o permisos ni se utiliza un paquete externo de
autorización. Los permisos se implementan mediante:

- métodos del modelo `User`;
- `PqrPolicy`;
- verificaciones `abort_unless()` y `abort_if()` en controladores;
- directivas y condiciones en vistas Blade;
- middleware `auth` y `guest`.

## 3. Capacidades auxiliares del usuario

| Método | Roles incluidos |
| --- | --- |
| `canViewAllPqrs()` | Administrador, gestor, auditor y apoyo. |
| `canManagePqrs()` | Administrador, gestor y apoyo. |
| `isAdmin()` | Administrador. |

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

No se permite:

- eliminar la propia cuenta;
- eliminar al único administrador;
- eliminar un usuario con PQR radicadas asociadas.

La validación consulta la relación `user.pqrs`, no las PQR donde el usuario sea
responsable. La clave `assigned_to_id` usa `nullOnDelete`, por lo que la base de
datos permite eliminar un responsable sin PQR radicadas.

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
5. El cambio de rol no protege la existencia de al menos un administrador
   cuando se modifica a otro usuario.

Estos puntos describen el código actual y no establecen el comportamiento
deseado.

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

- **Versión:** v1.2
- **Fecha de creación:** 30 de julio de 2026
- **Fecha de última actualización:** 14 de agosto de 2026
