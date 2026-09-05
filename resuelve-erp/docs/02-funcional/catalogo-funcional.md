# Catálogo funcional

## 1. Propósito

Este documento inventaría las capacidades funcionales implementadas en
Resuelve y las agrupa por módulo. No describe funciones proyectadas.

## 2. Actores representados

| Actor | Descripción verificable |
| --- | --- |
| Administrador | Usuario con acceso a la gestión de PQR y a las funciones administrativas. |
| Gestor | Usuario que consulta y gestiona PQR, configuración y herramientas operativas. |
| Apoyo | Usuario operativo con consulta general y actualización condicionada de PQR. |
| Auditor | Usuario con consulta general de PQR y sin permiso de actualización. |
| Residente | Usuario que radica solicitudes y consulta las PQR asociadas a su cuenta. |
| Sistema | Aplicación que aplica reglas, registra auditoría y genera notificaciones y recordatorios. |

Los nombres anteriores corresponden a los valores del atributo `role` del
modelo `User`, salvo “Sistema”, que representa acciones automatizadas.

## 3. Catálogo de módulos

### 3.1 Autenticación

| Capacidad | Comportamiento implementado |
| --- | --- |
| Iniciar sesión | Valida correo y contraseña, permite recordar la sesión y regenera la sesión al autenticar. |
| Cerrar sesión | Cierra la sesión, la invalida y regenera el token CSRF. |
| Solicitar recuperación | Envía el enlace mediante el broker de contraseñas de Laravel. |
| Restablecer contraseña | Valida token, correo, confirmación y una longitud mínima de ocho caracteres. |

No existe registro público de cuentas.

### 3.2 Perfil

El usuario autenticado puede modificar:

- nombre;
- correo electrónico;
- contraseña, suministrando la contraseña actual.

El correo debe ser único.

### 3.3 Administración de usuarios

El administrador puede:

- consultar usuarios paginados;
- crear cuentas;
- asignar uno de los cinco roles existentes;
- registrar torre y unidad;
- editar cuentas;
- cambiar roles;
- eliminar cuentas bajo restricciones de integridad.

Las restricciones verificables impiden eliminar la propia cuenta, eliminar al
último administrador o eliminar un usuario que tenga PQR asociadas.

### 3.4 Radicación de PQR

Un usuario autenticado puede crear una PQR indicando:

- asunto;
- descripción;
- tipo;
- fecha de radicación;
- fecha límite opcional;
- hasta ocho adjuntos permitidos.

La PQR queda asociada al usuario autenticado. Después de crearla, el sistema
puede aplicar una regla automática, guarda los adjuntos, registra una actividad
y notifica a administradores y gestores.

### 3.5 Consulta y seguimiento

El panel permite:

- listar PQR con paginación;
- consultar totales, pendientes, respondidas y próximas a vencer;
- visualizar tendencias de los últimos seis meses;
- visualizar distribución por estado;
- buscar por asunto, descripción, identificador, residente, correo o
  responsable;
- filtrar por estado, tipo, responsable y rango de fechas.

Los residentes solo reciben registros propios en la consulta. Los demás roles
con `canViewAllPqrs()` reciben el conjunto general.

### 3.6 Gestión de PQR

Los usuarios autorizados pueden:

- editar los datos principales;
- cambiar el estado;
- asignar un responsable;
- agregar adjuntos;
- eliminar la PQR;
- aplicar acciones rápidas de estado y responsable;
- asignar etiquetas.

Los cambios de estado y responsable generan actividades. Algunos cambios
también producen notificaciones.

### 3.7 Atención y conversación

El personal que cumple `canManagePqrs()` puede:

- guardar borradores de respuesta;
- enviar respuestas;
- adjuntar hasta cinco archivos por respuesta;
- consultar borradores;
- agregar comentarios internos;
- usar el contenido de una plantilla desde la interfaz.

Al enviar una respuesta, la PQR cambia a `respondida` y el residente radicador
es notificado. Los residentes no ven borradores ni comentarios internos.

### 3.8 Archivos

La aplicación ofrece:

- carga de adjuntos durante creación y actualización de PQR;
- descarga autorizada de adjuntos principales;
- carga y descarga de adjuntos de respuesta;
- carga de logo institucional.

Los adjuntos de negocio utilizan almacenamiento privado local. El logo utiliza
almacenamiento público.

### 3.9 Notificaciones

Los Usuarios con Membresía vigente y `notificaciones.consultar` pueden, dentro
de la Copropiedad activa:

- consultar sus notificaciones paginadas;
- consultar el contador de no leídas;
- abrir una notificación propia y marcarla como leída solo cuando el recurso
  continúa autorizado;
- marcar como leídas únicamente sus notificaciones del contexto activo.

Los cinco hechos comunicados son: creación, cambio de estado, asignación,
respuesta y recordatorio de vencimiento de una PQR. La entrega usa `database`
y correo; no persiste URL y resuelve el destino internamente al abrir. Los
recordatorios conservan una emisión diaria por PQR.

### 3.10 Herramientas operativas

Administradores y gestores pueden crear:

- plantillas de respuesta;
- etiquetas con nombre y color;
- reglas automáticas con tipo opcional, responsable opcional y estado inicial.

La interfaz actual permite crear y consultar estas herramientas. No se
encontraron acciones para editarlas, eliminarlas o desactivarlas.

### 3.11 Carga de trabajo

Administradores y gestores pueden consultar por integrante operativo:

- PQR activas;
- PQR vencidas;
- PQR completadas;
- representación visual de la carga activa.

Se incluyen usuarios con rol administrador, gestor o apoyo.

### 3.12 Residentes y unidades

El administrador puede consultar las cuentas con rol residente y actualizar
sus atributos `tower` y `unit`, mostrados como torre y apartamento o unidad en
la interfaz.

### 3.13 Encuesta de satisfacción

El usuario que radicó una PQR respondida o cerrada puede registrar:

- calificación entera de 1 a 5;
- comentario opcional.

Existe una sola encuesta por PQR. El controlador admite actualizarla, aunque la
vista presenta el resultado existente sin ofrecer un formulario de edición.

### 3.14 Configuración de la copropiedad

Administradores y gestores pueden configurar:

- nombre;
- NIT;
- representante legal;
- dirección;
- ciudad;
- teléfono;
- correo institucional;
- color principal;
- plazo estándar de respuesta;
- logo.

El plazo se usa para proponer la fecha límite en el formulario de radicación;
no se asigna en el modelo o controlador cuando la fecha se omite.

### 3.15 Informes

El sistema exporta los registros visibles para el usuario en:

- CSV;
- XLSX;
- PDF.

Los filtros del panel se reutilizan en las exportaciones. XLSX y PDF incluyen
resumen, distribución, vencimientos y datos de la copropiedad con diferencias
propias de cada formato.

### 3.16 Auditoría y actividades

Existen dos niveles:

- actividades específicas de cada PQR, visibles en su detalle;
- auditoría general de solicitudes HTTP mutantes exitosas, visible para el
  administrador.

La auditoría general registra usuario, método y nombre de ruta, referencia
opcional a PQR, dirección IP y ruta solicitada.

### 3.17 Recordatorios

El comando `pqrs:send-reminders` selecciona PQR `radicada` o `en_revision` cuya
fecha límite esté entre hoy y tres días después. Notifica al radicador y al
responsable, si existen, y actualiza `last_reminder_at`.

La programación declarada es diaria a las 08:00.

### 3.18 Interfaz

La interfaz implementa:

- diseño adaptable;
- navegación condicionada por rol;
- tema claro y oscuro persistido en `localStorage`;
- menú móvil;
- cuadros de confirmación para acciones sensibles;
- previsualización del logo;
- selección de plantillas de respuesta;
- contador de caracteres y listado previo de adjuntos.

## 4. Funciones no encontradas

No forman parte del catálogo actual:

- administración de varias copropiedades;
- Agente IA;
- documentos normativos;
- API de aplicación;
- registro público de usuarios;
- verificación obligatoria de correo;
- edición o eliminación de tipos de PQR desde la interfaz;
- edición o eliminación de plantillas, etiquetas y reglas;
- gestión individual de borradores después de crearlos.

## 5. Evidencias utilizadas

### Modelos y autorización

- `app/Models/User.php`
- `app/Models/Pqr.php`
- `app/Models/TipoPqr.php`
- `app/Models/PqrAttachment.php`
- `app/Models/PqrActivity.php`
- `app/Models/PqrReply.php`
- `app/Models/PqrInternalComment.php`
- `app/Models/PqrTag.php`
- `app/Models/ResponseTemplate.php`
- `app/Models/AutomationRule.php`
- `app/Models/SatisfactionSurvey.php`
- `app/Models/AuditLog.php`
- `app/Models/SiteSetting.php`
- `app/Policies/PqrPolicy.php`

### Controladores, rutas y automatizaciones

- `app/Http/Controllers/`
- `app/Http/Middleware/AuditMutations.php`
- `app/Notifications/PqrEventNotification.php`
- `routes/web.php`
- `routes/console.php`

### Interfaz y verificación

- `resources/views/`
- `resources/js/app.js`
- `tests/Feature/`

## Control documental

- **Versión:** v1.0
- **Fecha de creación:** 30 de julio de 2026
- **Fecha de última actualización:** 30 de julio de 2026
