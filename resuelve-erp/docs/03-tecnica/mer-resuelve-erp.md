# MER Resuelve ERP

## Figura para el informe

La [imagen resumida para Word](mer-resumen-informe.png) muestra las relaciones principales de organización y comunidad, atención de PQRS y mantenimiento con evidencia documental. A un ancho de 15,5 cm conserva las proporciones y cabe en una página vertical. El esquema físico completo puede citarse como anexo.

## Esquema físico implementado

[Abrir el diagrama completo como imagen ampliable](mer-fisico.svg)

El [MER físico editable en Mermaid](mer-fisico.mmd) reúne las 50 tablas resultantes de aplicar las [migraciones del proyecto](../../database/migrations/) en una base SQLite temporal. Incluye las 467 columnas y las 81 claves foráneas declaradas. En Visual Studio Code, abre el archivo y ejecuta `Mermaid: Preview Diagram` para ver el diagrama.

Cada línea lleva los nombres de las columnas de la tabla hija que forman la clave foránea. Por ejemplo, `mantenimientos.pqr_id` es opcional y permite asociar varios mantenimientos a una PQRS. Las claves compuestas se representan con una sola línea. Las asociaciones polimórficas, como el destinatario de una notificación, se reconocen por sus columnas de tipo e identificador. Los tipos de columna corresponden a SQLite. El [modelo de datos](modelo-de-datos.md) detalla reglas de integridad y particularidades de MySQL.

## Modelo conceptual del negocio

El diagrama siguiente resume la administración de copropiedades y sus procesos. También se puede consultar como [imagen](diagrama_mer.png) o abrir su [fuente editable en Mermaid](erDiagram.mmd).

> **Implementación física (Sprint 19).** La integración operativa usa las
> tablas `mantenimientos` (con `pqr_id` para la PQRS de origen,
> `idempotency_key` única para la idempotencia y los campos de resultado
> público/interno con trazabilidad) y el puente
> `mantenimiento_documento_evidencia`, que fija la versión exacta de la
> evidencia (`documento_version_id`). Equivalen al agregado conceptual
> `solicitudes_mantenimiento`/`ordenes_trabajo`/`orden_documento` del diagrama;
> el detalle completo está en [modelo-de-datos.md](modelo-de-datos.md).

```mermaid
erDiagram
    organizaciones ||--o{ copropiedades : administra
    organizaciones ||--o{ personas : registra
    copropiedades ||--o{ unidades_privadas : contiene
    personas ||--o{ vinculos_unidad : establece
    unidades_privadas ||--o{ vinculos_unidad : recibe

    users ||--o{ membresias_copropiedad : participa
    copropiedades ||--o{ membresias_copropiedad : admite

    copropiedades ||--o{ pqrs : recibe
    tipo_pqrs ||--o{ pqrs : clasifica
    users ||--o{ pqrs : radica
    users o|--o{ pqrs : atiende
    pqrs ||--o{ pqr_replies : conserva

    copropiedades ||--o{ solicitudes_mantenimiento : gestiona
    tipos_mantenimiento ||--o{ solicitudes_mantenimiento : clasifica
    users ||--o{ solicitudes_mantenimiento : reporta
    unidades_privadas o|--o{ solicitudes_mantenimiento : ubica
    pqrs o|--o{ solicitudes_mantenimiento : origina
    solicitudes_mantenimiento ||--o{ ordenes_trabajo : genera
    users o|--o{ ordenes_trabajo : coordina
    personas o|--o{ ordenes_trabajo : ejecuta

    copropiedades ||--o{ documentos : organiza
    users ||--o{ documentos : responsabiliza
    documentos ||--o{ documento_versiones : contiene
    users ||--o{ documento_versiones : carga
    documentos ||--o{ documento_actuaciones : registra
    users o|--o{ documento_actuaciones : realiza
    ordenes_trabajo ||--o{ orden_documento : vincula
    documentos ||--o{ orden_documento : respalda
    documento_versiones o|--o{ orden_documento : fija_version_exacta

    organizaciones {
        bigint id PK
        varchar nombre
        varchar identificacion_tributaria
        varchar email
        varchar telefono
        varchar estado
    }

    copropiedades {
        bigint id PK
        bigint organizacion_id FK
        varchar nombre
        varchar nit
        varchar direccion
        varchar ciudad
        varchar estado
    }

    users {
        bigint id PK
        varchar name
        varchar email UK
        varchar password
        varchar estado
    }

    membresias_copropiedad {
        bigint id PK
        bigint usuario_id FK
        bigint copropiedad_id FK
        varchar estado
        datetime vigente_desde
        datetime vigente_hasta
    }

    personas {
        bigint id PK
        bigint organizacion_id FK
        varchar tipo_persona
        varchar nombre_razon_social
        varchar identificacion
        varchar email
        varchar telefono
    }

    unidades_privadas {
        bigint id PK
        bigint copropiedad_id FK
        varchar identificador
        varchar tipo
        varchar estado
    }

    vinculos_unidad {
        bigint id PK
        bigint persona_id FK
        bigint unidad_privada_id FK
        varchar tipo_vinculo
        date fecha_inicio
        date fecha_fin
    }

    tipo_pqrs {
        bigint id PK
        varchar nombre
        text descripcion
    }

    pqrs {
        bigint id PK
        bigint copropiedad_id FK
        bigint user_id FK
        bigint assigned_to_id FK
        bigint tipo_pqr_id FK
        varchar asunto
        text descripcion
        varchar estado
        varchar prioridad
        date fecha_radicacion
        date fecha_limite_respuesta
    }

    pqr_replies {
        bigint id PK
        bigint pqr_id FK
        bigint user_id FK
        text body
        boolean is_draft
        datetime sent_at
    }

    tipos_mantenimiento {
        bigint id PK
        varchar nombre
        text descripcion
        boolean activo
    }

    solicitudes_mantenimiento {
        bigint id PK
        bigint copropiedad_id FK
        bigint tipo_mantenimiento_id FK
        bigint unidad_privada_id FK
        bigint pqr_id FK
        bigint reportado_por FK
        varchar asunto
        text descripcion
        varchar ubicacion
        varchar prioridad
        varchar estado
        datetime fecha_reporte
        datetime fecha_cierre
    }

    ordenes_trabajo {
        bigint id PK
        bigint solicitud_mantenimiento_id FK
        bigint responsable_id FK
        bigint ejecutor_id FK
        text descripcion_trabajo
        datetime fecha_programada
        datetime fecha_inicio
        datetime fecha_fin
        varchar estado
        decimal costo_estimado
        decimal costo_real
        text observaciones
    }

    documentos {
        bigint id PK
        bigint copropiedad_id FK
        bigint propietario_id FK
        varchar titulo
        varchar categoria
        varchar nivel_acceso
        varchar estado
    }

    documento_versiones {
        bigint id PK
        bigint documento_id FK
        bigint cargado_por FK
        int numero
        varchar ruta_archivo
        varchar hash_sha256
        varchar estado
        date vigente_desde
        date vigente_hasta
    }

    documento_actuaciones {
        bigint id PK
        bigint documento_id FK
        bigint actor_id FK
        varchar accion
        text detalle
        datetime created_at
    }

    orden_documento {
        bigint orden_trabajo_id PK, FK
        bigint documento_id PK, FK
    }
```

## Lectura del diagrama

- **PK:** clave primaria.
- **FK:** clave foránea.
- **UK:** valor único.
- **Uno a muchos:** un registro puede relacionarse con varios registros de otra entidad.
- Las relaciones opcionales permiten que no exista un registro asociado.

## Reglas principales

- Una organización administra varias copropiedades.
- Los usuarios participan en copropiedades mediante membresías.
- Las personas se vinculan con unidades privadas como propietarios, residentes u otros tipos de vínculo.
- Una PQRS puede originar varias solicitudes de mantenimiento; también pueden registrarse solicitudes sin una PQRS previa.
- Una solicitud de mantenimiento puede generar varias órdenes de trabajo.
- El responsable de una orden coordina la actividad; el ejecutor puede ser una persona natural o jurídica.
- Las solicitudes pueden corresponder a una unidad privada o a una zona común identificada mediante su ubicación.
- Los documentos conservan versiones e historial de actuaciones.
- Una orden puede tener varios documentos de soporte y un documento puede respaldar varias órdenes.
- Los vínculos entre registros deben respetar la organización y copropiedad correspondientes.

## Alcance del modelo

Se muestran las entidades y atributos principales para explicar los procesos del negocio. Se omiten tablas auxiliares de permisos, notificaciones, auditoría técnica y otros detalles de infraestructura.
