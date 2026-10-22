# Sprint 19 — Integración de PQRS, mantenimiento y documentos

## Estado y control documental

- **Estado:** Cierre técnico aprobado por el Product Owner el 22 de octubre de
  2026. Condiciones 1–6 verificadas; la grabación del video académico queda
  **pendiente y fuera del cierre técnico**, con constancia explícita. El
  versionado está preparado y pendiente de autorización para ejecutarse.
- **Ajustes obligatorios aprobados:**
  1. **Idempotencia:** generar o vincular mantenimiento debe evitar duplicados
     por doble clic, reenvío o concurrencia mediante una operación idempotente
     (clave de idempotencia por solicitud, con restricción única en base de
     datos). Los mantenimientos legítimos múltiples siguen siendo posibles
     mediante acciones explícitas (nueva solicitud = nueva clave).
  2. **Autorización sobre ambos recursos:** generar o vincular mantenimiento
     exige autorización efectiva sobre la PQRS y el mantenimiento,
     pertenencia al contexto y permisos de gestión correspondientes
     (`pqrs.gestionar`, además de `mantenimiento.crear`/`mantenimiento.gestionar`).
     `pqrs.listar` no concede acceso suficiente.
  3. **Separación de la información:** el resultado público se separa de la
     información interna. El residente solo consulta sus PQRS y la información
     expresamente autorizada; los documentos restringidos **no filtran
     metadatos** (título, versión, nombre de archivo ni fechas) hacia
     espectadores sin acceso.
- **Fecha de la propuesta:** 21 de octubre de 2026.
- **Base:** cierre del Sprint 18 (`6c3e030`, aprobado el 21 de octubre de 2026).
- **Autorización académica:** el profesor autorizó adaptar la guía comercial al
  negocio de Resuelve: **no se requieren ventas, compras ni inventario**. El
  segundo corte exige demostrar un ERP con tres módulos integrados mediante un
  proceso completo.
- **Responsable:** Equipo de desarrollo de Resuelve.

## 1. Objetivo y alcance mínimo

**Objetivo:** demostrar la integración de los tres módulos operativos
existentes —PQRS, mantenimiento y gestión documental— mediante un proceso
completo y trazable: un residente registra una PQRS por una falla; un gestor
genera un mantenimiento vinculado, asigna responsable y fecha, registra el
resultado con evidencia documental y lo finaliza; el gestor consulta el
resultado, responde y cierra la PQRS mediante las operaciones existentes; el
expediente integrado puede consultarse y exportarse a PDF según permisos.

**Alcance mínimo (MVP del corte):**

1. vínculo PQRS ↔ mantenimiento con creación desde el expediente de PQRS y
   consulta bidireccional;
2. resultado y finalización del mantenimiento, con evidencia documental
   obligatoria (una versión exacta de un documento);
3. expediente integrado consultable y exportable a PDF con autorización;
4. indicadores imprescindibles de la integración en el panel compacto;
5. evidencias académicas en `docs/avance2/`.

**Regla inviolable del flujo:** finalizar el mantenimiento **no responde ni
cierra automáticamente la PQRS**; la respuesta y el cierre se realizan con las
operaciones existentes (`RegistrarRespuestaPqrs`, `AplicarAccionRapidaPqrs`).

## 2. Flujo propuesto (proceso completo)

1. El residente radica una PQRS por una falla (`pqrs.crear`, operación
   existente).
2. El gestor, desde el expediente de PQRS, usa **«Generar mantenimiento»**
   (`pqrs.gestionar` + `mantenimiento.crear`): se crea un mantenimiento en la
   misma Organización y Copropiedad, vinculado a esa PQRS.
3. Asigna responsable y fecha programada (`mantenimiento.gestionar`, operación
   existente de HU-MAN-02).
4. Registra el **resultado** del trabajo y adjunta **evidencia documental**:
   un documento y una versión específica, con estado de versión `aprobada`
   (`mantenimiento.gestionar` + `documentos.aprobar`/`documentos.gestionar`).
5. **Finaliza** el mantenimiento (`mantenimiento.gestionar`); la operación exige
   evidencia válida y **no altera el estado de la PQRS**.
6. El gestor consulta el resultado vinculado desde el expediente de PQRS y
   redacta la respuesta oficial; cierra la PQRS con las operaciones existentes
   (`pqrs.gestionar`).
7. El expediente integrado (PQRS + mantenimientos vinculados + evidencia
   documental autorizada) puede consultarse y exportarse a PDF
   (`informes.exportar`).

## 3. Refinamiento del Product Backlog (propuesta)

Historias propuestas, **pendientes de aprobación**, relacionadas con las
épicas existentes (detalle completo en `product-backlog.md`):

| Historia | Épica / sección | Actor principal | Permiso(s) |
| --- | --- | --- | --- |
| HU-PQRS-03 — Generar mantenimiento desde una PQRS | Épica 5. PQRS | Gestor | `pqrs.gestionar`, `mantenimiento.crear` |
| HU-MAN-03 — Vínculos entre PQRS y mantenimiento | Incorporación aprobada — Gestión de mantenimiento | Gestor | `mantenimiento.gestionar`, `pqrs.listar` |
| HU-DOC-03 — Evidencia documental de mantenimientos | Épica 6. Gestión Documental | Gestor | `mantenimiento.gestionar`, `documentos.gestionar` |
| HU-MAN-04 — Resultado y finalización del mantenimiento | Incorporación aprobada — Gestión de mantenimiento | Gestor | `mantenimiento.gestionar` |
| HU-REP-03 — Expediente integrado y exportación PDF | Épica 9. Reportes e Indicadores | Gestor / Auditor | `pqrs.ver_todas` o `pqrs.ver_propias`, `informes.exportar` |
| HU-REP-04 — Indicadores de integración en el panel compacto | Épica 9. Reportes e Indicadores | Cualquier usuario con acceso | permisos de consulta existentes |

Criterios de aceptación, permisos y experiencia visible de cada historia:
ver §3.1–§3.6 de este documento y las entradas «Propuesta — pendiente de
aprobación» del Product Backlog.

### 3.1 HU-PQRS-03 — Generar mantenimiento desde una PQRS

**Actor y objetivo:** como Gestor, quiero generar un mantenimiento vinculado a
una PQRS desde su expediente, para intervenir la falla sin duplicar
solicitudes.

**Criterios de aceptación:**

1. La acción «Generar mantenimiento» aparece en el expediente de PQRS solo con
   `pqrs.gestionar` y `mantenimiento.crear` efectivos; el backend exige ambos.
2. El mantenimiento se crea en la misma Organización y Copropiedad que la
   PQRS, con `solicitante` = autor de la PQRS, estado `pendiente` y el
   vínculo `pqr_id` registrado en la misma transacción.
3. Protección de duplicados accidentales: si existe un mantenimiento activo
   (no finalizado) vinculado a la misma PQRS con el mismo título normalizado,
   la interfaz exige confirmación explícita y la operación se audita como
   duplicado confirmado; nunca se bloquea un trabajo legítimo.
4. Estados vacíos y errores comprensibles: sin permisos (acción oculta y 403
   en backend), PQRS de otro contexto (404/403 según contrato existente),
   validación de título y descripción.
5. Trazabilidad: auditoría `mantenimiento.desde_pqr` con actor, PQRS,
   mantenimiento y contexto.

**Experiencia visible:** botón «Generar mantenimiento» en las acciones del
expediente de PQRS; formulario compacto con título, descripción y fecha
programada opcional; confirmación con el vínculo resultante.

### 3.2 HU-MAN-03 — Vínculos entre PQRS y mantenimiento

**Actor y objetivo:** como Gestor, quiero consultar los vínculos desde ambos
módulos, para comprender la relación entre la solicitud y su intervención.

**Criterios de aceptación:**

1. El expediente de PQRS lista los mantenimientos vinculados (título, estado,
   responsable, fecha programada/finalización) con acceso autorizado al
   detalle.
2. La ficha de mantenimiento muestra la PQRS de origen y enlaza a su
   expediente con las mismas reglas de visibilidad de `PqrPolicy`.
3. Un mantenimiento independiente puede vincularse posteriormente a una PQRS
   (`mantenimiento.gestionar` + `pqrs.listar`), en el mismo contexto; el
   vínculo y su retirada (solo antes de finalizar) se auditan.
4. La consulta de vínculos no amplía la visibilidad: cada recurso mantiene su
   política; el listado se limita a lo que el usuario puede ver.
5. Estados vacíos explícitos: «Sin mantenimientos vinculados» / «Sin PQRS de
   origen».

**Experiencia visible:** bloque «Mantenimientos vinculados» en el expediente
de PQRS y bloque «PQRS de origen» en la ficha de mantenimiento; acción
«Vincular a PQRS» en mantenimientos no finalizados.

### 3.3 HU-DOC-03 — Evidencia documental de mantenimientos

**Actor y objetivo:** como Gestor, quiero vincular un documento y una versión
específica como evidencia del mantenimiento, para conservar la prueba exacta
del trabajo realizado.

**Criterios de aceptación:**

1. La evidencia referencia el `documento_version_id` exacto; la versión queda
   fijada aunque el documento tenga versiones posteriores, sin alterar el ciclo
   documental (borrador → sometida → aprobada → vigente).
2. Solo versiones `aprobada` de documentos `activo` pueden vincularse como
   evidencia de finalización; el resto muestra el motivo concreto.
3. La vinculación exige `mantenimiento.gestionar` y acceso de lectura al
   documento (`documentos.consultar` + política del documento); audita actor,
   documento, versión, mantenimiento y fecha.
4. La retirada de la evidencia está permitida antes de finalizar y se audita;
   tras finalizar, el conjunto de evidencias es inmutable.
5. Estados vacíos: «Sin evidencia documental vinculada»; errores comprensibles
   al vincular versiones no aprobadas o documentos archivados.

**Experiencia visible:** sección «Evidencia documental» en la ficha de
mantenimiento, con selector de documento y versión aprobada, tarjetas con
versión fijada y acciones «Vincular»/«Retirar» condicionadas por permisos.

### 3.4 HU-MAN-04 — Resultado y finalización del mantenimiento

**Actor y objetivo:** como Gestor, quiero registrar el resultado y finalizar el
mantenimiento, para comunicar lo actuado y cerrar la intervención.

**Criterios de aceptación:**

1. El mantenimiento almacena `resultado` (obligatorio para finalizar),
   `resultado_registrado_por`, `resultado_registrado_at` y `fecha_finalizacion`.
2. La finalización exige: estado `en_proceso`, resultado no vacío y al menos
   una evidencia documental válida (HU-DOC-03); cualquier fallo indica el
   motivo concreto.
3. **Finalizar no responde ni cierra la PQRS**: el estado de la PQRS solo
   cambia con las operaciones existentes de respuesta y cierre.
4. Responsable válido: Usuario activo con membresía vigente en la Copropiedad
   (regla existente de HU-MAN-02); el registro conserva quién y cuándo.
5. Trazabilidad: auditoría `mantenimiento.resultado` y `mantenimiento.finalizar`
   con actor, fecha, estado previo/posterior y evidencias; operación atómica
   (transacción) con reintento idempotente ante fallo.

**Experiencia visible:** formulario «Resultado» en la ficha de mantenimiento;
botón «Finalizar mantenimiento» con confirmación que aclara «La PQRS no se
cerrará automáticamente»; estado finalizado con resultado e indicadores de
fecha.

### 3.5 HU-REP-03 — Expediente integrado y exportación PDF

**Actor y objetivo:** como Gestor o Auditor, quiero consultar el expediente
integrado y exportarlo a PDF, para compartir el proceso completo con
autorización.

**Criterios de aceptación:**

1. El expediente integra PQRS (datos, respuestas oficiales, actividades),
   mantenimientos vinculados (resultado, fechas, responsable) y evidencia
   documental (versión fijada) con la misma autorización de las políticas
   existentes.
2. La consulta respeta `pqrs.ver_todas`/`pqrs.ver_propias`, el rol activo y
   los permisos directos; el residente solo consulta sus PQRS (HU-REP-05).
3. La exportación PDF (`informes.exportar`) conserva identidad institucional,
   filtros del contexto y marcado de secciones; el PDF nunca revela
   documentos que el usuario no puede ver (se muestra «Evidencia restringida»
   o se omite según la política).
4. El PDF se genera con DomPDF (patrón de `reports.mantenimiento.pdf`) y se
   audita (`informe.expediente.pdf`).
5. Estados vacíos y mensajes comprensibles si faltan mantenimientos o
   evidencias.

**Experiencia visible:** pestaña/bloque «Expediente integrado» en el
expediente de PQRS con «Descargar PDF»; la vista usa los patrones de
informes existentes.

### 3.6 HU-REP-04 — Indicadores de integración en el panel compacto

**Actor y objetivo:** como Usuario autorizado, quiero ver los indicadores
imprescindibles de la integración en el panel, para seguir el proceso sin
ampliar la pantalla.

**Criterios de aceptación:**

1. Sin nuevas secciones: se enriquecen las tarjetas existentes de PQRS y
   Mantenimiento con contadores compactos (PQRS con mantenimiento vinculado;
   mantenimientos abiertos y finalizados con evidencia).
2. Los indicadores reutilizan las consultas existentes (sin N+1) y respetan
   autorización, rol activo y permisos directos.
3. El panel conserva su altura de una pantalla en escritorio (1366×768) y el
   apilado natural en móvil.
4. Estados vacíos cuando no hay datos.

**Experiencia visible:** microindicadores dentro de las tarjetas actuales
(barras y totales), sin nuevos títulos de sección.

### 3.7 HU-REP-05 — Visibilidad del residente (consulta del resultado)

**Actor y objetivo:** como Residente, quiero consultar el estado del
mantenimiento asociado a mi PQRS, para saber cómo avanza la falla.

**Criterios de aceptación:**

1. El residente ve, en su PQRS, los mantenimientos vinculados con estado,
   fecha programada/finalización y el resultado **público**; no accede a
   comentarios internos, documentos de nivel interno ni evidencia restringida.
2. La visibilidad se resuelve por las políticas existentes (`PqrPolicy`,
   `DocumentoPolicy`) y el `nivel_acceso` del documento; vincular no amplía
   la visibilidad.
3. Estados vacíos y mensajes neutros ante recursos restringidos.
4. Sin nuevas capacidades de mutación para el residente.

## 4. Decisiones que resuelve esta propuesta

| Decisión | Recomendación mínima | Justificación |
| --- | --- | --- |
| ¿Una PQRS puede originar uno o varios mantenimientos? | **Varios** (FK `mantenimientos.pqr_id` nullable) | Una falla puede exigir varias intervenciones (fontanería, electricidad). Evita duplicar PQRS artificiales y es el cambio mínimo (una columna). |
| ¿Cómo evitar duplicados accidentales sin bloquear trabajos legítimos? | **Advertencia con confirmación**, no bloqueo: mismo `pqr_id` + título normalizado + mantenimiento no finalizado → confirmación explícita y auditoría como duplicado confirmado | Sin índice único (trabajos repetidos son legítimos). La confirmación previene errores; la auditoría conserva la intención. |
| ¿Un mantenimiento independiente puede vincularse posteriormente? | **Sí**, antes de finalizar, con `mantenimiento.gestionar` + `pqrs.listar`, mismo contexto y auditoría; el vínculo es inmutable tras finalizar | El trabajo real se planifica y luego se relaciona; la inmutabilidad protege la evidencia final. |
| ¿Qué documentos y estados sirven como evidencia de finalización? | **Versiones `aprobada` de documentos `activo`** (al menos una) | Es el único estado documental que garantiza revisión; coincide con `versionVigente()` y con el ciclo documental existente. |
| ¿Cómo conservar la versión exacta sin alterar el ciclo documental? | **Fijar `documento_version_id`** en el vínculo de evidencia (tabla puente `mantenimiento_documento_evidencia`) | No modifica estados ni vigencias documentales; la evidencia apunta a la versión exacta aunque existan versiones posteriores. |
| Archivo de documentos, cambios de acceso y eliminación/anulación de vinculados | El vínculo **persiste** y muestra el estado actual (`archivado`, acceso restringido); la visibilidad se recalcula con `DocumentoPolicy`/`nivel_acceso`; **no se eliminan físicamente** registros vinculados; la retirada de evidencia solo antes de finalizar y siempre auditada | Preserva trazabilidad y evita huérfanos; coherente con «desactivar frente a eliminar» (§7.4 de roles-y-permisos). |
| ¿Qué consulta el residente sin acceder a documentos internos? | Estado, fechas y **resultado público** de los mantenimientos de su PQRS; la evidencia se muestra solo si `DocumentoPolicy` lo permite, en otro caso «Evidencia restringida» | Regla 7 (vincular no amplía visibilidad) y principios de experiencia de producto (contexto explícito, sin información interna). |

## 5. Reglas y seguridad (verificables)

| # | Regla | Ubicación de implementación | Cómo se prueba |
| --- | --- | --- | --- |
| 1 | Misma Organización y Copropiedad en todos los vínculos | Casos de uso (`GenerarMantenimientoDesdePqr`, `VincularMantenimientoPqr`, `VincularEvidenciaMantenimiento`): validación previa en transacción | Pruebas de cruce de contexto (otra org/copropiedad → 403/404), duplicado de vínculo y migración |
| 2 | Autorización efectiva sobre cada recurso y operación | Policies (`PqrPolicy`, `MantenimientoPolicy`, `DocumentoPolicy`) + permisos de gestión exigidos sobre **ambos** recursos (`pqrs.gestionar`, `mantenimiento.crear`/`gestionar`; `pqrs.listar` **no** es suficiente) | Accesos denegados por permiso faltante (403) en cada operación, incluido `pqrs.listar` aislado |
| 3 | Respeto del rol activo y permisos directos | `ContextoOperativo`/`AutorizacionContextual` (Sprint 18) | Pruebas con rol activo restrictivo y con permiso directo puntual |
| 4 | Responsable activo con membresía vigente | `GestionarMantenimiento`/`RegistrarMantenimiento` (regla existente) | Responsable sin membresía o desactivado → error concreto |
| 5 | Protección frente a duplicados accidentales **e idempotencia estricta** | Clave de idempotencia única (`mantenimientos.idempotency_key`); la clave pertenece a una solicitud concreta y su reutilización con otra PQRS o datos incompatibles se rechaza sin devolver ni modificar el recurso original; confirmación explícita ante mismo título activo | Doble clic/reenvío/concurrencia con la misma clave → una sola creación (probado con dos conexiones MySQL reales en paralelo); nueva clave explícita → mantenimiento legítimo adicional; reutilización indebida → rechazo con error claro |
| 6 | Evidencia documental válida antes de finalizar | `FinalizarMantenimiento`: exige ≥1 versión `aprobada` vinculada | Finalizar sin evidencia → error; con evidencia no aprobada → error con motivo |
| 7 | Vincular recursos no amplía su visibilidad **ni filtra metadatos** | Consultas y PDF usan las policies; los documentos restringidos se ocultan sin título, versión, archivo ni fechas | Residente/externo: ausencia total de metadatos de evidencia restringida en vista y PDF |
| 8 | Finalizar mantenimiento no cierra automáticamente la PQRS | `FinalizarMantenimiento` (transacción propia, sin mutar PQRS) | Prueba de finalización y assert de estado de la PQRS sin cambio |
| 9 | Operaciones relacionadas atómicas mediante transacciones | Casos de uso con `DB::transaction` | Fallo transaccional inyectado (versión inválida) → sin estados intermedios |
| 10 | Trazabilidad de actor, fecha, origen y resultado | `AuditLog` + `pqr_activities` existentes | Auditoría de cada operación con actor/contexto/origen |
| 11 | Separación de resultado público e interna | `mantenimientos.resultado_publico` / `resultado_interno`; vistas y PDF aplican la separación por rol y pertenencia | Residente ve solo el resultado público; el interno no aparece en su vista ni en su PDF |

## 6. Experiencia de producto

Acciones y pantallas dentro de los módulos existentes:

- **Expediente de PQRS:** botón «Generar mantenimiento» (permisos), bloque
  «Mantenimientos vinculados» (vacío: «Sin mantenimientos vinculados»),
  bloque «Resultado» visible para preparar la respuesta oficial (solo gestor),
  y «Expediente integrado → Descargar PDF».
- **Ficha de mantenimiento:** bloque «PQRS de origen» (enlace), sección
  «Evidencia documental» (vacío: «Sin evidencia documental vinculada»),
  formulario de resultado, botón «Finalizar mantenimiento» con confirmación
  «La PQRS no se cerrará automáticamente» y estado finalizado con
  resultado/fechas.
- **Vínculos y evidencia:** tarjetas con versión fijada (`Versión N —
  aprobada el …`), acciones condicionadas por permisos, estados vacíos,
  validaciones (versión no aprobada, documento archivado) y confirmaciones de
  retirada (solo antes de finalizar).
- **Panel compacto:** microindicadores dentro de las tarjetas existentes; sin
  secciones nuevas ni lógica de negocio en vistas (solo presentación de datos
  ya autorizados).

## 7. Preparación académica (entregables)

1. **MER y diccionario de datos:** actualizar
   `docs/03-tecnica/mer-resuelve-erp.md` (+`erDiagram.mmd`) y
   `modelo-de-datos.md` con `mantenimientos.pqr_id`, `mantenimientos.resultado`
   (+registro del resultado) y `mantenimiento_documento_evidencia`.
2. **Matriz de reglas:** tabla §5 con ubicación y pruebas; publicar en
   `docs/avance2/`.
3. **Datos reproducibles de demostración:** fixture aislado con ≥10 registros
   reales por módulo principal (PQRS, mantenimientos, documentos/versiones),
   generados por seeders sobre base de demostración; **sin tablas artificiales**
   para cumplir cantidades.
4. **Guion del video (≤10 min):** flujo integrado completo (radicación →
   generación → asignación/fecha → resultado + evidencia → finalización →
   respuesta/cierre de PQRS → expediente y PDF → panel).
5. **Evidencias y documento en `docs/avance2/`:** capturas, acta de la
   ejecución y adaptación de la guía comercial.
6. **Actualización del README** (`docs/README.md`) al cierre.

**Adaptación de requisitos comerciales:** la guía comercial se centra en
ventas, compras e inventario; Resuelve opera procesos de servicio. La
adaptación sustituye esos módulos por el proceso de atención (PQRS →
mantenimiento → evidencia documental → respuesta), conservando los principios
del ERP: integración entre módulos, autorización, trazabilidad, indicadores y
reportes.

**Separación de entregables:**

- *Software:* HU-PQRS-03, HU-MAN-03/04, HU-DOC-03, HU-REP-03/04/05 y sus
  pruebas.
- *Académicos:* MER/diccionario, matriz de reglas, fixture demostrable,
  guion de video, evidencias en `docs/avance2/` y README.

## 8. Dependencias, riesgos y orden de implementación

**Dependencias:** Sprint 18 publicado (`6c3e030`); módulos PQRS, mantenimiento
y documentos existentes; DomPDF en reportes; políticas y permisos vigentes.
**No se requieren dependencias nuevas.**

**Riesgos:**

- Ventana hasta el **25 de octubre de 2026** (4 días): alcance mínimo y
  orden estricto (abajo).
- PDF del expediente: reutiliza DomPDF, pero la composición integrada puede
  requerir ajustes de paginación (riesgo medio-bajo).
- Demostración con datos reproducibles sin contaminar la BD de desarrollo:
  fixture aislada (riesgo bajo).
- Pendientes del Sprint 18 **no bloquean** el flujo: el envío real de correo
  no interviene en el proceso (las notificaciones usan el canal actual) y las
  divergencias RBAC son deriva documentada en datos locales; se recomienda no
  usar la BD de desarrollo para la demostración.

**Orden de implementación y estimación (21–25 de octubre de 2026):**

| Orden | Contenido | Estimación |
| --- | --- | --- |
| 1 | Migraciones (vínculo, resultado, puente de evidencia) + HU-PQRS-03 + HU-MAN-03 (generación y vínculos) + pruebas | 6–8 h |
| 2 | HU-DOC-03 (evidencia con versión exacta) + HU-MAN-04 (resultado/finalización) + pruebas | 6–8 h |
| 3 | HU-REP-03 (expediente + PDF) + HU-REP-05 (visibilidad residente) + pruebas | 6–8 h |
| 4 | HU-REP-04 (indicadores compactos) + fixture demostrable (≥10 por módulo) | 3–4 h |
| 5 | MER/diccionario, matriz de reglas, guion de video, evidencias `docs/avance2/`, README y revisión final | 6–8 h |

Total estimado: **27–36 horas** de trabajo focalizado; factible en los cuatro
días si se aprueba el alcance mínimo sin ampliaciones. **Si el tiempo aprieta:**
prioridad absoluta a los pasos 1–2 y al guion/evidencias; el PDF puede
reducirse al formato mínimo con identidad institucional, y los indicadores a
los dos contadores imprescindibles.

**Condiciones concretas de cierre:**

1. Flujo completo ejecutado de extremo a extremo sin intervención manual de
   datos (radicación → finalización → respuesta/cierre).
2. Las 10 reglas §5 verificadas con pruebas (incluidos accesos denegados,
   cruces de contexto, reintentos y fallos transaccionales) y suite completa
   sin regresiones.
3. `npm run build`, `git diff --check` y diagnóstico RBAC sin regresiones.
4. Expediente integrado y PDF verificados en navegador (escritorio/móvil).
5. Entregables académicos §7 completos en `docs/avance2/`.
6. Acta con aprobación del Product Owner.

**Exclusiones explícitas (para no ampliar el sprint):**

- Ventas, compras, inventario y cualquier requisito comercial no aplicable a
  Resuelve (adaptación autorizada por el profesor).
- Modificación del ciclo documental (estados/vigencias), de las políticas
  existentes o de la arquitectura; sin dependencias nuevas.
- Nuevas entidades artificiales para cumplir volúmenes de demostración.
- Cambios en el envío real de correo y saneamiento de datos RBAC (pendientes
  del Sprint 18, conservados aparte).
- HU-REP-02 (visión consolidada multi-copropiedad), IA (HU-IA-01) y
  notificaciones nuevas.

## Registro de implementación (Sprint 19)

Implementado sobre las capas existentes, con los tres ajustes obligatorios
aprobados por el Product Owner (idempotencia, autorización sobre ambos recursos
y separación público/interno sin filtrado de metadatos).

**Esquema (2 migraciones):**

- `add_integracion_to_mantenimientos_table`: `pqr_id` (FK restrict),
  `idempotency_key` (única), `resultado_publico`/`resultado_interno`,
  `resultado_registrado_por_user_id`, `resultado_registrado_at`, `finalizado_at`;
  índice `(pqr_id, estado)`.
- `create_mantenimiento_documento_evidencia_table`: puente con
  `documento_version_id` (versión exacta), único por
  `(mantenimiento_id, documento_version_id)` y `vinculado_por_user_id`.

**Casos de uso (reutilizando `AuditLog`, `RegistrarActuacionPqrs` y las
policies existentes):** `GenerarMantenimientoDesdePqr`, `VincularMantenimientoPqr`,
`RegistrarResultadoMantenimiento`, `FinalizarMantenimiento`,
`VincularEvidenciaMantenimiento` y `ConsultaExpedienteIntegrado`.

**Experiencia:** bloque «Mantenimientos vinculados» + «Generar mantenimiento»
(clave de idempotencia por solicitud) y «Expediente PDF» en el expediente PQRS;
bloques «PQRS de origen», «Evidencia documental», «Resultado» (público/interno)
y «Finalizar» (con confirmación de que la PQRS no se cierra) en la ficha de
mantenimiento; microindicadores en las tarjetas del panel compacto.

**Reportes:** PDF del expediente integrado con identidad institucional
(`reports/expediente.blade.php`, ruta `pqrs.expediente.pdf`), construido sobre
la consulta autorizada y auditado (`informe.expediente.pdf`).

**Verificación:** suite completa en verde (719 pruebas: 710 en verde y 9
omitidas por requerir MySQL), incluidas las pruebas de integración del flujo
(idempotencia, doble autorización, cruce de contexto, evidencia exacta,
separación público/interno, no cierre automático, PDF y panel), la prueba de
no crecimiento N+1 de la página de PQRS y una **prueba real de concurrencia en
MySQL** (`MantenimientoIdempotencyMysqlConcurrencyTest`, dos conexiones
simultáneas con la misma clave → un único mantenimiento, y reutilización de la
clave con otra PQRS o datos incompatibles rechazada sin devolver ni modificar
recursos incorrectos). Flujo completo verificado además en navegador contra la
base aislada (generación, gestión, resultado, evidencia, finalización sin cierre
automático, respuesta, cierre explícito y PDF) y con una sesión de residente
(sin resultado interno ni metadatos de evidencia restringida). Datos de
demostración reproducibles en base aislada (`resuelve_sprint19_demo`, fixture
`DemoIntegracionFixtureSeeder`): 24 PQRS, 12 mantenimientos y 12 documentos con
evidencias y resultados reales; ver la receta completa en
[docs/avance2/aceptacion-visual.md](../avance2/aceptacion-visual.md).

**Entregables académicos:** `docs/avance2/` (flujo verificable, matriz de
reglas, guion de video, aceptación visual y adaptación de la guía), diccionario
de datos y MER actualizados **incluida la imagen `diagrama_mer.png`
regenerada desde `erDiagram.mmd`**.

**Actualización del entorno de desarrollo (`resuelve_erp`, 21 de octubre de
2026):** el código del Sprint 19 quedó desplegado antes de aplicar sus
migraciones a la base de desarrollo, lo que provocó el error 500 de `/panel`
(`Unknown column 'mantenimientos.pqr_id'` en el indicador de integración). Con
autorización explícita del Product Owner se aplicaron **únicamente** las dos
migraciones pendientes del Sprint 19
(`2026_10_21_000100_add_integracion_to_mantenimientos_table` y
`2026_10_21_000200_create_mantenimiento_documento_evidencia_table`), ambas
puramente aditivas (columnas anulables, índices y una tabla nueva; sin
eliminación ni sobrescritura de datos), tras:

1. verificar la conexión y el estado de migraciones (exactamente 2 pendientes);
2. comprobar que las migraciones no alteran datos existentes;
3. crear un respaldo recuperable en `storage/backups/` (gitignored) y
   **comprobar su restauración** en una base de verificación con conteos
   idénticos (`resuelve_erp_backup_check`).

Sin `migrate:fresh`, refresh, rollback, seeders ni saneamientos RBAC. Los
conteos de datos se conservaron intactos (users 10, pqrs 17, mantenimientos 6,
documentos 7, audit_logs 232). Verificación posterior en navegador: `/panel`
operativo con los microindicadores, listados y detalles de PQRS, mantenimiento y
documentos correctos, registros anteriores accesibles, flujo integrado completo
(mantenimiento `/mantenimiento/10` generado desde la PQRS #1, gestionado, con
resultado público/interno, evidencia de versión exacta y finalizado **sin cerrar
la PQRS**, que permaneció `radicada`; expediente PDF servido como
`application/pdf`) y **cero entradas nuevas en `storage/logs/laravel.log`** tras
la migración (las últimas son las del propio incidente, previas a ella).

**Pendientes reales del Sprint 19:**

1. **Cierre formal del sprint:** pendiente de aprobación del Product Owner; no
   se ha realizado commit, push ni acta de cierre.
2. **Video demostrativo (≤10 min):** solo existe el guion en
   `docs/avance2/guion-video.md`; la grabación está pendiente.
3. **Capturas de aceptación visual:** pendientes de la revisión del Product
   Owner (requieren ventana de escritorio visible).

## Cierre técnico (22 de octubre de 2026)

El Product Owner aprobó el **cierre técnico del Sprint 19** tras verificar las
seis condiciones de cierre: flujo completo de extremo a extremo, reglas §5 con
pruebas (incluidos cruce de contexto, reintentos y concurrencia MySQL real),
`npm run build`/`git diff --check` y diagnóstico RBAC sin regresiones (3
errores y 7 avisos: los mismos 3 errores documentados y un aviso adicional de
deriva previa, ajenos al sprint), expediente y PDF verificados en navegador en
ambos temas y anchos, y entregables académicos en `docs/avance2/`.

**Constancia pendiente:** la grabación del video académico (≤10 min) sigue
pendiente; existe únicamente el guion aprobado como material fuente
(`docs/avance2/guion-video.md`) y **no se marca como entregable completado**.
Quedan igualmente pendientes las capturas de aceptación visual del Product
Owner. Estos pendientes no afectan el cierre técnico ni bloquean el Sprint 20.

## Plan de versionado (preparado, pendiente de autorización)

Tres grupos separados; **no se ejecuta commit ni push hasta autorización
expresa**:

1. **Commit funcional — `feat(sprint19): integración de PQRS, mantenimiento y
   documentos`:** casos de uso `app/Application/{Mantenimiento,Documentos,Pqrs}`,
   modelos `Mantenimiento`, `MantenimientoDocumentoEvidencia`, `Pqr`, controladores
   (`MantenimientoController`, `PqrController`, `ReportController`), `routes/web.php`,
   2 migraciones, `DemoIntegracionFixtureSeeder`, vistas
   `mantenimiento/show`, `pqrs/show`, `pqrs/panel`, `reports/expediente`, pruebas
   `IntegracionModulosTest`, `EvidenciaMantenimientoTest`, `ExpedienteIntegradoTest`,
   `MantenimientoIdempotencyMysqlConcurrencyTest` y documentación del sprint
   (ficha, `docs/avance2/`, MER + PNG, `modelo-de-datos.md`, `product-backlog.md`,
   `docs/README.md`).
2. **Commit visual — `feat(ui): selector de rol activo y enlaces de tablas`:**
   `tests/Feature/MantenimientoTest.php`, `resources/views/mantenimiento/index.blade.php`
   y los hunks visuales de `resources/css/app.css` y
   `docs/03-tecnica/interfaz-web.md`.
3. **Fuera del versionado (preservados, sin eliminar):** cambios ajenos
   `resources/views/layouts/admin.blade.php`, `tests/Feature/LogoPresentationTest.php`
   y el hunk ajeno de `interfaz-web.md` (se separan con `git add -p`);
   artefactos residuales `.github/workflows/Panel Resuelve de Gestión Residencial.png`
   y el directorio duplicado `../resuye-erp/` (excluidos; retiro sujeto a
   autorización).

Nota: `app.css` e `interfaz-web.md` mezclan ambos grupos; se separarán por
hunks (`git add -p`), no por archivo.

## Pendientes del Sprint 18 (conservados, sin ejecutar)

1. **Envío real de recuperación de contraseña:** pendiente proveedor de correo
   real; no bloquea ninguna historia del Sprint 19 (el flujo integrado no
   depende de correo).
2. **Saneamiento de divergencias RBAC (3 errores + 6 avisos):** pendiente de
   autorización; no bloquea el Sprint 19, pero la demostración debe usar una
   base de datos aislada para no exponer la deriva local.
