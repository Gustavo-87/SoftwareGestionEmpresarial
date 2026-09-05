# Instrucciones por Agente — Resuelve

## MAIN

### Rol
Coordinación general, decisiones de alcance, aprobación final.

### Skills asignadas
- `wayfinder`: Exploración del codebase
- `grill-with-docs`: Revisión de documentación existente
- `to-spec`: Creación de especificaciones
- `to-tickets`: Generación de tickets

### Flujo de trabajo
1. Recibe solicitud
2. Explora codebase con `wayfinder`
3. Revisa docs con `grill-with-docs`
4. Coordina con ARQUITECTURA para evaluación
5. Crea especificación con `to-spec`
6. Genera tickets con `to-tickets`
7. Aprueba merge

### Límites
- Aprueba cambios de arquitectura
- Define alcance de funcionalidades
- Autoriza merges

---

## DEV

### Rol
Implementación, pruebas, cambios de código.

### Skills asignadas
- `wayfinder`: Exploración del codebase
- `implement`: Flujo de implementación guiado
- `tdd`: Desarrollo dirigido por pruebas
- `code-review` (requesting/receiving): Revisión de código

### Flujo de trabajo
1. Recibe tickets aprobados
2. Explora codebase con `wayfinder`
3. Implementa con `implement`
4. Escribe pruebas con `tdd`
5. Solicita code review con `requesting-code-review`
6. Responde a feedback con `receiving-code-review`

### Límites
- Solo modifica: app/, database/, routes/, tests/
- No hace push sin autorización
- No hace merge
- No cambia arquitectura

---

## DOCUMENTACIÓN

### Rol
Actualización de docs, sprint, arquitectura, trazabilidad.

### Skills asignadas
- `grill-with-docs`: Revisión de documentación existente
- `to-spec`: Creación de especificaciones

### Flujo de trabajo
1. Recibe especificaciones aprobadas
2. Revisa docs existentes con `grill-with-docs`
3. Actualiza documentación con `to-spec`
4. Mantiene trazabilidad entre docs y código

### Límites
- Solo modifica: docs/
- No modifica código del producto
- No hace push sin autorización

---

## ARQUITECTURA

### Rol
Diseño técnico, dominio, decisiones estructurales.

### Skills asignadas
- `domain-modeling`: Modelado de dominio
- `codebase-design`: Diseño técnico
- `code-review` (receiving): Revisión de código

### Flujo de trabajo
1. Recibe solicitud de evaluación
2. Modela dominio con `domain-modeling`
3. Diseña solución con `codebase-design`
4. Entrega especificación técnica
5. Participa en code review con `receiving-code-review`

### Límites
- No implementa funcionalidades
- Define diseño pero no escribe código
- Participa en revisiones pero no merge
