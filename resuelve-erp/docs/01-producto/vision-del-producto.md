# Visión del Producto

## Control documental

- **Versión:** v2.0
- **Estado:** Aprobado
- **Fecha de creación:** 31 de julio de 2026
- **Última actualización:** 3 de agosto de 2026
- **Responsable:** Product Owner de Resuelve

## Regla de gobernanza documental

Ningún agente de Arquitectura, Desarrollo o Documentación podrá modificar este
documento sin una instrucción explícita del Product Owner.

---

# 1. Propósito del producto

Centralizar y optimizar la gestión administrativa de las copropiedades mediante
una plataforma moderna, modular, segura y escalable, apoyada por Inteligencia
Artificial.

# 2. Visión

Resuelve es una plataforma SaaS especializada en la administración de propiedad
horizontal en Colombia. Se diseña para que una organización pueda administrar
una o múltiples copropiedades desde una misma plataforma.

La aplicación web constituye el núcleo operativo del producto. En fases
posteriores, Resuelve incorporará aplicaciones móviles especializadas para
residentes y administradores, construidas sobre la misma plataforma tecnológica
y la misma lógica de negocio mediante APIs.

# 3. Misión

Proveer a las organizaciones que administran copropiedades una plataforma que
centralice y optimice su gestión administrativa, automatice tareas y mejore la
productividad, sin sustituir el criterio ni la responsabilidad del
administrador.

# 4. Problema que Resuelve resuelve

Resuelve atiende la necesidad de las organizaciones de centralizar y optimizar
la administración de una o varias copropiedades desde una misma plataforma.

El producto articula la gestión administrativa, la automatización de tareas y
el apoyo a la toma de decisiones en una solución modular, segura y escalable.

# 5. Cliente objetivo

El cliente de Resuelve es una **Organización** que administra una o varias
copropiedades. Una Organización puede ser, entre otras:

- una empresa administradora;
- un administrador independiente;
- una copropiedad con administración directa;
- cualquier organización que administre una o varias copropiedades.

La naturaleza jurídica del cliente no condiciona el diseño del producto ni su
arquitectura.

# 6. Usuarios del sistema

Los administradores utilizan la plataforma para gestionar las copropiedades de
la Organización y son los usuarios a quienes asiste la Inteligencia Artificial.

Los residentes y propietarios son usuarios finales de la plataforma, pero no
constituyen el cliente comercial de Resuelve.

# 7. Propuesta de valor

Resuelve ofrece a cada Organización una plataforma única para administrar una o
múltiples copropiedades, con capacidades habilitadas de acuerdo con su
suscripción.

Su propuesta de valor se fundamenta en:

- centralización de la gestión administrativa;
- operación multicopropiedad dentro de una misma Organización;
- evolución modular del producto;
- apoyo de Inteligencia Artificial para automatizar tareas, mejorar la
  productividad y asistir la toma de decisiones;
- una plataforma tecnológica común para experiencias web y móviles.

# 8. Principios del producto

1. **Orientación a la Organización:** el cliente comercial es la Organización,
   con independencia de su naturaleza jurídica.
2. **Gestión multicopropiedad:** una Organización puede administrar una o varias
   copropiedades desde la misma plataforma.
3. **SaaS por capacidades:** los planes comerciales habilitan capacidades del
   sistema. La arquitectura depende de capacidades (*features*), no del nombre
   de cada plan.
4. **Inteligencia Artificial asistiva:** la IA apoya al administrador, automatiza
   tareas y mejora su productividad, pero no reemplaza su criterio ni su
   responsabilidad.
5. **Evolución modular:** los nuevos módulos deben integrarse sin afectar la
   arquitectura existente.
6. **Plataforma moderna, segura y escalable:** estas condiciones orientan la
   evolución funcional y técnica del producto.
7. **Enfoque multiplataforma con prioridad web:** la aplicación web es el núcleo
   operativo inicial; las aplicaciones móviles se desarrollarán en fases
   posteriores sobre la misma lógica de negocio mediante APIs.
8. **Experiencia de Producto transversal:** la experiencia visible forma parte
   del valor de cada capacidad con interacción humana. La interfaz debe expresar
   de forma comprensible el dominio y los casos de uso, sin duplicar reglas de
   negocio ni sustituir la autorización del backend.

# 9. Alcance de la primera versión comercial

La primera versión comercial prioriza la plataforma web y adopta un modelo SaaS
por suscripción.

Inicialmente existirán dos planes comerciales:

- **Resuelve Básico**;
- **Resuelve Pro**.

Cada plan habilita diferentes capacidades del sistema. El detalle de las
capacidades incluidas en cada plan está pendiente de definición y no forma parte
de esta versión del documento.

Las aplicaciones móviles para residentes y administradores corresponden a
fases posteriores.

# 10. Objetivos estratégicos

1. Centralizar y optimizar la gestión administrativa de las copropiedades.
2. Permitir que una Organización administre una o múltiples copropiedades.
3. Evolucionar el producto bajo un modelo SaaS por suscripción y habilitación de
   capacidades.
4. Incorporar Inteligencia Artificial como asistente del administrador para la
   toma de decisiones, la automatización de tareas y la productividad.
5. Mantener una evolución modular, segura y escalable.
6. Consolidar la aplicación web como núcleo operativo y habilitar posteriormente
   aplicaciones móviles sobre la misma plataforma tecnológica y lógica de
   negocio mediante APIs.
7. Incorporar la Experiencia de Producto como criterio transversal para que las
   capacidades interactivas sean comprensibles, utilizables y coherentes con el
   dominio, la autorización y el contexto operativo.

# 11. Criterios para incorporar nuevas funcionalidades

Una nueva funcionalidad deberá:

- aportar a la gestión administrativa de una o varias copropiedades;
- responder a las necesidades de la Organización como cliente;
- integrarse como capacidad habilitable, sin acoplar la arquitectura al nombre
  de un plan comercial;
- respetar la evolución modular sin afectar la arquitectura existente;
- conservar las condiciones de seguridad y escalabilidad de la plataforma;
- reutilizar la lógica de negocio mediante APIs cuando deba estar disponible en
  aplicaciones móviles;
- mantener a la Inteligencia Artificial como apoyo, sin trasladarle el criterio
  ni la responsabilidad propios del administrador.
- considerar la experiencia visible como parte del alcance cuando exista
  interacción humana, incluidos los estados y la retroalimentación necesarios
  para comprender o completar la tarea;
- consumir consultas y casos de uso existentes, sin trasladar ni duplicar
  reglas de negocio o autorización en la interfaz;
- cumplir los criterios transversales definidos en
  [Experiencia de Producto](experiencia-de-producto.md).

# 12. Visión de largo plazo

Resuelve evolucionará como una plataforma SaaS modular y multiplataforma para la
administración de propiedad horizontal en Colombia.

La aplicación web continuará como núcleo operativo. Sobre la misma plataforma
tecnológica y la misma lógica de negocio expuesta mediante APIs se desarrollarán
aplicaciones móviles especializadas para residentes y administradores.

La evolución del producto conservará la capacidad de cada Organización para
administrar una o múltiples copropiedades, incorporará nuevos módulos sin
afectar la arquitectura existente y ampliará el apoyo de Inteligencia Artificial
al administrador bajo un modelo estrictamente asistivo.
