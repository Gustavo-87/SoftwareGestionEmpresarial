# Gestión de PQR

## 1. Propósito

Este documento describe el proceso funcional implementado para radicar,
consultar y atender PQR en Resuelve.

## 2. Concepto de PQR en el sistema

Una PQR es un registro asociado a:

- un usuario radicador;
- un tipo de PQR;
- un responsable opcional;
- un estado;
- fechas de radicación y límite;
- adjuntos, actividades, respuestas, comentarios, etiquetas y una encuesta
  opcional.

El identificador visible se construye con el prefijo `PQR-` y el identificador
numérico completado a cuatro posiciones, por ejemplo `PQR-0007`.

## 3. Tipos y estados

Los tipos se almacenan en `tipo_pqrs`. El seeder crea Petición, Queja, Reclamo,
Sugerencia y Solicitud, pero el código no limita el dominio a esos cinco
valores: una PQR referencia cualquier tipo existente en la tabla.

Los estados persistidos son:

| Valor | Etiqueta visible | Interpretación utilizada por el código |
| --- | --- | --- |
| `radicada` | Radicada | Estado inicial predeterminado. |
| `en_revision` | En revisión | Solicitud abierta en gestión. |
| `respondida` | Respondida | Solicitud con respuesta o marcada manualmente como respondida. |
| `cerrada` | Cerrada | Solicitud finalizada manualmente. |

La base de datos restringe el estado a estos cuatro valores.

## 4. Radicación

### 4.1 Acceso

La política `PqrPolicy::create()` devuelve `true` para cualquier usuario
autenticado. No existe restricción técnica que reserve la radicación al rol
residente.

### 4.2 Datos de entrada

| Campo | Regla implementada |
| --- | --- |
| Asunto | Obligatorio, texto, máximo 150 caracteres. |
| Descripción | Obligatoria, texto. |
| Tipo | Obligatorio y existente en `tipo_pqrs`. |
| Adjuntos | Opcionales, arreglo de máximo ocho archivos. |
| Cada adjunto | Máximo 10 MB y extensión permitida. |

Extensiones aceptadas: JPG, JPEG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX, TXT,
CSV y ZIP.

Las fechas de radicación y límite no son campos de entrada. El backend fija la
radicación con la fecha civil de Bogotá y calcula el límite con los días de
respuesta configurados para el contexto y el calendario hábil colombiano. Las
fechas que el cliente intente agregar al payload son ignoradas.

### 4.3 Proceso de creación

La creación se realiza dentro de una transacción:

1. se fija la fecha de radicación y se calcula el plazo máximo en backend;
2. se crea la PQR con el usuario autenticado como radicador;
3. se busca una regla automática activa aplicable;
4. si existe, se asignan responsable y estado;
5. se almacenan los adjuntos;
6. se registra una actividad `created`.

Después de confirmar la transacción, se notifica a todos los usuarios con rol
administrador o gestor.

### 4.4 Regla automática

La consulta toma la primera regla activa cuyo `tipo_pqr_id` sea nulo o coincida
con el tipo de la PQR. No se define una prioridad explícita ni un orden en la
consulta. Solo se aplica una regla.

Una regla puede:

- asignar un responsable o dejar la PQR sin responsable;
- establecer `radicada` o `en_revision`.

## 5. Consulta

### 5.1 Visibilidad

- Administrador, gestor, auditor y apoyo pueden consultar todas las PQR.
- Un residente solo consulta PQR cuyo `user_id` corresponde a su cuenta.
- El detalle y las descargas principales aplican `PqrPolicy::view()`.

### 5.2 Panel e indicadores

El panel calcula, dentro del universo visible para el usuario:

- total;
- pendientes: `radicada` o `en_revision`;
- respondidas;
- próximas a vencer: abiertas con fecha límite entre hoy y tres días después;
- solicitudes creadas por mes durante los últimos seis meses;
- distribución por estado.

### 5.3 Búsqueda

La búsqueda coincide parcialmente con:

- asunto;
- descripción;
- nombre o correo del radicador;
- nombre del responsable.

También intenta coincidencia exacta del texto contra el identificador numérico.

### 5.4 Filtros

Se implementan filtros por:

- estado individual;
- agrupación `pendientes`;
- agrupación `por_vencer`;
- tipo;
- responsable;
- fecha de radicación desde;
- fecha de radicación hasta.

La lista se ordena por fecha de creación descendente y muestra diez registros
por página.

## 6. Detalle y trazabilidad

El detalle carga:

- radicador;
- tipo;
- responsable;
- adjuntos;
- actividades y sus usuarios;
- respuestas y sus usuarios;
- comentarios internos y sus usuarios;
- etiquetas;
- encuesta de satisfacción.

La vista presenta fechas, días transcurridos, días restantes o vencidos,
responsable, archivos, conversación e historial.

Una PQR se considera vencida si la fecha límite está en el pasado y el estado
no es `respondida` ni `cerrada`.

## 7. Actualización

### 7.1 Autorización y regla funcional

Quien crea una PQRS no puede editarla después de enviarla. La administración y
los gestores efectivos pueden realizar seguimiento según su autorización
contextual: cambiar estado, asignar o cambiar responsable y gestionar las
etiquetas de la PQRS. La política vigente basada en `pqrs.gestionar` es
coherente con esta regla; para apoyo, la gestión también está condicionada por
la asignación definida en `puedeGestionarPqr()`.

La asignación de etiquetas existentes quedó cerrada y aprobada en C.3.4.2. Solo
quien puede gestionar efectivamente la PQRS puede consultar el catálogo del
contexto, asignar, retirar o restaurar la selección. La autorización ocurre
antes de validar identificadores; una gestión no autorizada conserva el 403 y
una PQRS externa conserva el 404 contextual. Los identificadores se normalizan,
las etiquetas de otro contexto se rechazan atómicamente y una selección
idéntica es un no-op sin actividad. Cada cambio real produce una sola
actuación.

La administración del catálogo permanece separada de la asignación. C.3.4.3
quedó cerrada y aprobada: quienes poseen
`gestion.herramientas_gestionar` pueden crear etiquetas contextuales, editar su
nombre y color, desactivarlas y reactivarlas. No existe eliminación física. La
autorización ocurre antes de resolver la etiqueta o validar el payload y se
preservan aislamiento por Organización y Copropiedad, protección contra IDOR y
los contratos 302, 403, 404 y 419.

El cierre aprobado de C.3.4.1 hace visible en el expediente una tarjeta de
`Gestión administrativa` solo para quien posee autorización efectiva. Presenta
el estado y responsable actuales, explica el alcance de la gestión y su registro
en el historial, y enlaza a la edición completa existente. No añade controles
de estado o responsable dentro del expediente y mantiene separadas la gestión,
las respuestas y los comentarios internos. Para residentes, auditores y
usuarios sin gestión la tarjeta está completamente ausente.

C.3.4.2 mantiene la asignación dentro del expediente y no mezcla allí creación
ni edición del catálogo. C.3.4.3 queda **Cerrado y aprobado**. C.3.5 se registra
como el siguiente bloque aprobado de la línea, y C.3.5.1 permanece cerrado,
aprobado e integrado.

C.3.4-F establece que las fechas de radicación y límite no son datos de entrada.
Al radicar, el backend fija la fecha civil de `America/Bogota` y calcula el
plazo máximo con `configuraciones_copropiedad.dias_respuesta`, desde el día
siguiente y excluyendo fines de semana y festivos oficiales colombianos,
incluidos los trasladables. Cualquier fecha manipulada en el payload se ignora.
Las fechas se muestran como solo lectura en la gestión administrativa y no
existe una excepción para modificarlas posteriormente. Las PQRS históricas
conservan sus fechas persistidas.

### 7.2 Datos modificables

La edición completa admite:

- asunto;
- descripción;
- estado;
- tipo;
- responsable;
- nuevos adjuntos.

La fecha de radicación y la fecha límite se presentan como información de solo
lectura. No forman parte de la actualización administrativa ordinaria.

El sistema registra los valores anteriores de estado y responsable. Cuando
alguno cambia, crea una descripción específica en el historial.

### 7.3 Efectos secundarios

- Un cambio de estado notifica al radicador.
- Un cambio de responsable notifica al nuevo responsable.
- Se crea una actividad `updated`.
- Los archivos nuevos se agregan; no existe eliminación individual de adjuntos.

### 7.4 Acción rápida

La acción rápida permite actualizar estado y responsable y crea una actividad
`quick_action`. Si cambia el estado, notifica al radicador.

El campo `assigned_to_id` es validado como opcional. Debido a la forma en que
Laravel valida datos ausentes, la acción puede actualizar solo uno de los dos
campos.

## 8. Respuestas

### 8.1 Acceso

Los controladores resuelven primero la PQRS dentro de la Organización y
Copropiedad activas, comprueban después la gestión efectiva y solo entonces
validan el payload y ejecutan el caso de uso. El contrato es `404` contextual,
`403` efectivo, validación y ejecución. Un payload inválido no revela errores a
quien carece de gestión efectiva.

### 8.2 Borrador

Un borrador:

- guarda cuerpo, autor y adjuntos;
- conserva `sent_at` en nulo;
- crea una actividad `drafted_reply`;
- no cambia el estado;
- no notifica al radicador;
- se muestra a su autor si conserva gestión efectiva;
- se muestra, propio o ajeno, a administrador y gestor efectivos;
- apoyo efectivo solo consulta sus propios borradores.

El autor que conserva gestión efectiva puede editar, enviar o retirar
lógicamente su propio borrador. Administrador y gestor pueden consultar
borradores ajenos, pero no mutarlos. Un borrador retirado conserva su fila como
evidencia, queda fuera de las consultas ordinarias y no puede restaurarse ni
eliminarse físicamente.

### 8.3 Envío

Una respuesta enviada:

- registra `sent_at`;
- crea una actividad `sent_reply`;
- cambia la PQR a `respondida`;
- notifica al radicador.

El cambio a `respondida` ocurre directamente y no genera una actividad
adicional distinta de `sent_reply`. La respuesta oficial puede originarse al
enviar un borrador propio o mediante envío directo; es única por PQRS,
inmutable y no eliminable.

### 8.4 Adjuntos de respuesta

Se admiten hasta cinco archivos, cada uno de máximo 10 MB, con las mismas
extensiones de los adjuntos principales. Sus metadatos se guardan como JSON en
la respuesta.

La descarga resuelve la PQRS contextual y comprueba que la respuesta y el
índice pertenezcan al expediente. Una respuesta oficial sigue la visibilidad de
la PQRS. Para borradores se reutiliza una decisión backend única: un borrador
ajeno invisible produce `404`, mientras el autor que perdió gestión efectiva
recibe `403`. La ruta física nunca se presenta al cliente.

Los adjuntos nuevos se almacenan bajo una ruta determinista derivada del UUID
de la operación y de su posición, y se identifican mediante SHA-256. Al
reemplazarlos, un manifiesto durable limita la limpieza a referencias exactas,
comprueba pertenencia y huella y deja el trabajo pendiente para reintento si no
puede completarse con seguridad.

## 9. Comentarios internos

Administrador, gestor y apoyo con gestión efectiva pueden agregar comentarios
de máximo 3.000 caracteres. La autorización precede a la validación y cada
comentario crea una actividad `internal_comment`. Residentes, auditores y
lectores sin gestión no reciben la colección desde el backend.

## 10. Etiquetas

El expediente muestra la asignación solo a actores con autorización efectiva
por `puedeGestionarPqr()`. Para apoyo, la PQRS debe estar sin responsable o
asignada al propio usuario. Residentes, auditores y usuarios sin gestión no ven
el control.

La sincronización:

- usa exclusivamente etiquetas existentes de la Organización y Copropiedad
  activas;
- normaliza identificadores y no admite asociaciones duplicadas;
- rechaza de forma atómica identificadores externos o inexistentes;
- permite asignar, retirar, vaciar y restaurar la selección;
- trata una selección idéntica como no-op sin actividad;
- registra exactamente una actividad `tags_updated` por cambio real;
- conserva equivalencia entre carga directa, lazy y eager de la relación;
- presenta errores localizados y accesibles, selección guardada, cambios
  pendientes, confirmación solo al retirar y prevención de doble envío.

La interfaz contempla catálogo vacío y ninguna etiqueta asignada, funciona con
teclado y foco visible y conserva contraste y adaptación responsive. La
evidencia aprobada permanece local en `storage/evidence/c3-4-2/`.

### 10.1 Administración del catálogo — C.3.4.3

La creación y administración de etiquetas pertenece a Herramientas de gestión
y exige `gestion.herramientas_gestionar` efectivo. C.3.4.3 permite:

- crear una etiqueta dentro de la Organización y Copropiedad activas;
- editar su nombre y color;
- desactivarla y reactivarla sin eliminación física;
- conservar reservado su nombre aun cuando esté inactiva;
- normalizar espacios y limitar el nombre a 60 caracteres;
- almacenar el color en formato canónico `#RRGGBB`;
- registrar trazabilidad específica para cada cambio real.

La autorización precede a la resolución del recurso y a la validación. Las
operaciones conservan los contratos 302, 403, 404 y 419, impiden IDOR y
manipulación de contexto y traducen los conflictos de nombre sin producir error
500. Una solicitud sin cambios es un no-op sin escritura ni auditoría.

Una etiqueta inactiva permanece visible si ya está asociada a una PQRS. Puede
conservarse o retirarse, pero no incorporarse como asignación nueva. Después de
reactivarla puede asignarse nuevamente. La relación y el eager loading cerrados
en C.3.4.2 permanecen equivalentes. La experiencia es responsive, accesible y
no depende exclusivamente del color.

La persistencia del estado se incorporó mediante
`2026_08_13_000000_add_activo_to_pqr_tags.php`, con `activo BOOLEAN NOT NULL
DEFAULT TRUE` e índice por Organización, Copropiedad y estado, sin alterar la
unicidad contextual. La migración se validó con aplicación, rollback y
reaplicación sobre MySQL desechable y se aplicó exclusivamente a MySQL demo en
el lote 20. Las asociaciones y los históricos se conservaron.

La evidencia aprobada permanece local en `storage/evidence/c3-4-3/` y cubre
catálogo, creación, edición, cambio de color, nombre largo, desactivación,
reactivación, éxito, duplicado, teclado, foco y responsive en escritorio,
tableta, 390 y 320 px, sin overflow horizontal. Una captura móvil de página
completa documenta el flujo vertical y no exige mostrar simultáneamente todo el
catálogo dentro de 844 px.

### 10.2 C.3.5.1A — Persistencia segura e idempotencia

Estado: **Cerrado, aprobado e integrado**.
Este subbloque incorpora exclusivamente garantías de persistencia: una sola
respuesta oficial por PQRS, coherencia obligatoria entre borrador y fecha de
envío y una FK restrictiva que protege las respuestas frente a eliminación por
cascada.

Las respuestas y comentarios disponen de un ledger idempotente con clave
global, identidad técnica aleatoria y opaca, huella del payload y resultados
tipados. Las FKs compuestas impiden asociar el resultado a una respuesta o
comentario de otra PQRS. El ciclo de migración detecta estados parciales; en
SQLite conserva un respaldo hasta comprobar igualdad exacta y en MySQL el
rollback reintentable restaura la FK previa.

La migración fue validada en SQLite y MySQL desechable, incluida concurrencia
real con dos conexiones, y se aplicó en MySQL demo en el lote 21.

### 10.3 C.3.5.1B-1 — Autorización, privacidad y conservación

Estado: **Cerrado, aprobado e integrado**.
B-1 centraliza la visibilidad triestado de borradores, filtra comentarios y
actuaciones antes de Blade y publica únicamente las actividades `created`,
`updated`, `quick_action`, `sent_reply` y `tags_updated`. Toda acción futura no
clasificada se mantiene privada por defecto.

Las descargas respetan contexto, pertenencia y visibilidad. La eliminación de
una PQRS se bloquea dentro de transacción cuando existen respuestas,
borradores, comentarios, actuaciones u operaciones del ledger; no depende de
una excepción de FK ni elimina archivos antes del commit. El rechazo vuelve
explícitamente a edición con un mensaje genérico que no identifica la evidencia.
B-1 está integrado en la base de B-2.

### 10.4 C.3.5.1B-2 — Ciclo seguro de borradores y respuesta oficial única

Estado: **Cerrado, aprobado e integrado**.
B-2 incorpora las operaciones tipadas `create_draft`, `update_draft`,
`send_draft`, `delete_draft` y `send_reply`. Cada formulario usa un UUID oculto
generado en backend, conservado tras errores y renovado después del éxito. El
ledger valida actor, PQRS, operación y huella del payload; un replay equivalente
devuelve el resultado ya completado sin duplicar respuesta, actuación ni
notificación, y una reutilización con otro ámbito o contenido se rechaza.

Las mutaciones conservan el orden `404` contextual, `403` efectivo, validación
y ejecución. La transacción bloquea PQRS y borrador, admite envíos solo desde
`radicada` o `en_revision` y comprueba la ausencia de respuesta oficial antes
de mutar. La unicidad de base de datos sigue siendo la defensa concurrente
final. Los adjuntos usan almacenamiento determinista y limpieza compensatoria
recuperable.

La migración `2026_08_13_180000_add_safe_draft_lifecycle.php` añade borrado
lógico restringido a borradores no enviados, el manifiesto de limpieza y las
operaciones/resultados tipados. SQLite conserva respaldos hasta comprobar
igualdad exacta; MySQL realiza un preflight semántico de esquema, datos y
`CHECK` antes de cualquier DDL. La migración integrada se aplicó una sola vez
en MySQL demo, en el lote 22.

### 10.5 C.3.5.1B-3 — Notificación verificable y reintentos seguros

Estado: **Cerrado, aprobado e integrado**.
Tras confirmar una respuesta oficial, `send_reply` y `send_draft` usan el
ledger como fuente durable de reconciliación. `pending` identifica una emisión
recuperable, `completed` confirma atómicamente la notificación `database`, el
job en cola y el resultado técnico, y `no_recipient` cierra la operación cuando
no existe un destinatario contextual elegible. Ningún estado afirma entrega de
correo.

El replay y el comando periódico retoman solo operaciones B-3 `pending`; no
duplican respuesta, actuación, notificación ni job. El comando se ejecuta cada
cinco minutos sin solapamiento. Si el job agota sus intentos, devuelve solo su
operación a `pending`, sin modificar históricos.

El destinatario se resuelve de nuevo por organización, copropiedad, membresía
vigente y permiso. Un usuario revocado, externo o fuera de contexto no recibe
información y concluye en `no_recipient`. Los 17 jobs históricos sin operación
B-3 conservan su flujo previo y no son reclamados por la reconciliación. Los
mensajes web distinguen los tres resultados con texto neutral.

Estado oficial:

```text
C.3.4.1: Cerrado y aprobado
C.3.4-F: Cerrado y aprobado
C.3.4.2: Cerrado y aprobado
C.3.4.3: Cerrado y aprobado
C.3.5.1A: Cerrado, aprobado e integrado
C.3.5.1B-1: Cerrado, aprobado e integrado
C.3.5.1B-2: Cerrado, aprobado e integrado
C.3.5.1B-3: Cerrado, aprobado e integrado
C.3.5.1: Cerrado, aprobado e integrado
C.3.5.2: Siguiente bloque definido; no iniciado
```

C.3.5.1 queda completo y cerrado. C.3.5.2 es el siguiente bloque y permanece
no iniciado. Sprint 11 continúa **Pausado. No completado.** No se iniciaron
C.3.5.2, C.3.6 ni Sprint 11.

## 11. Comunicación segura de PQRS

La atención de PQRS opera como una comunicación interna entre el equipo y el
residente radicador, sujeta a las siguientes garantías:

- **Operación idempotente:** cada formulario lleva un UUID oculto generado en
  backend que se conserva tras errores de validación y se renueva solo después
  de una operación exitosa. Un reintento equivalente devuelve el resultado ya
  completado sin duplicar respuesta, actuación, notificación ni job.
- **Respuesta oficial única:** solo existe una respuesta oficial por PQRS. Se
  obtiene al enviar de forma directa o al enviar un borrador propio con gestión
  efectiva. Una respuesta oficial es inmutable y no eliminable.
- **Borradores propios:** un autor con gestión efectiva puede crear, editar,
  enviar y retirar lógicamente sus borradores. La consulta de borradores ajenos
  autorizada por B-1 no concede mutación.
- **Adjuntos deterministas:** los archivos se escriben en rutas previsibles a
  partir del UUID de la operación y de su posición. La limpieza compensatoria
  solo toca referencias exactas verificadas por pertenencia y huella SHA-256.
- **Privacidad por defecto:** comentarios, adjuntos y actuaciones internas se
  filtran antes de la capa de presentación. El historial público usa una
  allowlist cerrada y el backend conserva `404` contextual, `403` efectivo,
  validación y ejecución.
- **Reconciliación técnica:** el ledger durable `pqr_communication_operations`
  registra actor, PQRS, operación, resultado y estado. La reconciliación
  periódica solo retoma operaciones `pending` y nunca duplica evidencia ya
  completada.
- **Estados de aviso:** cada operación concluye en `completed`, cuando la
  notificación `database` y el job en cola quedan confirmados atómicamente; en
  `pending`, cuando aún es recuperable; o en `no_recipient`, cuando no existe
  un destinatario contextual elegible. Ninguno de esos estados promete entrega
  de correo.

Esta operación se documenta también en el modelo funcional de gestión de PQR y
en la técnica de notificaciones y automatizaciones para mantener coherencia
entre diseño, implementación verificable y estado operativo.

## 12. Encuesta de satisfacción

Solo el radicador puede valorar una PQR que esté `respondida` o `cerrada`.

- Calificación: entero entre 1 y 5.
- Comentario: opcional, máximo 2.000 caracteres.
- Cardinalidad: una encuesta por PQR.

El controlador utiliza `updateOrCreate`. La interfaz permite crear la
valoración cuando no existe y muestra el resultado cuando ya existe.

## 12. Eliminación

La eliminación está permitida para administrador y gestor. Se bloquea si la
PQRS conserva respuestas, borradores, comentarios, actuaciones u operaciones
del ledger. Una PQRS sin esas dependencias mantiene el flujo previo: se elimina
en transacción y sus adjuntos principales se retiran del disco después del
commit.

## 13. Notificaciones relacionadas

| Evento | Destinatario |
| --- | --- |
| Nueva PQR | Todos los administradores y gestores. |
| Cambio de estado | Radicador. |
| Asignación | Nuevo responsable. |
| Respuesta enviada | Radicador. |
| Recordatorio de vencimiento | Radicador y responsable, si existen. |

Cada notificación de evento usa base de datos y correo.

## 14. Jerarquía documental aplicada

En Resuelve:

- Git, las migraciones y las pruebas verifican el estado técnico;
- `docs/` define diseño, comportamiento funcional y decisiones aprobadas;
- `RESUELVE_ESTADO_ACTUAL.md` registra el estado operativo vivo;
- no existe una única fuente aislada que sustituya a las demás.

## 15. Evidencias utilizadas

### Dominio y persistencia

- `app/Models/Pqr.php`
- `app/Models/TipoPqr.php`
- `app/Models/PqrAttachment.php`
- `app/Models/PqrActivity.php`
- `app/Models/PqrReply.php`
- `app/Models/PqrInternalComment.php`
- `app/Models/PqrTag.php`
- `app/Models/SatisfactionSurvey.php`
- `app/Models/AutomationRule.php`
- `database/migrations/2026_07_07_003332_create_tipo_pqrs_table.php`
- `database/migrations/2026_07_07_003347_create_pqrs_table.php`
- `database/migrations/2026_07_24_180000_create_pqr_attachments_table.php`
- `database/migrations/2026_07_24_210000_add_assignee_to_pqrs_table.php`
- `database/migrations/2026_07_24_220000_create_pqr_activities_table.php`
- `database/migrations/2026_07_24_230000_create_pqr_replies_table.php`
- `database/migrations/2026_07_24_250000_create_pqr_internal_comments_table.php`
- `database/migrations/2026_07_24_270000_create_complementary_features.php`

### Aplicación e interfaz

- `app/Http/Controllers/PqrController.php`
- `app/Http/Controllers/PqrQuickActionController.php`
- `app/Http/Controllers/PqrReplyController.php`
- `app/Http/Controllers/PqrInternalCommentController.php`
- `app/Http/Controllers/ComplementaryController.php`
- `app/Policies/PqrPolicy.php`
- `app/Notifications/PqrEventNotification.php`
- `routes/web.php`
- `routes/console.php`
- `resources/views/pqrs/`

### Pruebas relacionadas

- `tests/Feature/PqrPermissionsTest.php`
- `tests/Feature/PqrPriorityFeaturesTest.php`
- `tests/Feature/PqrMediumFeaturesTest.php`
- `tests/Feature/PqrComplementaryFeaturesTest.php`
- `tests/Feature/PqrSafeDraftLifecycleTest.php`
- `tests/Feature/PqrSafeDraftMigrationRecoveryTest.php`
- `tests/Feature/PqrSafeDraftMysqlPartialDdlTest.php`

## Control documental

- **Versión:** v1.7
- **Fecha de creación:** 30 de julio de 2026
- **Fecha de última actualización:** 20 de agosto de 2026
