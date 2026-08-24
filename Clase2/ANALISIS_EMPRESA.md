# Análisis de la Empresa

## 1. Datos generales

**Nombre del proyecto:** ERP para Administración de Propiedad Horizontal

**Tipo de sistema:** Sistema de planificación de recursos empresariales (ERP).

**Sector:** Administración de propiedad horizontal.

**Descripción:**  
El proyecto consiste en desarrollar un sistema ERP orientado a organizaciones encargadas de la administración de propiedad horizontal. Una organización podrá gestionar una o varias copropiedades y centralizar diferentes procesos administrativos mediante módulos independientes.

Inicialmente, el ERP estará compuesto por tres módulos principales:

- Gestión de PQRS.
- Gestión de mantenimiento.
- Gestión documental.

El sistema se plantea de forma modular, permitiendo que en el futuro puedan incorporarse nuevos módulos de acuerdo con las necesidades de las organizaciones y copropiedades administradas.

## 2. Procesos clave

### Gestión de PQRS

Permite administrar las peticiones, quejas, reclamos y sugerencias relacionadas con una copropiedad.

Flujo general:

**Radicación → Clasificación → Asignación → Seguimiento → Respuesta → Cierre**

### Gestión de mantenimiento

Permite registrar y realizar seguimiento a las necesidades de mantenimiento que se presentan dentro de las copropiedades.

Flujo general:

**Solicitud → Clasificación → Asignación → Ejecución → Seguimiento → Cierre**

### Gestión documental

Permite organizar y consultar los documentos relacionados con la administración de cada copropiedad.

Flujo general:

**Registro → Clasificación → Almacenamiento → Consulta → Archivo**

## 3. Entidades principales

### Núcleo del ERP

- Organización.
- Copropiedad.
- Módulo.

### Gestión de PQRS

- PQRS.
- Tipo de PQRS.

### Gestión de mantenimiento

- Solicitud de mantenimiento.
- Tipo de mantenimiento.
- Orden de trabajo.

### Gestión documental

- Documento.
- Tipo de documento.

## 4. Relaciones generales

Una **organización** puede administrar una o varias **copropiedades**.

Cada **copropiedad** puede utilizar los diferentes **módulos** disponibles en el ERP.

Los módulos iniciales permiten gestionar los procesos de **PQRS**, **mantenimiento** y **gestión documental**.

La estructura modular permitirá ampliar posteriormente el ERP mediante nuevos módulos sin modificar el propósito general del sistema.