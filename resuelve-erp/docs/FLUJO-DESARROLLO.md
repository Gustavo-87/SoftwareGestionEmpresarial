# Flujo de Desarrollo — Resuelve

## Visión general

Este documento define el flujo de trabajo metodológico para el equipo de agentes de Resuelve. Establece cómo se procesan las solicitudes de funcionalidad, desde la demanda hasta la integración.

## Flujo supervisado (normal)

```
1. MAIN: Recibe solicitud
   └─ Usa: wayfinder (explora codebase)
   └─ Usa: grill-with-docs (revisa docs existentes)

2. ARQUITECTURA: Evalúa impacto
   └─ Usa: domain-modeling (modelado de dominio)
   └─ Usa: codebase-design (diseño técnico)
   └─ Entrega: especificación técnica

3. MAIN: Crea especificación
   └─ Usa: to-spec (convierte a spec formal)
   └─ Aprueba alcance

4. MAIN: Genera tickets
   └─ Usa: to-tickets (descompone en tickets)
   └─ Define criterios de aceptación

5. DEV: Implementa
   └─ Usa: implement (flujo guiado)
   └─ Usa: tdd (pruebas primero)
   └─ Archivos permitidos: app/, database/, routes/, tests/

6. DEV: Code review
   └─ Usa: requesting-code-review
   └─ ARQUITECTURA: receiving-code-review

7. DOCUMENTACIÓN: Actualiza docs
   └─ Usa: to-spec (actualiza especificaciones)
   └─ Actualiza: docs/

8. MAIN: Merge autorizado
   └─ Verifica: CI verde
   └─ Merge a resuelve/main
```

## Flujo AFK (autónomo)

### Condiciones mínimas para autonomía

- ✅ Especificación aprobada por MAIN
- ✅ Tickets con criterios de aceptación claros
- ✅ Archivos permitidos definidos
- ✅ Pruebas definidas en la spec

### Límites AFK

- ❌ No puede cambiar arquitectura
- ❌ No puede crear entidades nuevas sin aprobación
- ❌ No puede hacer push
- ❌ No puede hacer merge
- ❌ No puede ampliar alcance

## Skills utilizadas

| Skill | Agente | Cuándo se usa |
|---|---|---|
| `wayfinder` | MAIN, DEV | Exploración del codebase |
| `grill-with-docs` | MAIN, DOCUMENTACIÓN | Revisión de documentación existente |
| `to-spec` | MAIN, DOCUMENTACIÓN | Creación de especificaciones |
| `to-tickets` | MAIN | Generación de tickets |
| `implement` | DEV | Flujo de implementación guiado |
| `tdd` | DEV | Desarrollo dirigido por pruebas |
| `domain-modeling` | ARQUITECTURA | Modelado de dominio |
| `codebase-design` | ARQUITECTURA | Diseño técnico |
| `code-review` | DEV, ARQUITECTURA | Revisión de código |

## Reglas fundamentales

1. Las reglas actuales de Resuelve tienen prioridad sobre cualquier Skill
2. No se modifica código del producto en la definición de flujos
3. Se aplican solo los flujos que agregan valor real
4. Se mantiene simplicidad en la documentación
