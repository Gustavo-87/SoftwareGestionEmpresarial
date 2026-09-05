# Modelo de dominio

## 1. Propósito

Este documento describe las entidades Eloquent, sus responsabilidades y las
relaciones implementadas en Resuelve. El detalle físico de tablas y columnas se
documenta por separado en el modelo de datos.

## 2. Vista general

`Pqr` es la entidad central. Se relaciona con el usuario que radica, un tipo, un
responsable opcional y los registros que componen su atención y trazabilidad.

```text
User ──< Pqr >── TipoPqr
  │        │
  │        ├──< PqrAttachment
  │        ├──< PqrActivity >── User
  │        ├──< PqrReply >───── User
  │        ├──< PqrInternalComment >── User
  │        ├──< PqrTag
  │        └── SatisfactionSurvey
  │
  ├──< Pqr (como responsable)
  ├──< Notification
  └──< AuditLog

AutomationRule >── TipoPqr
AutomationRule >── User (responsable)
```

`SiteSetting` representa configuración global de la instancia y no tiene una
relación Eloquent con las demás entidades.

## 3. Entidades

### 3.1 User

Representa una cuenta autenticable.

Responsabilidades observables:

- autenticación y recuperación de contraseña;
- rol y capacidades auxiliares;
- identificación del residente mediante torre y unidad;
- radicación y asignación de PQR;
- recepción de notificaciones.

Relaciones declaradas:

| Relación | Tipo | Destino |
| --- | --- | --- |
| `pqrs()` | Uno a muchos | PQR radicadas por el usuario. |
| `assignedPqrs()` | Uno a muchos | PQR asignadas mediante `assigned_to_id`. |
| Notificaciones | Polimórfica, provista por Laravel | Notificaciones de la cuenta. |

No se declaran en el modelo relaciones hacia actividades, respuestas,
comentarios, encuestas, plantillas o auditorías, aunque sus tablas puedan
referenciar usuarios.

### 3.2 Pqr

Representa una solicitud radicada.

Atributos funcionales principales:

- asunto y descripción;
- fechas de radicación y límite;
- estado;
- radicador;
- responsable;
- tipo;
- última fecha de recordatorio.

Relaciones:

| Relación | Tipo | Destino |
| --- | --- | --- |
| `user()` | Muchos a uno | Usuario radicador. |
| `tipoPqr()` | Muchos a uno | Tipo de PQR. |
| `assignee()` | Muchos a uno | Usuario responsable opcional. |
| `attachments()` | Uno a muchos | Adjuntos principales. |
| `activities()` | Uno a muchos | Actividades, ordenadas desde la más reciente. |
| `replies()` | Uno a muchos | Respuestas, ordenadas desde la más reciente. |
| `internalComments()` | Uno a muchos | Comentarios internos, ordenados desde el más reciente. |
| `tags()` | Muchos a muchos | Etiquetas. |
| `satisfactionSurvey()` | Uno a uno | Encuesta de satisfacción. |

Comportamiento derivado:

- etiqueta legible del estado;
- determinación de vencimiento;
- días transcurridos;
- días restantes;
- búsquedas y scopes de respondidas o pendientes.

### 3.3 TipoPqr

Representa una clasificación principal de una PQR.

- Atributos: nombre y descripción.
- Relación: `pqrs()`, uno a muchos.

También puede ser referenciada por reglas automáticas, aunque esa relación
inversa no está declarada en el modelo.

### 3.4 PqrAttachment

Representa los metadatos de un archivo adjunto principal.

- Atributos: nombre original, ruta, tipo MIME y tamaño.
- Relación: `pqr()`, muchos a uno.

El contenido físico se almacena fuera de la base de datos.

### 3.5 PqrActivity

Representa un evento funcional dentro del historial de una PQR.

- Atributos: acción, descripción y metadatos opcionales.
- Relaciones: `pqr()` y `user()`.

Acciones creadas por el código:

- `created`;
- `updated`;
- `quick_action`;
- `drafted_reply`;
- `sent_reply`;
- `internal_comment`;
- `tags_updated`.

No existe un enum que restrinja estos valores.

### 3.6 PqrReply

Representa una respuesta o un borrador.

- Atributos: cuerpo, indicador de borrador, adjuntos JSON y fecha de envío.
- Relaciones: `pqr()` y `user()`.
- Garantías cerradas en C.3.5.1: existe una sola respuesta oficial por PQRS
  y esa respuesta es inmutable y no eliminable. Los borradores propios admiten
  creación, edición, envío y retiro lógico cuando el autor conserva gestión
  efectiva; la consulta de borradores ajenos no concede mutación.

Los adjuntos de respuesta no tienen una entidad propia; cada elemento del JSON
contiene nombre, ruta y tamaño.

### 3.7 PqrInternalComment

Representa una nota privada del equipo.

- Atributo funcional: cuerpo.
- Relaciones: `pqr()` y `user()`.

Su privacidad se implementa en controladores y vistas, no mediante un atributo
del modelo.

### 3.8 PqrTag

Representa una etiqueta reutilizable.

- Atributos: nombre y color hexadecimal.
- Relación: `pqrs()`, muchos a muchos.

La asociación se materializa mediante la tabla pivote `pqr_pqr_tag`.

### 3.9 ResponseTemplate

Representa contenido reutilizable para redactar respuestas.

- Atributos: nombre, asunto opcional, cuerpo y creador.

El modelo no declara una relación hacia el usuario creador. La interfaz actual
utiliza el cuerpo de la plantilla, pero no utiliza su asunto al preparar la
respuesta.

### 3.10 AutomationRule

Representa una regla aplicada durante la creación de una PQR.

- Atributos: nombre, tipo opcional, responsable opcional, estado y activación.
- Relaciones:
  - `type()`: muchos a uno con `TipoPqr`;
  - `assignee()`: muchos a uno con `User`.

Las reglas no forman un motor general de eventos y condiciones. Su uso actual
se limita a coincidencia por tipo durante la radicación.

### 3.11 SatisfactionSurvey

Representa la valoración de atención.

- Atributos: PQR, usuario, calificación y comentario.
- Relación declarada: `pqr()`, muchos a uno.

La base de datos restringe una encuesta por PQR. El modelo no declara la
relación con el usuario que responde.

### 3.12 AuditLog

Representa una mutación HTTP auditada.

- Atributos: usuario, acción, tipo e identificador auditables, dirección IP y
  metadatos.
- Relación: `user()`, muchos a uno.

Los campos auditables no implementan una relación polimórfica Eloquent. Son
valores informativos gestionados por el middleware.

### 3.13 SiteSetting

Representa la configuración institucional de la instancia.

Incluye:

- identidad y contacto;
- color principal;
- plazo estándar;
- ruta del logo.

`SiteSetting::current()` devuelve el primer registro o una instancia en memoria
con valores predeterminados. El modelo no garantiza por sí mismo que exista un
único registro.

## 4. Entidades de infraestructura de Laravel

Las migraciones también definen:

- sesiones;
- tokens de restablecimiento de contraseña;
- caché y bloqueos;
- jobs, lotes y jobs fallidos;
- notificaciones.

Estas estructuras soportan la aplicación, pero no constituyen entidades de
negocio propias del namespace `App\Models`.

## 5. Cardinalidades e integridad

| Origen | Cardinalidad | Destino | Regla de eliminación |
| --- | --- | --- | --- |
| User | 1:N | Pqr como radicador | Eliminar usuario elimina sus PQR. |
| User | 1:N | Pqr como responsable | Eliminar usuario deja responsable en nulo. |
| TipoPqr | 1:N | Pqr | Eliminar tipo elimina sus PQR. |
| Pqr | 1:N | PqrAttachment | Cascada. |
| Pqr | 1:N | PqrActivity | Cascada. |
| Pqr | 1:N | PqrReply | Cascada. |
| Pqr | 1:N | PqrInternalComment | Cascada. |
| Pqr | N:M | PqrTag | Cascada sobre registros de la tabla pivote. |
| Pqr | 1:1 | SatisfactionSurvey | Cascada. |
| User | 1:N | PqrActivity | Usuario queda en nulo. |
| User | 1:N | PqrReply | Usuario queda en nulo. |
| User | 1:N | PqrInternalComment | Usuario queda en nulo. |
| User | 1:N | SatisfactionSurvey | Cascada. |
| TipoPqr | 1:N | AutomationRule | Eliminar tipo elimina reglas. |
| User | 1:N | AutomationRule como responsable | Responsable queda en nulo. |

El ledger técnico de idempotencia `pqr_communication_operations` se documenta
en el modelo de datos. Sus referencias compuestas a `PqrReply` y
`PqrInternalComment` son opcionales, exclusivas y coherentes con la operación
y el resultado registrado.

Las reglas anteriores corresponden a claves foráneas de migraciones. No todas
se reflejan mediante relaciones inversas en modelos.

## 6. Límites del dominio actual

- No existe entidad `Copropiedad`.
- No existe entidad `UnidadPrivada`; torre y unidad son textos en `User`.
- No existe entidad para conversaciones externas o canales de recepción.
- No existe relación entre una PQR y una copropiedad.
- No existe entidad de permiso granular.
- No existe entidad propia para adjuntos de respuesta.

### 6.1 Gestión Documental implementada

`Documento` es un agregado de Copropiedad con `DocumentoVersion` y
`DocumentoActuacion`. El propietario documental es una responsabilidad de
negocio y no una fuente de autorización. Las Versiones conservan número,
estado, vigencia, hash y actores históricos; las Actuaciones son append-only.

Las operaciones se coordinan mediante casos de uso en
`app/Application/Documentos/`, la consulta contextual y `DocumentoPolicy`.
La descarga solo expone Versiones aprobadas, vigentes y autorizadas, desde el
disco privado. No se implementan IA, fragmentos ni indexación documental.

## 7. Evidencias utilizadas

### Modelos

- `app/Models/AuditLog.php`
- `app/Models/AutomationRule.php`
- `app/Models/Pqr.php`
- `app/Models/PqrActivity.php`
- `app/Models/PqrAttachment.php`
- `app/Models/PqrInternalComment.php`
- `app/Models/PqrReply.php`
- `app/Models/PqrTag.php`
- `app/Models/ResponseTemplate.php`
- `app/Models/SatisfactionSurvey.php`
- `app/Models/SiteSetting.php`
- `app/Models/TipoPqr.php`
- `app/Models/User.php`
- `app/Models/Documento.php`
- `app/Models/DocumentoVersion.php`
- `app/Models/DocumentoActuacion.php`
- `app/Application/Documentos/`
- `app/Policies/DocumentoPolicy.php`

### Persistencia y uso

- `database/migrations/`
- `app/Http/Controllers/PqrController.php`
- `app/Http/Controllers/PqrReplyController.php`
- `app/Http/Controllers/ComplementaryController.php`
- `app/Http/Middleware/AuditMutations.php`
- `app/Providers/AppServiceProvider.php`

## Control documental

- **Versión:** v1.2
- **Fecha de creación:** 30 de julio de 2026
- **Fecha de última actualización:** 20 de agosto de 2026
