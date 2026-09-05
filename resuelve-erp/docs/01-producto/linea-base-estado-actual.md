# Línea base del estado actual

## 1. Propósito

Este documento delimita el estado funcional y técnico verificable de Resuelve.
Sirve como referencia inicial para mantener sincronizados el software y su
documentación.

La línea base se construyó mediante inspección estática del árbol de trabajo
del repositorio. No certifica el estado de una base de datos desplegada ni el
resultado de ejecutar la aplicación. En Resuelve:

- Git, las migraciones y las pruebas verifican el estado técnico;
- `docs/` define diseño, comportamiento funcional y decisiones aprobadas;
- `RESUELVE_ESTADO_ACTUAL.md` registra el estado operativo vivo;
- no existe una única fuente aislada que sustituya a las demás.

## 2. Identificación del producto

- **Nombre del proyecto:** Resuelve.
- **Tipo de solución actual:** aplicación web para gestionar peticiones,
  quejas, reclamos y sugerencias.
- **Ámbito funcional observado:** operación de PQR en una copropiedad
  configurable.
- **Arquitectura observada:** aplicación monolítica Laravel con interfaz
  renderizada mediante Blade.
- **Usuarios representados en el código:** administrador, gestor, apoyo,
  auditor y residente.

Aunque el sistema permite que residentes tengan una cuenta y radiquen sus
propias solicitudes, la operación administrativa de PQR constituye el núcleo
de la implementación.

## 3. Alcance implementado hasta Sprint 10

La aplicación contiene capacidades verificables para:

1. autenticar usuarios mediante correo y contraseña;
2. recuperar y restablecer contraseñas;
3. administrar cuentas, roles y datos de unidad privada;
4. radicar, consultar, filtrar, asignar, actualizar y eliminar PQR;
5. manejar los estados `radicada`, `en_revision`, `respondida` y `cerrada`;
6. adjuntar archivos a una PQR y a sus respuestas;
7. enviar respuestas o guardarlas como borrador;
8. registrar comentarios internos y actividades de gestión;
9. recibir respuesta oficial única, inmutable y no eliminable por PQRS;
10. administrar borradores propios con ciclo seguro de creación, edición, envío
    y retiro lógico;
11. recibir notificaciones con reconciliación técnica, estados `completed`,
    `pending` y `no_recipient`, y sin promesa de entrega de correo;
12. clasificar PQR mediante tipos y etiquetas;
13. crear plantillas de respuesta y reglas de asignación automática;
14. notificar eventos por base de datos y correo;
15. emitir recordatorios diarios para solicitudes próximas a vencer;
16. recibir una valoración de satisfacción para solicitudes respondidas o
    cerradas;
17. consultar carga de trabajo y registros de auditoría;
18. configurar la identidad visible de una copropiedad;
19. consultar indicadores y exportar informes en CSV, XLSX y PDF;
20. personalizar aspectos de la interfaz, como logo, color y tema visual.

La línea base documental hasta Sprint 10 se considera completada tras el
merge de B-3 y el cierre integrado de C.3.5.1.

El Sprint 10 consolidó la experiencia de producto y la consola operativa
(C.1–C.3.6). Los bloques C.3.7.1–C.3.7.3 corresponden al Sprint 11
(Multi-copropiedad y Consola Administrativa).

## 3a. Alcance implementado en Sprint 11

El Sprint 11 habilitó la operación multi-copropiedad y la consola
administrativa SaaS:

1. selector y cambio de contexto activo por sesión (C.3.7.1);
2. autoridad de plataforma separada de roles contextuales con
   `es_administrador_sistema` (C.3.7.2.1);
3. CRUD completo de membresías de Copropiedad con estados y auditoría
   (C.3.7.2.2);
4. consola administrativa para Organizaciones, Copropiedades y Usuarios
   globales (C.3.7.3);
5. tests de integración de C.3.7.3 (S11-1);
6. sistema de invitaciones por correo, implementado y validado en rama de
   trabajo, pendiente integración a main (S11-2).

## 3b. Alcance consolidado en Sprint 12

El Sprint 12 consolidó estabilidad, salud técnica y consistencia visual
sin agregar nuevas funcionalidades:

1. corrección del fallo funcional en `UsuarioGlobalCrudTest::crear_administema`;
2. creación de vista `404.blade.php` coherente con la vista 403 existente;
3. actualización segura de dependencias vulnerables (`guzzlehttp/guzzle`,
   `league/commonmark`, `nanoid`, `postcss`);
4. auditorías Composer y NPM sin vulnerabilidades;
5. componente reutilizable `empty-state` para estados vacíos;
6. componente reutilizable `notice` para mensajes de éxito y error;
7. método `Documento::versionVigente()` para eliminar lógica Blade repetida
   de vigencia documental;
8. consistencia de páginas de error 403 y 404 verificada;
9. build frontend correcto;
10. suite automatizada sin fallos nuevos.

Estado técnico validado al cierre del alcance funcional del Sprint 12:
477 tests, 2114 aserciones, 0 fallos, 5 skipped preexistentes.

## 3c. Alcance completado en Sprint 13

El Sprint 13 simplificó la experiencia visual sin nuevas funcionalidades de
negocio:

1. navegación superior modular con menús desplegables reemplazando sidebar
   permanente;
2. Application Shell unificado entre layout principal y administrativo;
3. dashboard operativo con 4 métricas (vencidas, próximas a vencer,
   pendientes, total);
4. componentes reutilizables `x-page-heading`, `x-metric-card`, `x-badge`;
5. normalización de vistas administrativas con componentes nuevos;
6. paleta institucional fija `#1e3a5f` mediante `SiteSetting::COLOR_INSTITUCIONAL`;
7. eliminación del selector libre de color;
8. uso de `CalendarioLaboralColombia::hoy()` en semántica temporal del dashboard.

Estado técnico validado al cierre del Sprint 13:
484 tests, 479 passed, 5 skipped, 2116 aserciones, 0 fallos.

## 4. Delimitación organizacional actual

La configuración institucional se obtiene del primer registro de
`site_settings` mediante `SiteSetting::current()`. Las PQR, usuarios y demás
entidades no tienen una clave que identifique una copropiedad.

El Sprint 11 habilitó la operación multi-copropiedad, el selector de contexto activo y la consola administrativa SaaS. Por lo anterior, el alcance organizacional verificable incluye:

1. una sola identidad de usuario y un solo login;
2. selección de Copropiedad cuando un usuario tiene múltiples membresías;
3. autoridad de plataforma mediante `users.es_administrador_sistema`;
4. administración de Organizaciones, Copropiedades, Usuarios globales y Membresías desde `/admin`;
5. sistema de invitaciones por correo, implementado en rama de trabajo y pendiente de integración a main.

El alcance funcional documental en `docs` no debe presentar a Resuelve como una aplicación para una única copropiedad configurable sin matizar que el soporte multi-copropiedad ya está implementado y parcialmente integrado.

## 5. Componentes principales

| Componente | Responsabilidad observable |
| --- | --- |
| Autenticación | Inicio y cierre de sesión, recuperación y cambio de contraseña. |
| Gestión de usuarios | Creación, edición, cambio de rol y eliminación controlada. |
| Gestión de PQR | Radicación, seguimiento, búsqueda, filtros, asignación y cambio de estado. |
| Atención de PQR | Respuestas, borradores, adjuntos, comentarios internos y etiquetas. |
| Trazabilidad | Actividades propias de cada PQR y auditoría de mutaciones HTTP. |
| Automatización | Reglas aplicadas al crear PQR y recordatorio diario de vencimientos. |
| Notificaciones | Avisos persistidos en base de datos y mensajes de correo. |
| Informes | Exportaciones CSV, XLSX y PDF basadas en los filtros de PQR. |
| Configuración | Identidad institucional, plazo estándar, logo y color principal. |
| Interfaz | Vistas Blade adaptables, navegación superior modular por rol, preferencias locales de tema, componentes `empty-state`, `notice`, `x-page-heading`, `x-metric-card` y `x-badge`, y páginas de error 403 y 404 coherentes. |
| Consola administrativa | Administración de Organizaciones, Copropiedades, Usuarios globales y Membresías desde `/admin`, con autoridad de plataforma separada de roles contextuales. |
| Multi-copropiedad | Selector de contexto activo, cambio de Copropiedad por sesión y operación multi-copropiedad parcialmente integrada. |

## 6. Límites y ausencias verificadas

No se encontraron en el repositorio:

- archivo `routes/api.php` ni rutas de aplicación bajo un prefijo API;
- controladores o recursos destinados a una API;
- modelos, servicios, clientes o configuración de inteligencia artificial;
- un Agente IA;
- procesamiento de reglamentos o documentos normativos;
- integración con la Ley 675 de 2001;
- aislamiento multiarrendatario;
- eventos, listeners o jobs propios de la aplicación;
- componentes frontend basados en un framework JavaScript;
- invitaciones completamente integradas a main (S11-2 permanece en rama de trabajo).

El soporte multi-copropiedad, la consola administrativa, las membresías y el selector de contexto ya están implementados. Estas ausencias delimitan el estado actual sin constituir compromisos de implementación futura.

## 7. Persistencia y almacenamiento

El proyecto contiene migraciones para usuarios, sesiones, caché, colas, PQR y
sus entidades relacionadas. La conexión predeterminada del archivo
`.env.example` es SQLite, mientras `compose.yaml` define un servicio MySQL 8.4
para Laravel Sail.

Los adjuntos de PQR y respuestas se guardan en el disco `local`. El logo de la
copropiedad se guarda en el disco `public`. Las notificaciones se persisten en
la tabla `notifications`.

## 8. Automatizaciones actuales

Existen dos mecanismos de automatización propios:

- al crear una PQR se aplica la primera regla automática activa cuyo tipo sea
  general o coincida con el tipo de la solicitud;
- el comando `pqrs:send-reminders`, programado diariamente a las 08:00,
  notifica solicitudes abiertas que vencen entre el día actual y los tres días
  siguientes.

El envío programado depende de que el scheduler de Laravel sea ejecutado por el
entorno operativo. El repositorio define la programación, pero la inspección
estática no acredita que exista un scheduler activo en un despliegue.

## 9. Calidad y verificación existente

El repositorio contiene pruebas funcionales para autenticación, permisos,
configuración, gestión prioritaria de PQR, funciones complementarias e
informes. También contiene pruebas de ejemplo.

Al momento de la última actualización de este documento, el estado verificado de la suite es el siguiente:

- 484 tests;
- 2116 aserciones;
- 0 fallos;
- 5 skipped preexistentes;
- build frontend correcto;
- Composer audit sin vulnerabilidades;
- NPM audit sin vulnerabilidades.

Las pruebas no fueron ejecutadas durante el levantamiento inicial porque utilizan
`RefreshDatabase`, lo cual ejecuta migraciones y estaba excluido expresamente
del proceso documental. En consecuencia, esta línea base distingue entre:

- comportamiento respaldado por implementación;
- comportamiento adicionalmente expresado en pruebas;
- estado de ejecución verificado posteriormente.

## 10. Condiciones del árbol de trabajo

Durante el levantamiento se observaron cambios locales sin confirmar en
`.gitignore`, `app/Http/Controllers/AuthController.php` y `scripts/`. Esta línea
base describe el árbol de trabajo inspeccionado, no exclusivamente el último
commit de la rama.

Los cambios locales no fueron alterados por el proceso documental. La
actualización documental posterior a Sprint 10 se realizó sobre `resuelve/main`
tras el merge de B-3.

## 11. Criterio de mantenimiento

Este documento debe revisarse cuando ocurra alguno de los siguientes cambios:

- incorporación o retiro de un módulo;
- cambio de arquitectura;
- introducción de API o IA;
- modificación del esquema general de roles;
- cambio en la estrategia de persistencia o despliegue;
- creación de una decisión arquitectónica que altere el alcance vigente;
- integración a main de funcionalidades actualmente en rama, como S11-2;
- cambios de estabilización, dependencias o presentación que alteren el estado verificable del producto.

## 12. Evidencias utilizadas

### Código de aplicación

- `app/Models/`
- `app/Http/Controllers/`
- `app/Http/Middleware/AuditMutations.php`
- `app/Notifications/PqrEventNotification.php`
- `app/Policies/PqrPolicy.php`
- `app/Providers/AppServiceProvider.php`
- `bootstrap/app.php`

### Persistencia, rutas e interfaz

- `database/migrations/`
- `database/seeders/DatabaseSeeder.php`
- `routes/web.php`
- `routes/console.php`
- `resources/views/`
- `resources/js/app.js`

### Configuración y operación

- `.env.example`
- `composer.json`
- `package.json`
- `compose.yaml`
- `config/`
- `scripts/`

### Verificación declarada

- `tests/Feature/`
- `tests/Unit/`

## Control documental

- **Versión:** v1.4
- **Fecha de creación:** 30 de julio de 2026
- **Fecha de última actualización:** 30 de agosto de 2026
