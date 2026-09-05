# Modelo del Dominio

## Control documental

- **Versión:** v1.0
- **Estado:** En construcción
- **Fecha de creación:** 31 de julio de 2026
- **Última actualización:** 31 de julio de 2026
- **Responsable:** Product Owner de Resuelve

## Alcance documental

Este documento organiza el producto en grandes dominios funcionales desde una
perspectiva estratégica. Complementa la
[Visión del Producto](vision-del-producto.md), el
[Modelo de Negocio](modelo-de-negocio.md) y el
[Dominio del Negocio](dominio-del-negocio.md).

Los dominios aquí descritos expresan responsabilidades funcionales del producto
y no acreditan por sí mismos que todas sus capacidades estén implementadas.

---

# 1. Introducción

Resuelve se construye mediante dominios funcionales independientes que
evolucionan de manera modular. Cada dominio agrupa capacidades relacionadas con
un propósito de negocio y mantiene límites claros frente a los demás dominios.

Esta separación permite ampliar el producto de forma ordenada, conservar la
coherencia de las reglas del negocio y habilitar capacidades diferentes para
cada Organización. La coordinación entre dominios responde a necesidades
funcionales compartidas, sin confundir sus responsabilidades.

# 2. Dominios principales

## 2.1. Organización

- **Propósito:** representar al cliente comercial de Resuelve y servir como
  contexto principal de su operación.
- **Responsabilidades:** identificar la Organización, agrupar las
  Copropiedades que administra y determinar las capacidades habilitadas para
  ella.
- **Información que administra:** identidad de la Organización, relación con
  sus Copropiedades, suscripciones y capacidades habilitadas.
- **Relación con otros dominios:** establece el contexto para Copropiedades,
  Usuarios y Seguridad, Configuración, Reportes e Indicadores, Auditoría y
  Trazabilidad e Integraciones.

## 2.2. Copropiedades

- **Propósito:** representar cada Copropiedad administrada por una Organización
  y delimitar su contexto funcional.
- **Responsabilidades:** mantener la identidad de la Copropiedad, agrupar sus
  Unidades Privadas y relacionar la información propia de su gestión.
- **Información que administra:** identificación de la Copropiedad, Unidades
  Privadas y relaciones generales con sus Usuarios y ámbitos de gestión.
- **Relación con otros dominios:** contextualiza Residentes y Propietarios,
  PQRS, Gestión Documental, Notificaciones, Configuración, Reportes e
  Indicadores y Auditoría y Trazabilidad.

## 2.3. Usuarios y Seguridad

- **Propósito:** asegurar que cada persona acceda y actúe únicamente dentro del
  contexto autorizado.
- **Responsabilidades:** gestionar la identidad funcional de los Usuarios, sus
  Roles, autorizaciones y alcance de acceso por Organización y Copropiedad.
- **Información que administra:** Usuarios, Roles, responsabilidades,
  autorizaciones y relaciones de acceso.
- **Relación con otros dominios:** controla la participación de Usuarios en
  todos los dominios y delimita la información que puede utilizar Inteligencia
  Artificial.

## 2.4. Residentes y Propietarios

- **Propósito:** representar a las personas vinculadas con las Unidades Privadas
  de una Copropiedad.
- **Responsabilidades:** mantener las relaciones entre Unidades Privadas,
  Propietarios y Residentes, y reconocer su condición como Usuarios finales.
- **Información que administra:** Propietarios, Residentes y sus vínculos con
  las Unidades Privadas.
- **Relación con otros dominios:** depende del contexto de Copropiedades y se
  relaciona con Usuarios y Seguridad, PQRS, Notificaciones y Gestión
  Documental según las autorizaciones definidas.

## 2.5. PQRS

- **Propósito:** centralizar la gestión de peticiones, quejas, reclamos y
  sugerencias relacionadas con una Copropiedad.
- **Responsabilidades:** registrar, clasificar y gestionar las PQRS a lo largo
  de su ciclo de atención, conservando su contexto y trazabilidad.
- **Información que administra:** PQRS, Tipos de PQRS, personas involucradas,
  actuaciones y elementos documentales asociados con su gestión.
- **Relación con otros dominios:** utiliza Copropiedades, Usuarios y Seguridad,
  Residentes y Propietarios, Gestión Documental, Notificaciones, Inteligencia
  Artificial, Reportes e Indicadores y Auditoría y Trazabilidad.

## 2.6. Gestión Documental

- **Propósito:** centralizar los Documentos utilizados en la administración de
  una Organización y sus Copropiedades.
- **Responsabilidades:** organizar los Documentos, conservar su contexto de
  pertenencia y controlar su disponibilidad según las autorizaciones.
- **Información que administra:** Documentos, Reglamentos, categorías
  documentales y atributos funcionales como vigencia y nivel de acceso, cuando
  sean definidos.
- **Relación con otros dominios:** aporta información a PQRS, Inteligencia
  Artificial y los demás dominios que requieran Documentos autorizados dentro
  de su gestión.

## 2.7. Notificaciones

- **Propósito:** comunicar hechos relevantes de la gestión a los Usuarios
  correspondientes.
- **Responsabilidades:** determinar la comunicación que debe generarse, sus
  destinatarios y su seguimiento funcional conforme a reglas aprobadas.
- **Información que administra:** Notificaciones, destinatarios, hechos que las
  originan y estado de su comunicación.
- **Relación con otros dominios:** recibe necesidades de comunicación de los
  dominios funcionales y utiliza Usuarios y Seguridad para identificar
  destinatarios autorizados.

## 2.8. Inteligencia Artificial

- **Propósito:** asistir al Administrador en la toma de decisiones, la
  automatización de tareas y la mejora de la productividad.
- **Responsabilidades:** brindar asistencia sobre información autorizada y
  mantener al Administrador como responsable de toda decisión.
- **Información que administra:** contexto autorizado de asistencia,
  solicitudes del Administrador y resultados generados durante la asistencia.
- **Relación con otros dominios:** utiliza únicamente información autorizada de
  Gestión Documental, PQRS y otros dominios habilitados; depende de Usuarios y
  Seguridad y aporta trazabilidad a Auditoría y Trazabilidad.

## 2.9. Configuración

- **Propósito:** adaptar el comportamiento funcional de Resuelve al contexto de
  cada Organización y Copropiedad.
- **Responsabilidades:** mantener parámetros de negocio y preferencias
  autorizadas sin alterar las responsabilidades de los demás dominios.
- **Información que administra:** parámetros y preferencias definidos para la
  Organización o para una Copropiedad.
- **Relación con otros dominios:** proporciona condiciones de funcionamiento a
  los dominios que admiten configuración y respeta las capacidades habilitadas
  para la Organización.

## 2.10. Reportes e Indicadores

- **Propósito:** ofrecer información consolidada para el seguimiento de la
  gestión administrativa.
- **Responsabilidades:** presentar mediciones y resultados basados en
  información autorizada y con el contexto correcto de Organización y
  Copropiedad.
- **Información que administra:** definiciones de indicadores, criterios de
  consulta y resultados consolidados.
- **Relación con otros dominios:** utiliza información autorizada de los
  dominios operativos y depende de Usuarios y Seguridad para respetar el alcance
  de cada Usuario.

## 2.11. Auditoría y Trazabilidad

- **Propósito:** permitir conocer las acciones y hechos importantes ocurridos en
  la gestión.
- **Responsabilidades:** conservar el Historial de las actuaciones relevantes,
  su contexto y los participantes involucrados.
- **Información que administra:** acciones, hechos relevantes, responsables,
  contexto y referencias funcionales necesarias para su seguimiento.
- **Relación con otros dominios:** recibe hechos trazables de todos los dominios
  y los conserva dentro de la Organización y Copropiedad correspondientes.

## 2.12. Integraciones

- **Propósito:** permitir que Resuelve intercambie información autorizada con
  servicios o productos externos cuando exista una necesidad de negocio
  aprobada.
- **Responsabilidades:** delimitar el propósito de cada integración, la
  información involucrada y las autorizaciones aplicables.
- **Información que administra:** identificación funcional de las integraciones,
  autorizaciones, intercambios requeridos y resultados relevantes para el
  negocio.
- **Relación con otros dominios:** conecta necesidades externas con el dominio
  funcional correspondiente y debe respetar Usuarios y Seguridad, Configuración
  y Auditoría y Trazabilidad.

Las integraciones específicas están pendientes de definición y aprobación.

# 3. Dependencias funcionales

Las dependencias entre dominios expresan necesidades de colaboración funcional,
no subordinación técnica:

1. **Organización** establece el contexto principal y agrupa las
   **Copropiedades** que administra.
2. **Copropiedades** delimita el contexto de **Residentes y Propietarios**,
   **PQRS**, **Gestión Documental** y las demás capacidades propias de cada
   Copropiedad.
3. **Usuarios y Seguridad** autoriza quién puede participar en cada dominio y
   sobre cuál Organización o Copropiedad puede hacerlo.
4. **Configuración** aporta parámetros funcionales aplicables a la Organización
   o Copropiedad sin asumir las responsabilidades de los dominios configurados.
5. **Notificaciones** comunica hechos originados por otros dominios a Usuarios
   autorizados.
6. **Reportes e Indicadores** consolida información autorizada de los dominios
   operativos para apoyar el seguimiento administrativo.
7. **Auditoría y Trazabilidad** conserva las acciones y hechos importantes
   producidos por todos los dominios.
8. **Inteligencia Artificial** asiste al Administrador mediante información
   autorizada proveniente de los dominios habilitados y no toma decisiones en su
   lugar.
9. **Integraciones** atiende intercambios externos aprobados y conserva el
   contexto, la autorización y la trazabilidad definidos por los dominios
   involucrados.

Cada dominio conserva la responsabilidad sobre su propia información y sus
reglas de negocio, aunque comparta resultados o contexto con otros dominios.

# 4. Capacidades del producto

Las funcionalidades de Resuelve se habilitan mediante capacidades
(*features*). Cada capacidad representa una función del producto que puede ser
autorizada para una Organización.

Las capacidades no dependen del nombre de un plan comercial. Los planes
**Resuelve Básico** y **Resuelve Pro** únicamente habilitan conjuntos diferentes
de capacidades.

La asignación detallada de capacidades a cada plan continúa pendiente de
definición. La evolución o el cambio de los planes no debe alterar la identidad
ni las responsabilidades de los dominios funcionales.

# 5. Evolución del producto

La modularidad es un principio del producto. Resuelve podrá incorporar nuevos
dominios y ampliar los existentes sin afectar las responsabilidades funcionales
ya definidas.

Toda evolución deberá:

- establecer un propósito de negocio claro;
- delimitar sus responsabilidades e información;
- definir sus relaciones con los dominios existentes;
- respetar el contexto de la Organización y el aislamiento de cada
  Copropiedad;
- conservar la seguridad y la trazabilidad;
- integrarse como capacidades habilitables para la Organización.

# 6. Principios de diseño del dominio

1. **Bajo acoplamiento:** cada dominio limita sus dependencias funcionales y no
   asume responsabilidades propias de otro dominio.
2. **Alta cohesión:** las capacidades agrupadas en un dominio responden a un
   mismo propósito de negocio.
3. **Independencia funcional:** cada dominio puede evolucionar conservando sus
   límites y reglas.
4. **Escalabilidad:** el modelo debe permitir el crecimiento de Organizaciones,
   Copropiedades, Usuarios y capacidades.
5. **Seguridad:** toda participación y consulta de información debe respetar el
   contexto y las autorizaciones del Usuario.
6. **Trazabilidad:** las acciones y hechos importantes deben conservar un
   Historial verificable.
7. **Configuración por Organización:** las capacidades y parámetros se definen
   dentro del contexto de cada Organización.
8. **Aislamiento por Copropiedad:** la información propia de una Copropiedad no
   se mezcla con la de otra.
9. **Inteligencia Artificial asistiva:** la IA opera sobre información
   autorizada y nunca reemplaza el criterio o la responsabilidad del
   Administrador.

# 7. Dominios futuros

Los siguientes elementos se registran únicamente como posibles evoluciones del
producto. No constituyen alcance aprobado, funcionalidades comprometidas ni
dominios desarrollados en esta versión:

- Reservas
- Cartera
- Visitantes
- Correspondencia
- Activos
- Mantenimiento
- Encuestas
- Votaciones
- Proveedores

La incorporación de cualquiera de estos posibles dominios requerirá una
decisión explícita del Product Owner y la definición previa de su propósito,
responsabilidades, información, relaciones y capacidades.
