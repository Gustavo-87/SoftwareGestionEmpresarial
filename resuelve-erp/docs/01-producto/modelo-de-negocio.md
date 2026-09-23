# Modelo de Negocio

## Control documental

- **Versión:** v1.1
- **Estado:** En construcción
- **Fecha de creación:** 31 de julio de 2026
- **Última actualización:** 23 de septiembre de 2026
- **Responsable:** Gustavo Piedrahita, creador de Resuelve-ERP

## Fuente de las decisiones

Este documento desarrolla el modelo de negocio aprobado en la
[Visión del Producto](vision-del-producto.md). Describe decisiones de producto y
no representa el estado actual de la implementación técnica.

## Empresa del caso de estudio

- **Nombre:** Gestión Urbana de Copropiedades S.A.S.
- **Giro del negocio:** Administración de copropiedades residenciales.
- **Tamaño:** Pequeña empresa, definida para el caso de estudio académico.
- **Plataforma utilizada en el proyecto:** Resuelve ERP.

Para este caso académico, la empresa requiere centralizar la atención de PQRS, la gestión documental y el seguimiento del mantenimiento de las copropiedades que administra.

Resuelve ERP dispone de funcionalidades de PQRS y gestión documental. El módulo de mantenimiento se encuentra en construcción. Las capacidades de inteligencia artificial y las aplicaciones móviles descritas en este documento corresponden a la evolución prevista del producto.

## Procesos clave del caso de estudio

### Gestión de PQRS

El residente registra una PQRS. La administración revisa el caso, asigna un responsable y realiza seguimiento hasta emitir una respuesta y cerrar la solicitud. El proceso conserva los archivos adjuntos y el historial de actuaciones.

### Gestión documental

La administración registra y clasifica los documentos de la copropiedad. Las versiones pasan por revisión y aprobación o rechazo. Los usuarios autorizados pueden consultar y descargar los documentos disponibles.

### Gestión de mantenimiento

El proceso contempla identificar necesidades de mantenimiento, programar actividades, asignar responsables y registrar su ejecución sobre zonas comunes, equipos e instalaciones. Su implementación en Resuelve ERP se encuentra en construcción.

### Relación entre los procesos

Los procesos comparten la información de organizaciones, copropiedades y usuarios. Una PQRS puede evidenciar una necesidad de mantenimiento, y la gestión documental permite conservar información de apoyo para la administración. La integración automática con mantenimiento todavía no está implementada.
---

# 1. Objetivo del producto

Centralizar y optimizar la gestión administrativa de las copropiedades mediante
una plataforma moderna, modular, segura y escalable, apoyada por Inteligencia
Artificial.

# 2. Tipo de producto

Resuelve es una plataforma:

- **SaaS:** se ofrece mediante un modelo de suscripción;
- **especializada:** se orienta a la administración de propiedad horizontal en
  Colombia;
- **modular:** permite incorporar capacidades y módulos de forma independiente;
- **escalable:** se diseña para evolucionar y soportar la administración de una
  o múltiples copropiedades por Organización.

# 3. Cliente objetivo

El cliente comercial de Resuelve es una **Organización** que administra una o
varias copropiedades. Una Organización puede ser:

- una empresa administradora;
- un administrador independiente;
- una copropiedad con administración directa;
- cualquier otra organización que administre una o varias copropiedades.

La naturaleza jurídica de la Organización no condiciona el diseño del producto
ni su arquitectura.

# 4. Usuarios finales

Los administradores utilizan Resuelve para gestionar las copropiedades de la
Organización. Son, además, los usuarios a quienes asiste la Inteligencia
Artificial.

Los residentes y propietarios también son usuarios finales de la plataforma,
pero no constituyen el cliente comercial del producto.

# 5. Modelo comercial

Resuelve evolucionará bajo un modelo SaaS por suscripción. Inicialmente
existirán dos planes comerciales:

- **Resuelve Básico**;
- **Resuelve Pro**.

Los planes habilitan diferentes capacidades del sistema. El producto debe
gestionar estas diferencias mediante capacidades (*features*) habilitadas para
cada Organización. La arquitectura no depende del nombre del plan comercial.

La asignación detallada de capacidades a cada plan, los precios, la periodicidad
de cobro y las demás condiciones comerciales están pendientes de definición.

# 6. Estrategia de evolución

Resuelve evolucionará mediante módulos independientes que deberán integrarse
sin afectar el núcleo del sistema ni la arquitectura existente.

Las nuevas capacidades deberán responder a las necesidades de las
Organizaciones y conservar el carácter modular, seguro y escalable del
producto.

# 7. Estrategia multiplataforma

La plataforma web será el núcleo operativo y la prioridad inicial de Resuelve.

Las aplicaciones móviles constituyen una evolución natural del producto. En
fases posteriores existirán aplicaciones móviles especializadas para:

- residentes;
- administradores.

La plataforma web y las aplicaciones móviles compartirán la misma lógica de
negocio mediante APIs y formarán parte de una misma plataforma tecnológica.

# 8. Papel de la Inteligencia Artificial

La Inteligencia Artificial forma parte de Resuelve como asistente del
administrador. Su función es apoyar la toma de decisiones, automatizar tareas y
mejorar la productividad.

La IA nunca reemplaza el criterio profesional ni la responsabilidad del
administrador.

# 9. Principios del negocio

1. **La Organización es el cliente:** residentes y propietarios son usuarios
   finales, no clientes comerciales.
2. **Gestión multicopropiedad:** una Organización puede administrar una o varias
   copropiedades desde la misma plataforma.
3. **Suscripción basada en capacidades:** los planes habilitan *features* y la
   arquitectura no se acopla a sus nombres comerciales.
4. **Evolución modular:** los módulos nuevos se integran sin afectar el núcleo
   del sistema.
5. **Prioridad web y evolución móvil:** la plataforma web es el núcleo operativo
   y las aplicaciones móviles se incorporarán posteriormente.
6. **Lógica de negocio compartida:** las distintas experiencias de usuario
   utilizarán la misma lógica de negocio mediante APIs.
7. **IA asistiva:** la Inteligencia Artificial apoya al administrador y no
   sustituye su criterio ni su responsabilidad.
8. **Seguridad y escalabilidad:** la evolución del producto debe conservar estas
   condiciones.

# 10. Riesgos estratégicos identificados

Pendiente de definición por el Product Owner. No se registran riesgos
estratégicos oficiales en esta versión.

# 11. Oportunidades de crecimiento

Pendiente de definición por el Product Owner. No se registran oportunidades de
crecimiento oficiales en esta versión.

# 12. Conclusiones

El modelo de negocio de Resuelve se fundamenta en ofrecer por suscripción una
plataforma SaaS para Organizaciones que administran una o múltiples
copropiedades. La diferenciación entre planes se expresa mediante capacidades
habilitadas y no mediante dependencias arquitectónicas asociadas a sus nombres.

La evolución prevista mantiene la plataforma web como núcleo operativo,
incorpora módulos independientes, proyecta aplicaciones móviles que comparten
la lógica de negocio mediante APIs y utiliza Inteligencia Artificial como apoyo
del administrador. Los riesgos estratégicos, las oportunidades de crecimiento y
las condiciones comerciales detalladas requieren definición posterior del
Product Owner.
