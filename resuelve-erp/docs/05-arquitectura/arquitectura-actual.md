# Arquitectura actual

## 1. Propósito

Este documento describe la arquitectura implementada de Resuelve a partir de
la estructura y las dependencias del repositorio. No constituye una decisión
arquitectónica futura ni reemplaza un ADR.

## 2. Estilo arquitectónico observado

Resuelve es una aplicación web monolítica basada en Laravel:

- un único proyecto contiene interfaz, lógica de aplicación, dominio y acceso
  a datos;
- las solicitudes de negocio se reciben por rutas web;
- los controladores coordinan validación, autorización, persistencia y efectos
  secundarios;
- Eloquent representa y persiste las entidades;
- Blade renderiza HTML en el servidor;
- JavaScript agrega interacciones de interfaz sin implementar una SPA;
- no existe una API de aplicación separada.

```text
Navegador
   │ HTTP + sesión + CSRF
   ▼
Rutas web / Middleware
   │
   ▼
Controladores ─────► Políticas y reglas de autorización
   │
   ├───────────────► Notificaciones / correo
   ├───────────────► Almacenamiento de archivos
   ▼
Modelos Eloquent
   │
   ▼
Base de datos

Controladores ─────► Vistas Blade ─────► HTML + CSS + JavaScript
Scheduler ─────────► Comando de recordatorios ─► Notificaciones
```

## 3. Capas y responsabilidades

### 3.1 Entrada HTTP

`routes/web.php` define:

- rutas públicas para autenticación y recuperación;
- rutas protegidas por `auth` para toda la operación;
- descarga autorizada de adjuntos;
- recursos y acciones específicas de PQR;
- configuración, usuarios, informes y gestión complementaria.

No existen archivos de rutas para API. `bootstrap/app.php` configura el
renderizado JSON para solicitudes cuyo path coincida con `api/*`, pero no
registra rutas de aplicación bajo ese prefijo.

### 3.2 Middleware

La aplicación configura:

- middleware estándar de los grupos `web`, `auth` y `guest`;
- confianza en todos los proxies;
- `AuditMutations` agregado al grupo `web`.

`AuditMutations` ejecuta primero la solicitud y registra después las mutaciones
HTTP exitosas realizadas por un usuario autenticado cuando existe la tabla
`audit_logs`.

### 3.3 Controladores

| Controlador | Responsabilidad principal |
| --- | --- |
| `AuthController` | Sesión y recuperación de contraseña. |
| `PqrController` | CRUD, panel, búsqueda, filtros, adjuntos y reglas al crear. |
| `PqrReplyController` | Respuestas, borradores y sus archivos. |
| `PqrInternalCommentController` | Comentarios privados. |
| `PqrQuickActionController` | Cambios rápidos de estado y responsable. |
| `NotificationController` | Consulta y lectura de notificaciones. |
| `ProfileController` | Perfil y contraseña del usuario actual. |
| `UserManagementController` | Administración de cuentas y roles. |
| `SettingsController` | Identidad de la copropiedad y logo. |
| `ReportController` | CSV, XLSX y PDF. |
| `ComplementaryController` | Carga, herramientas, etiquetas, encuesta, residentes y auditoría. |

La lógica está concentrada principalmente en controladores y modelos. No existe
una capa propia de servicios, acciones, casos de uso o repositorios.

### 3.4 Dominio y persistencia

Los modelos de `app/Models` usan Eloquent y concentran:

- asignación masiva;
- casts;
- relaciones;
- scopes y propiedades derivadas;
- capacidades auxiliares del usuario;
- valores predeterminados de configuración.

Las migraciones definen integridad referencial y las factories y el seeder
preparan datos de prueba o demostración.

Gestión Documental agrega el agregado `Documento` con `DocumentoVersion` y
`DocumentoActuacion`, consultas contextuales y casos de uso bajo
`app/Application/Documentos/`. El Sprint 12 trasladó al modelo `Documento`
la lógica de vigencia repetida en vistas mediante el método
`Documento::versionVigente()`, reduciendo duplicación en la capa de
presentación. Sus controladores web conservan validación y transporte,
mientras los casos de uso coordinan transacciones, autorización, historial
y almacenamiento privado.

C.3.5.1A agrega `PqrCommunicationOperationRepository` bajo
`app/Application/Pqrs/Idempotency/` y el ledger
`pqr_communication_operations`. El repositorio reclama claves globalmente
únicas, compara PQRS, actor, operación y huella, y devuelve resultados técnicos
tipados. La base de datos asegura la respuesta oficial única, la coherencia
borrador/enviada y referencias compuestas dentro de la misma PQRS. Esta capa de
persistencia aún no está conectada a las mutaciones idempotentes futuras de
B-2 y B-3.

C.3.5.1B-1 incorpora `VisibilidadBorradoresPqrs` como decisión única para
cargar y descargar borradores. Los controladores resuelven contexto y gestión
efectiva antes de validar; el expediente filtra relaciones antes de Blade y usa
una allowlist pública de actuaciones, de modo que los identificadores futuros
son privados por defecto. La eliminación bloqueada por evidencia se decide en
transacción y devuelve explícitamente a la edición, sin depender de FK ni del
origen HTTP.

C.3.7.1 incorpora selector y contexto activo multi-copropiedad. El contexto de
ejecución se re-resuelve en cada solicitud. Los usuarios con múltiples membresías
pueden cambiar de Copropiedad mediante una operación autenticada que valida
membresía vigente. La selección automática ocurre cuando existe una única
membresía. `SiteSetting` se conserva como fallback institucional para usuarios
sin membresía. No se crearon nuevas tablas, migraciones ni permisos.

### 3.5 Autorización

La autorización combina:

- `PqrPolicy` para operaciones principales de PQR;
- métodos del modelo `User`;
- verificaciones directas de rol en controladores;
- condiciones de visibilidad en Blade.

No existe un mecanismo central único para todas las capacidades.

C.3.7.2.1 implementa la autoridad de plataforma. La columna
`users.es_administrador_sistema` constituye la única fuente de autoridad de
plataforma. El middleware `admin.sistema` protege el área `/admin`. El Gate
`administrar-sistema` centraliza la autorización administrativa. No se utilizan
nuevos permisos basados en `users.role`.

C.3.7.2.2 implementa la gestión administrativa de membresías. El CRUD
completo de membresías de Copropiedad está disponible desde `/admin` bajo
protección del middleware `admin.sistema`. La solución reutiliza el modelo
`MembresiaCopropiedad` y la relación `roles()` con campos pivote. La
asignación y revocación de roles contextuales se gestiona desde el
administrador de membresías. Cada operación genera trazabilidad específica.
La separación entre autoridad de plataforma y autoridad contextual de
Copropiedad se conserva intacta.

C.3.7.3 implementa la consola administrativa base SaaS. El Administrador
del sistema puede administrar Organizaciones, Copropiedades y Usuarios
globales desde `/admin`. Cada entidad tiene CRUD completo con desactivación
(no eliminación), creación automática de configuraciones, vinculación de
SiteSetting y auditoría. Los usuarios desactivados suspenden sus membresías
automáticamente. El último administrador del sistema no puede ser desactivado.

Para Gestión Documental, `DocumentoPolicy`, `AutorizacionContextual` y
`ConsultaDocumentosContextuales` exigen membresía vigente, permiso, nivel de
acceso y pertenencia al contexto de Copropiedad. Un Documento o Versión externo
se oculta como `404`.

### 3.6 Presentación

Las vistas Blade se organizan por:

- autenticación;
- layout;
- PQR;
- usuarios;
- perfil;
- configuración;
- notificaciones;
- informes;
- gestión complementaria;
- administración del sistema (`/admin`);
- páginas de error 403 y 404.

`AppServiceProvider` usa un view composer global para compartir
`SiteSetting::current()` con todas las vistas. El Sprint 12 incorporó
componentes reutilizables `empty-state` y `notice`, y creó una vista
`404.blade.php` coherente con la vista 403 existente para normalizar
estados vacíos, mensajes y páginas de error.

### 3.7 Frontend

Vite compila:

- `resources/css/app.css`;
- `resources/js/app.js`.

El JavaScript nativo gestiona menú, tema, confirmaciones, campos de archivo,
previsualización del logo, contador de caracteres y selección de plantillas.
Las preferencias de tema y menú se guardan en `localStorage`. El build
frontend se mantuvo correcto durante el Sprint 12 tras la actualización
de `postcss` y `nanoid`.

### 3.8 Notificaciones

`EmitirNotificacionPqrs` centraliza los cinco eventos de PQR. Primero persiste
`PqrEventNotification` por el canal `database` y después despacha
`EnviarCorreoNotificacionPqrs`. El job reconstruye y revalida el contexto antes
de usar correo, y aplica tres intentos, backoff progresivo y unicidad contextual.
La bandeja y su contador consultan exclusivamente mediante
`ConsultaNotificacionesContextuales`.

### 3.9 Automatización programada

`EnviarRecordatoriosPqrs` procesa diariamente las PQR próximas a vencer. La
reserva de `last_reminder_at` se revalida con bloqueo dentro de una transacción
y la emisión ocurre tras el commit. Existe un job propio de correo en
`app/Jobs`; no se introdujeron Events ni Listeners.

### 3.10 Informes

`ReportController` consulta directamente modelos Eloquent y construye:

- streams CSV;
- libros XLSX mediante PhpSpreadsheet;
- PDF mediante una vista Blade y DomPDF.

No existe una capa de consulta o servicio compartido fuera del controlador.

## 4. Flujos principales

### 4.1 Solicitud web autenticada

1. El navegador envía una solicitud con sesión y token CSRF cuando corresponde.
2. El middleware web resuelve sesión, autenticación y demás funciones estándar.
3. La ruta invoca un controlador o closure.
4. El controlador valida y autoriza.
5. Eloquent consulta o modifica datos.
6. El controlador devuelve una vista, redirección, descarga o stream.
7. Para una mutación exitosa, `AuditMutations` intenta crear un registro de
   auditoría.

### 4.2 Radicación de PQR

1. El controlador valida el transporte e invoca `PresentarPqrs`.
2. El caso de uso autoriza contextualmente e inicia una transacción.
3. Crea la PQR, aplica la regla opcional y registra adjuntos y actuación.
4. Confirma la transacción.
5. `EmitirNotificacionPqrs` persiste las notificaciones de los gestores
   autorizados y luego despacha sus jobs de correo.
6. El controlador redirige al detalle.

### 4.3 Envío de respuesta

1. El controlador valida el transporte e invoca `RegistrarRespuestaPqrs`.
2. El caso de uso resuelve y autoriza la PQR contextual.
3. Dentro de una transacción almacena metadatos, crea la respuesta, actualiza
   el estado y registra la actuación.
4. Tras el commit, una respuesta enviada pasa por `EmitirNotificacionPqrs`.

### 4.4 Exportación

1. Se autoriza la consulta de PQR.
2. Se aplica el alcance visible por rol.
3. Se reproducen filtros.
4. Se cargan relaciones.
5. El formato solicitado se genera en la misma solicitud HTTP.

## 5. Persistencia y servicios de infraestructura

| Recurso | Configuración observada |
| --- | --- |
| Base de datos | SQLite por defecto en `.env.example`; MySQL 8.4 en Sail. |
| Sesión | Base de datos por defecto. |
| Caché | Base de datos por defecto. |
| Cola | Base de datos por defecto. |
| Correo | Driver `log` por defecto. |
| Archivos privados | Disco `local`. |
| Archivos públicos | Disco `public`. |
| Logs | Configuración estándar de Laravel. |

La disponibilidad efectiva depende de las variables del entorno desplegado.
Los valores sensibles del archivo `.env` no fueron inspeccionados ni se
documentan.

Las Versiones documentales usan el disco local privado: staging temporal,
validación de archivo y SHA-256, movimiento a ruta final generada por el
servidor y limpieza compensatoria. La descarga se autoriza desde backend y el
comando `documentos:diagnosticar` solo informa archivos ausentes, huérfanos,
hash alterado o metadatos inconsistentes.

## 6. Empaquetado y ejecución

### Backend

- PHP `^8.3`;
- Laravel `^13.8`;
- Composer para dependencias y scripts.

### Frontend

- Node.js/npm como herramientas de construcción;
- Vite 8;
- Tailwind CSS 4.

### Contenedores

`compose.yaml` usa Laravel Sail:

- contenedor de aplicación construido desde runtime PHP 8.5 de Sail;
- contenedor MySQL 8.4;
- volúmenes para código y datos;
- puertos configurables para HTTP, Vite y MySQL.

### Demostración

El directorio `scripts` contiene una definición `launchd` y un script para
operar un túnel temporal de Cloudflare hacia `http://localhost:80`.

Este mecanismo es auxiliar y específico de macOS; no constituye una plataforma
de despliegue productivo documentada.

## 7. Seguridad observable

- autenticación basada en sesión;
- regeneración de sesión al iniciar;
- invalidación al cerrar;
- protección CSRF en formularios Blade;
- contraseñas casteadas como `hashed`;
- recuperación mediante broker de Laravel;
- descargas verificadas contra la visibilidad de la PQR;
- validación de tipo y tamaño de archivos;
- cookies HTTP-only por defecto;
- confianza en proxies configurada con `*`.

La confianza indiscriminada en proxies y la configuración efectiva de cookies,
correo y entorno deben evaluarse según el despliegue. Este documento solo
registra la configuración actual.

## 8. Dependencias arquitectónicas

| Dependencia | Uso |
| --- | --- |
| Laravel Framework | HTTP, Eloquent, Blade, autenticación, validación, scheduler y notificaciones. |
| DomPDF para Laravel | Generación de PDF. |
| PhpSpreadsheet | Generación de XLSX. |
| Vite | Construcción de activos. |
| Tailwind CSS | Utilidades y procesamiento CSS. |
| Laravel Sail | Entorno opcional con contenedores. |
| PHPUnit | Pruebas automatizadas. |
| Guzzle | Cliente HTTP para servicios externos. |
| CommonMark | Procesamiento de Markdown. |
| Nanoid | Generación de identificadores. |
| PostCSS | Procesamiento de CSS. |

El Sprint 12 actualizó `guzzlehttp/guzzle`, `league/commonmark`, `nanoid` y `postcss`. Las auditorías de Composer y NPM arrojaron 0 vulnerabilidades.

## 9. Restricciones y hallazgos

1. La arquitectura implementa selector y contexto activo multi-copropiedad (C.3.7.1), autoridad de plataforma (C.3.7.2.1), gestión administrativa de membresías (C.3.7.2.2) y consola administrativa base SaaS (C.3.7.3). Invitaciones permanecen implementadas en rama de trabajo y pendientes de integración a main. Reportes multi-copropiedad permanecen pendientes.
2. No existe API de aplicación.
3. No existe módulo de IA.
4. La lógica de negocio y los efectos secundarios se concentran en controladores.
5. La autorización está distribuida entre política, modelo, controladores y vistas.
6. Algunos flujos con varias escrituras no utilizan transacción explícita.
7. Notificaciones y exportaciones se generan sin evidencia de procesamiento asíncrono.
8. La configuración global usa el primer registro de `site_settings`.
9. `trustProxies(at: '*')` confía en todos los proxies.
10. El archivo `plist` del túnel contiene rutas distintas de la ubicación real inspeccionada del repositorio.
11. La dependencia `guzzlehttp/guzzle` fue actualizada y validada en el Sprint 12 sin abrir nuevas vulnerabilidades.
12. La presentación depende en buena medida de controladores y plantillas Blade; componentes como `empty-state`, `notice` y `Documento::versionVigente()` han comenzado a reducir lógica repetida en la capa de interfaz.

Estos hallazgos no definen una arquitectura objetivo.

## 10. Evidencias utilizadas

### Arranque, rutas y aplicación

- `bootstrap/app.php`
- `bootstrap/providers.php`
- `routes/web.php`
- `routes/console.php`
- `app/Http/Controllers/`
- `app/Http/Middleware/AuditMutations.php`
- `app/Policies/PqrPolicy.php`
- `app/Providers/AppServiceProvider.php`
- `app/Notifications/PqrEventNotification.php`
- `app/Models/`

### Presentación

- `resources/views/`
- `resources/js/app.js`
- `resources/css/app.css`
- `vite.config.js`

### Persistencia y configuración

- `database/migrations/`
- `database/factories/`
- `database/seeders/DatabaseSeeder.php`
- `config/`
- `.env.example`

### Dependencias y operación

- `composer.json`
- `package.json`
- `compose.yaml`
- `scripts/demo-tunnel.sh`
- `scripts/com.resuelve-pqrs.demo-tunnel.plist`
- `tests/`

## Control documental

- **Versión:** v1.6
- **Fecha de creación:** 30 de julio de 2026
- **Fecha de última actualización:** 26 de agosto de 2026
- **C.3.7.3 integrado**
- **Estado técnico de referencia del Sprint 12:** 477 tests, 2114 aserciones, 0 fallos, composer audit limpio, npm audit limpio, build frontend correcto.
