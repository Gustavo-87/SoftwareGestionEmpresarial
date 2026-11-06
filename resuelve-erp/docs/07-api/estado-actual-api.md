# Estado actual de la API

## 1. Propósito

Registrar el alcance verificable actual de la API REST de Resuelve: lo
implementado, su contrato y lo que todavía no existe. Este documento describe
solo comportamiento implementado y verificado.

## 2. Alcance implementado (HU-API-01: autenticación, consulta y radicación)

La API REST v1 se expone bajo `/api/v1/` con autenticación mediante tokens
personales de Laravel Sanctum (rutas en `routes/api.php`, registrado en
`bootstrap/app.php`). Forma parte del alcance aprobado de HU-API-01 para la
entrega final académica sobre el modelo residencial autorizado (sin ventas,
compras ni inventario).

### Endpoints existentes

| Método | Ruta | Autenticación | Operación |
| --- | --- | --- | --- |
| `POST` | `/api/v1/login` | pública | Emite un token personal y resuelve el Rol activo de una Membresía vigente |
| `POST` | `/api/v1/logout` | `auth:sanctum` | Revoca el token utilizado |
| `GET` | `/api/v1/pqrs` | `auth:sanctum` | Listado de PQRS del contexto activo, según Rol y permisos |
| `GET` | `/api/v1/pqrs/{identificador}` | `auth:sanctum` | Detalle de una PQRS con las reglas de visibilidad del canal web |
| `POST` | `/api/v1/pqrs` | `auth:sanctum` | Radicación de PQRS (operación de escritura) |

### `POST /api/v1/login`

Cuerpo: `email`, `password` y, opcionales, `membresia_id` y `rol_id`.

- Normaliza el correo (minúsculas y recorte) y aplica el mismo límite de
  intentos que el login web (3 por cuenta, 20 por IP, bloqueo de 300
  segundos; las claves de límite son compartidas entre canales). La respuesta
  de exceso de intentos es `429`.
- Credenciales inválidas: `401` con el mismo mensaje del login web, sin
  revelar si la cuenta existe.
- Usuario con `users.estado ≠ 'activo'`: `403` y ningún token (HU-USR-08).
- Sin Membresía vigente (`estado = 'activa'` y vigencia vigente): `403`.
- Varias Membresías vigentes sin `membresia_id`: `422` pidiendo selección;
  una `membresia_id` ajena o no vigente: `422`.
- `rol_id` opcional: se valida con el mismo mecanismo web de Rol activo
  (`GestionarRolActivo`): el Rol debe estar asignado al usuario en la
  Copropiedad de la Membresía, la selección se persiste en
  `membresias_copropiedad.rol_activo_id` y se audita
  (`usuario.rol_activo.seleccionar`). Un Rol no asignado responde `422` y no
  concede permisos nuevos (ADR-013).
- Respuesta `200`: `token`, `token_type`, `usuario`, `membresia`
  (Copropiedad activa), `rol_activo` (Rol persistido si sigue asignado; si su
  selección caducó se descarta y se informa `null`) y `roles` asignados en esa
  Copropiedad.

### `POST /api/v1/logout`

Requiere el token en `Authorization: Bearer {token}`. Elimina el token
personal utilizado (`200`). Un token revocado o desconocido responde `401`.

### Revalidación por petición (endpoints de PQRS)

Cada solicitud autenticada de PQRS revalida, en el mismo turno, el usuario
activo (`users.estado = 'activo'`), la Membresía vigente, el contexto
institucional y los roles/permisos efectivos (`ResolverContextoApi` →
`ContextResolver`). Un token emitido antes de una desactivación o de una
Membresía vencida responde `403`. Con varias Membresías vigentes se indica la
Membresía en la cabecera `X-Membresia-Id` (validada contra las asignaciones
propias y vigentes; `422` si no pertenece al usuario). Las policies existentes
operan sobre el mismo contexto resuelto.

### `GET /api/v1/pqrs`

Listado restringido al contexto activo (Organización/Copropiedad) y a los
permisos del Rol activo: con `pqrs.ver_todas` se listan todas las de la
Copropiedad; sin él, solo las propias (mismo criterio del índice web).
Parámetros opcionales: `estado`, `tipo_pqr_id`, `por_pagina` (máximo 50).
Respuesta `200` con `data` (resúmenes: radicado, asunto, estado, prioridad,
tipo, fechas, radicador y responsable) y `meta` de paginación. Sin permiso de
consulta: `403`.

### `GET /api/v1/pqrs/{identificador}`

Una PQR de otra Copropiedad responde `404` (mismo criterio de los bindings
contextuales). Sin permiso de visibilidad: `403`. El detalle conserva las
reglas del expediente web: los borradores solo se incluyen con gestión efectiva
(`VisibilidadBorradoresPqrs`), las actividades se limitan a las públicas para
el personal sin gestión (`PqrActivity::PUBLIC_ACTIONS`) y los comentarios
internos solo se incluyen con gestión efectiva (`comentarios_internos` es
`null` para el resto de actores).

### `POST /api/v1/pqrs`

Cuerpo: `asunto` (máx. 150), `descripcion` y `tipo_pqr_id`. Delega en
`PresentarPqrs` (misma transacción, plazos contextuales, actuación y
notificación que el canal web, sin duplicar reglas) y responde `201` con el
resumen de la PQR radicada. Validación incompleta o inválida: `422` con
errores por campo; sin permiso `pqrs.crear`: `403`. La radicación por API no
acepta adjuntos todavía.

## 3. Colección de pruebas importable

Importar [coleccion-postman.json](coleccion-postman.json) en Postman. Incluye
login, listado, radicación, detalle, rechazo sin token y logout, con
comprobaciones de estado y captura de token/PQRS. Ejecutar en ese orden sobre
una base de demostración: la radicación crea una PQRS sintética.

Configurar las variables locales `base_url` (por defecto
`http://localhost:8083/api/v1`), `email`, `password` y `tipo_pqr_id` de un tipo
existente. La contraseña está vacía en el archivo versionado. `membresia_id` y
`rol_id` son opcionales; indicarlos cuando el usuario deba seleccionar contexto
o rol. Las peticiones autenticadas usan la cabecera de membresía cuando se
configura. `token` y `pqr_id` se rellenan al ejecutar; no exportarlos con datos
reales. La colección no concede permisos nuevos al usuario.

Los accesos correctos, fallidos, bloqueados, de cuenta inactiva y cierres
alimentan la [bitácora de seguridad](../03-tecnica/auditoria-y-trazabilidad.md)
sin registrar contraseñas ni tokens.

## 4. Pendiente (no implementado)

Los siguientes componentes **no** están implementados y no deben tomarse como
parte del sistema actual:

- adjuntos en la radicación por API (el caso de uso los soporta; el contrato
  aún no los expone);
- otros módulos en API (mantenimiento, documentos, notificaciones) fuera del
  alcance de HU-API-01.

## 5. Evidencia

- Rutas: `routes/api.php`, registro en `bootstrap/app.php`.
- Controladores de transporte: `app/Http/Controllers/Api/AuthController.php`,
  `app/Http/Controllers/Api/PqrController.php`.
- Revalidación por petición: `app/Http/Middleware/ResolverContextoApi.php`.
- Casos de uso reutilizados: `app/Application/Roles/GestionarRolActivo.php` (Rol
  activo, ADR-013), `app/Application/Membresias/ConsultaMembresiasVigentes.php`
  (vigencia de Membresías), `app/Application/Pqrs/ConsultaPqrsContextuales.php`
  (aislamiento contextual), `app/Application/Pqrs/VisibilidadBorradoresPqrs.php`
  (visibilidad de borradores) y `app/Application/Pqrs/PresentarPqrs.php`
  (radicación transaccional).
- Sanctum: `laravel/sanctum` (composer), migración
  `create_personal_access_tokens_table`, trait `HasApiTokens` en
  `app/Models/User.php`.
- Pruebas: `tests/Feature/Api/ApiAuthTest.php` (login válido con Rol activo,
  selección de Rol asignado persistida, credenciales inválidas, usuario
  inactivo, Rol no autorizado sin concesión y revocación del token con token
  basura y token revocado rechazados) y `tests/Feature/Api/ApiPqrsTest.php`
  (listado por permisos del Rol activo, aislamiento entre Copropiedades en
  listado y detalle, visibilidad de borradores y contenido interno,
  radicación válida e inválida, radicación sin permiso, acceso sin token o sin
  permiso, y denegación con token de usuario inactivo o de Membresía vencida).

## Control documental

- **Versión:** v1.3
- **Fecha de creación:** 5 de noviembre de 2026
- **Fecha de última actualización:** 6 de noviembre de 2026
