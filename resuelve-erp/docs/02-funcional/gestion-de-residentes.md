# Gestión de residentes

## 1. Propósito

Describir la gestión de Unidades Privadas, Personas y vínculos residenciales de
una Copropiedad, incluida la importación masiva de Unidades Privadas desde
XLSX, con sus reglas de contexto, autorización y trazabilidad.

## 2. Alcance implementado

### Unidades Privadas, Personas y vínculos

- **Unidades Privadas:** alta, edición, consulta, desactivación y reactivación
  administrativa (sin eliminación física) de los inmuebles de la Copropiedad
  activa (`codigo`, `numero_nombre`, `torre_bloque`, `tipo`). El código es
  único por Copropiedad (índice `unidades_copropiedad_codigo_unique`,
  comparación insensible a mayúsculas).
- **Personas y vínculos:** Personas del contexto y vínculos
  propietario/residente con Unidades Privadas (`tipo_vinculo`, estado y
  vigencia), separados de las Membresías y permisos de acceso: operar la
  residencia no concede ni retira acceso al sistema.
- **Autorización:** `residentes.gestionar` verificado en backend (403 además
  de la ocultación de interfaz), con Membresía vigente y contexto activo.
- **Contexto:** `organizacion_id` y `copropiedad_id` están excluidos de
  `$fillable` y se fijan siempre en backend; los recursos de otras
  Copropiedades son invisibles (bindings contextuales).

### Importación masiva de Unidades Privadas desde XLSX (HU-RYP-03, Sprint 22)

Entrada en **Residentes → Unidades → «Importar»** (`management.unidades.importar`):

- **Solo altas:** la importación crea Unidades Privadas; no modifica ni elimina
  registros existentes (una fila con código existente se omite como duplicada).
- **Límites del archivo:** XLSX de hasta **2 MB** (`mimes:xlsx`) y máximo
  **1000 filas de datos**, sin contar el encabezado. Si excede cualquiera de
  los límites, el archivo se rechaza completo antes de crear registros.
- **Formato:** la primera fila debe contener la columna **Código**
  (obligatoria); las opcionales **Número / Nombre**, **Torre / Bloque** y
  **Tipo** se reconocen también con variantes de encabezado («número»,
  «nombre», «torre», «bloque»). Las demás columnas se ignoran; la Organización
  y la Copropiedad se asignan del contexto activo, nunca del archivo.
- **Validación por fila:** cada fila se valida con las reglas del alta
  individual (`GestionarUnidadPrivada`); una fila inválida no impide procesar
  las demás y se reporta con número de fila, campo y motivo.
- **Duplicados:** un código repetido dentro del archivo o ya existente en la
  Copropiedad (insensible a mayúsculas) se omite y se reporta como duplicado;
  la colisión con el índice único durante la concurrencia se trata igual (sin
  abortar la importación).
- **Resultado:** resumen de filas leídas, creadas, duplicadas omitidas e
  inválidas, con estados de **éxito**, **parcial** (filas no procesadas) y
  **error** (sin altas), y tabla de filas no procesadas.
- **Reporte de errores descargable:** XLSX con las columnas Fila, Campo y
  Motivo, con la identidad visual de las exportaciones existentes
  (`nombre_conjunto` y color institucional).
- **Trazabilidad:** cada importación se audita (`residencia.unidad.importar`)
  con actor, archivo y totales; cada unidad creada conserva su auditoría de
  alta (`residencia.unidad.crear`).

### Ciclo de vida completo (HU-CONF-03, Sprint 24)

- **Form Requests** para alta y edición de Unidades Privadas y Personas
  (`StoreUnidadPrivadaRequest`, `UpdateUnidadPrivadaRequest`,
  `StorePersonaRequest`, `UpdatePersonaRequest`), con la autorización
  (`residentes.gestionar`) antes de la validación y las reglas reutilizadas de
  los casos de uso (fuente única, sin duplicar).
- **Edición de Personas:** alta, consulta, edición, baja lógica, papelera y
  restauración completas; la edición se audita
  (`residencia.persona.actualizar`) y no altera los vínculos.
- **Papelera y restauración:** las unidades y personas desactivadas se listan
  en su papelera (`management.unidades.papelera`, `management.personas.papelera`)
  y se restauran con las acciones de reactivación existentes, sin eliminación
  física. El mismo ciclo aplica a las Etiquetas
  (`management.tags.papelera`).

## 3. Notas de implementación

- El libro se carga en memoria con PhpSpreadsheet en modo solo lectura de
  datos; los límites de filas y tamaño reducen el riesgo de memoria, pero no
  lo eliminan.
- La plantilla XLSX descargable y la importación de Personas o vínculos
  quedan fuera del alcance aprobado.

## 4. Evidencia

- Casos de uso: `app/Application/Residencia/GestionarUnidadPrivada.php`
  (reglas y unicidad reutilizadas), `ImportarUnidadesPrivadas.php`,
  `AutorizacionResidencia.php`.
- Controlador y vistas: `app/Http/Controllers/UnidadPrivadaController.php`
  (`importar`, `importarStore`, `reporteErrores`),
  `resources/views/management/unidades-importar.blade.php`,
  `resources/views/management/unidades.blade.php`.
- Rutas: `routes/web.php` (`management.unidades.importar*`).
- Modelos: `app/Models/UnidadPrivada.php`, `app/Models/Persona.php`,
  `app/Models/VinculoUnidad.php`.
- Pruebas: `tests/Feature/UnidadesPrivadasImportacionTest.php` (13 pruebas) y
  `tests/Feature/UnidadesPrivadasGestionTest.php`, `VinculoUnidadGestionTest.php`,
  `ResidenciaEstadosYFiltrosTest.php`, `ConsultasResidenciaContextualesTest.php`.

## Control documental

- **Versión:** v1.0
- **Fecha de creación:** 5 de noviembre de 2026
- **Fecha de última actualización:** 5 de noviembre de 2026
