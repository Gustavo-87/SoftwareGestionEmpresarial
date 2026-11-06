# Sprint 25 — Anulación trazable de respuestas oficiales (HU-PQRS-04)

## Estado y control documental

- **Estado:** Completado. HU-PQRS-04 implementada de principio a fin y
  verificada localmente el 5 de noviembre de 2026. La ejecución real de GitHub
  Actions queda **pendiente** y fuera de esta verificación.
- **Fecha de cierre técnico:** 5 de noviembre de 2026.
- **Origen:** HU-PQRS-04 (Product Backlog, Épica 5 — PQRS), incorporada y
  aprobada por el Product Owner el 5 de noviembre de 2026 para el componente de
  anulación del proceso de salida de la guía final.
- **Decisión registrada:** la adaptación de «anulación» al dominio residencial
  está **aprobada por el Product Owner** como anulación comunicada y trazable de
  la respuesta oficial. La adaptación está aprobada por el Product Owner para
  Resuelve ERP y no requiere aprobación docente para continuar.
- **Base:** cierre del Sprint 24 (HU-CONF-03).
- **Número de sprint:** 25 (siguiente disponible tras el Sprint 24).
- **Rama:** `sprint25-anulacion-respuestas` desde `main` (`4981638`).

## 1. Objetivo y alcance

Dejar sin efecto una respuesta oficial ya comunicada **sin que desaparezca ni
cambie**, marcándola como anulada con motivo, actor y fecha, comunicándolo al
residente y registrándolo en la trazabilidad existente. Retirar un borrador
sigue siendo una acción distinta (prec-comunicación).

## 2. Implementación

- **Migración** `2026_11_05_220000_add_anulacion_to_pqr_replies_table`:
  columnas `anulada_at`, `anulada_por_user_id` y `motivo_anulacion` en
  `pqr_replies` (el actor se añade sin FK declarada para no recrear la tabla en
  SQLite y conservar sus CHECKs de integridad; índice propio) y **ampliación de
  las restricciones del ledger** `pqr_communication_operations` para la
  operación `annul_reply` con resultado `reply_annulled`, conservando la matriz
  de integridad existente (reconstrucción en SQLite; `DROP/ADD CHECK` en MySQL).
- **Modelo `PqrReply`:** la guarda de inmutabilidad se amplía quirúrgicamente:
  la única mutación permitida sobre una respuesta oficial enviada y no anulada
  es fijar los campos de anulación; el contenido, adjuntos, envío, PQR y autor
  permanecen inmutables y una respuesta anulada no admite ningún cambio
  (`estaAnulada()`, relación `anuladaPor`).
- **Ciclo de respuestas** (`GestionarCicloRespuestaPqrs::annul` sobre `mutate`):
  operación idempotente por clave en el ledger existente, con la misma
  autorización contextual que el envío (`autorizarMutacion`: 404 sin IDOR,
  403 sin gestión efectiva; los residentes no pueden anular) y motivo
  obligatorio.
- **Trazabilidad:** registro en el ledger (`annul_reply`/`reply_annulled`),
  actuación pública `annulled_reply` (visible en el expediente), `AuditLog`
  `pqr.respuesta.anular` con motivo y contexto, y **notificación al radicador**
  (evento nuevo `pqr_respuesta_anulada` reutilizando `EmitirNotificacionPqrs`).
- **Interfaz:** la respuesta anulada se muestra en el expediente marcada como
  «Respuesta anulada» con actor, fecha y motivo, y el botón «Anular respuesta»
  (con motivo) solo aparece sobre respuestas oficiales no anuladas para
  personal con gestión.
- **Invariantes conservadas:** la PQR conserva su estado; la respuesta anulada
  no se edita, elimina, reactiva ni reenvía (para corregirla se emite una
  respuesta oficial nueva); retirar borradores no es anular.

## 3. Verificación (5 de noviembre de 2026)

| Verificación | Resultado |
| --- | --- |
| Pruebas de anulación (`tests/Feature/AnulacionRespuestaTest.php`) | **6 pruebas aprobadas** (48 aserciones): anulación válida con contenido y adjuntos inmutables y trazabilidad completa (ledger, actuación pública, auditoría y notificación), motivo faltante (422), permisos y aislamiento (404 sin IDOR, 403 sin gestión), restricciones sobre respuestas anuladas (sin edición, borrado ni reenvío), doble solicitud idempotente y retirar borrador ≠ anular |
| Pruebas del ciclo existentes (`PqrSafeDraftLifecycleTest`, `EmisionNotificacionesPqrsTest`) | **Sin modificaciones y en verde** (garantía de efectos idénticos del ciclo) |
| Suite completa (configuración aislada, SQLite en memoria) | **786 pruebas: 777 aprobadas y 9 omitidas (MySQL)**, 3763 aserciones, sin regresiones |
| `vendor/bin/pint --test` | **Aprobado** |
| Migración en base local (Sail, `resuelve_erp`) | **Aplicada** (`2026_11_05_220000`, 104.60 ms; única pendiente antes de aplicar; 0 pendientes tras) |

**Ámbito:** verificación **local**. La **ejecución real de GitHub Actions no
está verificada** (el workflow se ejecuta con un `pull_request` hacia `main`).

## 4. Decisiones y notas

1. **Inmutabilidad quirúrgica:** la única transición nueva es oficial → anulada
   (campos de trazabilidad); el resto de invariantes de `PqrReply` se conservan.
2. **Ledger ampliado, no sustituido:** la anulación se registra con la misma
   infraestructura idempotente del ciclo de respuestas; sus restricciones de
   integridad se ampliaron conservando la matriz previa.
3. **Comunicación obligatoria:** la anulación notifica al radicador (evento
   `pqr_respuesta_anulada`, destinatario reglado como la respuesta enviada).
4. **Fixture de pruebas:** el destinatario de notificaciones exige
   `notificaciones.consultar` (regla existente del resolver).

## 5. Exclusiones

Kardex, inventario, ventas/compras, anulación de la PQR como entidad y
reapertura automática. La validación de la equivalencia «anulación» con la
guía comercial queda registrada por decisión del Product Owner y no requiere aprobación docente para continuar.

## 6. Evidencia

- Migración: `database/migrations/2026_11_05_220000_add_anulacion_to_pqr_replies_table.php`.
- Modelo y ciclo: `app/Models/PqrReply.php`,
  `app/Application/Pqrs/GestionarCicloRespuestaPqrs.php`, enums
  `PqrCommunicationOperation`/`PqrCommunicationResultCode`.
- Notificación: `EmitirNotificacionPqrs`, `ResolverDestinatariosNotificacionPqrs`.
- Controlador y vista: `PqrReplyController::anular`, `routes/web.php`,
  `resources/views/pqrs/show.blade.php`.
- Pruebas: `tests/Feature/AnulacionRespuestaTest.php`.

## Control documental

- **Versión:** v1.0
- **Fecha de creación:** 5 de noviembre de 2026
- **Fecha de última actualización:** 5 de noviembre de 2026
