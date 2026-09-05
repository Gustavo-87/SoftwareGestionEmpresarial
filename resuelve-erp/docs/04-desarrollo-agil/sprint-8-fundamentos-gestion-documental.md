# Sprint 8 — Fundamentos de Gestión Documental

## Control documental

- **Versión:** v1.1
- **Estado:** Completado
- **Fecha de aprobación:** 3 de agosto de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## 1. Objetivo

Incorporar el fundamento funcional y técnico de Gestión Documental para
centralizar Documentos de Copropiedad, conservar versiones gobernadas y
permitir su consulta únicamente a Usuarios autorizados, manteniendo el
aislamiento contextual y la evolución incremental del monolito Laravel.

Este sprint desarrolla la Épica 6 del
[Product Backlog](product-backlog.md) y aplica especialmente la
[arquitectura objetivo](../05-arquitectura/arquitectura-objetivo.md), los ADR
003 a 006, el [ADR-008](../10-adr/ADR-008-casos-de-uso-compartidos.md) y el
[ADR-009](../10-adr/ADR-009-gestion-documental-antes-de-ia.md).

## 2. Alcance operativo y exclusiones

El alcance visible comprende:

- crear y organizar Documentos de la Copropiedad activa;
- cargar y conservar versiones en almacenamiento privado;
- someter, aprobar o rechazar versiones;
- consultar y descargar versiones aprobadas y vigentes según autorización;
- sustituir versiones sin sobrescribir su historial;
- archivar Documentos sin eliminación física;
- verificar de forma diagnóstica la integridad entre archivos y metadatos.

El ámbito Organización queda preparado estructuralmente mediante
`copropiedad_id` nullable, pero no tendrá interfaz, rutas, permisos, consultas
ni casos de uso operativos en este sprint. También quedan excluidos IA, RAG,
embeddings, extracción, indexación, API, integración con PQRS, nuevos reportes,
nuevas notificaciones, Documentos de plataforma y la Ley 675 como fuente
normativa.

## 3. Historias de Usuario

### HU-DOC-01 — Centralización documental

Como Administrador
Quiero centralizar los Documentos y Reglamentos de una Copropiedad
Para disponer de la información documental necesaria para su gestión.

### HU-DOC-02 — Consulta autorizada

Como Usuario autorizado
Quiero consultar los Documentos disponibles para mi contexto
Para utilizar información pertinente en mis actuaciones.

## 4. Fase A — Modelo Funcional aprobado

### 4.1. Identidad y clasificación

Un Documento es una unidad documental gobernada con identidad estable durante
todo su ciclo de vida. Sus modificaciones generan Versiones y nunca una nueva
identidad documental.

El Tipo identifica su naturaleza y la Categoría clasifica funcionalmente su
contenido; son conceptos independientes y ninguno sustituye al otro.

Tipos iniciales:

- Documento general.
- Reglamento.
- Manual de Convivencia.
- Acta.

Categorías iniciales:

- Normativo.
- Administrativo.
- Gobierno de la Copropiedad.
- Contractual.
- Financiero.
- Comunicaciones.
- Otro.

Reglamento y Manual de Convivencia son tipos de Documento, no entidades
independientes. Ambos son normativos, pertenecen obligatoriamente a una
Copropiedad y no pueden existir como Documentos de Organización.

### 4.2. Ámbito y Propietario Documental

Todo Documento pertenece exactamente a un ámbito: Organización o Copropiedad,
nunca a ambos. El ámbito es inmutable; si el contenido debe existir en otro
ámbito, se crea otro Documento con identidad propia.

Todo Documento tiene como Propietario Documental a un Usuario global con
Membresía vigente en la Copropiedad al asignarlo. Esta responsabilidad de
negocio no concede permisos, no representa aprobación y no sustituye la
autorización contextual. La pérdida posterior de la Membresía conserva la
referencia histórica.

### 4.3. Estados, acceso y aprobación

- Documento: `activo` o `archivado`.
- Versión: `borrador`, `pendiente_aprobacion`, `aprobada` o `rechazada`.
- Nivel de acceso: `administrativo`, `interno` o `comunidad`.

El flujo aprobado es:

```text
borrador → pendiente_aprobacion → aprobada | rechazada
```

Someter cierra la Versión para cambios; rechazar exige observación y corregir
crea una nueva Versión. No se exige que cargador y aprobador sean distintos,
pero ambas actuaciones deben quedar identificadas. El nivel de acceso
restringe la consulta y nunca concede acceso por sí solo.

### 4.4. Versionado, vigencia y retención

- La numeración es secuencial dentro del Documento y nunca se reutiliza.
- Una Versión aprobada es inmutable y no se sobrescribe.
- En un instante solo puede existir una Versión aprobada y vigente por
  Documento.
- No se admiten periodos de vigencia aprobados superpuestos.
- `sustituida` y `vencida` son condiciones derivadas, no estados persistidos.
- Una sucesora sustituye a la anterior al iniciar su vigencia; la anterior se
  conserva.
- Documentos y Versiones se retienen indefinidamente en este sprint.
- No existe eliminación física desde los casos de uso.

Una Versión aprobada debe ser potencialmente indexable en el futuro sin
cambiar identidad, versionado o trazabilidad. Esta previsión no habilita IA,
RAG, embeddings ni indexación.

### 4.5. Origen y hash

El origen informativo de una Versión es `usuario`, `sistema` o `importado`; no
modifica permisos ni comportamiento.

El hash SHA-256 pertenece a la Versión y permite detectar duplicados, verificar
integridad e identificar exactamente el archivo aprobado. Se calcula durante
la carga y se recalcula únicamente mediante diagnóstico, no en cada descarga.

## 5. Fase B — Diseño Técnico aprobado

Gestión Documental se incorpora como módulo interno del monolito. Blade será el
único canal; los controladores atenderán transporte HTTP y los casos de uso
coordinarán autorización, reglas, transacciones, persistencia, filesystem e
historial. Eloquent implementará el acceso a datos sin repositorios genéricos,
CQRS, Event Sourcing ni otras abstracciones no justificadas.

El agregado `Documento` contiene `DocumentoVersion` y
`DocumentoActuacion`. Los catálogos aprobados se representarán mediante enums
PHP, casts y validación backend, sin tablas de catálogo.

## 6. Modelo relacional aprobado

### 6.1. `documentos`

Conserva `organizacion_id`, `copropiedad_id` nullable, `ambito`,
`propietario_documental_user_id`, tipo, categoría, título, descripción, nivel
de acceso, estado, actores y fechas de creación y archivado.

Restricciones principales:

- clave candidata `(id, organizacion_id, copropiedad_id)`;
- FK compuesta `(copropiedad_id, organizacion_id)` hacia Copropiedad;
- `CHECK` que exige Organización y `copropiedad_id IS NULL` para ámbito
  Organización, o ambos IDs para ámbito Copropiedad;
- propietario obligatorio con FK restrictiva;
- reasignación previa o rechazo funcional controlado antes de eliminar al
  Usuario propietario.

La FK compuesta garantiza que la Copropiedad pertenece a la Organización
indicada. Todos los casos de uso del Sprint 8 exigen ámbito Copropiedad.

### 6.2. `documento_versiones`

Conserva Documento y contexto redundante, número, estado, origen, archivo,
MIME, extensión, tamaño, SHA-256, vigencia, Versión sustituida, actores,
instantáneas mínimas de sus nombres y fechas del flujo.

Restricciones principales:

- `UNIQUE (documento_id, numero)`;
- `UNIQUE (documento_id, hash)`;
- máximo una Versión `pendiente_aprobacion` por Documento mediante garantía
  transaccional y restricción única condicional;
- FK compuesta hacia Documento y contexto;
- FK compuesta autorreferenciada que obliga a que la Versión sustituida
  pertenezca al mismo Documento, Organización y Copropiedad;
- `UNIQUE (sustituye_version_id)` para una sola sucesora directa;
- actores históricos nullable con `ON DELETE SET NULL` e instantánea exclusiva
  del nombre visible.

### 6.3. `documento_actuaciones`

Registro de solo adición con contexto, Documento, Versión opcional, acción,
detalle no sensible, actor nullable e instantánea de nombre. Solo admite
inserción: no tendrá `updated_at`, `deleted_at`, edición ni eliminación desde
casos de uso.

## 7. Autorización y consultas contextuales

Permisos mínimos:

- `documentos.consultar`
- `documentos.gestionar`
- `documentos.aprobar`
- `documentos.archivar`

La autorización efectiva combina `ContextoOperativo`, Membresía vigente,
permiso contextual, pertenencia del recurso, nivel de acceso, estado y
vigencia. Ser Propietario Documental no concede acceso.

`ConsultaDocumentosContextuales` será el único punto operativo para construir
consultas, bindings, Versiones, descargas e historial. Toda consulta visible
exigirá Organización y Copropiedad activas y `ambito = copropiedad`. Un recurso
externo y uno inexistente devolverán el mismo `404`.

## 8. Almacenamiento privado

Los archivos se guardarán fuera del acceso público en rutas generadas por el
servidor y segmentadas por Organización, Copropiedad, Documento y Versión.
Nunca se aceptarán rutas o nombres físicos enviados por el cliente.

La base de datos y el filesystem no comparten transacción atómica. La carga
usará staging privado, validación de MIME, extensión, tamaño y hash, transacción
SQL, movimiento a ruta definitiva y limpieza compensatoria ante fallos. Un
comando diagnóstico detectará archivos ausentes, alterados o huérfanos sin
corregirlos automáticamente.

Toda descarga se resolverá mediante identificadores internos de Documento y
Versión, autorización backend y comprobación de existencia. No utilizará rutas
públicas ni recalculará SHA-256 en cada solicitud.

## 9. Versionado, transacciones y actuaciones

La creación de una Versión bloqueará el Documento dentro de una transacción
antes de calcular el siguiente consecutivo. La restricción única será la última
garantía ante concurrencia.

La aprobación o sustitución bloqueará Documento y Versiones afectadas,
validará contexto, estado, vigencia y sucesión, y registrará las actuaciones en
la misma transacción. Ningún formulario genérico podrá modificar una Versión
aprobada. El caso de aprobación podrá cerrar controladamente
`vigente_hasta` de la anterior cuando entre en vigor la sucesora, dejando la
actuación correspondiente.

## 10. Estrategia de pruebas

La validación obligatoria cubre:

- migraciones, rollback y reaplicación sobre MySQL 8.4;
- FKs compuestas, `CHECK`, catálogos y aislamiento entre dos contextos;
- consecutivos concurrentes sin duplicados;
- un solo pendiente por Documento;
- hash duplicado rechazado dentro del mismo Documento y permitido entre
  Documentos diferentes;
- sustitución cruzada rechazada y una sola sucesora directa;
- inmutabilidad y cierre controlado de vigencia;
- rollback y limpieza compensatoria de archivos;
- archivos temporales y definitivos inaccesibles públicamente;
- descarga autorizada y rechazo uniforme de IDOR;
- eliminación controlada del propietario y conservación del nombre de actores
  eliminados;
- ausencia de operación web para ámbito Organización;
- permisos mínimos, niveles de acceso e historial append-only;
- suite completa de regresión de Sprints 1 a 7 y equivalencia contextual con
  cero divergencias.

## 11. Definition of Done

El Sprint 8 podrá marcarse como Completado cuando:

- HU-DOC-01 y HU-DOC-02 operen dentro de la Copropiedad activa;
- Documentos, Versiones y Actuaciones cumplan las restricciones aprobadas;
- los archivos permanezcan privados y toda descarga sea contextual;
- versionado, aprobación, rechazo, vigencia, sustitución, hash y archivo sean
  verificables y trazables;
- no existan cruces entre Copropiedades ni estados parciales ante fallos;
- el Propietario Documental sea contextual al asignarse y no conceda permisos;
- el comando diagnóstico informe inconsistencias sin mutarlas;
- todas las pruebas específicas, de aislamiento y de regresión finalicen sin
  fallos;
- el ámbito Organización permanezca sin operación visible;
- no se incorporen IA, API, PQRS, reportes o notificaciones nuevas;
- la documentación oficial afectada quede actualizada.

## 12. Implementación y validación de Fase B

La Fase B incorpora Documentos, Versiones y Actuaciones de Copropiedad, con
consultas y bindings contextuales, `DocumentoPolicy`, permisos documentales y
casos de uso para crear, archivar, cargar, someter, aprobar, rechazar y
descargar Versiones. La operación web queda limitada a la Copropiedad activa;
los recursos externos o inexistentes se resuelven uniformemente como `404`.

Los archivos se validan y reciben hash SHA-256 antes de persistir. Se conservan
en staging privado y luego en una ruta privada generada por el servidor. La
compensación elimina los archivos creados ante fallos posteriores, sin afirmar
atomicidad entre SQL y filesystem. La descarga exige autorización, estado
`aprobada`, vigencia y existencia física del archivo.

El flujo de Versiones registra actuaciones append-only dentro de sus
transacciones: borrador, sometimiento, aprobación, rechazo y carga de una
sucesora. La aprobación puede cerrar la vigencia de la anterior y las
restricciones relacionales impiden sustituciones fuera del Documento y su
contexto. El comando `documentos:diagnosticar` es solo informativo: detecta
archivos ausentes, huérfanos, hash alterado y metadatos inconsistentes, sin
corregir ni eliminar datos.

### 12.1. Evidencia de validación

- Suite final SQLite: **189 pruebas y 815 aserciones**, sin fallos.
- Pruebas documentales específicas: 24 pruebas y 73 aserciones, sin fallos.
- MySQL 8.4: `migrate:fresh`, rollback completo y reaplicación de migraciones
  finalizaron correctamente; 8 pruebas de esquema y flujo documental, con 17
  aserciones, finalizaron sin fallos.
- MySQL verificó catálogos, CHECK, FKs compuestas, unicidad de número y hash,
  sucesión contextual y una única Versión pendiente mediante columna generada.
- `resuelve:verificar-equivalencia-autorizacion-contextual`, ejecutado con el
  contexto institucional de prueba, informó **0 divergencias**.
- `git diff --check` finalizó sin salida.

### 12.2. Estado de cierre

El Sprint queda Completado. Permanece como riesgo técnico no bloqueante la
automatización de una prueba empírica con dos conexiones MySQL simultáneas
sobre el consecutivo de Versiones. El bloqueo transaccional del Documento y la
restricción única están implementados y verificados; la carrera real no se
ejecutó como prueba independiente.
