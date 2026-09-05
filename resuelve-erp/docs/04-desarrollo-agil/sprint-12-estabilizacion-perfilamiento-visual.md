# Sprint 12 — Estabilización, dependencias y perfilamiento visual

## Control documental

- **Versión:** v1.3
- **Estado:** Completado
- **Fecha de creación:** 26 de agosto de 2026
- **Última actualización:** 27 de agosto de 2026
- **Responsable:** Documentación Resuelve
- **Aprobación:** Aprobado por el Product Owner — 27 de agosto de 2026

## Objetivo

Consolidar la estabilidad, consistencia visual y salud técnica de Resuelve
mediante corrección de errores, revisión de dependencias, mejoras visuales
acotadas y reducción puntual de deuda técnica.

## Contexto

- Sprint 10 completado con alcance C.1 a C.3.6.
- Sprint 11 completado con alcance C.3.7.1 a C.3.7.3, S11-1 y S11-2,
  donde S11-2 (Invitaciones por correo) permanece pendiente de integración a
  `main`.
- Sprint 12 no agrega nuevas funcionalidades y se centra en estabilizar el
  producto actual.

## Estado técnico de referencia

Al momento de la última actualización de este documento:

- 477 tests
- 2114 aserciones
- 0 fallos
- 5 skipped preexistentes
- Build frontend correcto
- Composer audit: 0 vulnerabilidades
- NPM audit: 0 vulnerabilidades

## Alcance

### A. Correcciones funcionales

Prioridad crítica.

#### A.1 Corrección de `UsuarioGlobalCrudTest::crear_administema`

Estado: **Completado.**

El fallo conocido en la prueba fue diagnosticado y corregido durante el
Sprint 12.

#### A.2 Vista 404 personalizada

Estado: **Completado.**

Se creó una vista `404.blade.php` coherente con la vista 403 existente.
La consistencia entre páginas de error queda verificada como parte del
Sprint 12.

### B. Dependencias y seguridad

Prioridad alta.

Revisión y actualización controlada de dependencias vulnerables o
desactualizadas compatibles con la versión actual del proyecto.

#### Dependencias actualizadas

| Paquete | Acción | Resultado |
| --- | --- | --- |
| `guzzlehttp/guzzle` | Actualizado | Sin vulnerabilidades |
| `league/commonmark` | Actualizado | Sin vulnerabilidades |
| `nanoid` | Actualizado | Sin vulnerabilidades |
| `postcss` | Actualizado | Sin vulnerabilidades |

#### Dependencias no actualizadas deliberadamente

No se realizaron actualizaciones innecesarias de `laravel/framework`,
`vite`, `tailwindcss` ni otras dependencias únicamente por existir versiones
más nuevas. Se priorizó la estabilidad sobre la actualización preventiva.

#### Dependencias excluidas inicialmente

- PHPUnit 12 a 13
- concurrently 9 a 10
- otros saltos mayores sin decisión posterior del Product Owner

#### Validación

- `composer audit`: 0 vulnerabilidades.
- `npm audit`: 0 vulnerabilidades.
- Suite automatizada: sin fallos nuevos.
- Build frontend: correcto.

### C. Perfilamiento visual acotado

Prioridad media.

No se realizó un rediseño general.

#### C.1 Componente reutilizable `empty-state`

Estado: **Completado.**

Se creó un componente para normalizar estados vacíos en la interfaz.

#### C.2 Componente reutilizable `notice`

Estado: **Completado.**

Se creó un componente para normalizar mensajes de éxito y error.

#### C.3 Reducción de lógica Blade repetida

Estado: **Completado.**

La lógica repetida de vigencia documental fue trasladada al modelo
`Documento::versionVigente()`, eliminando duplicación en vistas.

#### C.4 Consistencia de vistas de error 403 y 404

Estado: **Completado.**

La consistencia entre páginas de error quedó cubierta mediante la vista 404
creada en A.2.

### D. Deuda técnica y documentación

Prioridad baja.

- Actualizar la línea base documental cuando los cambios del Sprint 12
  estén validados.
- Mantener la arquitectura actual alineada con el estado implementado.
- Documentar limitaciones conocidas que permanezcan al cierre.

Estado: **Completado.**

Se actualizaron los siguientes documentos como parte del Bloque D:

- `docs/01-producto/linea-base-estado-actual.md`: se incorporó el alcance consolidado del Sprint 12, las métricas de verificación, la delimitación organizacional actualizada, la incidencia de `versionVigente()` en la capa de dominio y los componentes `empty-state` y `notice` en la interfaz.
- `docs/05-arquitectura/arquitectura-actual.md`: se actualizó la sección de dominio y persistencia, presentación, frontend, dependencias arquitectónicas y hallazgos para reflejar `versionVigente()`, los componentes de interfaz, la vista 404, las dependencias actualizadas y el estado técnico de referencia del Sprint 12.
- `docs/README.md`: se actualizó el estado del Sprint 12 a "Completado".
- `docs/04-desarrollo-agil/sprint-12-estabilizacion-perfilamiento-visual.md`: se actualizó su propio control documental y se cerró el Bloque D.

## Fuera de alcance del Sprint 12

- Nuevas funcionalidades.
- IA.
- API.
- Nuevos módulos.
- Cambios arquitectónicos mayores.
- Desarrollo adicional de Invitaciones.
- Integración de S11-2 a `main`, salvo decisión explícita posterior del
  Product Owner.
- Rediseño completo de la aplicación.

## Criterios de cierre

El Sprint 12 podrá considerarse cerrado cuando:

1. La suite automatizada quede sin fallos nuevos.
   **Estado:** Cumplido (477 tests, 0 fallos).
2. El fallo funcional conocido (`UsuarioGlobalCrudTest::crear_administema`)
   esté resuelto.
   **Estado:** Cumplido.
3. Las vulnerabilidades de prioridad alta incluidas en alcance estén
   resueltas o documentadas con justificación.
   **Estado:** Cumplido (composer audit y npm audit limpios).
4. Los cambios visuales aprobados estén implementados y validados.
   **Estado:** Cumplido (empty-state, notice, 403/404, vigencia documental).
5. No existan regresiones funcionales conocidas.
   **Estado:** Cumplido.
6. La documentación oficial refleje el estado final real.
   **Estado:** Cumplido (Bloque D cerrado).

## Notas de seguimiento

### Dependencia del estado anterior

El estado documental previo se encuentra consolidado en:

- Sprint 10: `sprint-10-experiencia-producto-consola-operativa.md`
- Sprint 11: `sprint-11-multi-copropiedad-consola-administrativa.md`

### Riesgos remanentes

- La integración de S11-2 a `main` queda deliberadamente fuera del Sprint
  12 para no mezclar estabilización con nueva integración funcional.

## Estado oficial

```text
Sprint 12: Completado
Bloque A: Completado
Bloque B: Completado
Bloque C: Completado
Bloque D: Completado
Criterios de cierre: 6 de 6 cumplidos
```

## Aprobación y cierre

- **Aprobación del Product Owner:** 27 de agosto de 2026
- **Fecha de cierre formal:** 27 de agosto de 2026
- **Métricas técnicas finales:** 477 tests, 2114 aserciones, 0 fallos, 5 skipped preexistentes, composer audit 0 vulnerabilidades, npm audit 0 vulnerabilidades, build frontend exitoso
- **Commits del cierre:**
  - `5e60895` — fix(base): consolida membresías y tests C.3.7.3
  - `592aeca` — feat(sprint-12): estabilización y perfilamiento visual
  - `c70a520` — docs: alinea sprints 10-12 y estado actual del proyecto

El Sprint 12 queda cerrado formalmente. No existen pendientes abiertos de producto para este sprint.
