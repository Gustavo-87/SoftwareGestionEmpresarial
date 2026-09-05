# Sprint 3 — Contextualización de PQRS y aislamiento multi-copropiedad

## Control documental

- **Versión:** v1.0
- **Estado:** Completado
- **Fecha de cierre:** 31 de julio de 2026
- **Fuente activa de autorización:** `users.role`
- **Multi-copropiedad visible:** no habilitada

## 1. Objetivo y resultado

El Sprint 3 contextualizó el agregado operativo PQRS por Organización y
Copropiedad sin cambiar rutas, navegación, vistas, Policies ni permisos
observables. Las 15 PQRS existentes fueron migradas al contexto institucional
inicial y las nuevas escrituras asignan el contexto de forma explícita desde
`ContextoOperativo`.

La aplicación mantiene una sola Copropiedad real y no ofrece selector ni
cambio de contexto. El aislamiento se demostró con una segunda Organización y
Copropiedad creadas exclusivamente en bases aisladas de pruebas.

## 2. Componentes implementados

### Migraciones

- `2026_07_31_130000_add_context_to_pqrs_table.php`: añadió columnas nullable,
  índice, FK de Organización y FK compuesta de Copropiedad.
- `2026_07_31_130100_make_pqr_context_required.php`: aborta si existen PQRS sin
  contexto y convierte ambas columnas a `NOT NULL` sin alterar FKs ni índices.

Ambas migraciones son reversibles. La segunda fue revertida y reaplicada sobre
el entorno local sin pérdida de datos.

### Modelo y escritura

- `Pqr` pertenece a `Organizacion` y `Copropiedad`.
- Ambas entidades exponen sus PQRS.
- Los IDs contextuales no forman parte de la asignación masiva HTTP.
- `PqrController::store()` asocia explícitamente el contexto operativo.
- `PqrFactory` genera contextos coherentes para pruebas.

### Backfill

El comando `resuelve:contextualizar-pqrs`:

- usa únicamente el contexto verificado de `SiteSetting`;
- bloquea y valida el conjunto completo antes de escribir;
- rechaza referencias parciales, inexistentes, cruzadas o de otro ámbito;
- ejecuta el backfill en una transacción;
- conserva todos los campos funcionales y timestamps;
- es idempotente y diagnóstico.

La primera ejecución contextualizó 15 PQRS. La segunda reconoció 15 existentes,
modificó 0 y reportó 0 inconsistencias.

### Consultas y resolución

`ConsultaPqrsContextuales` es el punto explícito para construir consultas y
resolver una PQRS por Organización y Copropiedad. Se usa en:

- listado, filtros, paginación, métricas y gráfica de PQRS;
- Route Model Binding de `{pqr}`;
- binding de adjuntos privados;
- respuestas, comentarios, acciones rápidas, etiquetas y encuestas;
- CSV, XLSX y PDF;
- carga de trabajo;
- recordatorios programados.

Una PQRS externa y un ID inexistente producen la misma respuesta `404` antes de
evaluar la Policy. Los IDs de Organización o Copropiedad enviados por HTTP son
ignorados.

### Recordatorios

El closure de `routes/console.php` fue reemplazado por el comando explícito
`EnviarRecordatoriosPqrs`. El comando enumera Copropiedades, construye un
contexto explícito validado para cada una y consulta las PQRS mediante
`ConsultaPqrsContextuales`. Conserva la ventana de vencimiento y evita repetir
un recordatorio enviado el mismo día.

## 3. Resultados de validación

- Suite completa: 120 pruebas, 532 aserciones y 0 fallos.
- Pruebas específicas de consumidores restantes: 4 pruebas y 22 aserciones.
- Pruebas de aislamiento HTTP: 5 pruebas y 31 aserciones.
- Migraciones aplicadas: contexto nullable en lote 10 y cierre `NOT NULL` en
  lote 11.
- Sintaxis PHP: todos los archivos nuevos o modificados sin errores.
- `git diff --check`: sin errores.
- Aplicación local: `HTTP 302` hacia `/iniciar-sesion`.

Conteos finales reales:

| Recurso | Conteo |
| --- | ---: |
| Usuarios | 4 |
| PQRS | 15 |
| PQRS con contexto | 15 |
| PQRS sin contexto | 0 |
| Organizaciones | 1 |
| Copropiedades | 1 |
| Site settings | 1 |

No se modificaron asunto, descripción, estado, usuarios, responsables, tipos,
fechas ni timestamps durante el backfill.

## 4. Evidencia de aislamiento

Las pruebas con dos contextos demuestran que:

- listados, filtros, paginación y métricas no mezclan PQRS;
- residentes solo ven sus propias PQRS del contexto activo;
- show, edit, update, destroy y acciones relacionadas devuelven `404` para otro
  tenant;
- adjuntos externos no pueden descargarse;
- CSV y XLSX no incluyen datos externos y PDF comparte la misma consulta;
- la carga de trabajo cuenta únicamente el contexto activo;
- los recordatorios procesan cada ámbito validado sin duplicados;
- una consulta interna contextual no recupera una PQRS externa;
- `users.role` conserva exactamente su función de autorización.

## 5. Búsqueda estática y usos justificados

No quedan consultas globales de PQRS en controladores web ni reportes. Los usos
directos restantes son deliberados:

- `ConsultaPqrsContextuales`: único constructor central de la consulta base.
- `ContextualizarPqrs`: inspecciona el conjunto completo durante el backfill
  histórico y deja de ser un consumidor operativo después de la migración.
- Migración `130100`: consulta exclusivamente la existencia de nulos antes del
  cambio estructural.
- `DatabaseSeeder`: elimina IDs obtenidos previamente desde una consulta
  contextual de demostración.
- `UserManagementController`: comprueba globalmente si una identidad global
  conserva cualquier PQRS antes de permitir su eliminación; no devuelve datos.
- Relación `assignedPqrs`: solo se consume en carga de trabajo con restricciones
  contextuales añadidas por `ConsultaPqrsContextuales`.

## 6. Criterios cumplidos

- Toda PQRS real tiene Organización y Copropiedad obligatorias.
- Las nuevas PQRS reciben contexto explícito.
- Existe FK compuesta para impedir cruces de ámbito.
- No existe fallback global ante contexto inválido.
- Route Model Binding oculta recursos externos con `404`.
- Reportes, métricas, carga y recordatorios están contextualizados.
- No se modificaron Policies, Roles o Permisos.
- No existe selector ni segunda Copropiedad real.
- La interfaz y el comportamiento visible permanecen iguales.

## 7. Riesgos residuales y bloqueo

Las tablas auxiliares —respuestas, comentarios, actividades, adjuntos,
etiquetas, encuestas, reglas y auditoría— todavía dependen físicamente de
`pqr_id` y no almacenan Organización y Copropiedad propias. Su acceso actual
queda protegido por la PQRS contextual y por FKs, pero no satisface todavía el
objetivo futuro de contexto explícito en cada recurso.

Por esta razón, **continúa bloqueado habilitar multi-copropiedad visible, un
selector o una segunda Copropiedad real** hasta contextualizar físicamente las
tablas auxiliares y validar sus escrituras, backfill y restricciones compuestas.

También quedan fuera de este sprint la contextualización comercial, API, IA,
Gestión Documental y cualquier cambio de la fuente activa de autorización.
