# Arquitectura objetivo

## Control documental

- **Versión:** v1.3
- **Estado:** Aprobado
- **Fecha de creación:** 31 de julio de 2026
- **Última actualización:** 22 de agosto de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## 1. Propósito y alcance

Este documento define la arquitectura objetivo de Resuelve como plataforma SaaS
multi-copropiedad. Orienta la evolución del sistema Laravel actual sin exigir
una reescritura completa ni representar funcionalidades ya implementadas.

La arquitectura se fundamenta en el código actual, la documentación de línea
base, la Visión del Producto, el Modelo de Negocio, el Dominio del Negocio, el
Modelo del Dominio y el Product Backlog. La
[arquitectura actual](arquitectura-actual.md) continúa siendo la referencia del
estado implementado.

## 2. Resumen ejecutivo

Resuelve evolucionará como un **monolito modular Laravel**. La Organización será
el tenant comercial y agrupará una o varias Copropiedades. La Copropiedad será
el ámbito operativo y la frontera principal de aislamiento de datos.

La plataforma utilizará una base de datos y un esquema compartidos. El contexto
se expresará mediante `organizacion_id` y `copropiedad_id`, acompañado por
controles de autorización, consultas contextualizadas, restricciones de
integridad y segmentación de archivos, caché, jobs y auditoría.

Los Usuarios tendrán identidad global y participarán mediante membresías por
Organización o Copropiedad. Los Roles y permisos determinarán qué puede hacer un
Usuario dentro de su ámbito; las capacidades comerciales determinarán qué
funciones tiene habilitadas la Organización. Los planes Resuelve Básico y
Resuelve Pro se traducirán a conjuntos de capacidades y no serán condiciones
directas dentro de los módulos funcionales.

Blade continuará como núcleo web inicial. La lógica de negocio se trasladará
gradualmente desde los controladores hacia casos de uso reutilizables por la
web, una API futura, jobs, comandos y aplicaciones móviles.

La Gestión Documental precederá a la Inteligencia Artificial. La IA será un
asistente del Administrador basado en RAG, documentos normativos autorizados,
citas verificables y revisión humana obligatoria. No tomará decisiones ni
ejecutará actuaciones en nombre del Administrador sin una acción humana
explícita.

No se utilizarán microservicios en esta etapa. La separación física de módulos
solo se evaluará en el futuro si existen necesidades verificables de escala,
aislamiento, despliegue o autonomía que compensen su costo operativo.

## 3. Principios arquitectónicos

1. **Evolución incremental:** cada cambio debe poder incorporarse sobre el
   sistema actual mediante pasos compatibles y verificables.
2. **Monolito modular:** los límites del dominio se expresan dentro de una sola
   aplicación desplegable, con responsabilidades y dependencias explícitas.
3. **Organización como tenant:** toda información de negocio pertenece al
   contexto de una Organización.
4. **Aislamiento por Copropiedad:** la información operativa de una Copropiedad
   no puede mezclarse ni exponerse a otra sin autorización.
5. **Seguridad por diseño:** el contexto, la membresía, el permiso, la capacidad
   y la pertenencia del recurso se validan en el backend.
6. **Una sola lógica de negocio:** web, API, jobs y clientes móviles utilizan
   los mismos casos de uso y reglas.
7. **Capacidades estables:** el dominio consulta capacidades habilitadas, no
   nombres de planes comerciales.
8. **Trazabilidad:** las acciones relevantes conservan actor, contexto, origen,
   resultado y correlación.
9. **Procesamiento confiable:** los efectos secundarios se desacoplan mediante
   eventos y colas cuando no requieren respuesta inmediata.
10. **IA asistiva:** toda salida generada por IA se apoya en información
    autorizada y queda sujeta a revisión humana.
11. **Simplicidad proporcional:** no se introducen abstracciones, servicios
    distribuidos o infraestructura sin una necesidad demostrada.
12. **Lenguaje del negocio en español:** el dominio funcional nuevo utiliza los
    términos aprobados; los elementos técnicos heredados pueden conservarse
    cuando renombrarlos produzca riesgo sin valor suficiente.
13. **Experiencia como criterio transversal:** toda capacidad con interacción
    humana considera su experiencia visible como parte del producto, sin
    trasladar reglas de negocio ni autorización a la Presentación.

## 4. Estilo y estructura arquitectónica

El sistema se desplegará inicialmente como una aplicación Laravel y una base de
datos relacional. Los módulos podrán contener cuatro áreas lógicas:

```text
Módulo
├── Dominio
│   ├── entidades y objetos de valor
│   ├── reglas
│   └── eventos de dominio
├── Aplicación
│   ├── casos de uso
│   ├── comandos y consultas
│   └── contratos
├── Infraestructura
│   ├── persistencia Eloquent
│   ├── jobs
│   └── adaptadores externos
└── Presentación
    ├── web
    └── API
```

Esta estructura es una dirección de evolución, no una obligación de trasladar
mecánicamente todo el código existente. Se aplicará primero a los flujos que se
modifiquen o incorporen, comenzando por contexto multi-tenant, autorización y
PQRS.

La relación entre la Experiencia de Producto y las capas internas será:

```text
Experiencia de Producto
          │
          ▼
Presentación
          │
          ▼
Aplicación
          │
          ▼
Dominio
```

La Experiencia de Producto define cómo el Usuario comprende y utiliza una
capacidad. Presentación captura su intención y representa resultados;
Aplicación expone consultas y casos de uso reutilizables; Dominio conserva las
reglas e invariantes. Esta relación no crea un módulo adicional ni altera los
límites de los módulos existentes.

## 5. Módulos propuestos

| Módulo | Responsabilidad principal |
| --- | --- |
| Organización y Suscripción | Identidad del cliente, suscripción y capacidades habilitadas. |
| Copropiedades | Identidad de la Copropiedad, configuración y Unidades Privadas. |
| Identidad y Acceso | Usuarios, membresías, Roles, permisos y ámbitos autorizados. |
| Residentes y Propietarios | Personas y vínculos con Unidades Privadas. |
| PQRS | Radicación, clasificación, asignación, respuesta, estados y seguimiento. |
| Gestión Documental | Documentos, versiones, vigencia, acceso y archivos. |
| Inteligencia Artificial | Consultas RAG, resultados, fuentes y revisión humana. |
| Notificaciones | Destinatarios, mensajes, preferencias y canales de entrega. |
| Reportes e Indicadores | Consultas operativas y consolidados autorizados. |
| Auditoría y Trazabilidad | Acciones, hechos relevantes y correlación. |
| Configuración | Parámetros por Organización y Copropiedad. |
| Integraciones | Intercambios y adaptadores para servicios externos aprobados. |
| Plataforma | Colas, almacenamiento, scheduler, caché y observabilidad. |

Etiquetas, plantillas y reglas automáticas permanecerán inicialmente dentro de
PQRS. Solo se separarán si adquieren responsabilidades y consumidores más
amplios.

## 6. Reglas de dependencia

- Presentación invoca casos de uso de Aplicación y no contiene reglas de
  negocio reutilizables.
- Aplicación coordina el Dominio, transacciones y contratos de infraestructura.
- Infraestructura implementa persistencia, almacenamiento, mensajería y
  adaptadores externos.
- Un módulo no modifica directamente la información interna de otro; utiliza
  sus casos de uso o contratos públicos.
- Las colaboraciones no inmediatas se expresan mediante eventos internos.
- Los efectos secundarios externos se ejecutan después de confirmar la
  transacción que origina el hecho.
- Las consultas entre módulos se limitan a interfaces de lectura explícitas o
  proyecciones autorizadas.
- Eloquent se conserva y puede usarse dentro de los límites del módulo. No se
  exige una capa de repositorios genéricos sin beneficio concreto.
- Las dependencias deben evitar ciclos. Los módulos transversales no pueden
  convertirse en contenedores de reglas pertenecientes a otros dominios.

## 7. Modelo conceptual objetivo

```text
Organización
 ├── 1..N Copropiedades
 ├── 1..N Membresías de Organización ── Usuario
 ├── 0..N Suscripciones
 │        └── N..M Capacidades mediante Habilitación de Capacidad
 └── Configuración de Organización

Copropiedad
 ├── pertenece a una Organización
 ├── 1..N Unidades Privadas
 │        └── N..M Personas mediante Vínculo con Unidad
 ├── 1..N Membresías de Copropiedad ── Usuario
 │        └── N..M Roles y Permisos
 ├── 1..N PQRS
 │        ├── Tipo de PQRS
 │        ├── Radicador
 │        ├── Responsable
 │        ├── Respuestas
 │        ├── Comentarios internos
 │        ├── Adjuntos
 │        └── Historial
 ├── 1..N Documentos
 │        └── 1..N Versiones
 │                 └── 1..N Fragmentos indexados para RAG
 ├── Configuración de Copropiedad
 └── Eventos de Auditoría

Consulta IA
 ├── Organización y Copropiedad obligatorias
 ├── Usuario solicitante
 ├── fuentes documentales recuperadas
 ├── respuesta generada
 ├── versión de modelo y configuración
 └── Revisión Humana
          ├── pendiente
          ├── aprobada
          ├── modificada
          └── rechazada
```

`Usuario` representa una identidad autenticable global. `Persona` representa a
un sujeto del dominio y puede existir sin cuenta de Usuario. Propietario y
Residente se expresan mediante vínculos con Unidades Privadas y no se asumen
como equivalentes automáticos de un Rol técnico.

## 8. Estrategia multi-tenant

### 8.1. Modelo de datos compartido

La base de datos y el esquema serán compartidos. Las entidades de la
Organización incluirán `organizacion_id`; las entidades propias de una
Copropiedad incluirán `copropiedad_id` y, cuando refuerce integridad, seguridad
o eficiencia, también `organizacion_id`.

Las restricciones e índices deberán incluir el ámbito correspondiente:

```text
UNIQUE (organizacion_id, identificador_copropiedad)
UNIQUE (copropiedad_id, nombre_tipo_pqrs)
INDEX  (organizacion_id, copropiedad_id, estado)
```

Cada catálogo deberá declarar si su ámbito es plataforma, Organización o
Copropiedad. No se asumirán catálogos globales por conveniencia técnica.

### 8.2. Contexto de ejecución

Toda solicitud web o API, job, comando y evento resolverá un contexto que
contenga:

- Usuario autenticado o actor técnico;
- Organización activa;
- Copropiedad activa cuando corresponda;
- membresía vigente;
- Roles y permisos aplicables;
- capacidades habilitadas;
- identificador de correlación.

La Organización o Copropiedad enviada por un cliente no será confiable por sí
misma. El backend verificará que pertenezca al ámbito autorizado del Usuario.

C.3.7.1 ha implementado parcialmente este principio: el contexto de ejecución se
re-resuelve en cada solicitud, la selección de Copropiedad valida membresía
vigente y la selección automática opera cuando existe una única membresía. La
gestión administrativa de membresías y el soporte para múltiples Organizaciones
permanecen pendientes.

### 8.3. Defensa en profundidad

1. Resolución del contexto en middleware.
2. Route model binding restringido al contexto activo.
3. Consultas de aplicación contextualizadas.
4. Policies que validen acción, recurso y ámbito.
5. claves foráneas, restricciones e índices compuestos;
6. separación de archivos, caché y claves de idempotencia por tenant;
7. contexto serializado y revalidado al ejecutar jobs;
8. auditoría de acciones sensibles y cambios de contexto;
9. verificación automatizada de intentos de acceso entre Copropiedades durante
   la implementación.

Los global scopes de Eloquent podrán utilizarse como salvaguarda, pero no serán
el único control: el contexto debe permanecer explícito en los casos de uso.

## 9. Identidad, membresías, Roles y permisos

La identidad del Usuario será global. La autorización se determinará mediante
membresías vigentes por Organización o Copropiedad.

C.3.7.2.1 ha implementado la autoridad de plataforma. C.3.7.2.2 ha
implementado la gestión administrativa de membresías. C.3.7.3 ha implementado
la consola administrativa base SaaS. La separación entre autoridad de
plataforma y autoridad de Copropiedad es la siguiente:

```text
Autoridad de plataforma (C.3.7.2.1 — implementado)
  ├── users.es_administrador_sistema
  ├── Gate: administrar-sistema
  ├── Middleware: admin.sistema
  ├── Ruta: /admin
  └── Alcance: toda la plataforma, cualquier Organización y Copropiedad

Autoridad de Copropiedad
  └── membresía vigente + Rol + Permisos contextuales
        ├── Alcance: una Copropiedad específica dentro de una Organización
        └── Validación: ContextoOperativo re-resuelto en cada solicitud

Gestión de membresías (C.3.7.2.2 — implementado)
  ├── CRUD de membresías por Administrador del sistema
  ├── Asignación y revocación de roles contextuales
  ├── Cambio de estados (activa, suspendida, finalizada)
  └── Auditoría específica por operación

Consola administrativa base SaaS (C.3.7.3 — implementado)
  ├── CRUD de Organizaciones (desactivación, no eliminación)
  ├── CRUD de Copropiedades (desactivación, no eliminación)
  ├── CRUD de Usuarios globales (desactivación, no eliminación)
  ├── Configuraciones automáticas
  └── Protección de último administrador
```

La autorización efectiva combina ambos niveles:

```text
Autorización efectiva
  = (es_administrador_sistema OR membresía vigente)
  + (permiso otorgado por Rol O Gate de plataforma)
  + pertenencia del recurso al ámbito
  + regla contextual del caso de uso
```

Se distinguirán Roles de plataforma, Organización y Copropiedad. Las
condiciones de Propietario o Residente pertenecerán al dominio de personas y
Unidades Privadas; solo otorgarán acceso cuando exista la membresía o regla de
autorización correspondiente.

Los permisos serán acciones estables del producto. Los Roles agruparán permisos
y podrán asignarse dentro de un ámbito. Las capacidades comerciales no
reemplazarán permisos ni concederán acceso por sí solas.

La definición definitiva de Roles, permisos y facultades es **Pendiente de
decisión del Product Owner**.

## 10. Suscripciones y capacidades

La Organización será titular de la suscripción. Los planes Resuelve Básico y
Resuelve Pro actuarán como ofertas comerciales que habilitan conjuntos de
capacidades estables, por ejemplo:

```text
pqrs.reportes_avanzados
documentos.normativos
ia.asistente
```

El modelo conceptual incluirá Suscripción, Capacidad, Oferta comercial,
relación Oferta-Capacidad y Habilitación de Capacidad por Organización. Las
excepciones o vigencias se representarán en la habilitación efectiva, sin
introducir condiciones como `plan == pro` dentro de los módulos.

Los límites cuantitativos, si se aprueban, se gestionarán separadamente de los
permisos. La asignación de capacidades a Básico y Pro, los límites, la
periodicidad, los estados de suscripción y el comportamiento ante suspensión
son **Pendiente de decisión del Product Owner**.

## 11. Aplicación web, API y móviles

Blade continuará como interfaz operativa inicial. Los controladores reducirán
gradualmente su responsabilidad a recibir la solicitud, validar su forma,
invocar un caso de uso y transformar la respuesta.

La interfaz forma parte del valor entregado por las capacidades con interacción
humana. Puede representar contexto, permisos, capacidades y validaciones para
ofrecer retroalimentación inmediata, pero no constituye un control de
seguridad. La autorización efectiva y las reglas de negocio permanecerán en el
backend, y la Presentación consumirá las consultas y casos de uso existentes.

```text
Controlador Blade ─┐
Controlador API ───┼── Casos de uso ── Dominio ── Persistencia
Job o Comando ─────┘
```

La API futura tendrá contratos versionados, autenticación apropiada para
clientes propios, recursos JSON consistentes, paginación, filtros controlados,
idempotencia donde corresponda, rate limiting y la misma autorización de los
casos de uso web.

La preparación para API no exige construir inmediatamente todos los endpoints
ni convertir la aplicación en una SPA. Las aplicaciones móviles para
Residentes y Administradores se desarrollarán después de estabilizar los casos
de uso y contratos necesarios. La prioridad y los primeros flujos móviles son
**Pendiente de decisión del Product Owner**.

## 12. Gestión Documental

Gestión Documental será una capacidad previa y obligatoria para incorporar RAG.
Un Documento conservará como mínimo:

- Organización y Copropiedad o ámbito de plataforma;
- clase documental;
- versión, vigencia y estado;
- archivo original, hash y origen;
- nivel de acceso;
- responsable de carga y aprobación;
- estado de extracción e indexación.

Los Reglamentos y Manuales de Convivencia serán propios de cada Copropiedad. La
Ley 675 de 2001 se administrará como una fuente normativa común de plataforma,
versionada y trazable, sin mezclarla con documentos particulares.

Solo las versiones aprobadas y vigentes podrán habilitarse como fuente para la
IA. El catálogo documental, niveles de acceso, flujo de aprobación, vigencias,
retención y fuente oficial de la Ley 675 son **Pendiente de decisión del
Product Owner**.

## 13. Inteligencia Artificial y RAG

La IA se integrará mediante contratos y adaptadores para evitar que el dominio
dependa de un proveedor o modelo específico. Su flujo objetivo será:

```text
Documento aprobado
  → extracción y normalización
  → fragmentación
  → generación de embeddings
  → índice con metadatos de aislamiento y versión
  → recuperación filtrada por autorización y contexto
  → generación de respuesta con citas
  → respuesta pendiente de revisión humana
  → aprobación, modificación o rechazo
```

Cada fragmento indexado conservará Organización, Copropiedad, Documento,
versión, nivel de acceso y ubicación de la fuente. Los filtros de tenant y
autorización se aplicarán antes de la búsqueda semántica.

Cada consulta registrará Usuario, contexto, propósito, modelo, configuración,
fuentes recuperadas, respuesta, tiempos y resultado de la revisión. Se
incorporarán defensas frente a instrucciones maliciosas contenidas en los
documentos y controles para evitar información de otra Copropiedad.

Toda respuesta comenzará en estado `pendiente` y no constituirá una decisión ni
actuación de negocio. Su uso exigirá una acción explícita del Administrador que
la apruebe, modifique o rechace. El detalle de tareas permitidas a la IA y el
flujo funcional de revisión son **Pendiente de decisión del Product Owner**.

## 14. Auditoría y trazabilidad

La auditoría técnica actual se complementará con eventos semánticos generados
por los casos de uso. Cada registro relevante conservará:

- Organización y Copropiedad;
- actor y tipo de actor;
- acción de negocio;
- recurso afectado;
- cambios pertinentes, excluyendo secretos;
- fecha y resultado;
- IP y agente cuando existan;
- origen web, API, job, integración o IA;
- identificador de correlación;
- fuentes y revisión humana para actuaciones asistidas por IA.

Los registros serán de solo adición desde la aplicación. La política de
retención, acceso, anonimización y exportación es **Pendiente de decisión del
Product Owner**.

## 15. Eventos, colas y procesamiento asíncrono

Los módulos emitirán eventos internos después de confirmar la transacción. Los
primeros candidatos son:

```text
PqrsRadicada
PqrsAsignada
RespuestaEnviada
DocumentoAprobado
ProcesamientoDocumentalSolicitado
RespuestaIaGenerada
RevisionHumanaRegistrada
CapacidadHabilitada
```

Notificaciones, recordatorios, reportes costosos, extracción documental,
indexación, llamadas a IA e integraciones se ejecutarán mediante jobs cuando no
se requiera respuesta inmediata. Cada job incluirá contexto, idempotencia,
reintentos controlados, registro de fallos y observabilidad.

Cuando existan efectos externos que no puedan perderse, se incorporará un
patrón outbox transaccional. No se exige antes de los primeros jobs internos,
pero sí antes de integraciones críticas que requieran garantía de entrega.

## 16. Estrategia de migración

1. Introducir límites modulares y casos de uso en flujos críticos sin cambiar
   el comportamiento visible.
2. Crear una Organización y una Copropiedad heredadas que representen la
   instancia actual.
3. Añadir claves de contexto inicialmente opcionales y poblar los datos
   existentes mediante procesos repetibles.
4. Crear membresías a partir de `users.role` y mantener temporalmente una capa
   de compatibilidad.
5. trasladar `site_settings` a Configuración de la Copropiedad heredada;
6. crear Unidades Privadas desde las combinaciones existentes de torre y unidad
   y relacionarlas con las personas;
7. contextualizar PQRS, tipos, etiquetas, plantillas, reglas, notificaciones,
   auditoría, informes y archivos;
8. verificar cobertura, referencias huérfanas y acceso por ámbito;
9. convertir claves de contexto en obligatorias e incorporar restricciones;
10. habilitar múltiples Copropiedades únicamente después de eliminar las
    consultas y rutas sin contexto;
11. retirar campos y mecanismos heredados en una fase posterior y reversible.

Los procesos de backfill se diseñarán por lotes, con métricas y capacidad de
reanudar. Cada cambio de datos deberá definir verificación y estrategia de
reversión o compensación antes de ejecutarse.

## 17. Evolución de componentes actuales

### 17.1. Conservar

- Laravel, Eloquent y Blade.
- autenticación por sesión, recuperación de contraseña y CSRF;
- núcleo funcional de PQRS;
- almacenamiento privado de adjuntos;
- scheduler, infraestructura de colas y notificaciones de Laravel;
- exportaciones existentes mientras su volumen lo permita;
- modelos y flujos existentes como punto de partida para la evolución.

### 17.2. Adaptar

- controladores hacia casos de uso;
- `PqrPolicy` hacia autorización contextual;
- consultas e informes para incluir Organización y Copropiedad;
- `SiteSetting` hacia Configuración por ámbito;
- roles de texto hacia membresías, Roles y permisos;
- tipos, etiquetas, plantillas y reglas hacia catálogos contextualizados;
- rutas y model binding para restringir recursos al contexto;
- notificaciones y trabajos programados para ejecución asíncrona contextual;
- auditoría HTTP hacia trazabilidad semántica;
- rutas de archivos y claves de caché para segmentación por tenant.

### 17.3. Reemplazar gradualmente

- `tower` y `unit` en `users` por Unidad Privada y vínculos de Persona;
- selección global mediante `SiteSetting::first()`;
- verificaciones directas de cadenas de rol;
- consultas globales de entidades operativas sin contexto;
- efectos secundarios síncronos dentro de controladores;
- adjuntos de respuesta almacenados solo como JSON por una representación
  documental uniforme;
- cascadas que puedan destruir expedientes por eliminar Usuarios o catálogos.

## 18. Hoja de ruta arquitectónica

| Fase | Objetivo y alcance | Dependencias | Riesgos | Criterio de finalización |
| --- | --- | --- | --- | --- |
| 0. Decisiones y guardrails | Aprobar ADR iniciales, ámbitos y matriz de pertenencia. | Decisiones funcionales prioritarias. | Diseñar sobre reglas ambiguas. | Límites, pertenencia y controles acordados. |
| 1. Modularización mínima | Introducir casos de uso en PQRS sin cambiar comportamiento. | Arquitectura aprobada. | Refactorización demasiado amplia. | Flujos críticos web usan casos de uso reutilizables. |
| 2. Organización y Copropiedad | Incorporar contexto y configuración contextual con tenant heredado. | Plan de migración. | Registros sin contexto. | Todo dato operativo tiene contexto verificable. |
| 3. Identidad y autorización | Incorporar membresías, Roles, permisos y cambio seguro de contexto. | Fase 2 y definición funcional de Roles. | Acceso cruzado. | Recursos resueltos únicamente dentro del ámbito autorizado. |
| 4. Operación multicopropiedad | Habilitar varias Copropiedades por Organización. | Fases 2 y 3. | Consultas globales residuales. | Operación aislada y reportes por Copropiedad y Organización. |
| 5. Suscripciones y capacidades | Implementar habilitaciones desacopladas de Básico y Pro. | Catálogo comercial aprobado. | Confundir capacidad con permiso. | Funciones condicionables consultan capacidades, no planes. |
| 6. Plataforma asíncrona | Incorporar eventos, jobs, notificaciones y auditoría semántica. | Contexto serializable. | Jobs en tenant incorrecto. | Jobs revalidan ámbito y disponen de reintentos y trazabilidad. |
| 7. Gestión Documental | Implementar documentos, versiones, acceso y almacenamiento. | Política documental aprobada. | Exposición o vigencia incorrecta. | Documentos aislados, versionados y autorizados. |
| 8. IA y RAG | Incorporar recuperación contextual, citas y revisión humana. | Fases 3, 5, 6 y 7. | Alucinación o fuga entre tenants. | Toda salida tiene fuentes, trazabilidad y revisión humana. |
| 9. API | Exponer casos de uso priorizados mediante contratos versionados. | Casos de uso y autorización estables. | Duplicación de reglas. | Web y API comparten lógica y autorización. |
| 10. Aplicaciones móviles | Crear clientes especializados según alcance aprobado. | API estable. | Expansión prematura. | Flujos móviles aprobados sin lógica de negocio duplicada. |

## 19. Riesgos arquitectónicos

- Consultas heredadas sin filtro de Organización o Copropiedad.
- Route model binding que resuelva identificadores fuera del contexto activo.
- Autorización distribuida y roles actuales insuficientes para múltiples
  ámbitos.
- Configuración, archivos, caché, scheduler, notificaciones e informes con
  supuestos globales.
- Reglas de eliminación que afecten el historial o expedientes.
- Doble representación temporal de Unidades Privadas durante la migración.
- Jobs ejecutados con contexto ausente u obsoleto.
- Índices RAG o resultados de caché que mezclen Copropiedades.
- Uso de respuestas de IA sin revisión explícita.
- Falta de políticas de vigencia, privacidad y retención documental.
- Incorporación simultánea de demasiados módulos antes de consolidar el
  aislamiento multi-tenant.
- Ausencia inicial de requisitos cuantitativos de disponibilidad, volumen,
  recuperación y rendimiento.

## 20. Decisiones pendientes

Los siguientes asuntos no forman parte de las decisiones arquitectónicas
aprobadas y se registran como **Pendiente de decisión del Product Owner**:

1. Roles definitivos y permisos por ámbito.
2. Pertenencia de un Usuario a una o varias Organizaciones.
3. Reglas para transferir una Copropiedad entre Organizaciones.
4. Responsables autorizados para crear y administrar Copropiedades.
5. Capacidades incluidas en Resuelve Básico y Resuelve Pro.
6. Límites cuantitativos, estados de suscripción y comportamiento ante
   suspensión.
7. Catálogo, niveles de acceso, vigencia y flujo de aprobación documental.
8. Fuente oficial y estrategia de actualización de la Ley 675 de 2001.
9. Acciones específicas permitidas al Agente IA.
10. Flujo funcional y efectos de aprobar, modificar o rechazar una respuesta
    de IA.
11. Retención y tratamiento de prompts, respuestas, documentos y auditoría.
12. Reglas temporales de los vínculos entre Personas y Unidades Privadas.
13. Catálogo definitivo de Tipos de PQRS y sus reglas de eliminación.
14. Prioridad y primeros contratos de API y aplicaciones móviles.
15. Integraciones externas autorizadas.
16. Alcance de los reportes consolidados entre Copropiedades.
17. Objetivos no funcionales de disponibilidad, rendimiento, volumen,
    recuperación y residencia de datos.
18. Mecanismo de bootstrap del primer Administrador del sistema en entornos
    nuevos.
19. Evolución futura hacia RBAC de plataforma para autoridad de plataforma.
20. Gestión de múltiples Organizaciones por Usuario.

## 21. ADR sugeridos

Las siguientes decisiones deberán registrarse posteriormente como ADR en el
orden requerido por la hoja de ruta. Este documento no crea esos ADR:

1. Monolito modular como estilo arquitectónico.
2. Evolución incremental del monolito actual.
3. Multi-tenancy con base de datos y esquema compartidos.
4. Contexto de Organización y Copropiedad.
5. Identidad global y membresías por ámbito.
6. RBAC contextual y autorización de recursos.
7. Capacidades desacopladas de planes comerciales.
8. Límites y dependencias entre módulos.
9. Casos de uso compartidos por web, API y jobs.
10. Estrategia de API y versionado.
11. Almacenamiento y aislamiento de archivos.
12. Gestión y versionado de Documentos Normativos.
13. IA asistiva con revisión humana obligatoria.
14. RAG y aislamiento del índice documental.
15. Auditoría semántica y trazabilidad.
16. Eventos internos y procesamiento asíncrono.
17. Adopción gradual de outbox transaccional.
18. Migración y compatibilidad de datos heredados.
19. Reportes multi-copropiedad.
20. Convenciones de lenguaje del dominio.

## 22. Criterio de evolución

Esta arquitectura deberá revisarse cuando cambien las decisiones del Product
Owner, aparezcan requisitos no funcionales verificables, se apruebe un nuevo
módulo o la evidencia operativa cuestione alguna decisión. Un cambio
arquitectónico importante deberá registrarse mediante un ADR que conserve la
trazabilidad y, cuando sustituya una decisión, mantenga el registro anterior.
