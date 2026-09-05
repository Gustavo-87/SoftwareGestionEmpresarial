# IA — Asistencia documental con revisión humana

## Estado y alcance

**Diseño Técnico Definitivo aprobado para Sprint 11.** Describe el primer
incremento de `HU-IA-01`; no representa funcionalidad implementada ni define
proveedor, modelo, OCR, embeddings, base vectorial o infraestructura definitiva.

El incremento analiza un único Reglamento o Manual de Convivencia, con su
Versión aprobada y vigente en la Copropiedad activa. Produce asistencia
documental verificable y sin efectos de negocio, siempre sujeta a revisión
humana.

## Separación de responsabilidades

### Laravel — producto Resuelve

Laravel conserva la autoridad sobre:

- experiencia Blade y ciclo completo de la solicitud;
- `ContextoOperativo`, Membresía, permisos y autorización documental;
- elegibilidad del Documento y la Versión;
- persistencia, estados, transacciones, idempotencia y colas;
- trazabilidad, consulta de resultados y revisión humana;
- validación de contratos y protección de archivos privados.

La lógica de autorización y negocio no se delega al prototipo Python.

### Prototipo Python — artefacto académico

El prototipo demuestra de forma aislada la recuperación de evidencia y la
generación determinista. Recibe entradas explícitas, devuelve salidas
estructuradas y no accede directamente a la base de datos, sesión, permisos,
colas ni vistas de Resuelve. No constituye un servicio de producción ni obliga
a adoptar framework, proveedor, modelo o mecanismo definitivo de extracción.

La integración futura deberá implementar los mismos contratos desde la capa de
Infraestructura de Laravel. El repositorio Python no forma parte de la entrega
documental ni se crea durante esta formalización.

## Contratos internos

### `RecuperadorEvidenciaDocumental`

Recibe la identidad inmutable de la Versión autorizada, su archivo y límites de
procesamiento. Devuelve:

- manifiesto de la fuente;
- evidencia localizada en orden estable;
- anclajes verificables por página o sección;
- advertencias y causa explícita cuando no exista evidencia suficiente.

No decide autorización, no busca otros Documentos y no completa vacíos con
fuentes externas.

### `GeneradorAsistenciaDocumental`

Recibe la consulta normalizada, el manifiesto y únicamente la evidencia
recuperada. Devuelve:

- consideraciones preliminares;
- afirmaciones documentales asociadas a referencias identificables;
- advertencia de evidencia insuficiente cuando corresponda;
- metadatos mínimos para validación y trazabilidad.

No recibe acceso libre al repositorio documental ni ejecuta acciones de
negocio. Los contratos son independientes de proveedor, modelo y tecnología de
extracción.

## Modo académico determinista

El modo académico opera sin LLM. Con un archivo, consulta y configuración
idénticos debe producir el mismo manifiesto, selección de evidencia, orden de
referencias y resultado estructurado. La salida se construye de manera
determinista a partir de coincidencias y plantillas controladas; demuestra el
flujo, los contratos y la verificabilidad, no capacidad jurídica ni equivalencia
con el producto final.

## Flujo asíncrono e idempotente

1. Laravel autoriza y registra la solicitud con estado `procesando`.
2. La transacción confirmada despacha el procesamiento asíncrono.
3. El trabajo vuelve a validar contexto, permiso y elegibilidad de la fuente.
4. Recupera evidencia y genera la asistencia mediante los contratos internos.
5. Valida manifiesto, referencias y forma del resultado antes de persistirlo.
6. Registra el resultado como `pendiente_revision` o la solicitud como
   `fallida`.
7. Blade consulta y representa el estado sin ejecutar procesamiento.
8. Un Administrador autorizado aprueba, modifica o rechaza el resultado.

Una clave de idempotencia estable por solicitud impide crear resultados
duplicados. Los reintentos reutilizan la solicitud existente, bloquean la
transición concurrente y solo publican un resultado completo después de una
persistencia atómica. Un fallo parcial no se presenta como análisis válido.

## Estados y transiciones

Estados persistidos:

- `procesando`;
- `pendiente_revision`;
- `aprobada`;
- `modificada`;
- `rechazada`;
- `fallida`.

Transiciones permitidas:

```text
procesando ──→ pendiente_revision ──→ aprobada
     │                   ├──────────→ modificada
     │                   └──────────→ rechazada
     └──────────────────────────────→ fallida
```

Los estados de revisión son terminales. Un reintento técnico solo puede operar
sobre una solicitud `procesando` recuperable o `fallida`, sin sobrescribir
historia ni crear una segunda revisión. Toda transición valida el estado previo
y la autorización vigente.

## Modelo relacional propuesto

### `solicitudes_asistencia_documental`

Conserva `organizacion_id`, `copropiedad_id`, `usuario_solicitante_id`,
`documento_id`, `version_documento_id`, consulta, estado, clave de idempotencia,
resultado original, resultado humano modificado, decisión, observación de
rechazo, manifiesto, código de fallo controlado y fechas de procesamiento y
revisión.

Las claves contextuales y foráneas compuestas deben impedir asociaciones entre
Organizaciones o Copropiedades. La clave de idempotencia es única dentro del
contexto. Consulta, fuente, manifiesto y resultado original quedan inmutables
después de generarse.

### `referencias_asistencia_documental`

Pertenece a una solicitud y conserva identificador estable, ordinal, página o
sección, extracto verificable y huella del contenido referenciado. Una
restricción única por solicitud e identificador evita duplicados. Cada
afirmación documental del resultado apunta a una o más referencias persistidas.

No se proponen tablas de fragmentos vectoriales, conversaciones, memoria,
Agentes o telemetría de proveedor.

## Manifiesto y referencias verificables

El manifiesto fija la identidad exacta de la fuente procesada:

- Organización, Copropiedad, Documento y Versión;
- nombre lógico del archivo y huella criptográfica;
- tipo y tamaño;
- número de páginas o secciones reconocidas;
- método y versión interna del procesamiento;
- fecha de generación del manifiesto.

Una referencia incluye identificador estable, página o sección, extracto y
huella. La interfaz debe permitir contrastarla con la Versión autorizada. Si el
archivo no permite generar anclajes reproducibles o la evidencia no respalda la
consulta, el sistema declara evidencia insuficiente; no inventa una cita.

## Límites configurables

La aplicación debe imponer y validar límites configurables para:

- tamaño, tipo y extensión del archivo;
- cantidad de páginas o secciones procesadas;
- longitud de la consulta;
- cantidad y longitud de evidencias y referencias;
- tamaño del resultado y de la modificación humana;
- tiempo de procesamiento, reintentos y concurrencia;
- retención de solicitudes y resultados.

Los valores se definirán por configuración operativa, no por datos enviados por
el cliente. Superar un límite produce un fallo controlado y trazable.

## Autorización, aislamiento y revisión humana

El permiso específico es `ia.asistencia_documental`. Debe combinarse con
Usuario autenticado, `ContextoOperativo`, Membresía vigente, permiso documental
aplicable, pertenencia, tipo, estado, vigencia y nivel de acceso de la fuente.
No se utiliza `users.role`.

La autorización se valida al crear, procesar, consultar y revisar. Los recursos
ajenos, inexistentes o revocados reciben una respuesta uniforme. Los archivos
se mantienen privados; consulta, contenido y resultado no deben exponerse en
URLs, logs o errores. El Documento se trata como información no confiable y
nunca como instrucciones para el sistema.

Toda salida comienza en `pendiente_revision`, se identifica como generada y no
tiene efecto de negocio. Aprobar, modificar o rechazar exige acción humana
explícita y autorización vigente. La modificación conserva el original; el
rechazo conserva su observación. Ninguna transición modifica PQRS, Documentos,
Notificaciones u otros módulos.

## Matriz mínima de pruebas

| Ámbito | Verificación mínima |
| --- | --- |
| Contratos | Entradas y salidas válidas, evidencia insuficiente y rechazo de referencias inválidas. |
| Determinismo | Misma entrada y configuración producen manifiesto, referencias y resultado equivalentes. |
| Flujo asíncrono | Despacho posterior a confirmación, reintento seguro, fallo parcial y recuperación controlada. |
| Idempotencia | Solicitudes repetidas o concurrentes no duplican resultado ni revisión. |
| Estados | Solo se aceptan las transiciones aprobadas y los estados terminales son inmutables. |
| Fuentes | Solo Reglamento o Manual de Convivencia activo, aprobado, vigente y del contexto. |
| Referencias | Página o sección verificable, huella consistente y afirmaciones vinculadas. |
| Autorización | Permiso `ia.asistencia_documental`, permiso documental, Membresía y revocación en cada punto. |
| Aislamiento | Casos IDOR y cruces de Organización, Copropiedad, Documento, Versión y solicitud. |
| Seguridad web | Autenticación, CSRF, validación, acceso uniforme y no exposición en logs o errores. |
| Revisión humana | Aprobar, modificar y rechazar conservan original, actor, fecha y observación aplicable. |
| Experiencia Blade | Vacío, procesamiento, evidencia insuficiente, resultado, fallo, restricción y responsive. |
| Límites | Archivo, consulta, evidencia, resultado, tiempo, concurrencia y reintentos. |
| Regresión | Sprints anteriores permanecen sin fallos. |

## Prototipo académico y producto

El prototipo académico valida contratos y comportamiento determinista sin LLM.
El producto Laravel entrega autorización contextual, persistencia, colas,
experiencia Blade y revisión humana. Sus resultados no se presentan como
equivalentes: el prototipo es evidencia académica reemplazable; el producto
requiere posteriormente un adaptador aprobado que respete los contratos y las
mismas garantías.

## Decisiones posteriores no bloqueantes

No existen decisiones bloqueantes para iniciar la implementación del alcance
aprobado. Permanecen deliberadamente abiertas la elección de framework del
prototipo, proveedor, modelo, OCR, extracción definitiva, valores operativos de
los límites e infraestructura final. Estas decisiones no alteran los contratos,
el modelo de estados, el aislamiento ni la revisión humana aquí definidos.

## Referencias

- [Sprint 11 — Asistencia documental con revisión humana](../04-desarrollo-agil/sprint-11-asistencia-documental-revision-humana.md).
- [Experiencia de Producto](../01-producto/experiencia-de-producto.md).
- [Arquitectura objetivo](../05-arquitectura/arquitectura-objetivo.md).
- [ADR-008 — Casos de uso compartidos](../10-adr/ADR-008-casos-de-uso-compartidos.md).
- [ADR-009 — Gestión Documental antes de IA](../10-adr/ADR-009-gestion-documental-antes-de-ia.md).
- [ADR-010 — IA RAG y revisión humana](../10-adr/ADR-010-ia-rag-revision-humana.md).
