# Sprint 22 — Importación masiva de Unidades Privadas (HU-RYP-03)

## Estado y control documental

- **Estado:** Completado. HU-RYP-03 implementada de principio a fin y
  verificada localmente el 5 de noviembre de 2026. La comprobación posterior
  en GitHub Actions se registra en §3, con sus advertencias.
- **Fecha de cierre técnico:** 5 de noviembre de 2026.
- **Origen:** HU-RYP-03 (Product Backlog, Épica 4 — Residentes y Propietarios),
  incorporada y aprobada por el Product Owner el 5 de noviembre de 2026 para el
  requisito de importación masiva de la guía final.
- **Límites aprobados:** máximo **1000 filas de datos** (sin encabezado) y
  archivo XLSX de hasta **2 MB**. Reporte de errores XLSX aprobado; plantilla
  descargable fuera de alcance.
- **Base:** cierre del Sprint 21 (HU-API-01) y suite completa en verde.

## 1. Objetivo y alcance

Cargar de una vez el catálogo de Unidades Privadas de una Copropiedad desde un
archivo XLSX, con validación por fila, control de duplicados y reporte
descargable de errores, reutilizando las reglas y permisos existentes. Solo
altas: la importación no modifica ni elimina registros. Sin Personas, vínculos,
compras, inventario ni entidades nuevas.

## 2. Implementación

- **Caso de uso** `app/Application/Residencia/ImportarUnidadesPrivadas.php`:
  lectura del XLSX con PhpSpreadsheet en modo solo lectura de datos;
  rechazo completo del archivo —antes de crear nada— si excede 1000 filas de
  datos o no tiene la columna «Código»; validación de cada fila con las reglas
  del alta individual (`GestionarUnidadPrivada::crear`, reutilizado sin
  duplicar reglas); duplicados omitidos y reportados (en el archivo o
  existentes en la Copropiedad, insensible a mayúsculas) con manejo de la
  colisión del índice único durante la concurrencia; contexto de
  Organización/Copropiedad fijado en backend; auditoría
  `residencia.unidad.importar` con actor, archivo y totales.
- **Interfaz** en Residentes → Unidades → «Importar»
  (`management.unidades.importar`), protegida por `residentes.gestionar` en
  backend (403), con resumen (leídas, creadas, duplicadas, inválidas), estados
  de éxito/parcial/error y tabla de filas no procesadas.
- **Reporte de errores descargable** en XLSX (Fila, Campo, Motivo) con la
  identidad visual de las exportaciones existentes
  (`management.unidades.importar.reporte`).
- **Regla de unicidad compartida:** `GestionarUnidadPrivada::codigoExistente()`
  y la constante del mensaje canónico de duplicado, reutilizados por el alta
  individual y por la importación (sin duplicar la consulta).

## 3. Verificación (5 de noviembre de 2026)

| Verificación | Resultado |
| --- | --- |
| Pruebas de importación (`tests/Feature/UnidadesPrivadasImportacionTest.php`) | **13 pruebas aprobadas** (61 aserciones) |
| Suite completa (configuración aislada, SQLite en memoria) | **761 pruebas: 752 aprobadas y 9 omitidas (MySQL)**, 3613 aserciones, sin regresiones |
| `vendor/bin/pint --test` | **Aprobado** |

Pruebas incluidas: importación válida en el contexto activo; filas inválidas
mezcladas (las válidas continúan, con fila/campo/motivo); duplicados internos
y existentes con distinta caja; mismo código en otra Copropiedad (no es
duplicado); solo altas (no modifica existentes); exceso de filas y de tamaño
sin inserciones; encabezados inválidos sin inserciones; usuario sin permiso
(403); contexto nunca tomado del archivo; auditoría con totales; reporte de
errores descargable y redirección sin reporte.

**Ámbito:** verificación **local** histórica.

**Verificación posterior en GitHub Actions:** el CI del PR #3 terminó con
resultado satisfactorio el 5 de noviembre de 2026, con advertencias en
las pruebas. Evidencia, revisión y resultados en
[Integración continua](../08-despliegue/integracion-continua.md).
Las cifras locales anteriores se conservan como registro histórico
de la verificación de este sprint.

## 4. Decisiones

1. **Límites (aprobados por el Product Owner):** 2 MB y 1000 filas de datos,
   justificados con `upload_max_filesize`/`post_max_size` de 100M y
   `memory_limit` de 128M (por defecto) del entorno Sail, y con el consumo en
   memoria de PhpSpreadsheet.
2. **Solo altas:** una fila con código existente se omite como duplicada; no
   hay operaciones de modificación ni eliminación desde el archivo.
3. **Duplicados concurrentes:** la violación del índice único
   `unidades_copropiedad_codigo_unique` se clasifica como duplicada omitida
   mediante el mensaje canónico del caso de uso, sin abortar la importación.
4. **Estado del resultado:** «éxito» sin filas no procesadas, «parcial» con
   inválidas o duplicadas y altas efectivas, «error» sin ninguna alta.
5. **Reporte de errores en sesión:** la descarga XLSX se genera desde el
   resultado de la última importación del usuario; sin reporte, la ruta
   redirige al formulario.

## 5. Exclusiones y notas

- Plantilla XLSX descargable, formato CSV, Personas, vínculos, compras,
  inventario y otras entidades (fuera del alcance aprobado).
- **Riesgo de memoria:** los límites reducen el riesgo pero no lo eliminan (el
  libro se carga en memoria); el entorno Sail no fue verificado en ejecución.
- No se ejecutaron migraciones sobre la base de datos local ni se modificaron
  modelos, migraciones, permisos ni dependencias (PhpSpreadsheet ya estaba
  instalado).

## 6. Evidencia

- Documentación funcional: [Gestión de residentes](../02-funcional/gestion-de-residentes.md).
- Casos de uso: `ImportarUnidadesPrivadas.php`, `GestionarUnidadPrivada.php`,
  `AutorizacionResidencia.php`.
- Interfaz: `UnidadPrivadaController.php`,
  `resources/views/management/unidades-importar.blade.php`,
  `resources/views/management/unidades.blade.php`.
- Rutas: `routes/web.php` (`management.unidades.importar*`).
- Pruebas: `tests/Feature/UnidadesPrivadasImportacionTest.php`.

## Control documental

- **Versión:** v1.1
- **Fecha de creación:** 5 de noviembre de 2026
- **Fecha de última actualización:** 5 de noviembre de 2026
