# Sprint 24 — Catálogos maestros con ciclo de vida completo (HU-CONF-03)

## Estado y control documental

- **Estado:** Completado. HU-CONF-03 implementada de principio a fin para las
  cuatro entidades y verificada localmente el 5 de noviembre de 2026 (incluida
  la extensión aprobada de Tipos de PQRS, §5). La comprobación posterior
  en GitHub Actions se registra en §3, con sus advertencias.
- **Fecha de cierre técnico:** 5 de noviembre de 2026.
- **Origen:** HU-CONF-03 (Product Backlog, Épica 10 — Configuración),
  incorporada y aprobada por el Product Owner el 5 de noviembre de 2026 para el
  requisito de entidades maestras de la guía final.
- **Base:** cierre del Sprint 23 (HU-NOT-03).
- **Número de sprint:** 24 (siguiente disponible tras el Sprint 23).

## 1. Objetivo y alcance

Ciclo de vida completo —alta, consulta, edición, baja lógica, papelera y
restauración— para las cuatro entidades maestras (Tipos de PQRS, Etiquetas,
Unidades Privadas y Personas), con Form Requests para alta y edición,
reutilizando los campos existentes (`estado`/`desactivada_at`/`activo`), sin
eliminación física ni cambios de esquema, con permisos actuales y aislamiento
Organización/Copropiedad.

## 2. Implementación

- **Form Requests** (8, en `app/Http/Requests/`): `StoreTipoPqrRequest`,
  `UpdateTipoPqrRequest`, `StoreEtiquetaRequest`, `UpdateEtiquetaRequest`,
  `StoreUnidadPrivadaRequest`, `UpdateUnidadPrivadaRequest`,
  `StorePersonaRequest`, `UpdatePersonaRequest`. La validación HTTP se movió a
  los Form Requests **sin duplicar reglas**: las de formato/unicidad se
  declaran una sola vez en los casos de uso (`reglasValidacion()`/`mensajesValidacion()`)
  y se reutilizan desde los Form Requests; las reglas de dominio (unicidad
  contextual del código, de la identificación y del nombre de etiqueta)
  permanecen en los casos de uso. La autorización precede a la validación
  (403 antes de 422) y, en la edición de Etiquetas, la resolución contextual
  del recurso también precede a la validación (404 sin IDOR antes de 422).
- **Papelera y restauración** (vista compartida `management/papelera.blade.php`
  con acción Restaurar): etiquetas (`management.tags.papelera` → `tagStatus`),
  unidades (`management.unidades.papelera` → `reactivar`) y personas
  (`management.personas.papelera` → `reactivar`).
- **Edición de Personas** (antes inexistente): `GestionarPersona::actualizar`
  (con auditoría `residencia.persona.actualizar`), rutas `edit`/`update` y
  vista `management/personas-form.blade.php`.
- **Tipos de PQRS:** Form Requests y ciclo completo (baja lógica con
  `desactivada_at`, papelera y restauración) mediante la extensión aprobada
  (§5).

## 3. Verificación (5 de noviembre de 2026)

| Verificación | Resultado |
| --- | --- |
| Pruebas de ciclo de vida (`tests/Feature/CicloVidaCatalogosTest.php`) | **15 pruebas aprobadas** (74 aserciones): 422, alta, consulta, edición, baja lógica sin borrar la fila, listado en papelera, restauración, 403 y aislamiento contextual (404 para recursos de otra Copropiedad) |
| Pruebas de catálogo de Tipos de PQRS (`tests/Feature/Admin/TipoPqrCatalogoTest.php`) | **5 pruebas aprobadas** (52 aserciones), incluida la nueva de baja lógica con papelera y restauración: la fila persiste, el catálogo normal y los selectores la excluyen, la papelera la lista, restaurar limpia `desactivada_at`, las guardas por uso conservan sus respuestas y los tipos asociados a registros existentes mantienen su relación |
| Suite completa (configuración aislada, SQLite en memoria) | **780 pruebas: 771 aprobadas y 9 omitidas (MySQL)**, 3715 aserciones, sin regresiones |
| `vendor/bin/pint --test` | **Aprobado** |

**Ámbito:** verificación **local** histórica.

**Verificación posterior en GitHub Actions:** el CI del PR #3 terminó con
resultado satisfactorio el 5 de noviembre de 2026, con advertencias en
las pruebas. Evidencia, revisión y resultados en
[Integración continua](../08-despliegue/integracion-continua.md).
Las cifras locales anteriores se conservan como registro histórico
de la verificación de este sprint.

En la verificación local del sprint, la migración
`add_desactivada_at_to_tipo_pqrs_table` **no se ejecutó sobre la base de datos
local** (se aplicará con el flujo de migraciones del entorno).

## 4. Decisiones

1. **Fuente única de validación:** las reglas se declaran en los casos de uso
   (`reglasValidacion()`/`mensajesValidacion()`) y los Form Requests las
   reutilizan; no quedan validaciones inline duplicadas en los controladores.
2. **Autorización antes de la validación:** los Form Requests reproducen el
   orden previo (403 primero) y, donde existía resolución contextual del
   recurso antes de validar, esta se conserva en `authorize()` (404 sin IDOR).
3. **Baja lógica sin esquema:** se reutilizan `estado`/`desactivada_at`/
   `activo`; la papelera lista los desactivados y restaurar reutiliza las
   acciones de reactivación existentes (sin duplicar lógica).
4. **Solo altas/ediciones/bajas lógicas:** ninguna operación borra filas.

## 5. Extensión aprobada: ciclo de vida completo de Tipos de PQRS

La tabla `tipo_pqrs` carecía de columnas de estado; el Product Owner autorizó
el 5 de noviembre de 2026 una migración con la columna nullable
`desactivada_at` (activos con `null`; al desactivar se asigna la fecha; al
restaurar se limpia), que completa el ciclo de la cuarta entidad:

- **Baja lógica** mediante la ruta `admin.tipos-pqr.destroy` (contrato
  conservado), que deja de eliminar filas y registra `tipo_pqr.deactivate`;
  conserva las **guardas por uso** y su respuesta existente (un tipo asociado a
  PQRS o reglas automáticas no se desactiva).
- **Papelera** (`admin.tipos-pqr.papelera`) y **restauración**
  (`admin.tipos-pqr.reactivar`, auditoría `tipo_pqr.reactivate`), con
  idempotencia.
- **Consulta normal sin desactivados:** el catálogo de administración y los
  selectores de radicación y herramientas (`PqrController`,
  `ComplementaryController`) los excluyen; los tipos asociados a registros
  existentes conservan su relación.
- Migración: `2026_11_05_210000_add_desactivada_at_to_tipo_pqrs_table.php`
  (único cambio de esquema de este sprint, autorizado). No se ejecutó sobre la
  base de datos local.

## 6. Evidencia

- Form Requests: `app/Http/Requests/` (8 clases).
- Casos de uso: `GestionarTipoPqr`, `GestionarUnidadPrivada`,
  `GestionarPersona` (con `actualizar`), `AdministrarCatalogoEtiquetasPqrs`.
- Controladores: `Admin/TipoPqrController`, `ComplementaryController`,
  `UnidadPrivadaController`, `PersonaController`.
- Vistas: `management/papelera.blade.php`, `management/personas-form.blade.php`,
  enlaces de papelera en `management/unidades.blade.php`,
  `management/personas.blade.php` y `management/tools.blade.php`.
- Rutas: `routes/web.php` (`management.*.papelera`, `management.personas.edit/update`).
- Pruebas: `tests/Feature/CicloVidaCatalogosTest.php`.

## Control documental

- **Versión:** v1.1
- **Fecha de creación:** 5 de noviembre de 2026
- **Fecha de última actualización:** 5 de noviembre de 2026
