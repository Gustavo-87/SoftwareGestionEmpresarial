# Sprint 1 — Organización y Copropiedad

## Control documental

- **Versión:** v1.1
- **Estado:** Completado
- **Fecha:** 31 de julio de 2026
- **Fecha de cierre:** 31 de julio de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## 1. Objetivo

Introducir la base estructural de Organización, Copropiedad y sus
configuraciones, representando la instalación actual mediante una Organización
inicial y una Copropiedad inicial, sin cambiar el comportamiento funcional ni
la experiencia visible de la aplicación.

Al finalizar el sprint, la nueva estructura estará disponible para evoluciones
posteriores, mientras `SiteSetting` continuará funcionando como mecanismo de
compatibilidad de la instalación de una sola Copropiedad.

## 2. Criterio global de aceptación

Al finalizar el Sprint 1 debe ser imposible para el Usuario distinguir
visualmente que la arquitectura interna cambió. Deben conservarse exactamente:

- autenticación;
- rutas;
- navegación;
- gestión de PQRS;
- informes;
- configuración;
- identidad visual;
- comportamiento funcional.

La única diferencia será la nueva estructura interna de Organización,
Copropiedad y configuraciones. No se añadirá selector, cambio de contexto ni
otra función visible.

## 3. Alcance

### 3.1. Incluido

1. Organización.
2. Copropiedad.
3. Configuración de Organización.
4. Configuración de Copropiedad.
5. Creación de una Organización inicial.
6. Creación de una Copropiedad inicial.
7. Copia controlada de los datos actuales de `SiteSetting`.
8. Compatibilidad temporal con la instalación de una sola Copropiedad.
9. Pruebas automatizadas y validación funcional.
10. Actualización de la documentación afectada al finalizar.

### 3.2. Fuera de alcance

- Membresías.
- Nuevos Roles y Permisos.
- Multi-copropiedad visible.
- Selector o cambio de contexto.
- Suscripciones y capacidades.
- Gestión Documental.
- Inteligencia Artificial.
- API.
- Aplicaciones móviles.
- Refactorización general de controladores.
- Eliminación de `SiteSetting`.
- Diseño o implementación del Sprint 2.

## 4. Historias de Usuario

### HU-S1-01 — Estructura organizacional inicial

Como responsable de Resuelve, quiero que la instalación actual quede
representada mediante una Organización y una Copropiedad,
para preparar la evolución SaaS sin perder información ni comportamiento.

#### Criterios de aceptación

1. Existe exactamente una Organización inicial para la instalación actual.
2. Existe exactamente una Copropiedad inicial asociada con ella.
3. La Copropiedad permite consultar su Organización.
4. La Organización permite consultar sus Copropiedades.
5. Ambas se modifican como agregados independientes.
6. Ejecutar nuevamente la creación del contexto no genera duplicados.
7. No existe selector visible de Organización o Copropiedad.
8. No se crean Membresías, Roles ni Permisos.

#### Dependencias

- Migraciones estructurales de Organización y Copropiedad.
- Modelo de datos futuro aprobado.
- Existencia o conciliación del `SiteSetting` actual.

### HU-S1-02 — Conservación de identidad y configuración

Como Administrador, quiero conservar la identidad y configuración actual de la
Copropiedad,
para continuar operando sin cambios después de introducir el nuevo modelo.

#### Criterios de aceptación

1. La información institucional de `SiteSetting` se copia a la Copropiedad
   inicial.
2. Color, logo y días de respuesta se copian a Configuración de Copropiedad.
3. No se infieren parámetros de Configuración de Organización.
4. La fila original de `site_settings` no se elimina ni sobrescribe de forma
   destructiva.
5. Las referencias a Organización y Copropiedad quedan registradas.
6. Filas adicionales o datos ambiguos se informan para conciliación manual.
7. Los valores nulos y la ruta del logo se conservan.

#### Dependencias

- HU-S1-01.
- Configuraciones estructuralmente disponibles.
- Comando de creación del contexto inicial.

### HU-S1-03 — Compatibilidad funcional

Como Usuario de Resuelve, quiero que la aplicación continúe funcionando
exactamente como antes,
para que el cambio estructural no afecte mi operación.

#### Criterios de aceptación

1. `SiteSetting::current()` continúa disponible.
2. Las vistas conservan nombre, logo, color e información institucional.
3. Los informes conservan la misma identidad de Copropiedad.
4. El formulario de PQRS conserva el plazo configurado.
5. Actualizar configuración sincroniza `SiteSetting`, Copropiedad y
   Configuración de Copropiedad dentro de una única transacción.
6. Un fallo no deja representaciones parcialmente actualizadas.
7. El logo conserva el comportamiento actual de carga y sustitución.
8. Las rutas, navegación y permisos heredados no cambian.
9. No aparece ninguna función multi-copropiedad.

#### Dependencias

- HU-S1-01 y HU-S1-02.
- Caso de uso de sincronización.
- Pruebas de regresión existentes.

### HU-S1-04 — Preparación para la evolución

Como desarrollador, quiero disponer de Organización, Copropiedad y
Configuraciones correctamente relacionadas,
para que los siguientes sprints puedan evolucionar sin volver a modificar la
estructura base.

#### Criterios de aceptación

1. Organización y Copropiedad tienen claves, relaciones e índices coherentes
   con el modelo de datos futuro.
2. Configuración de Organización pertenece a una Organización.
3. Configuración de Copropiedad referencia mediante contexto compuesto una
   Copropiedad de la Organización correcta.
4. Las relaciones uno a uno impiden configuraciones duplicadas.
5. Los modelos expresan relaciones en ambos sentidos cuando corresponde.
6. El contexto inicial puede localizarse sin seleccionar globalmente el primer
   registro de las tablas nuevas.
7. Las migraciones contienen únicamente cambios estructurales.
8. El comando Artisan concentra creación, backfill, diagnóstico y
   recuperación del contexto inicial.
9. La estructura no debe modificarse nuevamente para incorporar Membresías o
   contextualizar PQRS en sprints posteriores.

#### Dependencias

- Migraciones estructurales completadas.
- Modelos y relaciones implementados.
- Modelo de datos futuro y ADR-003 a ADR-006.
- Comando idempotente validado.

## 5. Migraciones estructurales

Las migraciones de este sprint crean o modifican únicamente estructura. No
deben insertar, copiar, transformar ni conciliar datos de negocio.

### M01 — Organización y Copropiedad

Crear:

- `organizaciones`;
- `copropiedades`.

Incluir claves primarias, FK de Copropiedad a Organización, clave candidata
`(id, organizacion_id)`, estados, desactivación e índices.

### M02 — Configuraciones

Crear:

- `configuraciones_organizacion`;
- `configuraciones_copropiedad`.

Configuración de Copropiedad incluye `organizacion_id`, `copropiedad_id`, color,
logo y días de respuesta, con FK compuesta y unicidad por Copropiedad.

Configuración de Organización se crea sin inventar parámetros funcionales.

### M03 — Puente temporal de `SiteSetting`

Añadir a `site_settings`, inicialmente nullable:

- `organizacion_id`;
- `copropiedad_id`.

Agregar índices y referencias compatibles con Organización y Copropiedad. La
nulabilidad permite instalar la estructura antes de ejecutar el comando y
detectar filas pendientes de conciliación.

No existe una migración de backfill ni una migración denominada “Backfill del
contexto inicial”.

## 6. Modelos y relaciones

### `Organizacion`

- `hasMany(Copropiedad::class)`.
- `hasOne(ConfiguracionOrganizacion::class)`.

### `Copropiedad`

- `belongsTo(Organizacion::class)`.
- `hasOne(ConfiguracionCopropiedad::class)`.

### `ConfiguracionOrganizacion`

- `belongsTo(Organizacion::class)`.

### `ConfiguracionCopropiedad`

- `belongsTo(Copropiedad::class)`.
- conserva explícitamente Organización y Copropiedad para integridad
  compuesta.

### `SiteSetting`

- mantiene su modelo actual;
- incorpora relaciones temporales con Organización y Copropiedad;
- continúa siendo la fachada consumida por las superficies actuales.

Las relaciones no convierten Organización y Copropiedad en un único agregado.

## 7. Comando Artisan para el contexto inicial

El backfill se ejecutará exclusivamente mediante un comando Artisan específico:

```text
php artisan resuelve:crear-contexto-inicial
```

El comando deberá:

1. inspeccionar la fila usada por `SiteSetting::current()`;
2. crear la Organización inicial cuando no exista;
3. crear la Copropiedad inicial cuando no exista;
4. asociar la Copropiedad con la Organización;
5. copiar la identidad institucional desde `SiteSetting` hacia Copropiedad;
6. crear o actualizar Configuración de Copropiedad;
7. registrar en `site_settings` las referencias necesarias;
8. no inferir parámetros de Configuración de Organización;
9. comprobar la coherencia de referencias ya existentes;
10. informar inconsistencias y filas adicionales para conciliación manual;
11. devolver un resultado legible con acciones realizadas, omitidas y
    problemas encontrados.

### 7.1. Idempotencia

El comando debe ser completamente idempotente:

- puede ejecutarse varias veces;
- no crea Organizaciones duplicadas;
- no crea Copropiedades duplicadas;
- no crea configuraciones duplicadas;
- reutiliza referencias registradas y válidas;
- verifica antes de actualizar;
- no sobrescribe silenciosamente datos divergentes;
- detiene o reporta ambigüedades.

### 7.2. Diagnóstico y recuperación

El mismo comando sirve para:

- completar una instalación donde las migraciones ya existen pero el contexto
  inicial no fue creado;
- reconstruir referencias faltantes cuando los datos destino siguen siendo
  inequívocos;
- detectar divergencias entre `SiteSetting`, Copropiedad y Configuración;
- informar acciones de conciliación manual;
- confirmar que la instalación está preparada antes de desplegar la adaptación
  de escritura.

El comando no elimina datos como mecanismo de recuperación.

## 8. Estrategia de backfill

El backfill es una operación de aplicación ejecutada por el comando, no una
migración.

Orden interno recomendado:

1. validar existencia y forma de las tablas;
2. obtener el `SiteSetting` actual;
3. validar referencias existentes;
4. abrir una transacción;
5. crear o localizar la Organización inicial;
6. crear o localizar la Copropiedad inicial;
7. copiar identidad institucional;
8. crear o actualizar Configuración de Copropiedad;
9. registrar referencias en `site_settings`;
10. confirmar la transacción;
11. ejecutar verificaciones posteriores;
12. emitir reporte de resultado.

El nombre funcional siempre será Organización. Un indicador técnico como
`es_instalacion_inicial` puede utilizarse internamente si resulta necesario,
pero no cambia el lenguaje del dominio ni debe mostrarse al Usuario.

## 9. Compatibilidad con `SiteSetting`

Durante este sprint, las lecturas existentes permanecen así:

```text
Vistas, informes y formularios
    └── SiteSetting::current()
```

Las escrituras se coordinan mediante un caso de uso explícito:

```text
ActualizarConfiguracionCopropiedadInicial
  ├── actualiza SiteSetting
  ├── actualiza identidad de Copropiedad
  └── actualiza Configuración de Copropiedad
```

Reglas:

- las tres escrituras de base de datos se ejecutan en una transacción;
- no se utiliza un observer con efectos secundarios ocultos;
- la Copropiedad destino se obtiene desde referencias explícitas;
- no se selecciona globalmente la primera Copropiedad;
- `SiteSetting` no se elimina;
- no se cambia todavía la fuente general de lectura;
- el seeder reutiliza el mismo servicio de aplicación o deja el contexto listo
  para ejecutar el comando, sin duplicar lógica de backfill.

## 10. Tareas técnicas

Se definen **20 tareas técnicas**, en orden de ejecución:

1. Confirmar el mapeo de campos entre `site_settings`, Copropiedad y
   Configuración de Copropiedad.
2. Definir claves, índices y reversión de las migraciones estructurales.
3. Crear la migración de `organizaciones` y `copropiedades`.
4. Crear la migración de configuraciones.
5. Crear la migración del puente temporal en `site_settings`.
6. Crear los modelos Organización y Copropiedad.
7. Crear los modelos de Configuración.
8. Implementar y verificar todas las relaciones y restricciones uno a uno.
9. Crear factories o builders mínimos para pruebas.
10. Crear el servicio de aplicación que resuelve y construye el contexto
    inicial.
11. Crear `resuelve:crear-contexto-inicial` sobre ese servicio.
12. Implementar idempotencia, transacción y reporte de diagnóstico del comando.
13. Implementar copia y conciliación de los datos actuales de `SiteSetting`.
14. Añadir relaciones y referencias temporales a `SiteSetting`.
15. Crear el caso de uso transaccional de actualización sincronizada.
16. Adaptar únicamente `SettingsController::update()` y el seeder cuando sea
    necesario para usar los servicios definidos.
17. Crear pruebas de migraciones, relaciones, restricciones y rollback.
18. Crear pruebas del comando, backfill, idempotencia, diagnóstico y
    compatibilidad funcional.
19. Ejecutar validación funcional y la suite completa.
20. Actualizar documentación y registrar evidencias del sprint.

Las tareas 6 a 12 satisfacen específicamente HU-S1-04 y dependen de las
migraciones estructurales.

## 11. Pruebas automatizadas

### 11.1. Estructura

- Se crean las cuatro tablas nuevas.
- Una Copropiedad no referencia una Organización inexistente.
- No pueden existir dos configuraciones para la misma raíz.
- Una Configuración de Copropiedad no mezcla Organización y Copropiedad.
- Las columnas puente de `site_settings` aceptan el estado previo al comando.
- Las migraciones revierten estructura sin borrar `site_settings`.

### 11.2. Comando y backfill

- Con un `SiteSetting`, crea Organización y Copropiedad iniciales.
- Copia identidad y configuración correctamente.
- Registra las referencias.
- Ejecutarlo dos o más veces no crea duplicados.
- Reutiliza correctamente un contexto inicial parcial pero coherente.
- Revierte toda su transacción si falla una escritura.
- Informa referencias cruzadas o inconsistentes.
- Informa filas adicionales.
- Conserva valores nulos y `logo_path`.
- No crea parámetros de Configuración de Organización.
- Puede ejecutarse como diagnóstico sin eliminar información.

### 11.3. Modelos

- Organización consulta Copropiedades.
- Copropiedad consulta Organización.
- Cada Configuración consulta su raíz.
- Las relaciones uno a uno respetan unicidad.
- Desactivar no elimina dependencias.

### 11.4. Compatibilidad

Ampliar `SettingsTest` para demostrar que:

- se mantiene el control de acceso actual;
- la actualización modifica `SiteSetting`;
- modifica también Copropiedad y Configuración de Copropiedad;
- las escrituras son atómicas;
- el logo permanece sincronizado;
- vistas, informes y formulario de PQRS conservan su comportamiento;
- no aparece selector de Copropiedad.

### 11.5. Regresión

Antes de cerrar el sprint deberán pasar:

- autenticación;
- configuración;
- PQRS;
- informes;
- pruebas de permisos actuales;
- suite completa.

## 12. Orden exacto de implementación

### 1. Migraciones estructurales

Implementar M01, M02 y M03. No ejecutar backfill desde migraciones.

### 2. Modelos y relaciones

Crear modelos, relaciones, casts y factories mínimos.

### 3. Comando Artisan de creación del contexto inicial

Crear el servicio y `resuelve:crear-contexto-inicial` con transacción,
idempotencia, diagnóstico y recuperación.

### 4. Backfill

Ejecutar el comando, revisar el reporte y conciliar cualquier inconsistencia.

### 5. Adaptación de `SiteSetting`

Añadir referencias, caso de uso de escritura sincronizada y adaptación puntual
de configuración y seeder.

### 6. Pruebas

Completar pruebas estructurales, unitarias, del comando, funcionales y de
regresión.

### 7. Validación funcional

Comparar antes y después autenticación, rutas, navegación, PQRS, informes,
configuración, identidad visual y permisos.

### 8. Actualización documental

Actualizar únicamente documentos afectados y registrar evidencias verificadas.

## 13. Estrategia de rollback

### 13.1. Si falla el comando

- La transacción revierte Organización, Copropiedad, Configuración y
  referencias creadas en esa ejecución.
- `SiteSetting` conserva los datos originales.
- El comando devuelve un estado fallido y un diagnóstico accionable.
- Después de corregir la causa puede ejecutarse nuevamente sin duplicar datos.
- Ningún cambio visible se activa mientras el comando no finalice y valide el
  contexto.

### 13.2. Volver al estado anterior

- Antes de adaptar escrituras, la aplicación continúa leyendo `SiteSetting`.
- La adaptación de escritura puede deshabilitarse y volver temporalmente al
  flujo anterior.
- Las referencias puente pueden permanecer nullable sin alterar la interfaz.
- Las migraciones estructurales solo se revierten si aún no existen datos
  dependientes que deban conservarse.
- No se habilita una segunda Copropiedad en este sprint, por lo que el retorno a
  lectura heredada permanece viable.

### 13.3. Prevención de duplicados

- referencias explícitas desde `site_settings`;
- restricciones únicas por configuración;
- búsqueda por claves estables dentro de la transacción;
- bloqueos transaccionales durante creación;
- comprobaciones posteriores y reejecución idempotente.

### 13.4. Datos que nunca deben eliminarse

- fila original de `site_settings`;
- identidad institucional actual;
- ruta del logo;
- Organización o Copropiedad que ya tengan dependencias;
- configuraciones conciliadas;
- cualquier dato de PQRS, Usuario, informe o auditoría.

El rollback nunca debe borrar datos de negocio para simular que el sprint no se
ejecutó.

## 14. Riesgos

### Críticos

1. **Divergencia entre `SiteSetting` y el nuevo modelo.** Mitigación: caso de
   uso transaccional y pruebas de comparación.

2. **Creación duplicada del contexto inicial.** Mitigación: restricciones,
   referencias explícitas, bloqueos e idempotencia.

3. **Copia de una fila incorrecta de `site_settings`.** Mitigación: reproducir
   la semántica actual y reportar filas adicionales.

4. **Cambio visible accidental.** Mitigación: pruebas de regresión y validación
   funcional comparativa.

### Adicionales

- inferir incorrectamente la identidad de Organización;
- eliminar un logo antes de confirmar la actualización;
- convertir el comando en lógica de migración oculta;
- agregar parámetros de Organización no aprobados;
- expandir el sprint hacia Membresías o cambio de contexto;
- dejar deuda transitoria sin dueño o criterio de retiro.

## 15. Definición de Terminado

El Sprint 1 está terminado únicamente cuando:

1. todas las pruebas pasan;
2. el sistema conserva el comportamiento funcional actual;
3. existe una Organización inicial;
4. existe una Copropiedad inicial asociada correctamente;
5. existen las configuraciones y relaciones estructurales aprobadas;
6. `SiteSetting` continúa funcionando;
7. el comando es idempotente y sirve para diagnóstico y recuperación;
8. el backfill fue ejecutado y conciliado sin duplicados;
9. no existen cambios visibles para el Usuario;
10. autenticación, rutas, navegación, PQRS, informes, configuración e identidad
    visual permanecen sin regresiones;
11. el rollback fue validado;
12. la documentación está actualizada;
13. no quedan inconsistencias de datos sin registrar;
14. no queda deuda técnica abierta correspondiente al alcance de este sprint;
15. no se implementaron Membresías ni multi-copropiedad visible.

## 16. Documentación a actualizar al finalizar la implementación

- `docs/01-producto/linea-base-estado-actual.md`.
- `docs/02-funcional/configuracion-copropiedad.md`, cuando se cree o complete.
- `docs/03-tecnica/modelo-de-dominio.md`.
- `docs/03-tecnica/modelo-de-datos.md`.
- `docs/03-tecnica/modelo-del-dominio-futuro.md`, únicamente para trazabilidad
  del estado de implementación.
- `docs/03-tecnica/modelo-de-datos-futuro.md`, únicamente para trazabilidad del
  estado de implementación.
- `docs/05-arquitectura/arquitectura-actual.md`.
- `docs/README.md`.

La arquitectura objetivo y los ADR solo cambian si aparece una nueva decisión
arquitectónica aprobada.

## 17. Propuesta de rama y commits

### Rama

```text
feat/sprint-1-organizacion-copropiedad
```

### Commits

1. `feat(tenancy): crea estructura de organización y copropiedad`
2. `feat(tenancy): agrega configuraciones por ámbito`
3. `feat(tenancy): agrega comando de contexto inicial`
4. `refactor(settings): sincroniza la configuración actual`
5. `test(tenancy): cubre comando backfill y compatibilidad`
6. `docs(architecture): documenta la base organizacional implementada`

No se mezclan estructura, backfill, compatibilidad, pruebas y documentación en
un único commit.

## 18. Resumen cuantitativo

- **Historias de Usuario:** 4.
- **Tareas técnicas:** 20.
- **Complejidad relativa:** pequeña a media; 10 puntos propuestos.
- **Riesgos críticos:** 4.
- **Funciones visibles nuevas:** ninguna.
- **Membresías:** fuera de alcance.
- **Multi-copropiedad visible:** fuera de alcance.

## 19. Cierre técnico

### 19.1. Estado y resultado

El Sprint 1 quedó **Completado** el 31 de julio de 2026. Se implementaron las
cuatro Historias de Usuario sin introducir funciones visibles ni ampliar el
alcance hacia Membresías, autorización contextual, suscripciones, API,
Inteligencia Artificial o selección de Copropiedad.

La implementación conserva `SiteSetting::current()` como fachada de lectura e
introduce Organización, Copropiedad y sus configuraciones como estructura
interna compatible. La escritura de configuración sincroniza las tres
representaciones dentro de una transacción explícita.

### 19.2. Evidencias de validación

- Suite completa: **54 pruebas, 231 aserciones, 0 fallos**.
- Sintaxis PHP: correcta en migraciones, modelos, comando, caso de uso,
  controlador y pruebas creadas o modificadas.
- `git diff --check`: sin errores.
- Migraciones M01, M02 y M03: aplicadas en el batch 8.
- Rollback y reaplicación de M01, M02 y M03: verificados sin pérdida de
  `users`, `pqrs` ni `site_settings`.
- Comando `resuelve:crear-contexto-inicial`: reejecutado sin cambios ni
  duplicados.
- Aplicación local: HTTP `302` hacia `/iniciar-sesion`, comportamiento esperado
  para una sesión invitada.
- Actualización sincronizada real: teléfono y color propagados a sus
  representaciones correspondientes y restaurados a sus valores originales.
- Carga y sustitución de logo: validada con almacenamiento aislado; el archivo
  anterior se elimina solo después de confirmar la escritura transaccional.

La revisión funcional automatizada cubrió inicio y cierre de sesión, panel y
búsqueda, listado y consulta autorizada de PQRS, creación de PQRS, adjuntos,
informes CSV/XLSX/PDF, configuración, identidad visual, permisos y auditoría.
La creación de datos funcionales se ejecutó exclusivamente sobre la base
aislada de pruebas, por lo que no dejó registros de prueba en el entorno local.

### 19.3. Estado conciliado del entorno local

| Verificación | Resultado |
| --- | --- |
| Organizaciones iniciales | 1 |
| Copropiedades iniciales | 1 |
| Configuraciones de Copropiedad | 1 |
| Configuraciones de Organización | 0, conforme a la ausencia de parámetros aprobados |
| Filas de `site_settings` | 1 |
| Usuarios conservados | 4 |
| PQRS conservadas | 15 |
| Contexto de `SiteSetting` | Organización 1, Copropiedad 1 |
| Identidad institucional | Sincronizada |
| Configuración operativa | Sincronizada |

### 19.4. Criterios de aceptación

Los criterios de HU-S1-01 a HU-S1-04 quedaron cumplidos:

1. existe un único contexto inicial relacionado y verificable;
2. el comando es transaccional, idempotente y diagnostica estados ambiguos;
3. la fila original de `site_settings` y sus datos se conservaron;
4. la identidad y configuración aprobadas fueron copiadas y conciliadas;
5. la actualización de configuración evita escrituras parciales;
6. las relaciones uno a uno, claves compuestas y eliminaciones restringidas
   están activas;
7. autenticación, PQRS, informes, configuración, rutas, vistas y permisos no
   presentan regresiones;
8. no existe selector ni comportamiento multi-copropiedad visible;
9. no se implementaron elementos declarados fuera de alcance.

### 19.5. Archivos principales implementados

- `database/migrations/2026_07_31_110000_create_organizaciones_and_copropiedades_tables.php`;
- `database/migrations/2026_07_31_110100_create_configuraciones_tables.php`;
- `database/migrations/2026_07_31_110200_add_context_to_site_settings_table.php`;
- `app/Models/Organizacion.php`;
- `app/Models/Copropiedad.php`;
- `app/Models/ConfiguracionOrganizacion.php`;
- `app/Models/ConfiguracionCopropiedad.php`;
- `app/Models/SiteSetting.php`;
- `app/Console/Commands/CrearContextoInicial.php`;
- `app/Application/Configuracion/ActualizarConfiguracionCopropiedadInicial.php`;
- `app/Http/Controllers/SettingsController.php`;
- pruebas de relaciones, comando, sincronización y regresión de configuración.

### 19.6. Riesgos residuales registrados

- `site_settings` conserva referencias nullable durante la transición; una
  instalación debe ejecutar el comando antes de actualizar configuración.
- Filas adicionales de `site_settings` requieren conciliación manual y nunca se
  migran silenciosamente.
- Las tablas operativas de PQRS todavía no están contextualizadas; no debe
  habilitarse una segunda Copropiedad.
- `SiteSetting::current()` conserva deliberadamente su semántica global para
  compatibilidad de la instalación actual.
- El filesystem no participa en la transacción SQL del logo; se utiliza
  limpieza compensatoria del archivo nuevo ante fallo y eliminación del
  anterior únicamente después de confirmar la escritura transaccional.
- La recuperación sin referencias explícitas se detiene ante identidades
  ambiguas y exige conciliación manual.

Estos riesgos no se resolvieron ampliando el Sprint 1. Deben permanecer como
restricciones de transición hasta los sprints que incorporen contexto
operativo, Membresías y aislamiento multi-copropiedad.
