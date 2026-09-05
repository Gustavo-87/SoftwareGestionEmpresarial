# AGENTS.md

## 1. Comportamiento del agente de IA
- Actuar de forma determinista, analítica y sin asumir comportamientos o características no documentadas.
- Tratar la carpeta `docs/` como la fuente oficial de diseño, comportamiento y decisiones aprobadas del proyecto.
- Git, las migraciones y las pruebas verifican el estado técnico; `docs/` define diseño; `RESUELVE_ESTADO_ACTUAL.md` registra el estado operativo. Ninguna fuente aislada sustituye a las demás.

## 2. Flujo obligatorio de trabajo
1. Leer `docs/README.md` para identificar el contexto y el sprint asignado.
2. Consultar únicamente los documentos de `docs/` relacionados con la tarea específica.
3. Al finalizar un sprint o requerir uno nuevo, proponer el alcance del siguiente sprint basándose exclusivamente en el `Product Backlog` y esperar la aprobación explícita del Product Owner.
4. Implementar estrictamente el alcance delimitado y aprobado, incluida la experiencia visible cuando exista interacción de Usuario; no limitar la entrega al backend si el alcance requiere que un Usuario consulte o ejecute la capacidad.
5. Actualizar la documentación afectada dentro de `docs/`.
6. Ejecutar y validar las pruebas de verificación necesarias.

## 2.1. Experiencia de Producto
- Consultar `docs/01-producto/experiencia-de-producto.md` al refinar o desarrollar toda capacidad con interacción humana.
- Considerar dentro de cada Sprint la experiencia visible necesaria, incluidos contexto, acciones, información y estados relevantes para completar la tarea.
- No crear una línea de trabajo o Épica de frontend separada de las capacidades de negocio aprobadas.
- No duplicar reglas de negocio, consultas de aplicación ni decisiones de autorización en Blade, JavaScript o futuros clientes.
- Mantener en el backend la validación efectiva de contexto, membresía, permiso, capacidad y pertenencia del recurso. Ocultar o deshabilitar una acción en la interfaz no constituye autorización.
- Consumir las consultas y casos de uso existentes. Cualquier operación nueva debe ubicarse en la capa correspondiente y justificarse dentro del alcance aprobado.

## 3. Restricciones
- No avanzar a sprints posteriores ni asumir el contenido de un nuevo sprint. El Sprint no se crea automáticamente y debe ser aprobado por el Product Owner.
- No alterar la arquitectura ni introducir abstracciones o dependencias sin justificación previa.
- No eliminar archivos ni carpetas.

## 4. Reglas de seguridad
- Aplicar estándares OWASP y buenas prácticas de seguridad en Laravel (validaciones en backend, sanitización y autorización contextual).

## 5. Reglas de Git
- No realizar `git commit` ni `git push`.
- No modificar la historia de Git ni crear/cambiar ramas sin autorización.

## 6. Cuándo detenerse y pedir confirmación
- Cuando la documentación en `docs/` sea insuficiente, ambigua o contradictoria.
- Para presentar la propuesta de un nuevo sprint basada en el `Product Backlog` y esperar su aprobación antes de crearlo o desarrollarlo.
- Antes de proponer o implementar cualquier cambio arquitectónico.
- Si una prueba o comando falla y requiere modificar el alcance planificado.

## 7. Uso de la documentación en `docs/`
- Consultar `docs/` siempre antes de analizar o escribir código.
- Nunca reconstruir o deducir la arquitectura, el dominio o los modelos basándose únicamente en la lectura del código fuente.
- Mantener la trazabilidad y sincronización entre el código y la documentación.

## 8. Idioma operativo

- Idioma operativo obligatorio: español.
- Los informes, planes y explicaciones se redactan en español.
- Solo se conservan en inglés identificadores técnicos, código,
  comandos, rutas, nombres propios y mensajes literales de error.
- Cada elemento técnico relevante en inglés debe explicarse en español.
- No mezclar inglés y español en texto narrativo.
