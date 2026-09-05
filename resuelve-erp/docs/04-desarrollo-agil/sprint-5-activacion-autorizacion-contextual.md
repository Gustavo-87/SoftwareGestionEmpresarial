# Sprint 5 — Activación de autorización contextual

## Control documental

- **Versión:** v1.0
- **Estado:** Completado
- **Fecha de finalización:** 2 de agosto de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## Objetivo y decisión implementada

La autorización efectiva usa exclusivamente la Membresía de Copropiedad
vigente, Roles contextuales vigentes y Permisos contextuales activos del
`ContextoOperativo`. La pertenencia del recurso al contexto también se valida
en las operaciones de PQRS. `users.role` se conserva para sincronización
administrativa, compatibilidad, presentación, diagnóstico, recuperación y
pruebas; no participa en decisiones de acceso.

`AutorizacionContextual` concentra la comprobación de permisos y Roles, así
como las reglas para consultar y gestionar PQRS dentro del ámbito operativo.
Las Policies, controladores, informes, configuración y herramientas de gestión
consumen esa decisión contextual sin fallback hacia `users.role`.

## Transición y verificación de equivalencia

Antes de activar consumidores se ejecuta:

```bash
php artisan resuelve:verificar-equivalencia-autorizacion-contextual
```

El comando compara, para cada Usuario, Rol heredado, Membresía, Rol contextual
y vector de Permisos. Las divergencias se registran y el comando falla; con
cero divergencias confirma que puede continuarse con la activación. Después de
la activación, una divergencia se registra como diagnóstico sin modificar la
decisión contextual.

La verificación final del sprint produjo:

```text
Equivalencia completa verificada: 0 divergencias.
```

## Consumidores activados

- `PqrPolicy` valida permisos y pertenencia del recurso al contexto.
- Listados, detalle, edición y notificaciones de PQRS usan permisos y
  Membresías de la Copropiedad activa.
- Los informes exigen `informes.exportar` y conservan el universo contextual
  permitido para el Usuario.
- Configuración, carga de trabajo, herramientas, usuarios, residentes y
  auditoría exigen sus Permisos contextuales específicos.
- El seeder sincroniza la identidad contextual de los Usuarios de demostración
  y falla de forma explícita si la sincronización no puede completarse.

## Infraestructura de pruebas contextual

Los escenarios positivos crean expresamente Organización, Copropiedad, Rol,
Permisos mínimos, Membresía vigente y asignación contextual. No existe una
identidad global automática para todas las pruebas y no se infieren Permisos
desde `users.role`.

La infraestructura permite definir por Usuario:

- Copropiedad y Organización;
- Rol contextual exacto;
- conjunto mínimo de Permisos;
- estado de la Membresía;
- inicio y final de vigencia.

Se validan expresamente Usuarios sin Membresía, Membresías inactivas,
Membresías de otra Copropiedad, ausencia del Permiso requerido y divergencia
entre `users.role` y el Rol contextual. Al cambiar de actor dentro de una misma
prueba se renueva la instancia scoped de `ContextoOperativo` para no reutilizar
el contexto del actor anterior.

## Correcciones funcionales finales

### Ausencia de contexto institucional

`ContextResolver` lanza `ContextoInstitucionalNoConfigurado` cuando no existe
el contexto inicial. `SettingsController::update()` captura exclusivamente esa
excepción, devuelve un error controlado en `contexto` y no realiza escrituras.
Las demás excepciones se propagan normalmente y no existe fallback basado en
`users.role`.

### Protección del último administrador

La eliminación de Usuarios resuelve primero la Membresía y los Roles vigentes
del objetivo dentro de la Copropiedad activa:

- un Usuario ajeno al contexto se oculta con `404`;
- la restricción del último administrador solo se aplica si el objetivo posee
  el Rol contextual `admin` vigente;
- un Usuario no administrador puede eliminarse aunque exista un solo
  administrador;
- un administrador puede eliminarse cuando permanece otro administrador
  contextual vigente;
- la decisión no consulta `users.role`.

La eliminación autorizada se ejecuta transaccionalmente y retira la asignación
de Rol y la Membresía del ámbito inicial antes de eliminar la cuenta, respetando
las claves foráneas restrictivas. Se mantiene el rechazo de la propia cuenta y
de Usuarios con PQRS asociadas.

## Validación final

```text
php artisan test
137 pruebas, 137 aprobadas, 588 aserciones.

php artisan resuelve:verificar-equivalencia-autorizacion-contextual
0 divergencias, código de salida 0.

php -l
Sin errores en los archivos modificados.

git diff --check
Sin errores.
```

## Límites conservados

El sprint no contextualiza físicamente recursos que todavía usan tablas
globales ni modifica el esquema de datos. Permanecen registrados para trabajo
posterior los accesos globales de recursos complementarios, la presentación
heredada basada en `users.role` y la contextualización de responsables. Estos
límites no introducen un fallback permisivo en `AutorizacionContextual`.

## Evidencia de implementación

- `app/Application/Autorizacion/AutorizacionContextual.php`
- `app/Application/Autorizacion/VerificadorEquivalenciaAutorizacionContextual.php`
- `app/Console/Commands/VerificarEquivalenciaAutorizacionContextual.php`
- `app/Application/Contexto/ContextResolver.php`
- `app/Application/Contexto/ContextoInstitucionalNoConfigurado.php`
- `app/Policies/PqrPolicy.php`
- `app/Http/Controllers/PqrController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/SettingsController.php`
- `app/Http/Controllers/ComplementaryController.php`
- `app/Http/Controllers/UserManagementController.php`
- `app/Providers/AppServiceProvider.php`
- `database/seeders/DatabaseSeeder.php`
- `tests/Concerns/CreatesInstitutionalContext.php`
- `tests/Feature/UserManagementContextualDeletionTest.php`
- suite completa en `tests/Feature/` y `tests/Unit/`.
