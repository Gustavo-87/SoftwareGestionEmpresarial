# Sprint 20 — Residentes, Unidades y Auditoría

## Estado y control documental

- **Estado:** Plan presentado por solicitud del Product Owner el 22 de octubre
  de 2026. **Pendiente de aprobación para iniciar la implementación** (regla de
  AGENTS.md: el sprint no se crea automáticamente).
- **Base:** cierre técnico del Sprint 19 (22 de octubre de 2026).
- **Origen:** HU pendientes del Product Backlog (HU-RYP-01, HU-RYP-02,
  HU-AUD-01). **HU-CONF-02 queda fuera del Sprint 20** por decisión del
  Product Owner.
- **Responsable:** Equipo de desarrollo de Resuelve.

## Objetivo

Que la administración de propiedad horizontal gestione su materia prima —quién
ocupa cada unidad, con qué vínculo— y pueda auditar la gestión, reutilizando el
backend existente sin duplicar lógica.

## Regla de dominio transversal

Los **vínculos residenciales** (`vinculos_unidad`: una Persona es propietaria o
residente de una Unidad Privada, con estado y vigencia) y las **Membresías y
permisos de acceso** (`membresias_copropiedad`, roles activos, permisos
directos) son conceptos distintos:

- un vínculo residencial **no concede, modifica ni retira acceso** al sistema;
- el acceso sigue exigiendo cuenta de Usuario con Membresía vigente y
  autorización contextual (Sprint 18);
- ninguna vista de residencia expone roles, permisos ni membresías;
- las pruebas deben verificar que operar vínculos no altera
  `membresias_copropiedad`, `membresia_copropiedad_rol` ni `model_has_roles`.

## Alcance por historia

### HU-RYP-01 — Gestión visible de Unidades Privadas y vínculos (P1)

**Backend reutilizado:** `UnidadPrivada`, `VinculoUnidad`, `Persona` (Sprint 6,
con contexto excluido de `$fillable`), permiso `residentes.gestionar`.

**Criterios de aceptación:**

1. Listado de Unidades Privadas de la Copropiedad activa con identificación,
   estado y personas vinculadas; registros de otros contextos invisibles
   (convención de bindings contextuales).
2. Alta y edición de Unidad Privada con los campos del modelo existente y
   unicidad contextual de identificación; validación en backend.
3. Vinculación de Persona a Unidad con `tipo_vinculo`, `estado`,
   `vigente_desde`/`vigente_hasta`, `fuente` y `observacion`; el contexto no se
   acepta del payload.
4. Protección de duplicados: no se crean vínculos idénticos (misma unidad,
   persona y tipo con vigencias solapadas); la operación es idempotente y una
   acción explícita permite el cambio legítimo.
5. Terminación de vínculos por vigencia o estado, sin eliminación física, con
   confirmación visible.
6. **Separación de dominio:** ninguna operación de esta HU altera Membresías,
   Roles ni permisos (pruebas dedicadas, ver regla transversal).
7. Trazabilidad de altas, cambios y terminaciones (actuaciones/auditoría
   existentes).
8. Estados vacío, éxito y error; confirmaciones; foco, teclado y responsive
   reutilizando componentes (`x-page-heading`, tablas, `empty-state`, `notice`).

### HU-RYP-02 — Consulta de unidad y vínculos desde el perfil del residente (P1)

**Backend reutilizado:** consultas de `VinculoUnidad`/`UnidadPrivada` por
persona; visibilidad de lo propio (sin permiso nuevo).

**Criterios de aceptación:**

1. En «Mi perfil», el residente autenticado ve sus unidades y vínculos propios
   vigentes (tipo, vigencia y estado); los de otras personas no aparecen.
2. La vista es de solo lectura para el residente; la edición se realiza con
   `residentes.gestionar` (HU-RYP-01).
3. La vista no muestra datos de acceso (roles, permisos, membresías).
4. La consulta no amplía visibilidad sobre PQRS, documentos ni datos de otras
   personas de la unidad (pruebas de no ampliación).
5. Estado vacío «Sin residencia vinculada», 403/404 contextuales y responsive.

### HU-AUD-01 — Consulta contextual del historial de auditoría (P2)

**Backend reutilizado:** `AuditLog` y la vista `management.audit` actual
(`ComplementaryController@audit`, permiso `auditoria.ver`).

**Criterios de aceptación:**

1. El historial se limita a la Organización/Copropiedad activas; hoy el listado
   es global y este es el cambio principal (pruebas de cruce de contexto).
2. Filtros por actor, acción, recurso y rango de fechas, con paginación y orden
   cronológico inverso.
3. Detalle con actor, acción, recurso, contexto, fecha e IP, sin exponer
   secretos en metadatos.
4. Solo lectura: sin exportación ni mutaciones; `auditoria.ver` verificado en
   backend (403) además de la ocultación de menú.
5. Sin crecimiento N+1 (eager loading de actor) y estados vacío/filtrado
   legibles; responsive.

## Dependencias y orden

1. **HU-RYP-01 → HU-RYP-02:** el perfil del residente consulta los vínculos que
   HU-RYP-01 gestiona; aunque el backend de Sprint 6 ya permite poblar datos, el
   orden garantiza datos consistentes para la demostración.
2. **HU-AUD-01 es independiente** y puede ejecutarse en paralelo; se prioriza
   después de RYP-02 si el tiempo es limitado (menor valor operativo inmediato).
3. Sin dependencias externas nuevas; sin migraciones previstas (esquema de
   Sprint 6 suficiente; solo se evaluaría un índice si el rendimiento lo exige).

## Exclusiones

- HU-CONF-02 (decisión del Product Owner), HU-REP-02, HU-IA-01/02 y HU-INT-01/02.
- Cambios en Membresías, Roles, permisos o en el ciclo de acceso (Sprint 18).
- Envío real de correo y saneamiento RBAC: siguen como pendientes separados del
  Sprint 18, no bloquean este sprint.
- Exportaciones (PDF/Excel) del historial de auditoría.

## Condiciones de cierre propuestas

1. Los criterios de las tres HU verificados con pruebas (denegaciones, cruces de
   contexto, separación vínculo/membresía) y suite completa sin regresiones.
2. Experiencia visible completa (listados, formularios, perfil del residente,
   auditoría) verificada en navegador en ambos temas y anchos.
3. `npm run build`, `git diff --check` y diagnóstico RBAC sin regresiones.
4. Acta con aprobación del Product Owner.
