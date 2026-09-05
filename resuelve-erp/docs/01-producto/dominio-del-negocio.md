# Dominio del Negocio

## Control documental

- **Versión:** v1.0
- **Estado:** En construcción
- **Fecha de creación:** 31 de julio de 2026
- **Última actualización:** 31 de julio de 2026
- **Responsable:** Product Owner de Resuelve

## Fuentes de las decisiones

Este documento desarrolla el dominio funcional aprobado en la
[Visión del Producto](vision-del-producto.md) y el
[Modelo de Negocio](modelo-de-negocio.md). Utiliza lenguaje de negocio y no
describe la implementación técnica del producto.

---

# 1. Introducción al dominio

Resuelve pertenece al dominio de la administración de propiedad horizontal en
Colombia. El producto permite que una Organización centralice la gestión de una
o varias Copropiedades y que sus Usuarios participen de acuerdo con el Rol que
les haya sido autorizado.

El dominio comprende la relación entre Organizaciones, Copropiedades, Unidades
Privadas, personas, órganos de la Copropiedad, PQRS, Documentos, comunicaciones
y trazabilidad. También incorpora un Agente de Inteligencia Artificial que
asiste al Administrador sobre información autorizada, sin sustituir su criterio
ni su responsabilidad.

# 2. Conceptos principales del negocio

## 2.1. Organización

Cliente comercial de Resuelve que administra una o varias Copropiedades. Puede
ser una empresa administradora, un Administrador independiente, una
Copropiedad con administración directa u otra organización que administre
Copropiedades. Su naturaleza jurídica no condiciona el diseño del producto.

## 2.2. Copropiedad

Comunidad sometida al régimen de propiedad horizontal cuya gestión
administrativa es realizada por una Organización. Agrupa Unidades Privadas,
personas, Documentos, órganos de gobierno, PQRS, Notificaciones e Historial
propios.

## 2.3. Unidad privada

Bien privado que forma parte de una Copropiedad. Puede estar relacionado con uno
o varios Propietarios y con uno o varios Residentes.

## 2.4. Propietario

Persona natural o jurídica que ostenta la calidad de titular de una Unidad
Privada. Puede ser Usuario de Resuelve, pero no constituye por esa condición el
cliente comercial del producto.

## 2.5. Residente

Persona que habita o utiliza una Unidad Privada. Puede ser o no Propietario y
puede acceder a Resuelve como Usuario final cuando esté autorizado.

## 2.6. Administrador

Persona responsable de la gestión administrativa de una o varias
Copropiedades. Opera en representación de la Organización dentro del alcance
que le corresponda y conserva el criterio profesional y la responsabilidad
sobre sus decisiones.

## 2.7. Colaborador

Persona autorizada por una Organización para apoyar la gestión de una o varias
Copropiedades. Sus responsabilidades y facultades dependen del Rol que le sea
asignado. Los tipos de Colaborador y su alcance detallado están pendientes de
definición.

## 2.8. Usuario

Persona con acceso autorizado a Resuelve. Un Usuario puede actuar como
Administrador, Colaborador, Propietario o Residente, de acuerdo con sus Roles y
con la Copropiedad sobre la cual tenga autorización.

## 2.9. Rol

Conjunto de responsabilidades y capacidades autorizadas para un Usuario dentro
del contexto de una Organización y sus Copropiedades. La definición detallada
de Roles y permisos está pendiente de aprobación funcional.

## 2.10. PQRS

Petición, queja, reclamo o sugerencia relacionada con una Copropiedad. Su
gestión debe conservar la identificación de la Copropiedad, los Usuarios
involucrados y el Historial de las actuaciones importantes.

## 2.11. Tipo de PQRS

Clasificación de negocio utilizada para distinguir la naturaleza de una PQRS.
El catálogo de tipos y sus reglas específicas está pendiente de definición.

## 2.12. Documento

Contenido documental relacionado con una Organización o una Copropiedad y
utilizado dentro de su gestión. Su clase, vigencia, nivel de acceso y efectos
dependen del contexto de negocio que se defina.

## 2.13. Reglamento

Documento normativo propio de una Copropiedad que establece disposiciones para
su funcionamiento y convivencia. Forma parte de la información autorizada que
puede apoyar la gestión del Administrador.

## 2.14. Asamblea

Órgano de la Copropiedad conformado por los Propietarios o sus representantes
autorizados. Sus procesos, facultades y tratamiento funcional en Resuelve están
pendientes de definición.

## 2.15. Consejo de Administración

Órgano colegiado de una Copropiedad, cuando exista, que participa en su gestión
conforme a las disposiciones aplicables y al Reglamento. Sus facultades y su
tratamiento funcional en Resuelve están pendientes de definición.

## 2.16. Comité

Órgano opcional de una Copropiedad creado para atender una materia o función
específica. Su existencia, composición, facultades y tratamiento funcional en
Resuelve dependen de decisiones futuras.

## 2.17. Notificación

Comunicación dirigida a uno o varios Usuarios sobre un hecho relevante dentro
de la gestión de una Copropiedad. Los eventos, destinatarios y medios de entrega
requieren definición funcional específica.

## 2.18. Historial

Registro ordenado de las acciones y hechos relevantes ocurridos en el dominio.
Permite conocer qué ocurrió y mantener la trazabilidad de la gestión.

## 2.19. Agente de Inteligencia Artificial

Asistente del Administrador que opera sobre información autorizada para apoyar
la toma de decisiones, automatizar tareas y mejorar la productividad. No toma
decisiones en lugar del Administrador ni reemplaza su criterio profesional o su
responsabilidad.

# 3. Relaciones del dominio

## 3.1. Organización y Copropiedades

- Toda la información gestionada en Resuelve pertenece a una Organización.
- Una Organización administra una o varias Copropiedades.
- Cada Copropiedad conserva su propio contexto y el aislamiento de sus datos.

## 3.2. Copropiedad, Unidades Privadas y personas

- Una Copropiedad contiene múltiples Unidades Privadas.
- Una Unidad Privada puede tener uno o varios Propietarios.
- Una Unidad Privada puede tener uno o varios Residentes.
- Una persona puede ser Propietario y Residente al mismo tiempo.

## 3.3. Usuarios, Roles y gestión

- Los Usuarios acceden a la información y a las capacidades de acuerdo con sus
  Roles y autorizaciones.
- Un Administrador gestiona una o varias Copropiedades en el contexto de una
  Organización.
- Un Colaborador apoya la gestión según el Rol que le haya sido asignado.
- Propietarios y Residentes pueden participar como Usuarios finales, pero no son
  el cliente comercial de Resuelve.

## 3.4. PQRS, Documentos y trazabilidad

- Toda PQRS pertenece a una Copropiedad y se clasifica mediante un Tipo de PQRS.
- Los Documentos pertenecen al contexto de una Organización o de una
  Copropiedad.
- Un Reglamento es un Documento normativo propio de una Copropiedad.
- Las acciones importantes relacionadas con la gestión deben quedar reflejadas
  en el Historial.
- Las Notificaciones comunican hechos relevantes a los Usuarios autorizados.

## 3.5. Órganos de la Copropiedad

- La Asamblea pertenece al contexto de una Copropiedad.
- El Consejo de Administración pertenece a una Copropiedad cuando exista.
- Una Copropiedad puede contar con uno o varios Comités cuando corresponda.
- Las relaciones detalladas entre estos órganos, los Usuarios y los procesos de
  negocio están pendientes de definición.

## 3.6. Inteligencia Artificial

- El Agente de Inteligencia Artificial asiste al Administrador.
- El Agente actúa únicamente sobre información autorizada de la Organización y
  sus Copropiedades.
- El resultado de la asistencia no reemplaza la decisión, el criterio ni la
  responsabilidad del Administrador.

# 4. Límites del dominio

## 4.1. Dentro del alcance

El dominio de Resuelve comprende, al nivel aprobado actualmente:

- Organizaciones que administran una o varias Copropiedades;
- Copropiedades, Unidades Privadas, Propietarios y Residentes;
- Administradores, Colaboradores, Usuarios y Roles;
- PQRS y sus Tipos;
- Documentos y Reglamentos;
- Asamblea, Consejo de Administración y Comités como conceptos del dominio;
- Notificaciones e Historial;
- asistencia al Administrador mediante Inteligencia Artificial.

## 4.2. Fuera del alcance definido

No forman parte del alcance aprobado:

- la sustitución del Administrador o de su responsabilidad mediante
  Inteligencia Artificial;
- el acceso a información de una Organización o Copropiedad sin autorización;
- procesos, reglas, módulos o capacidades que no hayan sido definidos y
  aprobados por el Product Owner.

Esta delimitación no establece exclusiones permanentes. Los conceptos cuyo
tratamiento funcional continúa pendiente solo podrán incorporarse mediante una
decisión posterior del Product Owner.

# 5. Principios del dominio

1. Toda la información pertenece a una Organización.
2. Cada Organización administra una o varias Copropiedades.
3. Cada Copropiedad mantiene aislamiento de sus datos.
4. Los Usuarios actúan únicamente dentro del alcance de sus Roles y
   autorizaciones.
5. Toda acción importante debe ser trazable mediante el Historial.
6. La información utilizada por el Agente de Inteligencia Artificial debe estar
   autorizada.
7. La Inteligencia Artificial nunca toma decisiones por el Administrador ni
   reemplaza su criterio o responsabilidad.
8. Propietarios y Residentes son usuarios finales, no clientes comerciales.

# 6. Reglas generales del negocio

- Una Organización debe ser el contexto principal de la información gestionada
  en Resuelve.
- Una Copropiedad debe estar vinculada a la Organización que la administra.
- La información de cada Copropiedad debe mantenerse separada de la información
  de las demás Copropiedades.
- Una Unidad Privada pertenece al contexto de una Copropiedad.
- Los vínculos de Propietarios y Residentes deben establecerse respecto de una
  Unidad Privada.
- Todo acceso de un Usuario debe responder a un Rol y a una autorización dentro
  de la Organización y la Copropiedad correspondientes.
- Toda PQRS debe quedar asociada con una Copropiedad.
- Todo Documento debe conservar el contexto de la Organización o Copropiedad a
  la que pertenece.
- Las acciones importantes de gestión deben conservar trazabilidad.
- El Agente de Inteligencia Artificial solo puede asistir sobre información
  autorizada y no puede reemplazar la decisión del Administrador.

Las reglas específicas de procesos, estados, plazos, facultades, permisos y
excepciones deberán documentarse cuando sean definidas y aprobadas.

# 7. Glosario del dominio

| Concepto | Definición breve |
| --- | --- |
| Organización | Cliente comercial que administra una o varias Copropiedades. |
| Copropiedad | Comunidad de propiedad horizontal administrada por una Organización. |
| Unidad privada | Bien privado perteneciente al contexto de una Copropiedad. |
| Propietario | Titular de una Unidad Privada. |
| Residente | Persona que habita o utiliza una Unidad Privada. |
| Administrador | Responsable de la gestión administrativa de una o varias Copropiedades. |
| Colaborador | Persona autorizada para apoyar la gestión según su Rol. |
| Usuario | Persona con acceso autorizado a Resuelve. |
| Rol | Conjunto de responsabilidades y capacidades autorizadas para un Usuario. |
| PQRS | Petición, queja, reclamo o sugerencia de una Copropiedad. |
| Tipo de PQRS | Clasificación de la naturaleza de una PQRS. |
| Documento | Contenido documental asociado con una Organización o Copropiedad. |
| Reglamento | Documento normativo propio de una Copropiedad. |
| Asamblea | Órgano de la Copropiedad conformado por Propietarios o sus representantes. |
| Consejo de Administración | Órgano colegiado de una Copropiedad, cuando exista. |
| Comité | Órgano opcional orientado a una materia o función específica. |
| Notificación | Comunicación de un hecho relevante a Usuarios autorizados. |
| Historial | Registro ordenado de acciones y hechos relevantes. |
| Agente de Inteligencia Artificial | Asistente del Administrador que opera sobre información autorizada. |
