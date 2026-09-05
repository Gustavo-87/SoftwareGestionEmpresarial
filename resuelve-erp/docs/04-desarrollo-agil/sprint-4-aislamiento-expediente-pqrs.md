# Sprint 4 — Aislamiento de etiquetas de PQRS

## Control documental

- **Versión:** v1.0
- **Estado:** Completado
- **Fecha de cierre:** 31 de julio de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## Objetivo

Impedir que una PQRS se asocie a una etiqueta de otra Copropiedad mediante la
contextualización física exclusiva de `pqr_tags` y `pqr_pqr_tag`.

El sprint conserva una única Copropiedad visible, no cambia rutas, vistas,
Policies, Roles ni permisos. `users.role` continúa como fuente activa de
autorización.

## Componentes implementados

### Estructura e integridad

- `pqr_tags` incorpora `organizacion_id` y `copropiedad_id`.
- La etiqueta referencia a Copropiedad mediante FK compuesta y declara la clave
  candidata `(id, organizacion_id, copropiedad_id)`.
- La unicidad global de `name` fue reemplazada por
  `(copropiedad_id, name)`: el mismo nombre es válido en Copropiedades
  diferentes y se rechaza dentro de la misma.
- `pqrs` declara la clave candidata contextual
  `(id, organizacion_id, copropiedad_id)`.
- `pqr_pqr_tag` conserva su PK `(pqr_id, pqr_tag_id)`, añade ambos IDs y usa
  FKs compuestas hacia la PQRS y la etiqueta contextualizadas.
- Se conservaron las cascadas existentes de la relación original.

Las columnas fueron creadas nullable mediante
`2026_07_31_140000_add_context_to_pqr_tags.php` y se cerraron como obligatorias
mediante `2026_07_31_140100_make_pqr_tag_context_required.php`. Ambas
migraciones son reversibles; la reversión de la primera se bloquea claramente
si ya existen nombres repetidos entre Copropiedades, evitando pérdida de datos.

### Escrituras y consultas

- `PqrTag` pertenece a Organización y Copropiedad; `Pqr` y `PqrTag` exponen la
  relación many-to-many con los valores contextuales del pivote.
- Las etiquetas nuevas se asocian explícitamente desde `ContextoOperativo`.
- Herramientas, etiquetas disponibles en el detalle y sincronización se limitan
  a Organización y Copropiedad activas.
- Los IDs de contexto no se aceptan desde HTTP.
- La sincronización escribe explícitamente el contexto en `pqr_pqr_tag` y
  rechaza una etiqueta externa o inexistente con la misma validación.

### Backfill

El comando `php artisan resuelve:contextualizar-etiquetas-pqrs` valida el
contexto institucional de `SiteSetting`, bloquea y examina etiquetas, PQRS y
asociaciones antes de escribir. Es transaccional, idempotente y aborta ante
contextos parciales, asociaciones huérfanas o cruces de ámbito.

En el entorno local no existían etiquetas ni pivotes históricos. Dos
ejecuciones reales examinaron 0 filas, actualizaron 0 y reportaron 0
inconsistencias, sin modificar datos funcionales.

## Validación

- Pruebas específicas: 15 pruebas, 59 aserciones y 0 fallos.
- Suite completa: 131 pruebas, 576 aserciones y 0 fallos.
- `php -l` y `git diff --check`: correctos.
- Migraciones nullable y `NOT NULL`: aplicadas, revertidas y reaplicadas sin
  pérdida de datos.
- Validación manual HTTP autenticada: creación, rechazo de duplicado,
  asociación, desasociación y recarga de herramientas/detalle respondieron de
  forma correcta; el pivote se creó con contexto y se eliminó al desasociar.
- Aplicación local: `HTTP 302` hacia `/iniciar-sesion`.

Las pruebas con dos Organizaciones y Copropiedades demuestran el aislamiento de
listados, selección de etiquetas, relaciones y FKs compuestas.

## Exclusiones deliberadas y reevaluación futura

Los demás hijos de PQRS no forman parte de este sprint. Permanecen ligados por
la PQRS, que ya tiene contexto obligatorio y se resuelve de forma contextual.
No se afirma que requieran columnas físicas propias ni se registra un riesgo
residual no demostrado. Cualquier necesidad futura de ampliar su estructura se
evaluará como un sprint independiente, con evidencia de un cruce de contexto
real y una decisión aprobada.

No se habilitó selector de Copropiedad, cambio de contexto, segunda
Copropiedad real, API, IA, suscripciones ni Gestión Documental.
