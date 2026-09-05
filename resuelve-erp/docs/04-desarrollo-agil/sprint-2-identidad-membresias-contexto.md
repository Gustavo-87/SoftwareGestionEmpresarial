# Sprint 2 — Identidad, Membresías y Contexto Operativo

## Control documental

- **Versión:** v1.1
- **Estado:** Completado
- **Fecha:** 31 de julio de 2026
- **Fecha de cierre:** 31 de julio de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## 1. Objetivo

Construir la infraestructura de identidad global, Membresías por ámbito, Roles,
Permisos y contexto operativo que permitirá evolucionar la autorización en un
sprint posterior, manteniendo sin cambios el comportamiento actual de la
aplicación.

Durante todo el Sprint 2, `users.role` continuará siendo la fuente funcional
activa de autorización. El modelo contextual se poblará, sincronizará y medirá,
pero no reemplazará Policies, controladores, vistas, navegación ni métodos de
autorización existentes.

El objetivo no es activar multi-copropiedad. La aplicación continuará operando
exclusivamente sobre la Organización y Copropiedad iniciales creadas en Sprint
1, sin selector ni cambio de contexto visible.

## 2. Criterio global de aceptación

Al terminar Sprint 2 debe ser imposible para el Usuario distinguir visual o
funcionalmente el cambio interno. Se conservarán exactamente:

- autenticación;
- rutas;
- navegación;
- PQRS y su visibilidad actual;
- administración de Usuarios;
- informes;
- configuración;
- identidad visual;
- comportamiento observable de cada rol actual.

El modelo contextual se considerará preparado cuando alcance equivalencia
medible con el modelo heredado, sin convertirse todavía en la fuente principal
de autorización.

## 3. Alcance

### 3.1. Incluido

1. Membresías de Organización.
2. Membresías de Copropiedad.
3. Roles y Permisos.
4. Relaciones Rol-Permiso.
5. Asignaciones de Rol separadas por tipo de Membresía.
6. Catálogo de compatibilidad con los roles actuales.
7. Comando Artisan idempotente para poblar el modelo contextual.
8. Contexto operativo inmutable.
9. `ContextResolver` central registrado como scoped.
10. Sincronización transaccional con `users.role`.
11. Métricas de equivalencia y diagnóstico.
12. Pruebas de integridad, regresión y rollback.

### 3.2. Fuera de alcance

- Usar el modelo contextual como fuente principal de autorización.
- Reemplazar o corregir Policies.
- Refactorizar verificaciones de controladores o navegación.
- Corregir inconsistencias actuales de autorización.
- Segunda Copropiedad visible.
- Selector o cambio de contexto por interfaz.
- Contextualización completa de PQRS.
- Suscripciones y capacidades comerciales.
- Gestión Documental.
- Inteligencia Artificial.
- API.
- Aplicaciones móviles.
- Eliminación de `users.role`.
- Diseño del Sprint 3.

## 4. Historias de Usuario

### HU-S2-01 — Estructura de autorización contextual

Como desarrollador, quiero disponer de Roles y Permisos relacionados por
ámbito para representar la autorización sin depender en el futuro de cadenas
dispersas.

#### Criterios de aceptación

1. Existen `roles`, `permisos` y `rol_permiso`.
2. Rol y Permiso declaran el ámbito aplicable.
3. Las restricciones impiden relacionar ámbitos incompatibles.
4. Las claves funcionales son únicas y estables.
5. Los roles actuales se representan como catálogo de compatibilidad.
6. El catálogo reproduce el comportamiento verificable sin declararse
   definitivo.
7. Ningún Rol representa capacidades comerciales.
8. Las migraciones contienen únicamente estructura.

#### Dependencias

- ADR-006.
- Modelo de datos futuro.
- Matriz de autorización actual respaldada por código y pruebas.

### HU-S2-02 — Membresías separadas por ámbito

Como responsable de la plataforma, quiero representar separadamente la
pertenencia a Organización y Copropiedad para impedir ámbitos ambiguos.

#### Criterios de aceptación

1. Existen `membresias_organizacion` y `membresias_copropiedad`.
2. Existen `membresia_organizacion_rol` y
   `membresia_copropiedad_rol`.
3. Una Membresía de Copropiedad referencia mediante FK compuesta una
   Copropiedad de la Organización correcta.
4. Un Rol de Organización no puede asignarse a una Membresía de Copropiedad.
5. Un Rol de Copropiedad no puede asignarse a una Membresía de Organización.
6. No existen duplicados por Usuario y ámbito.
7. Membresías y asignaciones conservan estado y vigencia.
8. La eliminación física se restringe cuando existe historia.

#### Dependencias

- Sprint 1 terminado y validado.
- HU-S2-01.
- ADR-003, ADR-004 y ADR-005.

### HU-S2-03 — Identidad contextual inicial

Como Usuario actual, quiero conservar mis accesos mientras se crea la
representación contextual para continuar trabajando exactamente igual.

#### Criterios de aceptación

1. Cada Usuario con `users.role` reconocido obtiene una Membresía de la
   Copropiedad inicial.
2. Cada Membresía recibe el Rol contextual equivalente.
3. No se crean Membresías de Organización por inferencia.
4. El comando no modifica `users.role`.
5. Los roles desconocidos se reportan y no se convierten silenciosamente.
6. El proceso completo es transaccional e idempotente.
7. Ejecutarlo nuevamente no duplica catálogos, Membresías ni asignaciones.
8. Produce conteos de creados, existentes, omitidos e inconsistentes.
9. Permite diagnóstico y recuperación de ejecuciones pendientes.

#### Dependencias

- HU-S2-01 y HU-S2-02.
- `resuelve:crear-contexto-inicial` de Sprint 1 ejecutado correctamente.
- Organización y Copropiedad iniciales coherentes.

### HU-S2-04 — Contexto operativo central

Como desarrollador, quiero resolver Organización, Copropiedad, configuración,
Usuario y representación contextual desde un componente central para evitar
resoluciones inconsistentes en evoluciones posteriores.

#### Criterios de aceptación

1. Existe un `ContextResolver` registrado como scoped.
2. Produce un `ContextoOperativo` inmutable.
3. Resuelve la Organización y Copropiedad iniciales sin aceptar selección del
   Usuario.
4. Incluye un identificador de correlación.
5. Para un Usuario autenticado puede resolver Membresía de Copropiedad, Roles y
   Permisos contextuales.
6. La ausencia de Membresía se registra como diagnóstico y no concede permisos.
7. Un contexto estructuralmente inconsistente falla de forma clara.
8. Funciona en HTTP sin variables estáticas.
9. Jobs y comandos pueden solicitar contexto explícito mediante identificadores
   confiables.
10. No acepta `organizacion_id` o `copropiedad_id` enviados libremente por el
    Usuario.

#### Dependencias

- Sprint 1.
- HU-S2-02 y HU-S2-03.
- ADR-003, ADR-004, ADR-005 y ADR-008.

### HU-S2-05 — Sincronización y equivalencia sin activación

Como responsable de Resuelve, quiero mantener sincronizado el modelo contextual
con `users.role` y medir su equivalencia antes de utilizarlo para autorizar.

#### Criterios de aceptación

1. `users.role` continúa siendo la fuente funcional activa.
2. Crear un Usuario sincroniza su Membresía y Rol contextual.
3. Cambiar `users.role` sincroniza la asignación contextual.
4. La escritura se ejecuta mediante un caso de uso explícito y transaccional.
5. No se utilizan observers con efectos secundarios ocultos.
6. Se comparan Rol heredado, Rol contextual y vectores de permisos.
7. Toda divergencia queda registrada y diagnosticable.
8. No se reemplazan Policies, controladores, vistas, navegación ni métodos
   actuales de autorización.
9. El modelo contextual no se activa como fuente principal durante el sprint.
10. La experiencia y permisos observables permanecen sin cambios.

#### Dependencias

- HU-S2-03 y HU-S2-04.
- Inventario completo de escrituras de `users.role`.
- Pruebas funcionales actuales.

## 5. Decisiones técnicas

### 5.1. Fuente activa de autorización

`users.role` será la única fuente funcional activa durante Sprint 2. El modelo
contextual tendrá tres usos:

- representación paralela;
- comparación de equivalencia;
- diagnóstico y preparación de infraestructura.

No se introduce fallback desde autorización contextual porque esta aún no se
activa. Las Policies, controladores, vistas y métodos `canViewAllPqrs()`,
`canManagePqrs()` e `isAdmin()` conservan su comportamiento actual.

La activación contextual requiere una decisión y un sprint posterior.

### 5.2. Catálogo de compatibilidad

Los Roles y Permisos iniciales se derivan exclusivamente de las decisiones que
toma actualmente el código. Se identifican como catálogo de compatibilidad y no
como catálogo funcional definitivo.

Una inconsistencia entre dos caminos actuales no se normaliza. Se representa y
reporta como hallazgo para que la comparación pueda explicar la diferencia.

El catálogo definitivo de Roles y Permisos es **Pendiente de decisión del
Product Owner**.

### 5.3. Datos fuera de migraciones

Las migraciones solo crean tablas, claves, restricciones e índices. El catálogo,
Membresías y asignaciones se generan mediante un comando Artisan.

### 5.4. Membresías de Organización

La tabla y sus relaciones se implementan y prueban. El comando no crea
Membresías de Organización para Usuarios actuales, porque determinar sus
destinatarios sería una decisión funcional no aprobada.

### 5.5. Sincronización explícita

La creación o cambio de rol utilizará un caso de uso como:

```text
SincronizarIdentidadContextualUsuario
  ├── conserva o actualiza users.role según la operación solicitada
  ├── crea o localiza Membresía de Copropiedad
  ├── termina la asignación contextual anterior
  └── asigna el Rol contextual equivalente
```

La operación completa se ejecuta en una transacción. El caso de uso es invocado
desde los puntos de escritura existentes y no mediante observers.

## 6. Migraciones estructurales

### M01 — Roles y Permisos

Crear:

- `roles`;
- `permisos`;
- `rol_permiso`.

Incluir claves estables, ámbito aplicable, estado, claves candidatas, FKs
compuestas e índices. No insertar datos.

### M02 — Membresías

Crear:

- `membresias_organizacion`;
- `membresias_copropiedad`.

Incluir estado, vigencia, creador, motivo de terminación, unicidad por Usuario y
ámbito y FK compuesta entre Copropiedad y Organización.

### M03 — Asignaciones de Rol

Crear:

- `membresia_organizacion_rol`;
- `membresia_copropiedad_rol`.

Incluir contexto explícito, vigencia, estado, asignador y FKs compuestas que
impidan Roles incompatibles.

Las tres migraciones deben ser reversibles mientras no existan dependencias
posteriores. Ninguna modifica o elimina `users.role`.

## 7. Modelos y relaciones

Modelos nuevos:

1. `Rol`.
2. `Permiso`.
3. `MembresiaOrganizacion`.
4. `MembresiaCopropiedad`.

Las asignaciones se implementan mediante las relaciones many-to-many y sus
tablas pivote con estado, vigencia y actor asignador. No se añadieron modelos
Pivot porque el Sprint 2 no requiere todavía comportamiento de dominio propio
en las asignaciones.

Relaciones:

```text
Usuario 1 ─── N MembresíaOrganización
Usuario 1 ─── N MembresíaCopropiedad

Organización 1 ─── N MembresíaOrganización
Copropiedad 1 ─── N MembresíaCopropiedad

MembresíaOrganización N ─── M RolOrganización
MembresíaCopropiedad N ─── M RolCopropiedad
Rol N ─── M Permiso
```

Las asignaciones tienen modelos propios porque conservan estado, vigencia y
actor asignador. `rol_permiso` puede mantenerse como pivote sin comportamiento.

## 8. Adaptaciones de `User`

Añadir relaciones de consulta:

- `membresiasOrganizacion()`;
- `membresiasCopropiedad()`.

Añadir operaciones diagnósticas separadas de la autorización activa:

- obtener Membresía contextual de Copropiedad;
- obtener Roles contextuales;
- obtener Permisos contextuales;
- construir vector de equivalencia;
- detectar divergencia con `users.role`.

No se modificará el significado de:

- `role`;
- `canViewAllPqrs()`;
- `canManagePqrs()`;
- `isAdmin()`.

El atributo `role` permanece en validaciones, asignación masiva, factories y
seeders.

## 9. Comando de identidad contextual inicial

Comando aprobado para el sprint:

```text
php artisan resuelve:crear-identidad-contextual-inicial
```

### 9.1. Precondiciones

- Las migraciones de Sprint 2 están aplicadas.
- Existe la Organización inicial.
- Existe la Copropiedad inicial.
- Ambas están correctamente relacionadas.
- El contexto inicial de Sprint 1 pasa su diagnóstico.

Si una precondición falla, el comando no crea datos parciales y devuelve un
diagnóstico claro.

### 9.2. Responsabilidades

1. Crear o actualizar el catálogo de compatibilidad.
2. Crear o actualizar relaciones Rol-Permiso.
3. Recorrer los Usuarios actuales.
4. Reconocer los valores heredados soportados.
5. Crear Membresías de Copropiedad faltantes.
6. Asignar el Rol contextual equivalente.
7. No crear Membresías de Organización.
8. No modificar `users.role`.
9. Reportar roles desconocidos.
10. Detectar asignaciones divergentes.
11. Producir conteos de creados, existentes, omitidos e inconsistentes.

### 9.3. Transacción e idempotencia

La ejecución será transaccional. Una falla revierte los cambios producidos por
esa ejecución. El comando usa claves únicas y operaciones idempotentes, por lo
que puede ejecutarse varias veces sin duplicar:

- Roles;
- Permisos;
- relaciones Rol-Permiso;
- Membresías;
- asignaciones activas.

Después de corregir una inconsistencia, el comando puede ejecutarse nuevamente
como mecanismo de recuperación y diagnóstico.

## 10. ContextResolver

### 10.1. Contrato

El servicio central ofrecerá operaciones equivalentes a:

```text
resolveForHttp(request): ContextoOperativo
resolveExplicitly(organizacionId, copropiedadId, usuarioId?): ContextoOperativo
```

La resolución explícita está reservada para jobs, comandos, servicios internos
y pruebas. No se alimenta con parámetros libres del Usuario.

### 10.2. ContextoOperativo

Objeto inmutable que contiene:

- Usuario opcional;
- Organización;
- Copropiedad;
- Membresía de Copropiedad opcional;
- colección de Roles contextuales;
- colección de Permisos contextuales;
- identificador de correlación.

No contiene Suscripción, capacidades comerciales ni un ámbito seleccionable.

### 10.3. Resolución para invitados

Para una solicitud sin Usuario autenticado, el contexto contiene únicamente:

- Organización;
- Copropiedad;
- configuración institucional;
- identificador de correlación.

La Membresía será nula y las colecciones de Roles y Permisos estarán vacías. La
resolución de contexto no concede autorización implícita.

### 10.4. Resolución para Usuarios autenticados

1. Resolver la Organización y Copropiedad iniciales mediante referencias de
   Sprint 1.
2. Verificar que la Copropiedad pertenece a la Organización.
3. Buscar la Membresía vigente de Copropiedad.
4. Cargar asignaciones, Roles y Permisos contextuales para diagnóstico.
5. Generar identificador de correlación.
6. Construir el objeto inmutable.

La ausencia de Membresía produce un contexto sin Roles ni Permisos y una
inconsistencia diagnóstica. No cambia la autorización activa basada en
`users.role`.

Una referencia cruzada, un Rol de ámbito incompatible o un contexto estructural
inválido provoca una excepción explícita del `ContextResolver`.

### 10.5. Ciclo de vida

- registro scoped en el contenedor;
- una resolución coherente por solicitud;
- sin variables estáticas;
- sin estado compartido entre solicitudes;
- disponible en HTTP;
- contexto explícito para procesos no HTTP;
- sin selector, cabecera, ruta, sesión o subdominio elegible por el Usuario.

El `ContextResolver` no se usará todavía para tomar decisiones de autorización.

## 11. Sincronización temporal

Puntos de escritura que deben invocar el caso de uso explícito:

- creación de Usuario;
- edición de Usuario;
- cambio específico de `users.role`;
- inicialización mediante seeder cuando corresponda.

Reglas:

1. `users.role` mantiene su significado actual.
2. La Membresía corresponde siempre a la Copropiedad inicial.
3. La asignación anterior termina su vigencia; no se elimina su historia.
4. La asignación nueva corresponde al Rol de compatibilidad.
5. Todo se confirma o revierte conjuntamente.
6. Un rol desconocido rechaza la sincronización y se informa.
7. No se crea Membresía de Organización.
8. No se usan observers.

La sincronización no implica que el modelo contextual autorice la solicitud.

## 12. Métricas de equivalencia

La equivalencia se calcula sin activar el nuevo modelo como fuente principal.
Para cada Usuario con rol reconocido se construyen dos representaciones:

```text
Representación heredada
  users.role
    └── vector de decisiones efectivas actuales

Representación contextual
  MembresíaCopropiedad
    └── Rol contextual
          └── vector de Permisos contextuales
```

### 12.1. Métricas obligatorias

| Métrica | Cálculo | Criterio de cierre |
| --- | --- | --- |
| Cobertura de Usuarios reconocidos | Usuarios con Membresía / Usuarios con rol reconocido | 100 % |
| Cobertura de asignaciones | Membresías con Rol equivalente / Membresías esperadas | 100 % |
| Equivalencia de Rol | `users.role` mapeado coincide con Rol contextual | 100 % |
| Equivalencia de permisos | Vector heredado coincide con vector contextual documentado | 100 % o diferencia explicada por hallazgo registrado |
| Roles desconocidos | Usuarios con valor no mapeado | 0 para cierre o conciliación registrada |
| Asignaciones incompatibles | Roles con ámbito diferente a su Membresía | 0 |
| Referencias cruzadas | Membresías fuera de Organización/Copropiedad inicial | 0 |
| Divergencias de sincronización | escrituras con modelos distintos | 0 |
| Invitados con autorización | contextos invitados con Membresía, Rol o Permiso | 0 |
| Idempotencia | variación de conteos al reejecutar sin cambios fuente | 0 |

### 12.2. Vector heredado

El vector heredado no se deduce solamente del nombre del rol. Se construye a
partir de las decisiones efectivas en:

- `PqrPolicy`;
- métodos de `User`;
- verificaciones directas de controladores;
- restricciones de vistas;
- pruebas funcionales existentes.

Esto permite registrar contradicciones actuales sin normalizarlas.

### 12.3. Criterio de equivalencia

El sprint se considera equivalente cuando todos los Usuarios reconocidos tienen
Membresía y Rol coherentes, no existen cruces de ámbito, las divergencias están
en cero o explicadas por hallazgos actuales y la suite demuestra que
`users.role` sigue produciendo el mismo comportamiento visible.

La equivalencia no autoriza activar el modelo contextual.

## 13. Hallazgos de autorización

Sprint 2 registrará, pero no corregirá, los siguientes hallazgos actuales:

1. El rol apoyo puede gestionar, responder y comentar una PQRS sin responsable
   o asignada a sí mismo; `PqrPolicy::update()` rechaza las asignadas a otra
   persona.
2. La vista muestra controles de respuesta y gestión según
   `canManagePqrs()`, por lo que apoyo puede ver controles que el backend
   rechazará con `403` cuando la PQRS está asignada a otra persona.
3. El auditor puede consultar todas las PQRS y exportar su universo visible,
   pero no el módulo general de
   auditoría, que exige `isAdmin()`.
4. Todo Usuario autenticado puede radicar PQRS, con independencia de su rol.
5. Todo rol puede registrar una encuesta cuando es radicador y se cumplen los
   estados actuales.
6. El backend permite exportaciones a todos los Usuarios autenticados, limitado
   por su universo visible.
7. Cambiar el rol de otro administrador no protege en todos los caminos la
   existencia de al menos un administrador.
8. La autorización está distribuida entre Policy, métodos de `User`,
   controladores y vistas.

Estos comportamientos forman parte de la línea base de equivalencia. No se
interpretan como decisiones funcionales aprobadas y no deben corregirse dentro
del sprint.

## 14. Estrategia de rollback

### 14.1. Migraciones

- Revertir tablas nuevas en orden inverso mientras no existan dependencias
  posteriores.
- `users` y `users.role` permanecen intactos.
- Sprint 1 continúa funcionando.

### 14.2. Comando

- Una falla revierte la transacción de la ejecución.
- No se modifica `users.role`.
- El diagnóstico identifica el registro causante.
- Corregida la causa, el comando puede reejecutarse sin duplicados.
- No se eliminan Membresías válidas como mecanismo de recuperación.

### 14.3. Sincronización

- Si falla la representación contextual, se revierte también la creación o
  cambio de `users.role` solicitado en esa transacción.
- Los datos previos permanecen coherentes.
- Puede deshabilitarse la sincronización y volver al flujo heredado antes de
  nuevas escrituras, registrando la incidencia.

### 14.4. ContextResolver

- Al no ser fuente de autorización, puede retirarse de los consumidores de
  diagnóstico sin cambiar permisos.
- Un error no se corrige concediendo autorización implícita.
- Los datos contextuales permanecen para diagnóstico.

### 14.5. Datos que nunca deben eliminarse

- Usuarios y `users.role`;
- Organización y Copropiedad iniciales;
- información y configuraciones de Sprint 1;
- Membresías y asignaciones válidas;
- historial de vigencias;
- PQRS y demás datos operativos.

## 15. Riesgos

### Críticos

1. **Mapeo incorrecto entre `users.role` y el catálogo contextual.** Puede
   producir métricas engañosas y preparar una futura autorización incorrecta.
2. **Contexto resuelto para una Organización o Copropiedad equivocada.** Es el
   principal riesgo de aislamiento.
3. **Divergencia durante doble escritura.** Puede dejar `users.role` y la
   asignación contextual en estados distintos.
4. **Confundir equivalencia con autorización para activar el modelo.** El cierre
   del sprint no autoriza el reemplazo de `users.role`.

### Adicionales

- Crear Membresías de Organización sin decisión funcional.
- Convertir el catálogo de compatibilidad en definitivo.
- Ocultar roles desconocidos.
- Asignar permisos a invitados por resolución de contexto.
- Introducir estado estático entre solicitudes.
- Corregir incidentalmente un hallazgo actual y cambiar comportamiento.
- Expandir el sprint hacia contextualización de PQRS o selector de ámbito.

## 16. Iteraciones y tareas técnicas

El sprint se divide en cinco iteraciones con **25 tareas técnicas**.

### Iteración 1 — Migraciones estructurales

1. Congelar el inventario de tablas, claves, índices y reglas de reversión.
2. Crear M01 para Roles y Permisos.
3. Crear M02 para Membresías.
4. Crear M03 para asignaciones de Rol.
5. Verificar reversibilidad y ausencia de inserciones de datos.

**Resultado:** estructura vacía y reversible.

### Iteración 2 — Modelos, relaciones y pruebas de integridad

6. Crear modelos Rol y Permiso.
7. Crear modelos de Membresía.
8. Crear modelos de asignación con vigencia.
9. Añadir relaciones a User, Organización y Copropiedad.
10. Crear pruebas de relaciones, unicidad, ámbitos y referencias compuestas.

**Resultado:** modelo persistente navegable y protegido estructuralmente.

### Iteración 3 — Catálogo, Membresías y backfill

11. Inventariar el comportamiento efectivo de cada `users.role`.
12. Crear el servicio del catálogo de compatibilidad.
13. Crear `resuelve:crear-identidad-contextual-inicial`.
14. Implementar transacción, idempotencia, conteos y diagnóstico.
15. Ejecutar backfill controlado y conciliar roles desconocidos.

**Resultado:** Usuarios reconocidos representados contextualmente sin modificar
su rol activo.

### Iteración 4 — ContextResolver y sincronización temporal

16. Crear `ContextoOperativo` inmutable.
17. Implementar `ContextResolver` HTTP y explícito.
18. Registrar el resolver como scoped y probar el contexto de invitados.
19. Crear el caso de uso de sincronización transaccional.
20. Adaptar únicamente los puntos que escriben `users.role`, sin cambiar
    decisiones de autorización.

**Resultado:** contexto disponible y doble escritura coherente, todavía no
autoritativa.

### Iteración 5 — Comparación, regresión, documentación y cierre

21. Implementar colector y reporte de métricas de equivalencia.
22. Registrar hallazgos actuales sin corregirlos.
23. Ejecutar pruebas del comando, sincronización, resolver y rollback.
24. Ejecutar suite completa y validación funcional comparativa.
25. Actualizar documentación y evidencias de cierre.

**Resultado:** equivalencia demostrada y Sprint 2 listo para entrega, sin
activar autorización contextual.

## 17. Orden exacto de implementación

1. Migraciones estructurales.
2. Modelos y relaciones.
3. Pruebas de integridad.
4. Catálogo de compatibilidad.
5. Comando Artisan.
6. Backfill y conciliación.
7. Contexto operativo y `ContextResolver`.
8. Sincronización temporal de escrituras.
9. Métricas de equivalencia.
10. Registro de hallazgos.
11. Regresión y rollback.
12. Actualización documental.

No se activa el modelo contextual al completar esta secuencia.

## 18. Definición de Terminado

Sprint 2 está terminado únicamente cuando:

1. las migraciones son reversibles;
2. el catálogo de compatibilidad fue creado;
3. todos los Usuarios con rol reconocido tienen Membresía de Copropiedad;
4. las asignaciones contextuales son coherentes con `users.role`;
5. no se crearon Membresías de Organización por inferencia;
6. el comando es transaccional, idempotente y diagnosticable;
7. `ContextResolver` está operativo en HTTP y mediante contexto explícito;
8. los invitados no reciben Membresía, Rol, Permiso ni autorización implícita;
9. la sincronización de creación y cambio de rol es transaccional;
10. las métricas de equivalencia están completas;
11. los hallazgos actuales están documentados y no fueron corregidos;
12. el comportamiento visible permanece sin cambios;
13. la suite completa está aprobada;
14. rollback fue validado;
15. la documentación está actualizada;
16. `users.role` se conserva y mantiene su significado;
17. el nuevo modelo no está activado como fuente principal;
18. no existe selector ni segunda Copropiedad visible;
19. no se diseñó ni implementó Sprint 3;
20. no queda deuda técnica abierta correspondiente al alcance acordado.

## 19. Documentación a actualizar al finalizar la implementación

- `docs/01-producto/linea-base-estado-actual.md`.
- `docs/02-funcional/roles-y-permisos.md`.
- `docs/03-tecnica/modelo-de-dominio.md`.
- `docs/03-tecnica/modelo-de-datos.md`.
- `docs/03-tecnica/modelo-del-dominio-futuro.md`, solo para trazabilidad de
  implementación.
- `docs/03-tecnica/modelo-de-datos-futuro.md`, solo para trazabilidad de
  implementación.
- `docs/05-arquitectura/arquitectura-actual.md`.
- `docs/README.md`.

Los ADR solo cambian si aparece una nueva decisión arquitectónica aprobada.

## 20. Propuesta de rama y commits

### Rama

```text
feat/sprint-2-identidad-contextual
```

### Commits

1. `feat(auth): crea estructura contextual de roles y membresias`
2. `feat(auth): agrega modelos y relaciones por ambito`
3. `feat(auth): crea identidad contextual inicial`
4. `feat(context): incorpora context resolver scoped`
5. `refactor(auth): sincroniza el rol heredado con el contexto`
6. `test(auth): verifica equivalencia y regresion`
7. `docs(architecture): registra la infraestructura contextual`

## 21. Resumen cuantitativo

- **Historias de Usuario:** 5.
- **Iteraciones:** 5.
- **Tareas técnicas:** 25.
- **Complejidad relativa:** media-alta; 13 puntos propuestos.
- **Riesgos críticos:** 4.
- **Fuente funcional activa:** `users.role`.
- **Modelo contextual como fuente principal:** no.
- **Membresías de Organización automáticas:** no.
- **Segunda Copropiedad visible:** no.
- **Selector o cambio de contexto:** no.

## 22. Cierre técnico del Sprint 2

### 22.1. Resultado

El Sprint 2 quedó completado el 31 de julio de 2026. Se implementaron las siete
tablas de identidad contextual, sus modelos y relaciones, el catálogo de
compatibilidad, el comando idempotente de inicialización, el contexto operativo
scoped y la sincronización transaccional de creación y actualización de
Usuarios.

`users.role` continúa siendo la única fuente funcional activa de autorización.
El modelo contextual se utiliza exclusivamente como representación paralela,
diagnóstico y sincronización; no participa en Policies, rutas, vistas ni
navegación.

### 22.2. Validaciones ejecutadas

- Suite completa: 96 pruebas, 430 aserciones y 0 fallos.
- Sintaxis PHP: 21 archivos nuevos o modificados sin errores.
- Migraciones: las tres migraciones del Sprint 2 aplicadas en el lote 9.
- `git diff --check`: sin errores.
- Aplicación local: `HTTP 302` hacia `/iniciar-sesion`, comportamiento esperado.
- Comando `resuelve:crear-identidad-contextual-inicial`: reejecutado sin crear
  registros ni alterar conteos.

### 22.3. Métricas reales de equivalencia

| Métrica | Resultado |
| --- | ---: |
| Usuarios totales | 4 |
| Usuarios con rol reconocido | 4 |
| Usuarios con Membresía vigente de Copropiedad | 4 |
| Usuarios con Rol contextual equivalente | 4 |
| Roles desconocidos | 0 |
| Membresías faltantes | 0 |
| Asignaciones divergentes | 0 |
| Permisos heredados esperados | 28 |
| Permisos contextuales asignados | 28 |
| Usuarios con vector de permisos equivalente | 4 de 4 |
| Referencias cruzadas inválidas | 0 |
| Invitados con permisos contextuales | 0 |
| Membresías duplicadas | 0 |
| Asignaciones activas duplicadas | 0 |
| Membresías de Organización inferidas | 0 |
| Inconsistencias totales | 0 |

El catálogo final contiene 5 Roles, 13 Permisos y 38 relaciones Rol-Permiso.
Existen 4 Membresías de Copropiedad y 4 asignaciones activas. La reejecución del
comando informó todos estos registros como existentes y produjo variación cero.

### 22.4. Criterios de aceptación cumplidos

- Integridad estructural por ámbito y FKs compuestas.
- Catálogo de compatibilidad sin datos insertados desde migraciones.
- Backfill transaccional, idempotente y diagnóstico.
- Conservación exacta de `users.role`.
- Ausencia de Membresías de Organización inferidas.
- Contexto institucional disponible para invitados sin conceder acceso.
- Membresías, Roles y Permisos ausentes cuando el Usuario no tiene Membresía.
- Contexto HTTP inmune a parámetros libres de Organización o Copropiedad.
- Ciclo de vida scoped sin estado compartido entre solicitudes.
- Sincronización transaccional al crear Usuarios o cambiar `users.role`.
- Rollback completo ante fallos de sincronización.
- Sin cambios en rutas, vistas, navegación o Policies durante el Sprint 2.
- Sin selector ni segunda Copropiedad visible.

### 22.5. Riesgos residuales y hallazgos

- El aislamiento de PQRS y demás recursos operativos por Copropiedad sigue
  pendiente y bloquea habilitar una segunda Copropiedad.
- El contexto no debe convertirse en fuente de autorización hasta que se
  apruebe y pruebe la transición correspondiente.
- La vista decide algunos controles con métodos generales de `User`, mientras
  el backend aplica además restricciones por instancia mediante Policies; en
  particular, apoyo puede ver controles que reciben `403` sobre PQRS asignadas
  a otra persona.
- Auditor consulta todas las PQRS y exporta informes, pero el módulo de
  auditoría continúa reservado a administradores.
- Todo Usuario autenticado puede radicar PQRS y exportar informes dentro de su
  universo visible, conforme a la línea base actual.
- La eliminación de Usuarios con Membresías históricas requiere una decisión
  explícita compatible con las FKs restrictivas.
- No existe fallback permisivo cuando falta una Membresía o el contexto es
  inconsistente.

### 22.6. Archivos principales implementados

- `database/migrations/2026_07_31_120000_create_roles_and_permisos_tables.php`.
- `database/migrations/2026_07_31_120100_create_membresias_tables.php`.
- `database/migrations/2026_07_31_120200_create_membresia_rol_tables.php`.
- `app/Models/Rol.php` y `app/Models/Permiso.php`.
- `app/Models/MembresiaOrganizacion.php` y
  `app/Models/MembresiaCopropiedad.php`.
- `app/Console/Commands/CrearIdentidadContextualInicial.php`.
- `app/Application/Contexto/ContextoOperativo.php`.
- `app/Application/Contexto/ContextResolver.php`.
- `app/Application/Identidad/SincronizarIdentidadContextualUsuario.php`.
- `app/Providers/AppServiceProvider.php`.
- `app/Http/Controllers/UserManagementController.php`.
- Pruebas de relaciones, comando, resolución contextual, sincronización,
  rollback y regresión funcional.
