# Flujo verificable — Integración de módulos

Proceso completo que demuestra la integración de PQRS, Mantenimiento y
Documentos. Cada paso es reproducible con los datos de demostración de la base
aislada y está cubierto por pruebas automatizadas.

## Preparación de la demostración (base aislada)

La demostración **nunca** se ejecuta contra la base de desarrollo:

```bash
# 1) Crear la base aislada (root del contenedor MySQL de Sail) y autorizar al usuario
docker exec resuelve-erp-mysql-1 sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e \
  "CREATE DATABASE resuelve_sprint19_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   GRANT ALL PRIVILEGES ON resuelve_sprint19_demo.* TO '\''sail'\''@'\''%'\'';"'

# 2) Migrar y cargar el catálogo base + usuarios demo en la base aislada
DB_HOST=127.0.0.1 DB_PORT=3308 DB_DATABASE=resuelve_sprint19_demo \
  RESUELVE_DEMO_PASSWORD=<password-demo> php artisan migrate:fresh --seed --force

# 3) Cargar el fixture de integración (opt-in explícito)
DB_HOST=127.0.0.1 DB_PORT=3308 DB_DATABASE=resuelve_sprint19_demo \
  RESUELVE_DEMO_FIXTURE=1 php artisan db:seed --class=DemoIntegracionFixtureSeeder --force
```

Resultado verificado del fixture: **24 PQRS, 12 mantenimientos y 12 documentos**
(≥10 registros por módulo principal, sin tablas artificiales), con 8
mantenimientos vinculados a PQRS, 6 evidencias documentales, 3 mantenimientos
finalizados con evidencia y 2 documentos de nivel administrativo (para demostrar
que no se filtran metadatos).

## El flujo, paso a paso

1. **El residente radica una PQRS** — flujo existente de radicación con
   contraseña/adjuntos seguros (Sprint 17).
2. **El gestor genera el mantenimiento desde el expediente de la PQRS**
   (HU-PQRS-03) — botón «Generar mantenimiento» con clave de idempotencia por
   solicitud: el doble clic, el reenvío o la concurrencia no crean duplicados;
   una acción explícita nueva sí puede crear otro trabajo legítimo. Exige
   autorización efectiva sobre **ambos** recursos (`pqrs.gestionar` +
   `mantenimiento.crear`): `pqrs.listar` no concede acceso suficiente.
3. **Se consultan los vínculos desde ambos módulos** (HU-MAN-03) — bloque
   «Mantenimientos vinculados» en el expediente PQRS y bloque «PQRS de origen»
   en la ficha de mantenimiento. Un mantenimiento independiente puede
   vincularse a una PQRS antes de finalizar (auditable e idempotente); el
   vínculo es inmutable tras la finalización.
4. **Se registra el responsable y la fecha** — gestión existente
   (HU-USR-02 / `mantenimiento.update`), con responsable activo y membresía
   vigente.
5. **Se vincula evidencia documental con versión exacta** (HU-DOC-03) — solo
   versiones `aprobada` de documentos `activo`, con acceso efectivo al
   documento (autorización sobre ambos recursos). La evidencia fija
   `documento_version_id`: aunque existan versiones posteriores, la prueba
   utilizada permanece inmutable y el ciclo documental no se altera.
6. **Se registra el resultado** (HU-MAN-04) — **resultado público** (comunicable
   al radicador) separado del **resultado interno** (reservado al personal con
   gestión).
7. **Se finaliza el mantenimiento** (HU-MAN-04) — exige estado `en_proceso`,
   resultado público y al menos una evidencia válida. **La PQRS no se cierra
   automáticamente**: respuesta y cierre siguen siendo operaciones explícitas
   del gestor (operaciones existentes `RegistrarRespuestaPqrs`,
   `AplicarAccionRapidaPqrs`, `PresentarPqrs`).
8. **El gestor consulta el resultado y responde/cierra la PQRS** — con las
   operaciones existentes del expediente.
9. **El residente consulta su expediente** (HU-REP-05) — estado, fechas y
   **resultado público** de los mantenimientos vinculados. El resultado interno
   no aparece y los documentos restringidos **no filtran metadatos** (ni título,
   ni versión, ni archivo, ni fechas): solo se informa que hay evidencia
   disponible para personal autorizado.
10. **Expediente integrado en PDF** (HU-REP-03) — «Expediente PDF» en el
    encabezado, con `informes.exportar` y acceso a la PQRS; el PDF se construye
    sobre la misma consulta autorizada, por lo que nunca revela contenido ni
    metadatos no autorizados. La exportación queda auditada
    (`informe.expediente.pdf`).
11. **Panel compacto** (HU-REP-04) — microindicadores dentro de las tarjetas
    existentes: «N con mantenimiento vinculado» en PQRS y «N vinculados · N
    abiertos · N con evidencia» en Mantenimiento. Sin nuevas secciones.

## Cobertura de pruebas

| Paso | Prueba automatizada |
| --- | --- |
| 2 | `IntegracionModulosTest::test_generar_mantenimiento_desde_una_pqrs_vincula_audita_y_registra_actividad`, `::test_la_operacion_es_idempotente_ante_doble_clic_reenvio_y_concurrencia`, `::test_duplicado_accidental_exige_confirmacion_explicita`, `::test_generar_exige_autorizacion_efectiva_sobre_ambos_recursos` |
| 3 | `IntegracionModulosTest::test_los_vinculos_se_consultan_desde_ambos_modulos_y_se_administran_antes_de_finalizar`, `::test_el_vinculo_no_se_administra_tras_finalizar_y_requiere_gestion_en_ambos_recursos` |
| 5 | `EvidenciaMantenimientoTest` (versión exacta, estados válidos, autorización doble, idempotencia, inmutabilidad) |
| 6–7 | `IntegracionModulosTest::test_el_resultado_separa_lo_publico_de_lo_interno_y_se_registra_antes_de_finalizar`, `::test_finalizar_exige_evidencia_valida_y_no_cierra_la_pqrs` |
| 8 | Pruebas de respuesta/cierre existentes del expediente (Sprint 14) |
| 9–10 | `ExpedienteIntegradoTest::test_el_residente_solo_consulta_estado_fechas_y_resultado_publico`, `::test_los_documentos_restringidos_no_filtran_metadatos`, `::test_el_pdf_del_expediente_exige_informes_exportar_y_audita` |
| 11 | `ExpedienteIntegradoTest::test_los_indicadores_de_integracion_se_muestran_en_el_panel_compacto` |
