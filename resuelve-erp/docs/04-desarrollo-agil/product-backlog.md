# Product Backlog

## Control documental

- **Versión:** v1.1
- **Estado:** En construcción
- **Fecha de creación:** 31 de julio de 2026
- **Última actualización:** 3 de agosto de 2026
- **Responsable:** Product Owner de Resuelve

## Propósito y alcance

Este documento contiene el Product Backlog inicial de Resuelve organizado por
Épicas de negocio. Se deriva de la
[Visión del Producto](../01-producto/vision-del-producto.md), el
[Modelo de Negocio](../01-producto/modelo-de-negocio.md), el
[Dominio del Negocio](../01-producto/dominio-del-negocio.md) y el
[Modelo del Dominio](../01-producto/modelo-del-dominio.md).

Las Historias de Usuario expresan necesidades iniciales del producto. Esta
versión no define criterios de aceptación, prioridades, estimaciones, tareas ni
decisiones de implementación.

## Criterio transversal de refinamiento

Toda Épica, Historia de Usuario o Sprint con interacción humana debe considerar
la experiencia visible necesaria para que el Usuario comprenda y complete la
tarea. El refinamiento debe identificar, en proporción al alcance, el actor, el
contexto, las acciones, la información requerida, los estados visibles y las
consultas o casos de uso que soportan la interacción.

La experiencia se desarrolla dentro de cada capacidad de negocio. No se creará
una Épica de frontend independiente ni se considerará completa una capacidad
interactiva por disponer únicamente de comportamiento backend cuando su alcance
aprobado requiera interacción de un Usuario.

La Presentación no duplicará reglas de negocio ni autorización y aplicará los
criterios de [Experiencia de Producto](../01-producto/experiencia-de-producto.md).

---

# 1. Épica: Organización

- **Objetivo:** establecer la Organización como cliente comercial y contexto
  principal de la operación de Resuelve.
- **Alcance:** identidad de la Organización, relación con las Copropiedades que
  administra y capacidades habilitadas por su suscripción.
- **Valor para el cliente:** permite centralizar la administración de una o
  varias Copropiedades bajo una misma Organización.

## Historias de Usuario iniciales

### HU-ORG-01 — Gestión de la Organización

Como representante de una Organización  
Quiero gestionar la información general de la Organización  
Para mantener identificado el contexto desde el cual se administran las Copropiedades.

### HU-ORG-02 — Capacidades habilitadas

Como representante de una Organización  
Quiero conocer las capacidades habilitadas por la suscripción  
Para comprender qué funciones del producto puede utilizar la Organización.

# 2. Épica: Copropiedades

- **Objetivo:** permitir que una Organización administre una o múltiples
  Copropiedades dentro de Resuelve.
- **Alcance:** identidad de cada Copropiedad, asociación con la Organización y
  delimitación de su contexto de información.
- **Valor para el cliente:** facilita una gestión centralizada y conserva la
  separación de la información de cada Copropiedad.

## Historias de Usuario iniciales

### HU-COP-01 — Administración multicopropiedad

Como Administrador  
Quiero gestionar una o varias Copropiedades de la Organización  
Para centralizar su administración en Resuelve.

### HU-COP-02 — Contexto de la Copropiedad

Como Administrador  
Quiero trabajar dentro del contexto de una Copropiedad determinada  
Para gestionar su información sin mezclarla con la de otras Copropiedades.

# 3. Épica: Usuarios y Seguridad

- **Objetivo:** asegurar que cada Usuario participe únicamente dentro del
  contexto y las capacidades que le hayan sido autorizados.
- **Alcance:** Usuarios, Roles, responsabilidades y autorizaciones por
  Organización y Copropiedad.
- **Valor para el cliente:** protege la información y permite distribuir las
  responsabilidades de gestión de forma controlada.

## Historias de Usuario iniciales

### HU-USR-01 — Autorización de Usuarios

Como Administrador  
Quiero autorizar Usuarios en las Copropiedades que gestiono  
Para permitir su participación dentro del alcance que les corresponda.

### HU-USR-02 — Acceso según el Rol

Como Usuario  
Quiero acceder únicamente a la información y capacidades autorizadas para mi Rol  
Para participar de forma segura en la gestión de la Copropiedad.

# 4. Épica: Residentes y Propietarios

- **Objetivo:** representar a Residentes y Propietarios y su relación con las
  Unidades Privadas de cada Copropiedad.
- **Alcance:** identificación de Residentes y Propietarios, relación con
  Unidades Privadas y participación como Usuarios finales.
- **Valor para el cliente:** conserva el contexto de las personas vinculadas con
  cada Unidad Privada y permite ofrecerles servicios autorizados.

## Historias de Usuario iniciales

### HU-RYP-01 — Relación con Unidades Privadas

Como Administrador  
Quiero relacionar Propietarios y Residentes con sus Unidades Privadas  
Para mantener organizado el contexto de las personas de cada Copropiedad.

### HU-RYP-02 — Participación como Usuario final

Como Residente o Propietario  
Quiero utilizar las capacidades que tenga autorizadas en mi Copropiedad  
Para participar en los servicios ofrecidos por Resuelve.

# 5. Épica: PQRS

- **Objetivo:** centralizar la gestión de peticiones, quejas, reclamos y
  sugerencias de las Copropiedades.
- **Alcance:** registro, clasificación, atención, seguimiento e Historial de las
  PQRS dentro de su Copropiedad.
- **Valor para el cliente:** organiza la atención de las PQRS y mantiene la
  trazabilidad de su gestión.

## Historias de Usuario iniciales

### HU-PQRS-01 — Presentación de una PQRS

Como Residente o Propietario  
Quiero presentar una PQRS relacionada con mi Copropiedad  
Para comunicar formalmente una petición, queja, reclamo o sugerencia.

### HU-PQRS-02 — Gestión de una PQRS

Como Administrador  
Quiero gestionar las PQRS de una Copropiedad y conservar su Historial  
Para darles atención con trazabilidad.

# 6. Épica: Gestión Documental

- **Objetivo:** centralizar los Documentos utilizados en la administración de
  una Organización y sus Copropiedades.
- **Alcance:** Documentos, Reglamentos, contexto de pertenencia y disponibilidad
  para Usuarios autorizados.
- **Valor para el cliente:** facilita el acceso controlado a la información
  documental necesaria para la gestión.

## Historias de Usuario iniciales

### HU-DOC-01 — Centralización documental

Como Administrador  
Quiero centralizar los Documentos y Reglamentos de una Copropiedad  
Para disponer de la información documental necesaria para su gestión.

### HU-DOC-02 — Consulta autorizada

Como Usuario autorizado  
Quiero consultar los Documentos disponibles para mi contexto  
Para utilizar información pertinente en mis actuaciones.

# 7. Épica: Notificaciones

- **Objetivo:** comunicar hechos relevantes de la gestión a los Usuarios
  correspondientes.
- **Alcance:** hechos que originan comunicaciones, destinatarios autorizados y
  seguimiento funcional de las Notificaciones.
- **Valor para el cliente:** mantiene informados oportunamente a los Usuarios
  involucrados en la gestión.

## Historias de Usuario iniciales

### HU-NOT-01 — Recepción de Notificaciones

Como Usuario  
Quiero recibir Notificaciones sobre hechos relevantes para mí  
Para mantenerme informado dentro del contexto de la Copropiedad.

### HU-NOT-02 — Comunicación a destinatarios autorizados

Como Administrador  
Quiero que los hechos relevantes se comuniquen a los destinatarios autorizados  
Para facilitar el seguimiento de la gestión.

# 8. Épica: Inteligencia Artificial

- **Objetivo:** asistir al Administrador en la toma de decisiones, la
  automatización de tareas y la mejora de la productividad.
- **Alcance:** asistencia basada en información autorizada, manteniendo el
  criterio y la responsabilidad en el Administrador.
- **Valor para el cliente:** mejora la productividad administrativa sin delegar
  decisiones profesionales a la Inteligencia Artificial.

## Historias de Usuario iniciales

### HU-IA-01 — Asistencia con información autorizada

Como Administrador  
Quiero recibir asistencia basada en información autorizada de la Copropiedad  
Para contar con apoyo al analizar situaciones de gestión.

### HU-IA-02 — Apoyo a tareas administrativas

Como Administrador  
Quiero que el Agente de Inteligencia Artificial me apoye en tareas administrativas  
Para mejorar mi productividad sin transferirle mi criterio ni responsabilidad.

# 9. Épica: Reportes e Indicadores

- **Objetivo:** ofrecer información consolidada para el seguimiento de la
  gestión administrativa.
- **Alcance:** indicadores, criterios de consulta y resultados autorizados por
  Organización y Copropiedad.
- **Valor para el cliente:** apoya el seguimiento y la toma de decisiones con
  una visión consolidada de la gestión.

## Historias de Usuario iniciales

### HU-REP-01 — Indicadores por Copropiedad

Como Administrador  
Quiero consultar indicadores de una Copropiedad  
Para hacer seguimiento a su gestión administrativa.

### HU-REP-02 — Visión consolidada

Como representante de una Organización  
Quiero consultar información consolidada de las Copropiedades administradas  
Para comprender el estado general de la gestión de la Organización.

# 10. Épica: Configuración

- **Objetivo:** adaptar los parámetros y preferencias del producto al contexto
  de cada Organización y Copropiedad.
- **Alcance:** configuración funcional autorizada para la Organización y sus
  Copropiedades.
- **Valor para el cliente:** permite adecuar Resuelve a las necesidades de
  gestión sin perder la coherencia del producto.

## Historias de Usuario iniciales

### HU-CONF-01 — Configuración de la Organización

Como representante de una Organización  
Quiero definir los parámetros autorizados para la Organización  
Para adaptar Resuelve a su contexto de gestión.

### HU-CONF-02 — Configuración de la Copropiedad

Como Administrador  
Quiero definir los parámetros autorizados de una Copropiedad  
Para adecuar su gestión dentro de Resuelve.

# 11. Épica: Integraciones

- **Objetivo:** permitir intercambios autorizados con servicios o productos
  externos cuando exista una necesidad de negocio aprobada.
- **Alcance:** propósito de cada integración, información involucrada,
  autorizaciones y resultados relevantes para el negocio.
- **Valor para el cliente:** permite ampliar el valor de Resuelve mediante
  intercambios controlados con servicios externos aprobados.

## Historias de Usuario iniciales

### HU-INT-01 — Vinculación con servicios aprobados

Como representante de una Organización  
Quiero vincular Resuelve con servicios externos aprobados  
Para ampliar las capacidades disponibles para la gestión.

### HU-INT-02 — Intercambio autorizado de información

Como Administrador  
Quiero utilizar intercambios de información previamente autorizados  
Para apoyar la gestión sin perder el contexto ni el control de los datos.

Las integraciones específicas permanecen pendientes de definición y
aprobación.

# 12. Épica: Auditoría y Trazabilidad

- **Objetivo:** conservar el Historial de las acciones y hechos importantes de
  la gestión.
- **Alcance:** actuaciones relevantes, responsables y contexto de Organización
  y Copropiedad.
- **Valor para el cliente:** permite conocer qué ocurrió y mantener trazabilidad
  sobre la gestión administrativa.

## Historias de Usuario iniciales

### HU-AUD-01 — Consulta del Historial

Como Administrador  
Quiero consultar el Historial de las actuaciones importantes de una Copropiedad  
Para hacer seguimiento a su gestión.

### HU-AUD-02 — Trazabilidad de acciones

Como representante de una Organización  
Quiero que las acciones importantes conserven su responsable y contexto  
Para contar con trazabilidad sobre la operación de las Copropiedades.

# Evolución del Product Backlog

Este Product Backlog evolucionará durante el desarrollo de Resuelve. Las
Épicas y las Historias de Usuario podrán ser refinadas, ampliadas o ajustadas
únicamente a partir de decisiones de producto aprobadas por el Product Owner.

Los criterios de aceptación, prioridades y estimaciones se incorporarán en los
procesos de refinamiento correspondientes cuando hayan sido definidos y
aprobados.
