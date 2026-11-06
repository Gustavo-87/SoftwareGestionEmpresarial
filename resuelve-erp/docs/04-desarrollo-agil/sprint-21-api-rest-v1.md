# Sprint 21 — API REST v1 (HU-API-01)

## Estado y control documental

- **Estado:** Completado. HU-API-01 implementada en dos bloques y verificada
  localmente el 5 de noviembre de 2026. La comprobación posterior
  en GitHub Actions se registra en §3, con sus advertencias.
- **Fecha de cierre técnico:** 5 de noviembre de 2026.
- **Origen:** HU-API-01 (Product Backlog, Épica 11 — Integraciones), incorporada
  y aprobada por el Product Owner el 5 de noviembre de 2026 para el requisito
  de API de la guía final.
- **Base:** saneamiento de formato (requisito 15) con la suite completa y Pint
  en verde, y el flujo CI activo en la raíz del repositorio.
- **Responsable:** Equipo de desarrollo de Resuelve.

## 1. Objetivo y alcance

Exponer la operación residencial de PQRS mediante una API REST v1 autenticada
con Laravel Sanctum, reutilizando casos de uso, policies y autorización
contextual (ADR-008), sin duplicar reglas ni alterar el canal web. Alcance
aprobado: login con rol activo, logout con revocación de token, consulta
contextual y detalle de PQRS, radicación de PQRS como operación de escritura,
mínimo cuatro endpoints (uno de escritura) y colección de pruebas. Modelo
residencial autorizado: **sin ventas, compras ni inventario**.

## 2. Bloques implementados

### Bloque 1 — Autenticación con Sanctum

- `laravel/sanctum`, migración `create_personal_access_tokens_table` y trait
  `HasApiTokens` en `User`; registro de `routes/api.php` en `bootstrap/app.php`.
- `POST /api/v1/login`: normalización de correo, límite de intentos idéntico
  al web (compartido entre canales), credenciales inválidas `401`, usuario
  inactivo `403` sin token (HU-USR-08), Membresía vigente requerida y
  selección opcional de Rol (`rol_id`) validada y persistida con
  `GestionarRolActivo` (mismo mecanismo web, sin conceder permisos nuevos).
- `POST /api/v1/logout`: revocación del token personal utilizado.

### Bloque 2 — Consulta, detalle y radicación de PQRS

- `GET /api/v1/pqrs`: listado restringido al contexto activo y a los permisos
  del Rol activo (`pqrs.ver_todas` vs. propias), con filtros y paginación.
- `GET /api/v1/pqrs/{identificador}`: detalle con las reglas de visibilidad del
  expediente web (borradores solo con gestión efectiva, actividades públicas
  sin gestión, comentarios internos solo con gestión efectiva); `404` para
  PQR de otra Copropiedad.
- `POST /api/v1/pqrs`: radicación delegada en `PresentarPqrs` (transacción,
  plazos contextuales, actuación y notificación), respuesta `201`.
- `ResolverContextoApi`: revalidación por petición de usuario activo, Membresía
  vigente (cabecera opcional `X-Membresia-Id`, validada), contexto
  institucional y roles/permisos efectivos. Un token de usuario inactivo o de
  Membresía vencida responde `403` en cualquier operación.

## 3. Verificación (5 de noviembre de 2026)

| Verificación | Resultado |
| --- | --- |
| Colección de pruebas API (`tests/Feature/Api/`) | **15 pruebas aprobadas** (6 de autenticación y 9 de PQRS), 69 aserciones |
| Suite completa (configuración aislada, SQLite en memoria) | **748 pruebas: 739 aprobadas y 9 omitidas (MySQL)**, 3552 aserciones, sin regresiones |
| `vendor/bin/pint --test` | **Aprobado** |

**Ámbito de estas verificaciones:** ejecución **local** histórica.

**Verificación posterior en GitHub Actions:** el CI del PR #3 terminó con
resultado satisfactorio el 5 de noviembre de 2026, con advertencias en
las pruebas. Evidencia, revisión y resultados en
[Integración continua](../08-despliegue/integracion-continua.md).
Las cifras locales anteriores se conservan como registro histórico
de la verificación de este sprint.

## 4. Decisiones

1. **Parámetro de ruta plano** (`{identificador}`): el binding web `pqr`
   resuelve el contexto con sesión, inexistente en API; la resolución usa la
   misma consulta contextual de los bindings (idéntico `404`), sin duplicar.
2. **Selección de contexto por petición:** cabecera opcional
   `X-Membresia-Id` validada contra las Membresías propias y vigentes; con una
   única Membresía vigente no se requiere.
3. **Rate limit compartido entre canales:** las claves de límite de intentos
   del login son las mismas que las del login web (protección única por
   cuenta/IP ante fuerza bruta distribuida).
4. **Login sin tocar el guard web:** credenciales verificadas con `Hash::check`
   y emisión de token; el login web permanece intacto.
5. **Policies sobre el contexto revalidado:** `ResolverContextoApi` deja el
   `ContextoOperativo` resuelto para que las policies existentes operen sobre
   el mismo contexto que el controlador (ADR-008, sin duplicar autorización).

## 5. Exclusiones y conflictos conocidos

- **Adjuntos fuera del alcance aprobado:** la radicación por API no acepta
  adjuntos; el caso de uso los soporta pero HU-API-01 no los contempla.
  Registrado como pendiente en [Estado actual de la API](../07-api/estado-actual-api.md).
- **Formato del Product Backlog:** se conservan sus dos espacios finales
  intencionales de Markdown. El flujo CI activo excluye ese archivo de
  `git diff --check` y comprueba el resto del diff; la decisión está
  registrada en [Integración continua](../08-despliegue/integracion-continua.md), §4.
- Sin ventas, compras ni inventario (adaptación residencial autorizada).
- Advertencias de las pruebas en GitHub Actions pendientes de diagnóstico (ver §3).

## 6. Evidencia

- Contrato implementado: [Estado actual de la API](../07-api/estado-actual-api.md).
- Rutas: `routes/api.php`; registro en `bootstrap/app.php`.
- Transporte: `app/Http/Controllers/Api/AuthController.php`,
  `app/Http/Controllers/Api/PqrController.php`.
- Revalidación por petición: `app/Http/Middleware/ResolverContextoApi.php`.
- Casos de uso reutilizados: `GestionarRolActivo`, `ConsultaMembresiasVigentes`,
  `ConsultaPqrsContextuales`, `VisibilidadBorradoresPqrs`, `PresentarPqrs`.
- Pruebas: `tests/Feature/Api/ApiAuthTest.php` y
  `tests/Feature/Api/ApiPqrsTest.php`.

## Control documental

- **Versión:** v1.1
- **Fecha de creación:** 5 de noviembre de 2026
- **Fecha de última actualización:** 5 de noviembre de 2026
