# Matriz de reglas verificables — Integración de módulos

Cada regla se implementa en una capa concreta y se verifica con pruebas que
cubren denegaciones, cruces de contexto, reintentos y fallos transaccionales.

| # | Regla | Ubicación de implementación | Verificación (pruebas) |
| --- | --- | --- | --- |
| 1 | Misma Organización y Copropiedad en todos los vínculos | `GenerarMantenimientoDesdePqr`, `VincularMantenimientoPqr`, `VincularEvidenciaMantenimiento` (validación previa en transacción); bindings de ruta contextuales (`AppServiceProvider`) | `IntegracionModulosTest::test_generar_exige_autorizacion_efectiva_sobre_ambos_recursos` (cruce de contexto → recurso invisible), `EvidenciaMantenimientoTest::test_vincular_exige_autorizacion_sobre_ambos_recursos_y_pertenencia_al_contexto` |
| 2 | Autorización efectiva sobre cada recurso y operación | Policies `PqrPolicy`/`MantenimientoPolicy`/`DocumentoPolicy`; generar exige `pqrs.gestionar` + `mantenimiento.crear`; vincular exige gestión sobre **ambos** recursos (`pqrs.listar` insuficiente) | `IntegracionModulosTest::test_generar_exige_autorizacion_efectiva_sobre_ambos_recursos`, `::test_el_vinculo_no_se_administra_tras_finalizar_y_requiere_gestion_en_ambos_recursos`, `EvidenciaMantenimientoTest::test_vincular_exige_autorizacion_sobre_ambos_recursos_y_pertenencia_al_contexto` |
| 3 | Respeto del rol activo y permisos directos | `ContextoOperativo`/`AutorizacionContextual` (Sprint 18) — todas las operaciones pasan por las policies contextuales | Suite de roles y permisos (Sprint 18) + denegaciones por rol restrictivo en las pruebas de integración |
| 4 | Responsable activo con membresía vigente | `GestionarMantenimiento`/`RegistrarMantenimiento` (regla existente) | Pruebas existentes de asignación de responsable con membresía suspendida o usuario desactivado |
| 5 | Protección de duplicados **e idempotencia estricta** | `mantenimientos.idempotency_key` (índice único) + la clave pertenece a una solicitud concreta: reutilizarla con otra PQRS o datos incompatibles se rechaza sin devolver ni modificar el recurso original; duplicado por mismo título exige `confirmar_duplicado` | `IntegracionModulosTest::test_la_operacion_es_idempotente_ante_doble_clic_reenvio_y_concurrencia`, `::test_duplicado_accidental_exige_confirmacion_explicita`, `::test_reutilizar_la_clave_con_otra_pqrs_no_devuelve_ni_modifica_un_recurso_incorrecto`, `::test_reutilizar_la_clave_con_datos_incompatibles_rechaza_sin_alterar_el_registro` y **concurrencia real MySQL** con dos conexiones paralelas: `MantenimientoIdempotencyMysqlConcurrencyTest` |
| 6 | Evidencia documental válida antes de finalizar | `FinalizarMantenimiento` exige ≥1 versión `aprobada` vinculada; `VincularEvidenciaMantenimiento` solo acepta versiones `aprobada` de documentos `activo` | `IntegracionModulosTest::test_finalizar_exige_evidencia_valida_y_no_cierra_la_pqrs`, `EvidenciaMantenimientoTest::test_solo_versiones_aprobadas_de_documentos_activos_sirven_como_evidencia` |
| 7 | Vincular recursos **no amplía la visibilidad ni filtra metadatos** | `ConsultaExpedienteIntegrado` aplica policies por recurso; evidencia restringida se oculta sin título, versión, archivo ni fechas; el PDF se construye sobre la misma consulta | `ExpedienteIntegradoTest::test_los_documentos_restringidos_no_filtran_metadatos` (vista HTML), `::test_el_pdf_del_expediente_exige_informes_exportar_y_audita` |
| 8 | Finalizar mantenimiento **no cierra automáticamente la PQRS** | `FinalizarMantenimiento` (transacción propia, solo registra actuación en la PQRS) | `IntegracionModulosTest::test_finalizar_exige_evidencia_valida_y_no_cierra_la_pqrs` (assert del estado de la PQRS sin cambio) |
| 9 | Operaciones relacionadas atómicas | Todos los casos de uso usan `DB::transaction`; violaciones de unicidad (concurrencia) se resuelven devolviendo el registro previo sin estados intermedios | Pruebas de idempotencia bajo repetición; migración con índice único (fallo transaccional = rollback completo) |
| 10 | Trazabilidad de actor, fecha, origen y resultado | `AuditLog` (`mantenimiento.desde_pqr`, `mantenimiento.vincular_pqr`, `mantenimiento.evidencia.vincular`, `mantenimiento.resultado`, `mantenimiento.finalizar`, `informe.expediente.pdf`) + `pqr_activities` (`mantenimiento_generado`, `mantenimiento_vinculado`, `mantenimiento_finalizado`) | Assertions de `audit_logs` y `pqr_activities` en `IntegracionModulosTest` y `EvidenciaMantenimientoTest`; `ExpedienteIntegradoTest` para la exportación |
| 11 | Separación de resultado público e interno | `mantenimientos.resultado_publico` / `resultado_interno`; `ConsultaExpedienteIntegrado` impone la separación en la capa de consulta (no solo en plantillas) | `IntegracionModulosTest::test_el_resultado_separa_lo_publico_de_lo_interno_y_se_registra_antes_de_finalizar`, `ExpedienteIntegradoTest::test_el_residente_solo_consulta_estado_fechas_y_resultado_publico` |

## Criterios de cobertura adicionales

- **Denegaciones:** cada operación tiene prueba de permiso faltante (403) y de
  recurso fuera de contexto (oculto con 404 por los bindings contextuales).
- **Reintentos:** la repetición de peticiones (doble clic, reenvío) se verifica
  en idempotencia de generación y de vinculación de evidencia.
- **Fallo transaccional:** la validación de estados y versiones se ejecuta
  dentro de la transacción del caso de uso: un rechazo no deja vínculos,
  resultados ni estados intermedios (verificado por assertions de conteo tras
  cada denegación).
