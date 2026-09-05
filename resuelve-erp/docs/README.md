# Documentación oficial de Resuelve

Este directorio contiene la documentación técnica, funcional y de producto de
Resuelve. Su propósito es describir el comportamiento verificable del software,
registrar las decisiones de producto aprobadas y mantener su trazabilidad.

## Línea base

La línea base inicial corresponde al estado del árbol de trabajo inspeccionado
el 30 de julio de 2026. En esta fase:

- Git, las migraciones y las pruebas verifican el estado técnico del
  software;
- `docs/` define diseño, comportamiento funcional, trazabilidad y decisiones
  aprobadas del producto;
- `RESUELVE_ESTADO_ACTUAL.md` registra el estado operativo vivo del
  proyecto;
- no existe una única fuente aislada que sustituya a las demás;
- solo se documentan funcionalidades implementadas y verificables;
- las inconsistencias se registran como hallazgos y no se corrigen mediante la
  documentación;
- no se presentan funcionalidades futuras como parte del sistema actual.

La implementación inspeccionada es una aplicación web monolítica construida
con Laravel para gestionar PQR de una copropiedad configurable. El repositorio
no contiene actualmente una API de aplicación ni una implementación de Agente
IA. Estas ausencias se documentarán expresamente en sus secciones respectivas.

## Cómo consultar esta documentación

La documentación se organiza por ámbito. Cada documento debe incluir una
sección de evidencia con referencias a los modelos, migraciones, controladores,
políticas, rutas, vistas, pruebas u otros archivos que respaldan su contenido.

Los estados utilizados en este índice son:

- **Completado:** documento creado y validado.
- **Aprobado:** documento estratégico consolidado con decisiones aprobadas por
  el Product Owner.
- **En construcción:** documento creado cuyo contenido aprobado aún está
  pendiente de completar.
- **Pendiente:** documento aprobado en el inventario, aún no creado.

## Índice general

### 01. Producto

| Documento | Estado | Propósito |
| --- | --- | --- |
| [Visión del Producto](01-producto/vision-del-producto.md) | Aprobado | Servir como referencia estratégica para las decisiones funcionales, arquitectónicas y de desarrollo de Resuelve. |
| [Modelo de negocio](01-producto/modelo-de-negocio.md) | En construcción | Documentar el cliente, los usuarios, el modelo comercial y las estrategias de evolución del producto. |
| [Dominio del negocio](01-producto/dominio-del-negocio.md) | En construcción | Definir los conceptos, relaciones, límites y reglas generales del dominio funcional de Resuelve. |
| [Modelo del dominio](01-producto/modelo-del-dominio.md) | En construcción | Organizar el producto en dominios funcionales, capacidades y dependencias estratégicas. |
| [Línea base del estado actual](01-producto/linea-base-estado-actual.md) | Completado | Delimitar el alcance implementado, las exclusiones y el estado comprobado del producto. |

### 02. Documentación funcional

| Documento | Estado | Propósito |
| --- | --- | --- |
| [Catálogo funcional](02-funcional/catalogo-funcional.md) | Completado | Presentar los módulos, actores y capacidades existentes. |
| [Gestión de PQR](02-funcional/gestion-pqr.md) | Completado | Describir la radicación, consulta, gestión, respuesta, cierre, adjuntos e historial. |
| [Roles y permisos](02-funcional/roles-y-permisos.md) | Completado | Registrar la matriz de acceso y las restricciones implementadas. |
| [Ciclo de vida de una PQR](02-funcional/ciclo-de-vida-pqr.md) | Pendiente | Documentar estados, transiciones y efectos secundarios. |
| [Reglas de negocio](02-funcional/reglas-de-negocio.md) | Pendiente | Consolidar validaciones, plazos, vencimientos, visibilidad y eliminación. |
| [Autenticación y cuentas](02-funcional/autenticacion-y-cuentas.md) | Pendiente | Describir acceso, recuperación de contraseña y gestión del perfil. |
| [Informes e indicadores](02-funcional/informes-e-indicadores.md) | Pendiente | Documentar filtros, métricas y exportaciones. |
| [Configuración de la copropiedad](02-funcional/configuracion-copropiedad.md) | Pendiente | Registrar la identidad institucional y los parámetros configurables. |
| [Herramientas operativas](02-funcional/herramientas-operativas.md) | Pendiente | Describir plantillas, etiquetas y reglas automáticas. |
| [Gestión de residentes](02-funcional/gestion-de-residentes.md) | Pendiente | Documentar cuentas de residentes, torre y unidad privada. |

### 03. Documentación técnica

| Documento | Estado | Propósito |
| --- | --- | --- |
| [Modelo de dominio](03-tecnica/modelo-de-dominio.md) | Completado | Describir entidades, relaciones y cardinalidades. |
| [Modelo del dominio futuro](03-tecnica/modelo-del-dominio-futuro.md) | Completado | Definir las entidades y agregados conceptuales de la arquitectura objetivo. |
| [Modelo de datos](03-tecnica/modelo-de-datos.md) | Completado | Documentar tablas, columnas, claves y reglas de integridad. |
| [Modelo de datos futuro](03-tecnica/modelo-de-datos-futuro.md) | Completado | Definir el esquema relacional objetivo del núcleo multi-copropiedad. |
| [Rutas web](03-tecnica/rutas-web.md) | Pendiente | Inventariar métodos HTTP, URI, controladores y autorización. |
| [Notificaciones y automatizaciones](03-tecnica/notificaciones-y-automatizaciones.md) | Completado | Describir eventos, destinatarios, canales, aislamiento contextual y tareas programadas. |
| [Archivos y almacenamiento](03-tecnica/archivos-y-almacenamiento.md) | Pendiente | Registrar discos, adjuntos, límites, acceso y eliminación. |
| [Auditoría y trazabilidad](03-tecnica/auditoria-y-trazabilidad.md) | Pendiente | Documentar actividades de PQR y auditoría de mutaciones HTTP. |
| [Interfaz web](03-tecnica/interfaz-web.md) | Completado | Registrar la línea base y los correctivos visibles aprobados del Sprint 10. |
| [Diseño técnico — Invitaciones por correo](03-tecnica/diseno-tecnico-invitaciones.md) | Aprobado e implementado (pendiente integración a main) | Formalizar el diseño técnico del sistema de invitaciones por correo (S11-2, Sprint 11). |
| [IA — Asistencia documental con revisión humana](03-tecnica/ia-asistencia-documental.md) | Aprobado | Formalizar el Diseño Técnico Definitivo de HU-IA-01, sus contratos, procesamiento, trazabilidad, seguridad y separación académica. |
| [Dependencias](03-tecnica/dependencias.md) | Pendiente | Registrar paquetes de backend y frontend y su uso verificable. |
| [Configuración técnica](03-tecnica/configuracion-tecnica.md) | Pendiente | Describir base de datos, sesión, caché, colas, correo y filesystem. |

### 04. Desarrollo ágil

| Documento | Estado | Propósito |
| --- | --- | --- |
| [Product Backlog](04-desarrollo-agil/product-backlog.md) | En construcción | Organizar las Épicas e Historias de Usuario iniciales desde la perspectiva del negocio. |
| [Sprint 1 — Organización y Copropiedad](04-desarrollo-agil/sprint-1-organizacion-copropiedad.md) | Completado | Registrar la implementación y validación de la primera evolución estructural hacia Organización y Copropiedad sin cambios visibles. |
| [Sprint 2 — Identidad, Membresías y Contexto Operativo](04-desarrollo-agil/sprint-2-identidad-membresias-contexto.md) | Completado | Registrar la identidad contextual, su equivalencia comprobada y la sincronización con `users.role` sin activarla como fuente de autorización. |
| [Sprint 3 — Contextualización de PQRS](04-desarrollo-agil/sprint-3-contextualizacion-pqrs.md) | Completado | Registrar el aislamiento contextual de PQRS, consultas, rutas, reportes y procesos programados sin habilitar multi-copropiedad visible. |
| [Sprint 4 — Aislamiento de etiquetas de PQRS](04-desarrollo-agil/sprint-4-aislamiento-expediente-pqrs.md) | Completado | Aislar etiquetas y sus asociaciones para impedir cruces entre Copropiedades. |
| [Sprint 5 — Activación de autorización contextual](04-desarrollo-agil/sprint-5-activacion-autorizacion-contextual.md) | Completado | Activar Membresías, Roles y Permisos contextuales como fuente de autorización, con 137 pruebas aprobadas y cero divergencias. |
| [Sprint 6 — Unidades, Personas y Vínculos Contextuales](04-desarrollo-agil/sprint-6-unidades-personas-vinculos.md) | Completado | Implementar Personas, Unidades Privadas, consultas contextuales y backfill idempotente sin inferir vínculos indeterminables. |
| [Sprint 7 — Evolución contextual del módulo PQRS](04-desarrollo-agil/sprint-7-evolucion-contextual-pqrs.md) | Completado | Consolidar presentación y gestión de PQRS mediante casos de uso reutilizables y contexto comunitario complementario, sin cambios funcionales visibles. |
| [Sprint 8 — Fundamentos de Gestión Documental](04-desarrollo-agil/sprint-8-fundamentos-gestion-documental.md) | Completado | Implementar Documentos de Copropiedad, Versiones, autorización contextual, almacenamiento privado y diagnóstico informativo; queda como riesgo técnico no bloqueante automatizar concurrencia real con dos conexiones MySQL. |
| [Sprint 9 — Notificaciones contextuales](04-desarrollo-agil/sprint-9-notificaciones-contextuales.md) | Completado | Contextualizar bandeja, apertura, emisión, correo y recordatorios de los cinco eventos aprobados de PQRS. |
| [Sprint 10 — Experiencia de Producto y Consola Operativa](04-desarrollo-agil/sprint-10-experiencia-producto-consola-operativa.md) | Completado | C.1–C.3.6 cerrados e integrados; experiencia de producto y consola operativa consolidadas. |
| [Sprint 11 — Multi-copropiedad y Consola Administrativa](04-desarrollo-agil/sprint-11-multi-copropiedad-consola-administrativa.md) | Completado (S11-2 pendiente integración a main) | C.3.7.1–C.3.7.3 cerrados; multi-copropiedad, consola administrativa SaaS e invitaciones por correo. |
| [Sprint 12 — Estabilización, dependencias y perfilamiento visual](04-desarrollo-agil/sprint-12-estabilizacion-perfilamiento-visual.md) | Completado | Estabilizar el producto, revisar dependencias y mejorar consistencia visual sin nuevas funcionalidades. |
| [Sprint 13 — Simplificación visual y experiencia de usuario](04-desarrollo-agil/sprint-13-simplificacion-visual.md) | Completado | Simplificar la experiencia visual, reducir carga cognitiva, reorganizar navegación y homogeneizar componentes sin nuevas capacidades de negocio. |
| [Pruebas automatizadas](04-desarrollo-agil/pruebas-automatizadas.md) | Pendiente | Inventariar la cobertura existente y sus límites. |

### 05. Arquitectura

| Documento | Estado | Propósito |
| --- | --- | --- |
| [Arquitectura actual](05-arquitectura/arquitectura-actual.md) | Completado | Describir la estructura monolítica Laravel, sus capas y flujos. |
| [Arquitectura objetivo](05-arquitectura/arquitectura-objetivo.md) | Completado | Definir la evolución incremental hacia un monolito modular SaaS multi-copropiedad. |

### 06. Inteligencia artificial

| Documento | Estado | Propósito |
| --- | --- | --- |
| [Estado actual de IA](06-ia/estado-actual.md) | Pendiente | Dejar constancia del alcance verificable actual respecto a IA. |
| [IA — Asistencia documental con revisión humana (HU-IA-01)](06-ia/ia-asistencia-documental-hu-ia-01-pausado.md) | Futuro. Pausado. | Propuesta funcional y Diseño Técnico Definitivo aprobados de HU-IA-01; implementación pausada hasta nueva autorización. |

### 07. API

| Documento | Estado | Propósito |
| --- | --- | --- |
| [Estado actual de la API](07-api/estado-actual-api.md) | Pendiente | Registrar el alcance verificable actual de interfaces API. |

### 08. Despliegue

| Documento | Estado | Propósito |
| --- | --- | --- |
| [Entorno local](08-despliegue/entorno-local.md) | Pendiente | Documentar requisitos y mecanismos de ejecución local declarados. |
| [Túnel de demostración](08-despliegue/tunel-demostracion.md) | Pendiente | Describir el servicio de demostración y sus limitaciones. |

### 09. Historial

| Documento | Estado | Propósito |
| --- | --- | --- |
| [Hallazgos de la línea base](09-historial/hallazgos-linea-base.md) | Pendiente | Registrar inconsistencias y deuda técnica observada sin corregirla. |

### 10. Registros de decisiones arquitectónicas

| Documento | Estado | Propósito |
| --- | --- | --- |
| [ADR-001 — Monolito modular Laravel](10-adr/ADR-001-monolito-modular-laravel.md) | Completado | Adoptar un monolito modular Laravel como estilo arquitectónico. |
| [ADR-002 — Evolución incremental](10-adr/ADR-002-evolucion-incremental.md) | Completado | Evolucionar sin reescritura total mediante cambios graduales. |
| [ADR-003 — Multi-tenancy con esquema compartido](10-adr/ADR-003-multitenancy-esquema-compartido.md) | Completado | Usar una base de datos y un esquema compartidos con aislamiento contextual. |
| [ADR-004 — Organización y Copropiedad](10-adr/ADR-004-organizacion-tenant-copropiedad-ambito.md) | Completado | Definir Organización como tenant comercial y Copropiedad como ámbito operativo. |
| [ADR-005 — Usuario global y membresías](10-adr/ADR-005-usuario-global-membresias.md) | Completado | Mantener una identidad global con participación autorizada por ámbito. |
| [ADR-006 — Roles, permisos y capacidades](10-adr/ADR-006-roles-permisos-capacidades.md) | Completado | Separar autorización de Usuarios y disponibilidad comercial. |
| [ADR-007 — Planes mediante capacidades](10-adr/ADR-007-planes-mediante-capacidades.md) | Completado | Desacoplar Básico y Pro mediante capacidades habilitadas. |
| [ADR-008 — Casos de uso compartidos](10-adr/ADR-008-casos-de-uso-compartidos.md) | Completado | Reutilizar lógica de aplicación entre web, API, jobs y móviles. |
| [ADR-009 — Gestión Documental antes de IA](10-adr/ADR-009-gestion-documental-antes-de-ia.md) | Completado | Establecer fuentes documentales gobernadas antes de incorporar IA. |
| [ADR-010 — IA asistiva con RAG](10-adr/ADR-010-ia-rag-revision-humana.md) | Completado | Usar RAG, citas y revisión humana obligatoria. |

### 11. Glosario

Esta sección alojará términos funcionales y técnicos consolidados a partir de
los documentos de la línea base.

### 12. Decisiones funcionales

Esta sección alojará decisiones funcionales aprobadas y trazables. No contiene
por defecto propuestas ni comportamiento futuro.

## Prioridad de construcción

La documentación se construye incrementalmente en este orden:

1. **P0:** línea base, catálogo funcional, gestión de PQR, roles y permisos,
   modelo de dominio, modelo de datos y arquitectura actual.
2. **P1:** ciclo de vida, reglas de negocio, rutas, autenticación,
   notificaciones, almacenamiento, informes, auditoría y configuración.
3. **P2:** herramientas operativas, residentes, interfaz, dependencias,
   configuración técnica, entorno local, túnel de demostración, pruebas y
   hallazgos.
4. **P3:** estado actual de API e IA.

## Evidencia de este índice

El alcance y la organización inicial se determinaron mediante inspección de:

- `app/Models/`
- `app/Http/Controllers/`
- `app/Http/Middleware/`
- `app/Notifications/`
- `app/Policies/`
- `app/Providers/`
- `bootstrap/app.php`
- `config/`
- `database/factories/`
- `database/migrations/`
- `database/seeders/`
- `resources/js/`
- `resources/views/`
- `routes/`
- `scripts/`
- `tests/`
- `composer.json`
- `compose.yaml`
- `package.json`
- `README.md`

## Mantenimiento

Cuando cambie una funcionalidad, deben revisarse el documento funcional, el
documento técnico y los registros de trazabilidad relacionados. Los enlaces y
estados de este índice deben actualizarse en el mismo cambio documental.

## Documentación faltante por prioridad

Los siguientes documentos siguen pendientes de creación y deben abordarse
antes de avanzar a capacidades nuevas:

1. Ciclo de vida de una PQR
2. Reglas de negocio
3. Rutas web
4. Autenticación y cuentas
5. Informes e indicadores
6. Configuración de la copropiedad
7. Herramientas operativas
8. Gestión de residentes
9. Archivos y almacenamiento
10. Auditoría y trazabilidad
11. Dependencias
12. Configuración técnica
13. Entorno local
14. Túnel de demostración
15. Pruebas automatizadas
16. Hallazgos de la línea base
17. Estado actual de API
18. Estado actual de IA

## Control documental

- **Versión:** v1.35
- **Fecha de creación:** 30 de julio de 2026
- **Fecha de última actualización:** 30 de agosto de 2026
