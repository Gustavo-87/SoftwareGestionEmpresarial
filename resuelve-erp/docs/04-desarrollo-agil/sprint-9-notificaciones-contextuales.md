# Sprint 9 — Notificaciones contextuales

## Estado

**Completado.** Implementado y validado el 3 de agosto de 2026. Este estado no
implica commit, push ni tag.

## Objetivo y trazabilidad

Implementar la Épica 7 mediante `HU-NOT-01 — Recepción de Notificaciones` y
`HU-NOT-02 — Comunicación a destinatarios autorizados`, preservando el contexto
de Copropiedad en bandeja, lectura, emisión, correo y recordatorios.

## Alcance completado

- permiso contextual mínimo `notificaciones.consultar`;
- consulta paginada y contador de no leídas por contexto activo;
- apertura propia con revalidación de la PQR y destino interno;
- marcado individual y masivo mediante `PATCH` con CSRF;
- emisión central de creación, estado, asignación, respuesta y recordatorio;
- persistencia `database` posterior al commit y dispatch posterior del correo;
- job con identificadores internos, revalidación contextual, tres intentos,
  backoff progresivo e idempotencia por evento, recurso, destinatario y canal;
- recordatorio diario protegido mediante `last_reminder_at` y bloqueo.

Quedan excluidos canales nuevos, preferencias, panel administrativo, entidad de
entregas, outbox, reportes, API, IA y cambios en recuperación de contraseña.

## Decisiones de seguridad

Se aplican `ContextoOperativo`, `AutorizacionContextual`, Membresía vigente,
permiso y pertenencia del recurso. La notificación persistida conserva tipo e
identificador interno del recurso, evento, Organización y Copropiedad; nunca
una URL. La apertura externa, ajena, revocada o inexistente responde `404` sin
marcar lectura ni revelar diferencias. Los jobs reconstruyen el contexto y
cancelan el envío cuando quedó obsoleto.

## Implementación por bloques

1. **9.1:** fundamento contextual, permiso y resolución uniforme de
   destinatarios.
2. **9.2:** bandeja, contador, lectura contextual e interfaz segura.
3. **9.3:** emisión central, correo, reintentos y recordatorios idempotentes.
4. **9.4:** regresión, seguridad, MySQL y cierre documental.

## Validación final

- pruebas focales de Sprint 9 en SQLite: **55 pruebas, 242 aserciones**;
- suite integral Sprints 1–9 en SQLite: **207 pruebas, 867 aserciones**;
- MySQL **8.4.10**, flujos críticos: **36 pruebas, 169 aserciones**;
- migración completa y seed de validación en la base aislada `testing`:
  correctos;
- equivalencia de autorización contextual: **0 divergencias**;
- búsqueda estática: la única instancia directa de
  `PqrEventNotification` está en `EmitirNotificacionPqrs`;
- no se encontraron consultas globales operativas de notificaciones fuera de
  `ConsultaNotificacionesContextuales`;
- `git diff --check`: sin errores.

La matriz cubre membresías inexistente, inactiva, futura y vencida; bandejas y
conteos separados; IDOR; CSRF; apertura y lectura cruzadas; jobs con contexto
obsoleto; reintentos; deduplicación; recordatorio diario y recuperación de
contraseña.

## Definition of Done y criterio de cierre

El Sprint 9 cumple su Definition of Done: ambas Historias de Usuario operan
solo en el contexto autorizado; los cinco eventos pasan por el emisor central;
base de datos y correo respetan la secuencia aprobada; no existen divergencias
de autorización ni regresiones; SQLite y MySQL superan las validaciones; la
documentación oficial está sincronizada y no se amplió el alcance.

## Riesgos no bloqueantes

- Sin outbox, una interrupción entre persistir `database` y despachar el job
  puede dejar el correo sin encolar; es una exclusión aprobada.
- Los reintentos y la unicidad dependen del worker y del almacén de locks de la
  cola configurada.
