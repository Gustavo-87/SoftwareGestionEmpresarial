# Guion del video — Integración de módulos (≤10 minutos)

Demostración del segundo corte: un proceso completo que atraviesa tres módulos
(PQRS → Mantenimiento → Documentos) con datos reales de la base aislada.

| Tiempo | Pantalla | Acción y narración |
| --- | --- | --- |
| 0:00–0:40 | Panel compacto (`/panel`) | Presentación del ERP y de los tres módulos integrados. Señalar los microindicadores: «N con mantenimiento vinculado» y «N vinculados · N abiertos · N con evidencia» (HU-REP-04). |
| 0:40–1:30 | Expediente PQRS (rol gestor) | Abrir una PQRS radicada por un residente. Mostrar el bloque «Mantenimientos vinculados» (vacío al inicio del caso) y el botón «Generar mantenimiento» (HU-PQRS-03). |
| 1:30–2:30 | Formulario «Generar mantenimiento» | Registrar título, descripción y fecha programada. Explicar la **idempotencia**: pulsar dos veces el envío muestra que solo se crea un mantenimiento (clave única por solicitud). Una segunda acción explícita con otro título sí crea un trabajo legítimo adicional. |
| 2:30–3:30 | Ficha del mantenimiento | Bloque «PQRS de origen» con enlace al expediente (HU-MAN-03). Asignar responsable activo con membresía vigente y fecha programada. |
| 3:30–5:00 | Sección «Evidencia documental» | Vincular una versión **aprobada** de un documento activo (HU-DOC-03): mostrar que la evidencia fija la versión exacta y que el ciclo documental del documento no cambia. Intentar vincular una versión en borrador → error comprensible. |
| 5:00–6:00 | Sección «Resultado del mantenimiento» | Registrar **resultado público** («Fuga reparada…») y **resultado interno** («Se sustituyó la válvula…») y explicar la separación (HU-MAN-04). |
| 6:00–7:00 | Finalización | Finalizar el mantenimiento con la confirmación que advierte: **«La PQRS no se cierra automáticamente»**. Volver al expediente PQRS y mostrar que la PQRS sigue abierta: la respuesta y el cierre son operaciones explícitas del gestor. |
| 7:00–8:00 | Vista del residente | Ingresar con el usuario residente radicador: en su PQRS se ven los mantenimientos con estado, fechas y **resultado público**; el resultado interno no aparece y la evidencia restringida se muestra sin metadatos («Evidencia documental disponible solo para personal autorizado») (HU-REP-05). |
| 8:00–9:00 | Expediente PDF | Volver al gestor y descargar «Expediente PDF» (HU-REP-03): PQRS, mantenimientos, evidencia visible y respuestas oficiales con la identidad institucional. Mencionar que el PDF se construye sobre la misma consulta autorizada. |
| 9:00–10:00 | Cierre | Resumen de la integración (tres módulos, un proceso), reglas clave (idempotencia, autorización doble, separación público/interno) y estado del cierre formal pendiente de aprobación. |

## Notas de grabación

- Usar la base aislada `resuelve_sprint19_demo` (fixture
  `DemoIntegracionFixtureSeeder`): datos reales, ≥10 registros por módulo.
- Credenciales demo: las del `DatabaseSeeder` de la base aislada
  (`RESUELVE_DEMO_PASSWORD`), nunca las de producción.
- Si el tiempo aprieta (plan de contingencia): recortar 2:30–3:30 (asignación)
  y 8:00–9:00 (PDF) manteniendo el flujo completo PQRS → mantenimiento →
  evidencia → resultado → finalización sin cierre automático.
