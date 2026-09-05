# Notificaciones y automatizaciones

## Alcance implementado

Sprint 9 contextualiza la bandeja y centraliza las comunicaciones de los cinco
eventos existentes de PQR: creación, cambio de estado, asignación, respuesta y
recordatorio de vencimiento. No incorpora canales, preferencias, paneles,
reportes, outbox ni recuperación de contraseña propia.

## Consulta y apertura

`ConsultaNotificacionesContextuales` restringe las notificaciones por Usuario,
Organización y Copropiedad. `AbrirNotificacionContextual` resuelve primero la
notificación propia, obtiene la PQR mediante `ConsultaPqrsContextuales`, aplica
`AutorizacionContextual` y solo entonces marca como leída y construye el destino
interno. Recursos externos, revocados e inexistentes responden uniformemente
`404`. `MarcarNotificacionesContextualesComoLeidas` limita la mutación al mismo
contexto.

Las rutas de lectura usan `PATCH` y CSRF. `NotificationController` se limita al
transporte HTTP, la invocación de casos de uso y la respuesta web.

## Emisión y correo

`EmitirNotificacionPqrs` es el único componente que instancia
`PqrEventNotification`. Los casos de uso lo invocan después de confirmar su
transacción:

1. commit de la operación principal;
2. persistencia por el canal `database`;
3. dispatch de `EnviarCorreoNotificacionPqrs`.

La notificación persistida no contiene URL. El job recibe solo identificadores
internos, reconstruye `ContextoOperativo` y revalida Membresía, permiso y regla
de destinatario antes de enviar. Un contexto obsoleto cancela silenciosamente
el correo. El job usa tres intentos, backoff de 60, 300 y 900 segundos, y clave
única `evento:recurso:destinatario:mail`; el fallo definitivo se registra sin
datos personales innecesarios.

El comando `pqrs:send-reminders` conserva `last_reminder_at`, bloquea la PQR y
revalida la reserva diaria dentro de una transacción antes de emitir.

### Respuesta oficial verificable

C.3.5.1B-3 reconcilia la notificación de `send_reply` y `send_draft` desde el
ledger después del commit de la respuesta. `pending` conserva una emisión
recuperable; `completed` confirma en una misma transacción la notificación
`database`, el job de la conexión `database` y el resultado técnico; y
`no_recipient` cierra la operación sin divulgar información cuando falta un
destinatario contextual elegible. Estos estados no prueban entrega de correo.

El replay y `pqrs:reconcile-response-notifications` procesan exclusivamente
operaciones B-3 completadas cuyo aviso sigue `pending`. La fila se bloquea y la
notificación se identifica por operación, por lo que no se duplican respuesta,
actuación, notificación ni job. El scheduler ejecuta la reconciliación cada
cinco minutos con `withoutOverlapping`. Un fallo definitivo del job devuelve
solo su operación a `pending`; los 17 jobs históricos sin identificador de
operación mantienen el comportamiento anterior y el comando no los reclama.

Antes del correo se vuelven a validar organización, copropiedad, PQR,
membresía, permiso y regla de destinatario. Un radicador revocado, externo o
fuera de contexto concluye en `no_recipient`. La respuesta web comunica
únicamente que la respuesta quedó registrada y distingue si el aviso quedó
encolado, sin destinatario o pendiente de reintento.

## Seguridad y límites

- `ContextoOperativo`, `AutorizacionContextual`, Membresía vigente y
  `notificaciones.consultar` son obligatorios.
- La información del cliente, las URLs persistidas y `users.role` no son
  fuentes de autorización.
- La bandeja, contador, apertura, lectura masiva, destinatarios y jobs preservan
  el aislamiento entre Organizaciones y Copropiedades.
- Se reutilizan notificaciones, colas y correo de Laravel conforme a ADR-001,
  ADR-002, ADR-003, ADR-005, ADR-006 y ADR-008.

## Evidencia

- `app/Application/Notificaciones/`
- `app/Application/Notificaciones/ReconciliarNotificacionRespuestaPqrs.php`
- `app/Console/Commands/ReconciliarNotificacionesRespuestaPqrs.php`
- `app/Jobs/EnviarCorreoNotificacionPqrs.php`
- `app/Notifications/PqrEventNotification.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Console/Commands/EnviarRecordatoriosPqrs.php`
- `tests/Feature/NotificacionesContextualesTest.php`
- `tests/Feature/BandejaNotificacionesContextualesTest.php`
- `tests/Feature/EmisionNotificacionesPqrsTest.php`
- `tests/Feature/PqrVerifiedNotificationTest.php`
