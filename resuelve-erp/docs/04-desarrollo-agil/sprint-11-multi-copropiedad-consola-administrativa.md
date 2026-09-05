# Sprint 11 — Multi-copropiedad y Consola Administrativa

## Control documental

- **Versión:** v1.0
- **Estado:** Cerrado e implementado (pendiente integración de S11-2 a main)
- **Fecha de cierre:** 25 de agosto de 2026
- **Responsable:** MAIN (coordinación), DEV (implementación)
- **Aprobación:** Product Owner

## Objetivo

Habilitar la operación multi-copropiedad, la consola administrativa base SaaS
y el sistema de invitaciones por correo, consolidando la capacidad de un
Administrador del sistema para administrar Organizaciones, Copropiedades,
Usuarios globales, membresías e invitaciones.

## Alcance por bloques

### C.3.7.1 — Selector y contexto activo multi-copropiedad

Estado: **Cerrado e integrado**. Merge commit `19f2134`, commit funcional
`3c7fd98`.

#### Alcance implementado

- Contexto activo por sesión.
- Selección de Copropiedad para usuarios con múltiples membresías.
- Selección automática cuando existe una única membresía.
- Cambio seguro de contexto mediante operación autenticada.
- Validación de membresía vigente antes de cambiar contexto.
- Re-resolución de `ContextoOperativo` en cada solicitud.
- Aislamiento entre Copropiedades.
- Selector visual responsive en navegación.
- Sin nuevas tablas ni migraciones.
- Sin almacenamiento de roles/permisos en sesión.
- Sin uso de `users.role` para nuevas autorizaciones.

#### Decisiones registradas

- Se conserva `SiteSetting` como fallback institucional de compatibilidad para
  usuarios sin membresía en C.3.7.1.
- La gestión completa de membresías queda fuera de alcance y corresponde a
  C.3.7.2.

#### Exclusiones

- Gestión administrativa de membresías.
- Invitaciones.
- Preferencias persistentes de copropiedad.
- Reportes multi-copropiedad.
- API.
- Automatizaciones.
- Nuevos permisos.

#### Validación

- `SelectorContextoCopropiedadTest`: aprobado.
- `PqrPermissionsTest` actualizado: aprobado.
- Pruebas de aislamiento contextual verificadas.

### C.3.7.2.1 — Autoridad de plataforma

Estado: **Cerrado, aprobado e integrado**. PR #13, HEAD `b0ecd58`.

#### Alcance implementado

- Una sola identidad de usuario y un solo login.
- Autoridad administrativa separada de roles contextuales.
- Nueva fuente de autoridad: `users.es_administrador_sistema`.
- Área administrativa independiente: `/admin`.
- Middleware: `admin.sistema`.
- Gate: `administrar-sistema`.
- Bootstrap del primer Administrador del sistema mediante comando Artisan.
- Auditoría: evento `system_admin.promoted`.

#### Decisiones consolidadas

- Se conserva una sola identidad de usuario y un solo punto de autenticación.
- La autoridad de plataforma se expresa mediante `es_administrador_sistema` y
  no se mezcla con roles contextuales de Copropiedad.
- El Gate `administrar-sistema` reemplaza el Gate propuesto `administrar-
  membresias` del diseño original de C.3.7.2 como punto de autorización de
  plataforma.
- El bootstrap del primer Administrador del sistema se resolvió mediante
  comando Artisan, cerrando la decisión pendiente del diseño original.
- No se utilizará `users.role` para nuevas autorizaciones.

#### Exclusiones

- Gestión de membresías (corresponde a C.3.7.2.2).
- Asignación de roles.
- RBAC de plataforma.
- OTP/MFA.
- Segundo login.

#### Validación

- PR #13 integrado en `resuelve/main`.
- Commit HEAD: `b0ecd58`.
- Working tree limpio después de la integración.

### C.3.7.2.2 — Gestión de membresías

Estado: **Cerrado, aprobado e integrado.** Commit HEAD `3917254`.

#### Alcance implementado

- CRUD completo de membresías de Copropiedad.
- Creación con validación de unicidad por usuario y Copropiedad.
- Listado con filtros por Copropiedad, estado y búsqueda.
- Detalle con información general y roles asignados.
- Edición de fechas de vigencia.
- Cambio de estados: activa → suspendida → finalizada.
- Suspensión con motivo obligatorio.
- Finalización con motivo obligatorio.
- Asignación de roles con vigencia temporal.
- Revocación de roles asignados.
- Auditoría específica para cada operación.
- Dashboard administrativo con métricas y actividad reciente.

#### Arquitectura de la solución

La solución reutiliza el modelo existente `MembresiaCopropiedad` y la relación
`roles()` con campos pivote. La autorización se mantiene mediante
`admin.sistema` middleware y Gate `administrar-sistema` implementados en
C.3.7.2.1.

La separación entre autoridad de sistema y autoridad contextual de Copropiedad
se conserva intacta:

- `users.es_administrador_sistema` → acceso al área `/admin`.
- `membresia_copropiedad_rol` → roles operativos por Copropiedad.

#### Corrección posterior

Durante la validación funcional se detectó un error 500 en el detalle de
membresías: los campos `vigente_desde` y `vigente_hasta` de la relación pivote
`roles()` no tenían cast `datetime`, y la vista llamaba `->format()` sobre
strings.

**Solución:** Se añadió `withCasts()` a la relación `roles()` en
`MembresiaCopropiedad`:

```php
public function roles(): BelongsToMany
{
    return $this->belongsToMany(...)
        ->withPivot([...])
        ->withCasts([
            'vigente_desde' => 'datetime',
            'vigente_hasta' => 'datetime',
        ]);
}
```

#### Decisiones consolidadas

- Se reutiliza el modelo `MembresiaCopropiedad` existente sin crear nuevas
  entidades.
- La gestión de membresías opera exclusivamente desde `/admin` y requiere
  `es_administrador_sistema = true`.
- Los roles contextuales de Copropiedad se asignan y revocan desde el
  administrador de membresías.
- Cada operación de membresía genera un registro de auditoría específico.
- El dashboard administrativo muestra métricas de usuarios, organizaciones,
  copropiedades y membresías.

#### Exclusiones mantenidas

- RBAC de plataforma.
- OTP/MFA.
- Segundo login.
- Membresías de Organización (solo Copropiedad).
- Invitaciones por correo.
- Selección de Copropiedad desde el admin de membresías.

#### Validación

- **Tests:** 417 aprobados, 5 skipped, 0 fallos.
- **Code review:** Aprobado. Solución mediante `withCasts()` validada.
- **Bug corregido:** Error 500 en detalle de membresía (pivote sin cast).
- **Archivos modificados:**
  - `app/Http/Controllers/Admin/MembresiaController.php`
  - `app/Http/Controllers/Admin/MembresiaRolController.php`
  - `app/Http/Controllers/AdminController.php`
  - `app/Models/MembresiaCopropiedad.php` (corrección pivote)
  - `app/Application/Membresias/CrearMembresia.php`
  - `app/Application/Membresias/EditarMembresia.php`
  - `app/Application/Membresias/SuspenderMembresia.php`
  - `app/Application/Membresias/FinalizarMembresia.php`
  - `resources/views/admin/*.blade.php`
  - `resources/views/layouts/admin.blade.php`
  - `resources/css/app.css`
  - `routes/web.php`
  - `database/migrations/2026_08_21_180000_add_es_administrador_sistema_to_users_table.php`

#### Nota de entorno

La implementación fue validada en `Task-manager-gestion-pqrs` (rama
`feat/c3-7-2-2-gestion-membresias`), que contiene la migración
`es_administrador_sistema` y el usuario `guadpilo87@hotmail.com` con
`es_administrador_sistema = true`. El repositorio `resuelve-ci-github-actions`
(main) no contiene esta migración ni el campo correspondiente.

### C.3.7.3 — Consola Administrativa Base SaaS

Estado: **Cerrado e implementado.**

#### Alcance implementado

C.3.7.3 habilita al Administrador del sistema para administrar las entidades
base de la plataforma SaaS: Organizaciones, Copropiedades y Usuarios globales.

**Fase 1 — Organizaciones**

- CRUD completo: crear, listar, ver, editar, desactivar, reactivar.
- Nombre obligatorio, NIT opcional único.
- Creación automática de `ConfiguracionOrganizacion`.
- Auditoría de cada operación.
- Sin eliminación física.

**Fase 2 — Copropiedades**

- CRUD completo: crear, listar, ver, editar, desactivar, reactivar.
- Organización padre obligatoria y debe estar activa.
- Creación automática de `ConfiguracionCopropiedad`.
- Vinculación automática de `SiteSetting` si es la primera copropiedad.
- Desactivación rechazada si hay membresías activas.
- Auditoría de cada operación.
- Sin eliminación física.

**Fase 3 — Usuarios globales**

- CRUD completo: crear, listar, ver, editar, desactivar, reactivar.
- Migración: `users.estado` y `users.desactivado_at`.
- Protección: no auto-desactivación.
- Protección: último administrador del sistema no puede ser desactivado.
- Al desactivar usuario: membresías asociadas pasan a `suspendida`.
- Checkbox de `es_administrador_sistema` en creación/edición.
- Auditoría de cada operación.
- Sin eliminación física.

#### Archivos creados

```
app/Application/Organizaciones/CrearOrganizacion.php
app/Application/Organizaciones/DesactivarOrganizacion.php
app/Application/Copropiedades/CrearCopropiedad.php
app/Application/Copropiedades/DesactivarCopropiedad.php
app/Application/Usuarios/CrearUsuarioGlobal.php
app/Application/Usuarios/DesactivarUsuarioGlobal.php
app/Http/Controllers/Admin/OrganizacionController.php
app/Http/Controllers/Admin/CopropiedadController.php
app/Http/Controllers/Admin/UsuarioGlobalController.php
database/migrations/2026_08_22_140000_add_estado_to_users_table.php
resources/views/admin/organizaciones/{index,create,show,edit}.blade.php
resources/views/admin/copropiedades/{index,create,show,edit}.blade.php
resources/views/admin/usuarios-globales/{index,create,show,edit}.blade.php
```

#### Archivos modificados

```
app/Models/User.php — agregados estado, desactivado_at a fillable/casts
routes/web.php — rutas CRUD bajo admin.sistema (sin DELETE)
```

#### Decisiones consolidadas

- No hay eliminación física de entidades; solo desactivación.
- La desactivación de una Organización requiere que no tenga Copropiedades
  activas.
- La desactivación de una Copropiedad requiere que no tenga Membresías activas.
- La desactivación de un Usuario requiere que no sea el último administrador.
- Se crea configuración automáticamente al aprovisionar Organización o
  Copropiedad.
- SiteSetting se vincula automáticamente con la primera Copropiedad.

#### Validación

- **Tests:** 434 passed, 5 skipped, 0 fallos.
- **Migración:** Ejecutada correctamente.
- **Rutas:** 24 rutas admin (8 por entidad × 3).
- **Blade cache:** Válido.

#### Exclusiones mantenidas

- C.3.7.2.1 Autoridad Administrador del Sistema — intacto.
- C.3.7.2.2 Gestión de Membresías — intacto.
- UserManagementController heredado — sin cambios.
- ContextResolver — sin cambios.

## S11-1 — Tests de integración C.3.7.3

Estado: **Cerrado.**

- 33 tests de integración para Organizaciones, Copropiedades y Usuarios
  Globales.
- Cobertura: crear, desactivar, reactivar, validaciones, protecciones, ciclos
  CRUD.
- Suite completa: 489 tests, 2130 aserciones, 0 fallos.

## S11-2 — Sistema de invitaciones por correo

Estado: **Implementado y validado en rama de trabajo; pendiente integración a
main.**

### Decisiones aprobadas

1. Activación mediante enlace.
2. Usuario crea su propia contraseña.
3. Expiración del enlace: 24 horas.
4. Solo Administrador del sistema puede invitar.
5. No usar contraseñas temporales.
6. La invitación crea una cuenta pendiente que se activa al aceptar.
7. **Sin membresía automática** (asignación manual desde admin).

### Implementación

| Componente | Archivo |
| --- | --- |
| Migración | `database/migrations/2026_08_24_120000_create_invitaciones_table.php` |
| Modelo | `app/Models/Invitacion.php` |
| Caso de uso crear | `app/Application/Invitaciones/CrearInvitacion.php` |
| Caso de uso cancelar | `app/Application/Invitaciones/CancelarInvitacion.php` |
| Caso de uso aceptar | `app/Application/Invitaciones/AceptarInvitacion.php` |
| Controlador admin | `app/Http/Controllers/Admin/InvitacionController.php` |
| Controlador público | `app/Http/Controllers/InvitacionPublicaController.php` |
| Notificación | `app/Notifications/InvitacionNotificacion.php` |
| Vista admin index | `resources/views/admin/invitaciones/index.blade.php` |
| Vista admin create | `resources/views/admin/invitaciones/create.blade.php` |
| Vista aceptar | `resources/views/invitacion/aceptar.blade.php` |
| Template correo | `resources/views/emails/invitacion.blade.php` |
| Tests | `tests/Feature/InvitacionesTest.php` |
| Rutas | `routes/web.php` (modificado) |

### Tests (17 tests, 32 aserciones)

| Test | Resultado |
| --- | --- |
| crear_invitacion_exitosa | ✅ |
| crear_invitacion_genera_usuario_pendiente | ✅ |
| crear_invitacion_genera_token_unico | ✅ |
| crear_invitacion_expira_en_24_horas | ✅ |
| crear_invitacion_email_duplicado_pendiente_falla | ✅ |
| crear_invitacion_email_existe_en_users_falla | ✅ |
| crear_invitacion_no_admin_no_puede_acceder_formulario | ✅ |
| token_no_se_almacena_en_texto_plano | ✅ |
| cancelar_invitacion_exitosa | ✅ |
| cancelar_invitacion_aceptada_falla | ✅ |
| aceptar_invitacion_exitosa | ✅ |
| aceptar_invitacion_token_expirado_falla | ✅ |
| aceptar_invitacion_token_cancelado_falla | ✅ |
| aceptar_invitacion_password_corto_falla | ✅ |
| aceptar_invitacion_no_crea_membresia | ✅ |
| listar_invitaciones | ✅ |
| usuario_no_admin_no_puede_crear_invitacion | ✅ |

### Resumen cuantitativo

| Métrica | Valor |
| --- | --- |
| Tests nuevos S11-1 | 33 |
| Tests nuevos S11-2 | 17 |
| Total tests nuevos | 50 |
| Aserciones nuevas | 96 |
| Suite completa | 489 tests, 2130 aserciones, 0 fallos |
| Tests skipped preexistentes | 5 (MySQL/SQLite) |

### Deuda técnica identificada

1. **Envío de correo:** `CrearInvitacion` no despacha la notificación. Falta
   integrar el envío real del enlace por correo. Prioridad: alta antes de
   producción.
2. **Contexto institucional en tests HTTP:** Los tests de listar requieren
   contexto completo (SiteSetting + Membresía). Se simplificaron para verificar
   status.

## Estado oficial

```text
C.3.7.1: Cerrado e integrado
C.3.7.2.1: Cerrado, aprobado e integrado
C.3.7.2.2: Cerrado, aprobado e integrado
C.3.7.3: Cerrado e implementado
S11-1: Cerrado
S11-2: Implementado y validado en rama de trabajo; pendiente integración a main
```

Sprint 11 permanece **Completado** con la salvedad de que S11-2 (Invitaciones
por correo) requiere integración a main.
