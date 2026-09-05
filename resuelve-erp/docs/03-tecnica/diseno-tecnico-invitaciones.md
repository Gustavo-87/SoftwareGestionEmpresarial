# Diseño técnico — Invitaciones por correo

## Control documental

- **Versión:** v1.0
- **Estado:** Aprobado e implementado (pendiente integración a main)
- **Fecha:** 24 de agosto de 2026
- **Responsable:** MAIN (coordinación), Arquitectura Resuelve (diseño)
- **Aprobación:** Product Owner
- **Sprint asociado:** Sprint 11 — Multi-copropiedad y Consola Administrativa
  (S11-2)

## 1. Decisiones aprobadas

| N.º | Decisión | Estado |
| --- | --- | --- |
| 1 | Activación mediante enlace | Aprobada |
| 2 | Usuario crea su propia contraseña | Aprobada |
| 3 | Expiración del enlace: 24 horas | Aprobada |
| 4 | Solo Administrador del sistema puede invitar | Aprobada |
| 5 | No usar contraseñas temporales | Aprobada |
| 6 | La invitación crea una cuenta pendiente que se activa al aceptar | Aprobada |

## 2. Alcance funcional

### 2.1 Flujo de invitación

```
Administrador del sistema
  │
  ├─ POST /admin/invitaciones (crear)
  │   → valida: email único, organizacion_id requerido
  │   → crea registro en invitaciones con token, expira_at = now + 24h
  │   → crea usuario con estado = 'pendiente', password = hash aleatorio
  │   → envía correo con enlace de activación
  │   → registra auditoría
  │
  ├─ GET /admin/invitaciones (listar)
  │   → muestra invitaciones de la plataforma con estado y fechas
  │
  └─ DELETE /admin/invitaciones/{id} (cancelar)
      → cambia estado a 'cancelada'
      → usuario pendiente queda inactivo

Invitado (sin sesión)
  │
  ├─ GET /invitacion/{token}
  │   → valida token: existe, no expirado, no cancelado
  │   → muestra formulario de creación de contraseña
  │
  └─ POST /invitacion/{token}/aceptar
      → valida: token válido, nombre, password (>= 8 chars), confirmación
      → activa usuario: estado = 'activo', email_verified_at = now
      → actualiza password con el hash real
      → cambia estado de invitación a 'aceptada'
      → inicia sesión automáticamente
      → redirige al dashboard
```

### 2.2 Reglas de negocio

1. **Un usuario por invitación:** cada email solo puede tener una invitación activa a la vez.
2. **Email ya registrado:** si el email ya existe en `users`, se rechaza la invitación con mensaje claro. No se actualiza el usuario existente.
2. **Expiración:** 24 horas desde la creación. El enlace expirado muestra mensaje claro.
3. **Cancelación:** el Administrador puede cancelar una invitación antes de ser aceptada.
4. **Sin contraseña temporal:** el usuario crea su contraseña al aceptar.
5. **Estado pendiente:** el usuario creado queda con `estado = 'pendiente'` hasta aceptar.
6. **Sin membresía automática:** la cuenta se crea pendiente; la membresía se asigna manualmente desde el administrador de membresías (C.3.7.2.2).
7. **Solo admins:** solo usuarios con `es_administrador_sistema = true` pueden crear invitaciones.

## 3. Modelo de datos

### 3.1 Nueva tabla: `invitaciones`

```sql
CREATE TABLE invitaciones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    organizacion_id BIGINT UNSIGNED NOT NULL,
    copropiedad_id BIGINT UNSIGNED NULL,
    rol_asignado VARCHAR(50) NOT NULL DEFAULT 'residente',
    token VARCHAR(64) NOT NULL UNIQUE,  -- hash SHA-256, nunca el token plano
    estado ENUM('pendiente', 'aceptada', 'cancelada', 'expirada') NOT NULL DEFAULT 'pendiente',
    expira_at TIMESTAMP NOT NULL,
    aceptada_at TIMESTAMP NULL,
    cancelada_at TIMESTAMP NULL,
    creada_por BIGINT UNSIGNED NULL,
    usuario_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,

    FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE RESTRICT,
    FOREIGN KEY (copropiedad_id) REFERENCES copropiedades(id) ON DELETE RESTRICT,
    FOREIGN KEY (creada_por) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE SET NULL,

    UNIQUE KEY uniq_email_pendiente (email, estado),
    INDEX idx_token (token),
    INDEX idx_estado (estado),
    INDEX idx_expira (expira_at)
);
```

### 3.2 Campos añadidos a `users`

No se añaden columnas nuevas. El campo `estado` existente soporta `'pendiente'` como valor adicional.

### 3.3 Relaciones

```
Invitacion → belongsTo Organizacion
Invitacion → belongsTo Copropiedad (nullable)
Invitacion → belongsTo User (creada_por, nullable)
Invitacion → belongsTo User (usuario_id, nullable)

User → hasOne Invitacion (usuario_id)
```

## 4. Componentes

### 4.1 Migración

```
database/migrations/YYYY_MM_DD_HHMMSS_create_invitaciones_table.php
```

### 4.2 Modelo

```
app/Models/Invitacion.php
```

Campos fillable: `email`, `organizacion_id`, `copropiedad_id`, `rol_asignado`, `token`, `estado`, `expira_at`, `aceptada_at`, `cancelada_at`, `creada_por`, `usuario_id`.

Casts: `expira_at`, `aceptada_at`, `cancelada_at` como `datetime`.

### 4.3 Casos de uso

```
app/Application/Invitaciones/CrearInvitacion.php
app/Application/Invitaciones/CancelarInvitacion.php
app/Application/Invitaciones/AceptarInvitacion.php
```

#### CrearInvitacion

- Valida email único (no existente en users ni invitación pendiente)
- Genera token aleatorio de 64 caracteres
- Almacena solo el hash SHA-256 del token en DB (como `password_reset_tokens`)
- El token plano se envía por correo, nunca se persiste
- Crea usuario con `estado = 'pendiente'`, `password = Hash::make(Str::random(32))`
- Crea registro de invitación
- Despacha job de correo
- Registra auditoría

#### CancelarInvitacion

- Cambia estado a `'cancelada'`
- Registra `cancelada_at`
- Opcional: marca usuario pendiente como inactivo

#### AceptarInvitacion

- Valida token, expiración, estado
- Valida nombre, password (>= 8 chars), confirmación
- Actualiza usuario: nombre, password real, `estado = 'activo'`, `email_verified_at = now`
- Actualiza invitación: estado `'aceptada'`, `aceptada_at = now`
- **NO crea membresía automáticamente** (gestión manual desde admin)
- Inicia sesión automáticamente
- Registra auditoría

### 4.4 Controladores

```
app/Http/Controllers/Admin/InvitacionController.php
app/Http/Controllers/InvitacionPublicaController.php
```

#### InvitacionController (protegido por `admin.sistema`)

- `index` — listado de invitaciones
- `store` — crear invitación
- `destroy` — cancelar invitación

#### InvitacionPublicaController (rutas públicas)

- `show($token)` — formulario de aceptación
- `aceptar($token)` — procesar aceptación

### 4.5 Notificación por correo

```
app/Notifications/InvitacionNotificacion.php
```

Extiende `Illuminate\Notifications\Notification` y usa `Mail` channel.

Template Blade:
```
resources/views/emails/invitacion.blade.php
```

Contenido:
- Nombre de la organización
- Enlace de activación con token
- Texto: "Ha sido invitado a Resuelve. Cree su contraseña para activar su cuenta."
- Expiración: 24 horas

### 4.6 Rutas

```php
// Rutas protegidas (admin.sistema)
Route::prefix('admin')->middleware(['auth', 'admin.sistema'])->group(function () {
    Route::resource('invitaciones', InvitacionController::class)->only(['index', 'store', 'destroy']);
});

// Rutas públicas
Route::get('/invitacion/{token}', [InvitacionPublicaController::class, 'show'])->name('invitacion.show');
Route::post('/invitacion/{token}/aceptar', [InvitacionPublicaController::class, 'aceptar'])->name('invitacion.aceptar');
```

### 4.7 Vistas Blade

```
resources/views/admin/invitaciones/index.blade.php
resources/views/admin/invitaciones/create.blade.php
resources/views/invitacion/aceptar.blade.php
resources/views/emails/invitacion.blade.php
```

### 4.8 Middleware

Se reutiliza el middleware `admin.sistema` existente. No se requiere middleware nuevo.

### 4.9 Auditoría

Cada operación registra en `audit_logs`:
- crear invitación
- cancelar invitación
- aceptar invitación

## 5. Seguridad

1. **Token seguro:** 64 caracteres aleatorios; solo se almacena el hash SHA-256 en DB (patrón `password_reset_tokens`). El token plano solo aparece en el correo.
2. **Expiración:** 24 horas, verificada en cada acceso al enlace.
3. **Un solo uso:** al aceptar, el token se marca como aceptado.
4. **Sin contraseñas temporales:** el usuario define su propia contraseña.
5. **CSRF:** protección estándar de Laravel en formularios.
6. **Rate limiting:** se recomienda limitar intentos de aceptación por IP.
7. **Email único:** validación a nivel de aplicación y base de datos.

## 6. Tests

### 6.1 Tests de integración (crear)

| Test | Escenario |
| --- | --- |
| crear_invitacion_exitosa | Admin crea invitación válida |
| crear_invitacion_email_duplicado_falla | Email ya tiene invitación pendiente |
| crear_invitacion_email_existe_en_users_falla | Email ya está registrado |
| crear_invitacion_no_admin_falla | Usuario sin es_administrador_sistema no puede invitar |
| crear_invitacion_genera_usuario_pendiente | Usuario creado con estado pendiente |
| crear_invitacion_genera_token_unico | Token de 64 caracteres |
| crear_invitacion_expira_en_24_horas | expira_at = now + 24h |
| cancelar_invitacion_exitosa | Estado cambia a cancelada |
| cancelar_invitacion_aceptada_falla | No se puede cancelar una aceptada |
| aceptar_invitacion_exitosa | Usuario activado, sesión iniciada |
| aceptar_invitacion_token_expirado_falla | Token vencido rechazado |
| aceptar_invitacion_token_cancelado_falla | Token cancelado rechazado |
| aceptar_invitacion_password_corto_falla | Password < 8 caracteres rechazado |
| aceptar_invitacion_no_crea_membresia | No se crea membresía al aceptar |
| listar_invitaciones | Admin ve listado |
| usuario_no_admin_no_puede_crear | Restricción verificada |

## 7. Orden de implementación

1. Migración `invitaciones`
2. Modelo `Invitacion`
3. Caso de uso `CrearInvitacion`
4. Caso de uso `CancelarInvitacion`
5. Caso de uso `AceptarInvitacion`
6. Controlador `InvitacionController`
7. Controlador `InvitacionPublicaController`
8. Notificación `InvitacionNotificacion`
9. Template de correo
10. Rutas
11. Vistas Blade
12. Tests de integración
13. Suite completa
14. Documentación

## 8. Exclusiones

- Gestión de roles en la invitación (se asigna un rol predefinido)
- Selección de Copropiedad en la invitación (opcional, se define en una fase posterior)
- Invitación masiva
- Reenvío de invitación
- Plantilla de correo personalizada (se usa diseño simple)

## 9. Riesgos

1. **Email delivery:** si el correo no se envía, el usuario no recibe el enlace. Mitigación: job con reintentos.
2. **Tokens expirados acumulados:** limpieza periódica recomendada.
3. **CSRF en rutas públicas:** protegido por middleware web estándar.
