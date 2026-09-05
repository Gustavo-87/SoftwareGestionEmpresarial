# Sprint 13 — Simplificación visual y experiencia de usuario

## Control documental

- **Versión:** v2.0
- **Estado:** Completado — pendiente aprobación visual del Product Owner
- **Fecha de creación:** 28 de agosto de 2026
- **Última actualización:** 30 de agosto de 2026
- **Responsable:** Documentación Resuelve
- **Aprobación:** Aprobado por el Product Owner — 28 de agosto de 2026

## Objetivo

Simplificar la experiencia visual de Resuelve mediante reducción de carga
cognitiva, mejora de jerarquía visual, reorganización de navegación y
homogeneización de componentes, sin introducir nuevas capacidades de negocio.

## Contexto

- Sprint 10 completado: experiencia de producto y consola operativa (C.1–C.3.6).
- Sprint 11 completado: multi-copropiedad y consola administrativa
  (C.3.7.1–C.3.7.3). S11-2 pendiente de integración a main.
- Sprint 12 completado: estabilización, dependencias y perfilamiento visual.
- Se realizó una auditoría visual completa de la interfaz que identificó 10
  problemas prioritarios: navegación sobrecargada, dashboard con densidad
  insuficiente, inconsistencia entre layouts, estilos inline en vistas admin,
  tablas con demasiadas columnas, jerarquía plana en formularios, múltiples
  sistemas de badges, espaciado inconsistente, dashboard admin sin contexto
  operativo y componentes reutilizables insuficientes.

## Principios orientadores

1. **Enterprise UX:** interfaz profesional, densidad apropiada al contexto.
2. **Progressive disclosure:** mostrar información avanzada solo cuando se
   necesita.
3. **Dashboard orientado a situación:** priorizar lo que requiere atención.
4. **Menor densidad en pantallas ejecutivas; mayor densidad donde sea
   funcionalmente necesaria** (tablas, auditoría, reportes).
5. **Una acción primaria claramente identificable** por contexto.
6. **Uso consistente de espacio en blanco.**
7. **Navegación modular** con agrupaciones por dominio funcional.
8. **Responsive** conservando los breakpoints ya validados (1440, 768, 390, 320).
9. **Reutilización de componentes existentes.**
10. **Evitar sobrediseño** y abstracciones prematuras.

## Alcance

### Bloque A — Arquitectura de información y navegación

**Objetivo:** Reorganizar la navegación principal para reducir carga cognitiva,
agrupar opciones por dominio funcional y habilitar progressive disclosure.

**Alcance:**

1. Reemplazar el sidebar fijo por navegación superior modular en desktop
   (≥1025px).
2. Estructura aprobada:

```text
RESUELVE  |  Inicio  |  PQRS ▾  |  Personas ▾  |  Documentos ▾  |  Reportes ▾  |  Administración ▾
```

3. Agrupaciones por menú desplegable:
   - **PQRS ▾:** Listado, Radicar.
   - **Personas ▾:** Residentes, Mi perfil.
   - **Documentos ▾:** Biblioteca (cuando autorizado).
   - **Reportes ▾:** Informes PQRS (cuando autorizado).
   - **Administración ▾:** Carga del equipo, Herramientas, Auditoría,
     Configuración general, Usuarios y roles, Administración del sistema
     (solo admin del sistema).
4. Conservar el drawer móvil/tablet (≤1024px) con las mismas agrupaciones.
5. Mantener el selector de contexto (Organización + Copropiedad activa) en la
   barra superior, visible y compacto.
6. Mantener indicador de notificaciones y toggle de tema en la barra superior.
7. Mantener avatar de usuario con acceso a perfil y logout.

**Archivos probablemente afectados:**

- `resources/views/layouts/app.blade.php`
- `resources/css/app.css` (secciones de `.sidebar`, `.app-navbar`, `.nav-group`,
  `.nav-config`, breakpoints)
- `resources/js/app.js` (comportamiento de menús desplegables)

**Criterios de aceptación:**

- La navegación muestra máximo 6–7 items principales visibles (incluidos menús
  desplegables).
- Cada menú desplegable se abre con click/Enter y cierra con Escape/fuera.
- El selector de contexto permanece visible en desktop.
- Las notificaciones mantienen su badge contador.
- El drawer móvil conserva todas las opciones actuales agrupadas.
- No hay overflow horizontal en 1440, 768, 390 y 320 px.
- Foco visible y navegación por teclado funcionales.
- `aria-current="page"` se mantiene en el item activo.
- Los permisos contextuales siguen controlando la visibilidad de items.

**Dependencias:**

- CSS existente de `.nav-config` y `.nav-submenu`.
- Patrón de barra superior ya validado en 761–1024px.
- `$navegacion` array del `AppServiceProvider`.

**Riesgos:**

- El sidebar fue aprobado en C.1 (Sprint 10). El cambio a navegación superior
  modifica el shell aprobado.
- Los usuarios actuales pueden estar familiarizados con el sidebar.
- El selector de contexto en barra superior requiere diseño compacto.

**Pruebas necesarias:**

- `PanelOperativoTest`: regresión completa.
- `AuthenticationTest`: regresión.
- Pruebas manuales de navegación por teclado.
- Validación responsive en 1440, 768, 390 y 320 px.

**Documentación afectada:**

- `docs/03-tecnica/interfaz-web.md`: actualizar descripción de navegación.

**Fuera de alcance:**

- Nuevos items de navegación.
- Cambios en permisos o autorización.
- Nuevas rutas.

---

### Bloque B — Application Shell

**Objetivo:** Consolidar el shell visual de la aplicación para que sea coherente
con la nueva navegación y elimine redundancias.

**Alcance:**

1. Unificar el topbar del layout principal y el layout admin en un patrón
   coherente.
2. Eliminar del layout admin la duplicación de "Volver al panel" (existe en
   sidebar y topbar) y "Cerrar sesión" separado.
3. Mantener en el topbar: logo compacto, título de página, indicador de
   contexto, notificaciones, tema, usuario.
4. El indicador de contexto debe ser compacto: nombre de Copropiedad activa como
   texto principal, Organización como texto secundario.
5. Eliminar el `eyebrow` redundante que muestra "Organización · Copropiedad
   activa" + nombre de copropiedad + título de página simultáneamente.

**Archivos probablemente afectados:**

- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/admin.blade.php`
- `resources/css/app.css`

**Criterios de aceptación:**

- El topbar muestra como máximo: logo, título de página, contexto compacto,
  notificaciones, tema, usuario.
- El layout admin reutiliza el mismo patrón de topbar que el layout principal.
- No hay elementos duplicados entre navegación y topbar.
- El contexto de Copropiedad activa es visible sin saturar.
- Responsive verificado en los 4 breakpoints.

**Dependencias:**

- Bloque A (la estructura de navegación define el layout del shell).

**Riesgos:**

- El layout admin actual tiene diferencias intencionadas (sin notificaciones,
  sin selector de contexto). Mantener esas diferencias es correcto.

**Pruebas necesarias:**

- Regresión de `PanelOperativoTest` y `AuthenticationTest`.
- Verificación de que el layout admin no muestra notificaciones ni selector de
  contexto (comportamiento actual correcto).

**Documentación afectada:**

- `docs/03-tecnica/interfaz-web.md`.

**Fuera de alcance:**

- Cambios en el layout de login.
- Cambios en el layout de emails.
- Nuevos elementos de topbar.

---

### Bloque C — Dashboard operativo

**Objetivo:** Transformar el panel operativo (`/panel`) en una consola orientada
a situación, prioridades y acciones.

**Alcance:**

1. Reorganizar el panorama general para mostrar 4 indicadores clave:
   - Total PQRS autorizadas (contexto general).
   - Pendientes (requieren gestión).
   - Próximas a vencer (vencen en ≤3 días).
   - Vencidas (plazo superado, requieren atención inmediata).
2. Las PQRS vencidas deben tener prominencia visual superior (color `--coral`,
   sin alarma excesiva).
3. Los valores cero deben usar lenguaje neutral (comportamiento actual correcto,
   mantener).
4. Las prioridades operativas mantienen el patrón actual de tarjetas enlazadas a
   filtros.
5. Los accesos directos (Documentos, Notificaciones, Carga del equipo) se
   integran como parte del panorama, no como sección separada con su propio
   encabezado.
6. Mantener el CTA "Radicar PQRS" en el encabezado cuando el usuario tiene
   permiso.
7. Mantener el componente `<x-empty-state>` para el estado sin acceso a PQRS.

**Verificación de factibilidad — métrica "Vencidas":**

La lógica de vencimiento ya existe:

- `Pqr::getIsOverdueAttribute()` (Pqr.php:129–136): retorna `true` cuando
  `fecha_limite_respuesta < hoy()` y el estado no es `respondida` ni `cerrada`.
  Usa `CalendarioLaboralColombia::hoy()`.
- `ComplementaryController:29`: usa
  `whereDate('fecha_limite_respuesta', '<', today())` combinado con
  `whereIn('estado', ['radicada', 'en_revision'])`.
- `ReportController:57`: usa `$pqr->fecha_limite_respuesta?->isBefore(today())`.

Añadir el conteo de vencidas al panel es una composición de consultas
existentes, no una nueva regla de negocio. Se implementará como:

```php
'vencidas' => (clone $baseQuery)->whereIn('estado', ['radicada', 'en_revision'])
    ->whereDate('fecha_limite_respuesta', '<', Carbon::today())
    ->count(),
```

**Archivos probablemente afectados:**

- `resources/views/pqrs/panel.blade.php`
- `app/Http/Controllers/PqrController.php` (método `panel`, línea 47–53)
- `resources/css/app.css` (secciones de `.panel-overview`, `.priority-panel`,
  `.console-access`)

**Criterios de aceptación:**

- El panorama muestra 4 métricas con jerarquía visual clara (vencidas >
  por vencer > pendientes > total).
- Las PQRS vencidas son visualmente distinguibles de las próximas a vencer.
- Los accesos directos están integrados en el panorama, no como sección aparte.
- Los estados cero son neutrales y orientativos.
- Los permisos parciales ocultan los accesos no autorizados.
- El aislamiento contextual se mantiene.
- Responsive en 1440, 768, 390 y 320 px.

**Dependencias:**

- `ConsultaPqrsContextuales` y `PqrController::panel()`.
- Bloques A y B (el shell define el contexto del dashboard).

**Riesgos:**

- El cambio en `PqrController::panel()` es menor pero debe verificarse con
  regresión completa de `PanelOperativoTest`.

**Pruebas necesarias:**

- `PanelOperativoTest`: ampliar para cubrir las 4 métricas, estados cero,
  permisos parciales y aislamiento.
- Regresión de filtros de PQRS.

**Documentación afectada:**

- `docs/03-tecnica/interfaz-web.md`: actualizar descripción del dashboard.

**Fuera de alcance:**

- Gráficos o indicadores nuevos.
- Métricas de tendencia o comparación.
- Nuevos permisos.
- Cambios en la lógica de negocio de PQRS.

---

### Bloque D — Sistema visual mínimo

**Objetivo:** Homogeneizar los componentes visuales reutilizables y eliminar
inconsistencias entre módulos.

**Alcance:**

1. Crear componente `<x-page-heading>` parametrizado:
   - Props: `title`, `description` (opcional), `eyebrow` (opcional), slot para
     acciones.
   - Extraer el patrón repetido de `page-heading` con eyebrow + h1 +
     descripción + acciones.
   - Adoptar incrementalmente únicamente donde exista un patrón claro.

2. Crear componente `<x-metric-card>` parametrizado:
   - Props: `label`, `value`, `note` (opcional), `icon` (opcional),
     `color` (mint/amber/coral/blue), `href` (opcional).
   - Extraer el patrón repetido de tarjeta de métrica.
   - Adoptar incrementalmente únicamente donde exista un patrón claro.

3. Crear componente `<x-badge>` parametrizado:
   - Props: `variant` (status/priority/role), `label`, `color` (automático
     según variant o manual).
   - Unificar los sistemas `.status`, `.priority-badge`, `.role-badge` en un
     componente con variantes CSS.
   - Mantener las clases CSS existentes como variantes del componente.
   - Adoptar incrementalmente únicamente donde exista un patrón claro.

4. Eliminar estilos inline de las vistas administrativas (Sprint 11) y migrarlos
   a clases CSS del sistema de tokens.

5. Mantener `<x-empty-state>` y `<x-notice>` sin cambios.

**Archivos probablemente afectados:**

- `resources/views/components/page-heading.blade.php` (nuevo)
- `resources/views/components/metric-card.blade.php` (nuevo)
- `resources/views/components/badge.blade.php` (nuevo)
- `resources/views/admin/index.blade.php`
- `resources/views/admin/organizaciones/*.blade.php`
- `resources/views/admin/copropiedades/*.blade.php`
- `resources/views/admin/membresias/*.blade.php`
- `resources/views/admin/usuarios-globales/*.blade.php`
- `resources/css/app.css`

**Criterios de aceptación:**

- Los 3 componentes nuevos funcionan con las props definidas.
- Las vistas migradas producen HTML equivalente o mejorado al anterior.
- No hay estilos `style=""` en vistas administrativos (excepto casos justificados
  como `--forest` en body).
- Los badges mantienen su apariencia visual actual.
- `npm run build` correcto.
- `git diff --check` correcto.

**Dependencias:**

- Ninguna dependencia de bloques anteriores. Puede ejecutarse en paralelo con
  B y C.

**Riesgos:**

- La migración de estilos inline a clases puede introducir regresiones visuales.
- Los componentes Blade nuevos deben nombrarse para no colisionar con clases
  CSS existentes.

**Pruebas necesarias:**

- Regresión completa de la suite automatizada.
- Verificación visual de cada vista migrada en los 4 breakpoints.

**Documentación afectada:**

- `docs/03-tecnica/interfaz-web.md`: actualizar inventario de componentes.

**Fuera de alcance:**

- `<x-data-table>`, `<x-form-layout>`, `<x-detail-layout>`.
- Nuevo sistema de diseño o catálogo de componentes.
- Framework JavaScript.

---

### Bloque E — Normalización de vistas operativas

**Objetivo:** Homogeneizar la presentación de las vistas de detalle y listado
entre módulos para reducir la brecha de madurez visual.

**Alcance:**

1. Normalizar el dashboard administrativo (`/admin`):
   - Eliminar el badge "Framework C.3.7.2.2 integrado" (metadata de desarrollo).
   - Eliminar la sección "Actividad reciente" (solo muestra membresías, no
     actividad operativa real).
   - Migrar a los componentes `<x-page-heading>` y `<x-metric-card>`.
   - Mantener: métricas principales, distribución de membresías, accesos rápidos.

2. Normalizar las vistas de detalle administrativas (show pages):
   - Aplicar el patrón de `detail-grid` de forma consistente en Organizaciones,
     Copropiedades, Membresías y Usuarios globales.
   - Verificar que los estados de badges sean consistentes con el sistema
     unificado de `<x-badge>`.

3. Normalizar las vistas de listado administrativas (index pages):
   - Verificar que las tablas usen el mismo patrón de encabezado con
     `<x-page-heading>`.
   - Mantener el patrón de tarjeta móvil existente para PQRS; verificar
     consistencia en admin.

4. Añadir acceso a Gestión Documental en la navegación para usuarios
   autorizados (problema prioritario #5 de interfaz-web.md).

**Archivos probablemente afectados:**

- `resources/views/admin/index.blade.php`
- `resources/views/admin/organizaciones/show.blade.php`
- `resources/views/admin/copropiedades/show.blade.php`
- `resources/views/admin/membresias/show.blade.php`
- `resources/views/admin/usuarios-globales/show.blade.php`
- `resources/views/admin/organizaciones/index.blade.php`
- `resources/views/admin/copropiedades/index.blade.php`
- `resources/views/admin/membresias/index.blade.php`
- `resources/views/admin/usuarios-globales/index.blade.php`
- `resources/views/layouts/app.blade.php` (añadir Documentos a navegación)

**Criterios de aceptación:**

- El dashboard admin no contiene metadata de desarrollo.
- Las vistas de detalle admin usan `detail-grid` de forma consistente.
- Los badges son consistentes con `<x-badge>`.
- Documentos tiene acceso en la navegación para usuarios autorizados.
- Responsive verificado.

**Dependencias:**

- Bloque D (componentes `<x-page-heading>`, `<x-metric-card>`, `<x-badge>`).

**Riesgos:**

- Añadir Documentos a la navegación puede requerir verificar que todos los
  roles autorizados tengan el permiso correcto. El backend ya valida; el riesgo
  es solo de presentación.

**Pruebas necesarias:**

- Regresión completa.
- Verificación de permisos documentales por rol.

**Documentación afectada:**

- `docs/03-tecnica/interfaz-web.md`.

**Fuera de alcance:**

- Nuevos módulos.
- Cambios en la lógica de negocio de Gestión Documental.
- Rediseño de formularios.

---

### Bloque F — Responsive y accesibilidad

**Objetivo:** Verificar y corregir el comportamiento responsive y la accesibilidad
básica de todos los flujos modificados.

**Alcance:**

1. Validar que la nueva navegación superior funcione correctamente en 1440, 768,
   390 y 320 px.
2. Verificar que los menús desplegables sean accesibles por teclado (Tab, Enter,
   Escape, flechas).
3. Verificar que `aria-current="page"` se mantenga en el item activo.
4. Verificar que los menús desplegables tengan `aria-expanded` y `aria-haspopup`.
5. Verificar foco visible en todos los elementos interactivos nuevos.
6. Verificar contraste de colores en los badges unificados.
7. Verificar que no haya overflow horizontal en ningún breakpoint.
8. Verificar que los controles táctiles mantengan un mínimo de 44 px en móvil.

**Archivos probablemente afectados:**

- Todos los modificados en bloques anteriores.
- `resources/css/app.css`
- `resources/js/app.js`

**Criterios de aceptación:**

- Navegación por teclado funcional en todos los menús desplegables.
- `aria-current`, `aria-expanded`, `aria-haspopup` correctos.
- Foco visible en todos los elementos interactivos.
- Sin overflow horizontal en 1440, 768, 390 y 320 px.
- Controles táctiles ≥44 px en móvil.
- Contraste mínimo AA en badges y textos.

**Dependencias:**

- Bloques A, B, C, D, E.

**Riesgos:**

- Los menús desplegables en barra superior pueden requerir JavaScript adicional
  para manejo de foco y cierre.

**Pruebas necesarias:**

- Pruebas manuales de navegación por teclado.
- Pruebas de contraste (herramienta de auditoría del navegador).
- Regresión completa de la suite automatizada.

**Documentación afectada:**

- `docs/03-tecnica/interfaz-web.md`.

**Fuera de alcance:**

- Auditoría de accesibilidad completa (WCAG 2.1 AA).
- Soporte para lectores de pantalla avanzados.
- Internacionalización.

---

### Bloque G — Validación técnica y visual

**Objetivo:** Cerrar el sprint con verificación completa, documentación
actualizada y evidencia aprobada.

**Alcance:**

1. Ejecutar la suite automatizada completa sin fallos nuevos.
2. Ejecutar `npm run build` y `git diff --check` sin errores.
3. Generar evidencia visual antes/después de los flujos modificados en los 4
   breakpoints.
4. Actualizar la documentación afectada.
5. Registrar el cierre del sprint.

**Archivos probablemente afectados:**

- `docs/03-tecnica/interfaz-web.md`
- `docs/04-desarrollo-agil/sprint-13-simplificacion-visual.md`
- `docs/README.md` (actualizar índice)
- `docs/01-producto/linea-base-estado-actual.md` (actualizar si corresponde)

**Criterios de aceptación:**

- Suite automatizada sin fallos nuevos.
- `npm run build` correcto.
- `git diff --check` correcto.
- Evidencia visual generada y conservada.
- Documentación actualizada.

**Dependencias:**

- Todos los bloques anteriores.

**Pruebas necesarias:**

- Suite completa.

**Documentación afectada:**

- Todas las listadas.

**Fuera de alcance:**

- Nuevas pruebas automatizadas (salvo las necesarias para cubrir cambios
  nuevos).

---

## Fases de ejecución

El Sprint 13 se ejecuta en tres fases internas:

### Fase 1 — Bloques A y B (Navegación y Shell)

Debe tener validación visual independiente antes de comenzar la Fase 2. Si el
cambio de navegación no se aprueba visualmente, la Fase 2 puede ejecutarse
conservando el sidebar actual.

### Fase 2 — Bloques C, D y E (Dashboard, componentes y normalización)

Pueden ejecutarse en paralelo parcial. El Bloque D es independiente; los
Bloques C y E dependen parcialmente de D.

### Fase 3 — Bloques F y G (Responsive, accesibilidad y cierre)

Secuencial. Cierra el sprint.

---

## Decisiones aprobadas por el Product Owner

| # | Decisión | Fecha |
|---|----------|-------|
| 1 | Se aprueba reemplazar el sidebar permanente por navegación superior modular en desktop. | 28 ago 2026 |
| 2 | Se aprueba la arquitectura de navegación: Inicio, PQRS ▾, Personas ▾, Documentos ▾, Reportes ▾, Administración ▾. | 28 ago 2026 |
| 3 | En tablet/móvil se conserva navegación colapsable/drawer. | 28 ago 2026 |
| 4 | Se aprueba eliminar la sección "Actividad reciente" del dashboard administrativo. | 28 ago 2026 |
| 5 | Se aprueba incorporar "Vencidas" como métrica del dashboard operativo únicamente si puede derivarse de reglas y consultas existentes. Verificación: confirmado que `Pqr::getIsOverdueAttribute()` y consultas en `ComplementaryController` y `ReportController` ya implementan esta lógica. | 28 ago 2026 |
| 6 | Se aprueban como componentes candidatos iniciales: `x-page-heading`, `x-metric-card`, `x-badge`. No se aprueba crear: `x-data-table`, `x-form-layout`, `x-detail-layout`. | 28 ago 2026 |
| 7 | Los nuevos componentes deben adoptarse incrementalmente, únicamente donde exista un patrón claro. No realizar migración masiva innecesaria. | 28 ago 2026 |
| 8 | El layout principal y el administrativo deben compartir el mismo sistema visual y estructura base, pero pueden mostrar contenido diferente según contexto y permisos. | 28 ago 2026 |
| 9 | La Fase 1 (Bloques A+B) debe tener validación visual independiente antes de comenzar los Bloques C–E. | 28 ago 2026 |
| 10 | Mantener fuera de alcance S11-2 Invitaciones y cualquier stash. | 28 ago 2026 |

---

## Criterios de aceptación globales

1. La navegación superior modular funciona en desktop con menús desplegables
   agrupados por dominio.
2. El drawer móvil conserva todas las opciones actuales.
3. El dashboard operativo muestra 4 métricas clave con jerarquía visual clara.
4. El dashboard administrativo no contiene metadata de desarrollo.
5. Los 3 componentes nuevos (`x-page-heading`, `x-metric-card`, `x-badge`)
   funcionan y se adoptan incrementalmente.
6. Los estilos inline de vistas admin se eliminan.
7. Los layouts principal y admin comparten sistema visual y estructura base.
8. Responsive verificado en 1440, 768, 390 y 320 px.
9. Accesibilidad básica verificada (foco, teclado, contraste, aria attributes).
10. Suite automatizada sin fallos nuevos.
11. `npm run build` y `git diff --check` correctos.
12. Documentación oficial actualizada.

---

## Riesgos

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|------------|
| El cambio de sidebar a navegación superior no se aprueba visualmente. | Media | Alto | Fase 1 independiente; si no se aprueba, ejecutar Fases 2–3 conservando sidebar. |
| Menús desplegables requieren JavaScript complejo para foco y cierre. | Baja | Medio | El CSS de `.nav-config` ya existe; el JS es mínimo (toggle class + event listeners). |
| Migración de estilos inline introduce regresiones visuales. | Media | Medio | Verificación visual por vista migrada; regresión completa de suite. |
| Componentes nuevos colisionan con clases CSS existentes. | Baja | Bajo | Nombres con prefijo `x-` y verificación de unicidad. |
| Añadir Documentos a navegación expone permisos incorrectos. | Baja | Medio | El backend ya valida; solo se cambia la visibilidad en navegación. |

---

## Dependencias

- Sprints anteriores (S10–S12): shell, componentes, estabilización.
- `$navegacion` array del `AppServiceProvider`.
- `ConsultaPqrsContextuales` para métricas del dashboard.
- CSS existente de `.nav-config`, `.nav-submenu`, `.detail-grid`.
- `Pqr::getIsOverdueAttribute()` para métrica de vencidas.

---

## Exclusiones

- S11-2 Invitaciones por correo (pendiente de integración a main).
- Nuevas funcionalidades de negocio.
- Nuevos permisos, roles o capacidades.
- Cambios arquitectónicos.
- Nuevas entidades o cambios del modelo de dominio.
- Inteligencia Artificial.
- API.
- `<x-data-table>`, `<x-form-layout>`, `<x-detail-layout>`.
- Nuevo sistema de diseño o catálogo de componentes.
- Framework JavaScript.
- Rediseño de formularios.
- Internacionalización.
- Auditoría de accesibilidad completa (WCAG 2.1 AA).

---

## Estado oficial

```text
Sprint 13: Completado — pendiente aprobación visual del Product Owner
Bloque A: Completado
Bloque B: Completado
Bloque C: Completado
Bloque D: Completado
Bloque E: Completado
Bloque F: Completado
Bloque G: Completado
```

## Resultados del Bloque G — Validación técnica final

### Ejecución de validación

| Comando | Resultado |
|---|---|
| `php artisan test` | 484 tests, 479 passed, 5 skipped, 2116 aserciones, 0 fallos |
| `npm run build` | ✓ built in 87ms |
| `composer audit` | Sin vulnerabilidades |
| `npm audit` | 0 vulnerabilidades |
| `git diff --check` | Limpio |
| `git status --short` | 41 modificados + 4 nuevos |
| `git diff --stat` | 41 archivos, +643 −652 |

### Decisiones documentadas

1. **Navegación superior sin sidebar ni drawer:** El sidebar permanente se reemplazó por navegación superior modular con menús desplegables en desktop (≥1025px). El drawer se conserva para móvil/tablet (≤1024px).

2. **Color institucional fijo `#1e3a5f`:** La paleta se define por `SiteSetting::COLOR_INSTITUCIONAL`. El usuario no puede alterarla. El campo `color_principal` se conserva en la base de datos solo por compatibilidad con datos existentes.

3. **`color_principal` conservado solo por compatibilidad:** El formulario de configuración ya no expone el campo. El controlador fuerza el valor institucional. El caso de uso `ActualizarConfiguracionCopropiedadInicial` aplica la constante como segunda capa de seguridad.

4. **No se crearon `x-data-table`, `x-form-layout`, `x-detail-layout`:** Decisión aprobada por el Product Owner. Solo se crearon `x-page-heading`, `x-metric-card` y `x-badge`.

5. **Reversión de `x-badge` en PQRS donde afectaba N+1:** El componente se adoptó incrementalmente en vistas administrativas. En PQRS operativas se mantuvo el patrón existente para evitar consultas adicionales.

6. **Uso de `CalendarioLaboralColombia::hoy()` en la semántica temporal del dashboard:** La métrica de vencidas y los filtros de por_vencer/vencidas utilizan el calendario laboral colombiano en lugar de `Carbon::today()`, consistente con la lógica existente en `Pqr::getIsOverdueAttribute()`.

### Archivos modificados en Bloque G

- `docs/04-desarrollo-agil/sprint-13-simplificacion-visual.md` — estado oficial y resultados
- `docs/03-tecnica/interfaz-web.md` — actualización de navegación y componentes
- `docs/01-producto/linea-base-estado-actual.md` — actualización de estado técnico
- `docs/README.md` — referencia a Sprint 13

### Resumen de cambios del Sprint 13

| Bloque | Descripción | Estado |
|---|---|---|
| A | Navegación superior modular | Completado |
| B | Application Shell unificado | Completado |
| C | Dashboard operativo (4 métricas) | Completado |
| D | Sistema visual mínimo (3 componentes) | Completado |
| E | Normalización de vistas administrativas | Completado |
| F | Responsive y accesibilidad | Completado |
| G | Validación técnica y cierre | Completado |
| — | Paleta institucional #1e3a5f | Completado |
| — | Eliminación selector libre de color | Completado |

### Confirmación de cierre técnico

Sprint 13 queda técnicamente listo para revisión humana antes del commit. No se ejecutó commit, push ni stash. S11-2 Invitaciones no fue restaurado.
