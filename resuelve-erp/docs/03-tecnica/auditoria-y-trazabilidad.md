# Auditoría y trazabilidad

## Alcance

La auditoría conserva responsable, fecha y ámbito de las operaciones. El
Sprint 26 completa HU-AUD-02 mediante tres registros consultables, sin
sustituir el historial funcional del expediente ni el ledger de comunicaciones.

| Registro | Persistencia | Contenido |
| --- | --- | --- |
| Operación | `audit_logs`, `App\Models\AuditLog` | Actuaciones explícitas y mutaciones HTTP exitosas; no se duplica una actuación específica con la genérica. |
| Cambios de modelos | `activity_log`, `App\Models\Activity` | Eventos Eloquent mediante `spatie/laravel-activitylog` 4.12.3; valores anteriores y posteriores de una lista explícita de campos. |
| Seguridad de acceso | `audit_logs`, acciones `auth.*` | Acceso correcto, intento fallido, bloqueo temporal, cuenta inactiva y cierre de sesión en web y API. |

## Cinco modelos auditados

El trait `AuditaCambiosDeModelo` configura `logOnlyDirty` y
`dontSubmitEmptyLogs`: un guardado sin cambios auditables no produce ruido.

| Modelo | Campos registrados |
| --- | --- |
| `Copropiedad` | Nombre, estado y fecha de desactivación. |
| `UnidadPrivada` | Código, número, torre/bloque, tipo, estado y fecha de desactivación. |
| `PqrTag` | Nombre, color y estado activo. |
| `Mantenimiento` | Estado, responsable, programación y fecha de finalización. |
| `Pqr` | Estado, prioridad, responsable, tipo y fecha límite de respuesta. |

Cada actividad toma Organización y Copropiedad del recurso; el actor procede
de la sesión autenticada y puede ser nulo en tareas sin usuario. El registro
participa en la misma conexión/transacción del cambio: un rollback elimina
también su actividad. No se copian contraseñas, tokens, descripciones de PQRS,
comentarios internos ni resultados privados al registro de modelos.

## Aislamiento y consulta visible

- `/auditoria` exige contexto vigente y permiso `auditoria.ver`. Solo consulta
  registros cuya Organización **y** Copropiedad coinciden con el contexto.
- `/admin/auditoria` exige `esAdministradorSistema()` y permite la consulta
  global de plataforma, incluidos intentos anónimos e historia sin contexto.
- `ConsultaAuditoria` comprueba autorización antes de validar los filtros.
  Admite registro, fecha inicial/final, usuario y canal web/API (este último
  solo se aplica a seguridad), con paginación de 30 registros.
- El selector de usuarios se obtiene de los registros del ámbito autorizado.
  La pantalla muestra actor, acción, área, fecha y antigüedad; en modelos
  permite desplegar «Ver cambios». En seguridad muestra canal e IP.
- Blade escapa los valores y no imprime el JSON de metadatos ni el hash de
  cuenta. Los filtros se adaptan a móvil y a temas claro/oscuro.

La migración añade columnas de ámbito a `audit_logs` y crea `activity_log` con
índices por contexto/fecha. La historia antigua sin ámbito permanece nula y
reservada a plataforma; no se reasigna por la membresía actual de su actor.
Para operaciones nuevas, `AuditLog` aprovecha contexto explícito, recurso
reconocido o una única membresía vigente inequívoca; no inventa un ámbito
cuando la información es insuficiente.

## Bitácora de seguridad

`RegistrarEventoSeguridad` se reutiliza en los dos controladores de acceso.
Registra IP, canal y un HMAC SHA-256 del identificador normalizado de cuenta
con la clave de la aplicación. No persiste el correo ingresado en texto claro,
la contraseña, el cuerpo de la petición ni tokens.

Los accesos fallidos/bloqueados no tienen un contexto de confianza y quedan
reservados a plataforma. El acceso correcto por API usa la membresía validada;
por web solo se atribuye a una Copropiedad si hay una membresía vigente
inequívoca. El cierre de sesión se conserva como registro global de seguridad.
Se mantienen 3 intentos por cuenta, 20 por IP y 5 minutos de bloqueo.

## Evidencia y verificación

- `app/Models/Activity.php`, `app/Models/AuditLog.php` y
  `app/Models/Concerns/AuditaCambiosDeModelo.php`.
- `app/Application/Auditoria/ConsultaAuditoria.php` y
  `app/Application/Auditoria/RegistrarEventoSeguridad.php`.
- `app/Http/Controllers/AuditController.php`, ambos `AuthController.php`,
  `app/Http/Middleware/AuditMutations.php` y `routes/web.php`.
- `config/activitylog.php` y migración
  `2026_11_05_230000_create_activity_log_and_scope_audit_logs.php`.
- `resources/views/management/audit.blade.php`, enlace administrativo en
  `resources/views/layouts/admin.blade.php` y estilos en `resources/css/app.css`.
- `tests/Feature/AuditoriaSeguridadTest.php`: 9 pruebas de cinco modelos,
  valores antes/después, privacidad, rollback, acceso web/API, aislamiento,
  autoridad de plataforma y validación de filtros.
- `tests/Feature/AuditLogPresentationTest.php`: presentación histórica.

La versión 4.12 de Activitylog soporta Laravel 13 y permite conservar PHP 8.3;
se justifica su incorporación por el requisito 10 de la guía. No se crea un
segundo sistema de permisos ni se cambia la arquitectura modular.

## Control documental

- **Versión:** v1.0
- **Fecha:** 6 de noviembre de 2026 (America/Bogota)
