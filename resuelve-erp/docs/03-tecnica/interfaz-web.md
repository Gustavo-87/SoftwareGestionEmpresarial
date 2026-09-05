# Línea base de la interfaz web

## C.3.4.3 — Catálogo contextual de etiquetas cerrado y aprobado

La pantalla de Herramientas administra el catálogo de la Copropiedad activa:
creación, edición de nombre y color, desactivación y reactivación. No expone
eliminación física. El backend resuelve cada etiqueta contextualmente y exige
`gestion.herramientas_gestionar` antes de resolver o validar detalles.

El estado `activo` determina la disponibilidad para nuevas asignaciones. Una
etiqueta inactiva conserva sus asociaciones y se identifica textualmente en el
expediente. Los formularios comunican el efecto retroactivo de nombre y color,
mantienen foco de error y confirman la desactivación mediante el diálogo común.
La experiencia es responsive, accesible y no depende exclusivamente del color.

## Propósito y alcance

Este documento formaliza el inventario funcional visible aprobado como línea
base del Sprint 10. Describe la experiencia web existente al cierre documental
de los Sprints 1–9 y orienta el Bloque 10.1 sin definir nuevas pantallas,
reglas de negocio ni capacidades.

La evaluación considera la experiencia del Usuario, la autorización y los
flujos verificables del estado actual. No constituye un inventario de vistas
Blade ni declara completada la implementación del Sprint 10.

## Módulos visibles actuales

- **Dashboard:** resumen, tendencias, vencimientos, filtros, exportaciones y
  listado de PQRS. Es funcionalmente sólido, pero todavía opera como panel de
  PQRS y no como consola integral del producto.
- **Organización:** dispone de fundamento contextual en backend, sin módulo,
  pantalla ni flujo visible propio.
- **Copropiedades:** permite configurar y mostrar la identidad de la
  Copropiedad activa. El contexto se percibe como marca institucional, no como
  ámbito operativo explícito.
- **Usuarios y Membresías:** ofrece administración de cuentas, Roles, perfil y
  acceso. Las Membresías, su vigencia y sus permisos contextuales no tienen
  representación visible propia.
- **Residentes y Propietarios:** presenta un directorio de residentes y permite
  actualizar torre y unidad mediante los campos legados. Personas,
  Propietarios y Vínculos formales no tienen operación visible.
- **PQRS:** cubre radicación, consulta, filtros, detalle, asignación, estados,
  respuestas, adjuntos, comentarios, etiquetas, trazabilidad, satisfacción e
  informes. Es la experiencia más madura del producto.
- **Gestión Documental:** permite crear, consultar, versionar, someter, aprobar,
  rechazar, descargar y archivar Documentos. Su presentación es básica y no
  dispone de acceso en la navegación global.
- **Notificaciones:** ofrece contador, bandeja paginada, diferenciación de
  lectura, apertura contextual y marcado individual o masivo. La
  representación de tipos de evento y estados transversales es limitada.

## Matriz de madurez

| Módulo | Backend | Interfaz | Nivel de madurez |
| --- | --- | --- | --- |
| Dashboard | Completo | Parcial | Medio |
| Organización | Parcial | Pendiente | Bajo |
| Copropiedades | Parcial | Básica | Bajo |
| Usuarios y Membresías | Parcial | Parcial | Medio |
| Residentes y Propietarios | Parcial | Básica | Bajo |
| PQRS | Completo | Completa | Alto |
| Gestión Documental | Completo | Básica | Medio |
| Notificaciones | Completo | Parcial | Medio |

## Cinco problemas prioritarios

1. La navegación y el Dashboard presentan a Resuelve principalmente como una
   herramienta de PQRS y no reflejan todas las capacidades existentes.
2. La Copropiedad activa se comunica como identidad visual, pero no como
   contexto operativo inequívoco para la consulta y las acciones.
3. Existe una brecha de madurez visible entre PQRS, Gestión Documental,
   Notificaciones y las capacidades contextuales que permanecen solo en
   backend.
4. Los estados de carga, vacío, éxito, error y acceso restringido no tienen un
   tratamiento uniforme en los flujos actuales.
5. Gestión Documental carece de acceso global y utiliza una presentación
   básica para metadatos, estados, vigencias, Versiones y acciones ya
   implementadas.

## Componentes visuales reutilizables

- layout común, navegación superior modular y encabezado;
- identidad institucional, logo y color fijo `#1e3a5f` (no configurable por usuario);
- componentes `x-page-heading`, `x-metric-card`, `x-badge`;
- tarjetas de métricas, gráficos, paneles y tarjetas de carga de trabajo;
- tablas, paginación y tarjetas móviles de PQRS;
- etiquetas de estado, Rol, tipo y vencimiento;
- formularios, validaciones, ayudas y selección de archivos;
- avisos de éxito y error, estados vacíos y diálogo de confirmación;
- tarjetas de detalle, adjuntos, descargas y línea de tiempo de actividad;
- contador, filas y bandeja de Notificaciones;
- tema visual y comportamiento responsive existentes.

## Prioridades aprobadas para el Bloque 10.1

1. Consolidar la navegación y la jerarquía visual de las capacidades
   existentes, eliminando ambigüedades entre consola y solicitudes.
2. Comunicar claramente la Copropiedad activa como contexto operativo.
3. Presentar en la consola únicamente información ya disponible y autorizada
   de PQRS, pendientes, vencimientos, Notificaciones y acceso a Documentos.
4. Normalizar el lenguaje y los estados visibles de carga, vacío, éxito, error
   y acceso restringido.
5. Reutilizar el layout y los patrones visuales actuales, sin introducir un
   nuevo framework ni ampliar el alcance funcional aprobado.

## Exclusiones

- no crear nuevos módulos de Organización, Membresías o Vínculos;
- no agregar reglas de negocio;
- no habilitar selección multicopropiedad;
- no implementar Inteligencia Artificial ni indicadores nuevos.

## Actualización verificable — Sprint 10

Los Bloques 10.1–10.4 consolidaron la experiencia visible sin modificar rutas
de negocio, Dominio, Permisos ni casos de uso existentes.

- **Consola y navegación:** `/panel` se diferencia del listado de PQRS y el
  layout comunica la **Copropiedad activa**. Los accesos se presentan según
  permisos contextuales existentes; el backend conserva la autorización.
- **PQRS:** listado y detalle hacen visibles estado, plazo, responsable,
  fechas, respuestas, comentarios internos autorizados y actuaciones.
- **Documentos:** biblioteca y detalle muestran tipo, categoría, estado,
  acceso, propietario, versión vigente, otras versiones e historial, con las
  acciones documentales actuales.
- **Notificaciones:** la bandeja distingue evento, lectura y fecha, sin alterar
  contador, paginación, apertura ni marcado contextual.
- **Componentes reutilizados:** layout adaptable, tarjetas, tablas, etiquetas,
  formularios, avisos, estados vacíos, confirmaciones y líneas de tiempo. Se
  añadió foco visible para enlaces, botones, resúmenes y campos.

### Limitaciones y evidencia

- El propietario documental conserva el identificador numérico existente. No
  hay una consulta contextual reutilizable de Usuarios para un selector sin
  crear lógica nueva; el backend valida la Membresía vigente contextual.
- Pruebas focalizadas: **38 aprobadas, 141 aserciones**. Suite completa:
  **211 aprobadas, 885 aserciones**. `npm run build` y `git diff --check`:
  correctos.
- La comprobación directa de equivalencia requiere MySQL local. No se ejecutó
  en este entorno porque el host `mysql` no resolvió; no se cambió configuración.

## Reapertura de la línea base visible

El 4 de agosto de 2026 el Product Owner determinó que la implementación técnica
del Sprint 10 no alcanzó todavía la madurez visual requerida. Se conservan los
cambios y validaciones descritos, pero queda reabierta su aceptación de
producto.

La interfaz actual no debe presentarse como consola profesional terminada. La
corrección operativa del error 500 de Documentos no modifica esta valoración.
Antes de continuar con IA debe aprobarse y ejecutarse un incremento correctivo
que demuestre modularidad, coherencia visual, responsive y flujos completos
mediante evidencia renderizada revisada explícitamente por el Product Owner.

## Bloque correctivo C.1 — Línea base aprobada

El Product Owner aprobó visualmente el 4 de agosto de 2026 el shell y los
fundamentos esenciales implementados en C.1: login institucional, navegación
modular autorizada, contexto de Organización y Copropiedad, shell de
escritorio y menú accesible en tableta y móvil.

La validación responsive cubrió 1440×900, 768×1024, 390×844 y 320×568, sin
desbordamiento horizontal real. La evidencia antes y después se conserva en
`storage/evidence/c1/`; su inventario exacto, resultados de pruebas,
correcciones y riesgos están registrados en el documento de Sprint 10.

Esta aprobación no valida todavía los contenidos internos de los módulos ni
cierra la aceptación integral de producto. C.2 no ha sido iniciado y Sprint 11
continúa pausado.

## Bloque correctivo C.2 — Panel aprobado

El Product Owner aprobó visualmente el 4 de agosto de 2026 el Panel ejecutivo y
la consola operativa implementados en C.2. El Panel utiliza consultas y
autorización existentes para presentar panorama general, prioridades derivadas
de filtros aprobados y accesos frecuentes autorizados, sin métricas ni reglas
nuevas.

La validación cubrió permisos completos y parciales; estados cero de PQRS,
pendientes, próximas a vencer y Notificaciones; 403 seguro y orientativo;
aislamiento contextual; y renderizado sin overflow en 1440, 768, 390 y 320 px.
La evidencia se conserva en `storage/evidence/c2/` y su inventario exacto está
registrado en el documento de Sprint 10.

La aprobación no incluye todavía la experiencia interna de PQRS, Documentos,
Notificaciones o administración. C.3.1 no ha sido iniciado y Sprint 11 continúa
pausado.

## Bloque correctivo C.3.1 — Bandeja operativa de PQRS aprobada

El Product Owner aprobó visualmente el 4 de agosto de 2026 la reorganización
del listado de PQRS como bandeja operativa. Se conservaron consultas, filtros,
rutas, permisos y autorización contextual existentes: la tabla de escritorio y
las tarjetas móviles presentan identificación, asunto, tipo, estado,
vencimiento, responsable y acceso al expediente; las acciones rápidas siguen
condicionadas por el backend.

La evidencia aprobada en `storage/evidence/c3-1/` confirma el listado en
escritorio, tableta, 390 y 320 px, incluidos estados vacío y filtrado, acceso
propio, vencimientos próximos, vencidos y pasados. Los plazos pasados no
gestionables usan lenguaje neutral (`Venció hace N día(s)`), mientras los
vencidos gestionables muestran `Plazo vencido`; no se presentan valores
negativos. También se validaron foco visible, navegación por teclado, acción
rápida móvil legible y ausencia de recortes o desplazamiento horizontal.

La mejora menor pendiente es sustituir más adelante la expresión técnica
`día(s)` por singular y plural naturales. C.3.2 no ha sido iniciado y Sprint 11
continúa pausado.

## Bloque correctivo C.3.2 — Expediente de PQRS cerrado y aprobado

El 5 de agosto de 2026 el Product Owner aprobó explícitamente el expediente de
PQRS en lo visual y funcional. Al abrirlo quedan visibles el radicado, asunto,
estado, plazo, responsable, próximo paso y la acción principal autorizada. La
descripción, respuestas, adjuntos, comentarios internos, historial y
satisfacción se presentan como secciones semántica y visualmente distintas;
respuestas, borradores internos, comentarios internos y actuaciones no se
confunden entre sí.

La presentación mantiene fuera del alcance de residentes y actores no
autorizados toda información interna. Conserva el aislamiento por Organización
y Copropiedad, los contratos 403/404, las descargas privadas y la autorización
efectiva de las acciones en backend. Los plazos no muestran valores negativos y
usan pluralización natural: `Vence hoy`, `Vence en 1 día`, `Vence en N días`,
`Venció hace 1 día`, `Venció hace N días` y `Plazo vencido`. Los tiempos
relativos se presentan localizados en español.

La aprobación responsive cubre 1440, 768, 390 y 320 px, sin recortes ni
desplazamiento horizontal y preservando foco visible, navegación por teclado y
controles táctiles. El bloque comprende únicamente:

- `resources/views/pqrs/show.blade.php`;
- `resources/css/app.css`;
- `tests/Feature/PqrExpedientePresentationTest.php`;
- evidencia bajo `storage/evidence/c3-2/`.

No se atribuyen a C.3.2 cambios preexistentes de otros bloques. La verificación
final focal de presentación, aislamiento, autorización, regresión C.3.1,
adjuntos y comentarios obtuvo **23 pruebas aprobadas y 120 aserciones**. La
compilación de vistas Blade, la limpieza posterior de caché, `npm run build` y
`git diff --check` fueron correctos.

Como nota operativa no bloqueante, la eliminación final del perfil temporal de
automatización no produjo salida observable. No se autoriza manipular
directamente la tabla `sessions`; cualquier sesión residual debe expirar
mediante el comportamiento normal de Laravel. Esto no bloquea el cierre de
C.3.2 ni representa un defecto funcional del producto.

Estado oficial del incremento correctivo: **C.1 cerrado y aprobado; C.2 cerrado
y aprobado; C.3.1 cerrado y aprobado; C.3.2 cerrado y aprobado; C.3.3 cerrado
y aprobado**. C.3.3 separó creación de edición, consolidó jerarquía, ayudas de
adjuntos, lista de archivos, resumen y foco de errores, localización española
y conservación de valores. Antes de enviar exige confirmación mediante un
diálogo accesible con `Cancelar` y `Sí, radicar PQRS`, advierte que la solicitud
no podrá modificarse, admite Escape y teclado, devuelve el foco y solo después
de confirmar muestra `Radicando…`. El resultado final presenta exactamente
`PQR radicada correctamente. Ya no puede ser modificada.`

El responsive y el overflow quedaron conformes en 1440, 768, 390 y 320 px sin
`overflow-x: hidden`; la evidencia aprobada incluye el diálogo en 1440, 390 y
320 px y se conserva en `storage/evidence/c3-3/`. La verificación final registró
**23 pruebas aprobadas y 113 aserciones**, caché Blade, `npm run build` y
`git diff --check` correctos.

La regla funcional confirmada establece que quien crea una PQRS no puede
editarla después de enviarla. La administración realiza el seguimiento mediante
cambio de estado, asignación o cambio de responsable y gestión de etiquetas,
según `pqrs.gestionar` y las condiciones contextuales vigentes.

El cierre aprobado de C.3.4.1 añadió en el expediente una tarjeta de gestión
administrativa condicionada por autorización efectiva. Reutiliza el enlace a
`/pqrs/{pqr}/edit`, sin selectores embebidos, consultas nuevas ni cambios en
`PqrController::show()`, rutas, Policies, permisos, casos de uso o contratos
403/404. La verificación focal obtuvo **23 pruebas y 150 aserciones**; la
evidencia está en `storage/evidence/c3-4-1/`.

La corrección transversal de overflow se aplicó directamente a
`input#adjuntos.sr-only`, cuyo ancho accesible era sobrescrito por
`.field input { width: 100%; }`. La regla específica fija ancho mínimo y máximo
de 1 px y elimina padding y borde. No se usó `overflow-x: hidden` ni se
rediseñó edición; creación, expediente y edición quedaron sin overflow.

Estado oficial: **C.1, C.2, C.3.1, C.3.2, C.3.3, C.3.4.1, C.3.4-F,
C.3.4.2 y C.3.4.3 cerrados y aprobados; C.3.5 propuesto y no iniciado**. Sprint 11
permanece **Pausado. No completado.**
La migración `2026_08_04_100000_create_asistencia_documental_tables` permanece
`Pending` y sin ejecutar.

## Bloque funcional C.3.4-F — Cerrado y aprobado

El plazo máximo de respuesta quedó protegido en backend. `PresentarPqrs`
descarta fechas recibidas, fija la radicación con `America/Bogota`, resuelve
`dias_respuesta` para la Organización y Copropiedad activas y delega el cálculo
en `CalendarioLaboralColombia`. El servicio excluye fines de semana, festivos
fijos, trasladables y relativos a Pascua y comienza a contar desde el día
posterior a la radicación.

Creación no expone inputs de fechas. Edición las presenta como solo lectura y
el flujo de actualización excluye ambos atributos. `Pqr` calcula vencimiento y
diferencias visibles con el mismo calendario. Se conserva el tipo SQL `DATE`,
no se recalculan históricos y no se incorporaron APIs, dependencias o
migraciones.

La evidencia aprobada se conserva en `storage/evidence/c3-4-f/`. C.3.4-F queda
**cerrado y aprobado**. Sprint 11 continúa **Pausado. No completado.** y su
migración permanece sin ejecutar.

## Bloque correctivo C.3.4.2 — Cerrado y aprobado

La asignación de etiquetas existentes quedó consolidada en el expediente con
autorización efectiva previa a la validación. El backend normaliza los IDs,
limita catálogo y asociaciones a la Organización y Copropiedad activas, rechaza
atómicamente etiquetas externas y conserva los contratos 403 y 404. La clave
del pivote impide duplicados; una selección idéntica no escribe actividad y cada
cambio real registra una sola actuación.

`Pqr::tags()` conserva resultados equivalentes con carga directa, lazy y eager
sin introducir N+1. La interfaz diferencia selección guardada y cambios
pendientes, permite restaurar, confirma exclusivamente retiros, previene doble
envío y comunica errores localizados. También cubre estados vacíos, teclado,
foco, contraste y responsive. La evidencia permanece local en
`storage/evidence/c3-4-2/`.

La verificación aprobada registró **35 pruebas y 260 aserciones** en el focal,
**98 pruebas y 578 aserciones** en regresión PQRS y dos suites completas
consecutivas de **275 pruebas y 1.281 aserciones**, ambas con exit 0. Blade,
`npm run build` y `git diff --check` fueron correctos. Los datos demo se
mantuvieron en 17 PQRS, 4 etiquetas, 1 asociación y 29 actividades. No hubo
migraciones, seeders, commit, push ni tag.

## Bloque correctivo C.3.4.3 — Cerrado y aprobado

La administración del catálogo de etiquetas quedó separada de su asignación a
una PQRS. Herramientas permite crear etiquetas contextuales, editar nombre y
color, desactivar y reactivar. La eliminación física está prohibida y la
unicidad contextual del nombre continúa reservada para etiquetas activas e
inactivas. El backend normaliza espacios, limita el nombre a 60 caracteres y
canoniza el color como `#RRGGBB`.

La autorización con `gestion.herramientas_gestionar` ocurre antes de resolver
la etiqueta y antes de validar. Las consultas y mutaciones se limitan a la
Organización y Copropiedad activas; se preservan los contratos 302, 403, 404 y
419, la protección contra IDOR y la imposibilidad de suministrar o alterar el
contexto desde el payload. Los conflictos de nombre se presentan de forma
localizada sin error 500. Una operación idéntica es un no-op sin escritura ni
auditoría.

Cada cambio real registra trazabilidad específica del catálogo. Una etiqueta
inactiva ya asociada permanece visible en el expediente y puede conservarse o
retirarse; no puede añadirse como asignación nueva hasta su reactivación. La
relación y el eager loading aprobados en C.3.4.2 permanecen sin regresión.

### Persistencia y migración

`2026_08_13_000000_add_activo_to_pqr_tags.php` añade `activo BOOLEAN NOT NULL
DEFAULT TRUE` y un índice contextual por Organización, Copropiedad y estado,
conservando la unicidad del nombre. Se validó aplicación, rollback y
reaplicación sobre MySQL desechable, además de migración y rollback correctos
en SQLite. La migración se aplicó exclusivamente sobre MySQL demo en el lote 20;
asociaciones e históricos se preservaron. Sprint 11 no incorporó ni ejecutó su
migración.

### Verificación aprobada

- focal combinado C.3.4.2/C.3.4.3 y expediente: **42 pruebas y 303
  aserciones**;
- dos suites completas: **284 pruebas y 1.344 aserciones**, exit 0 en ambas;
- caché y limpieza de Blade, build y `git diff --check`: correctos;
- migración y rollback SQLite: correctos;
- migración sobre MySQL desechable: correcta.

La evidencia local bajo `storage/evidence/c3-4-3/` incluye catálogo, creación,
edición, cambio de color, nombre largo, confirmación de desactivación, estado
desactivado, reactivación, éxito, duplicado, escritorio, tableta, 390 y 320 px,
ausencia de overflow horizontal, teclado y foco. La captura móvil de página
completa representa válidamente el flujo vertical; no exige que todo el
catálogo quepa simultáneamente en 844 px.

Los datos finales aprobados registran 17 PQRS, 4 etiquetas —4 activas y 0
inactivas—, 4 asociaciones y 32 actividades. La asociación `PQR 35 → etiqueta
6` permanece intacta. Los colores finales son etiqueta 3 `#F2DE02`, etiqueta 4
`#FF0000`, etiqueta 5 `#1F6B57` y etiqueta 6 `#0160F9`. Las asociaciones y
actividades adicionales corresponden a validaciones controladas de Dev y los
cambios de color fueron confirmados por el Product Owner; no deben revertirse.
Las auditorías de acceso y notificaciones sin atribución individual no
modificaron catálogo, asociaciones ni actividades y son no bloqueantes.

## C.3.5.1B-2 — Ciclo seguro de borradores cerrado y aprobado

El expediente permite crear un borrador, editarlo, enviarlo como respuesta
oficial o retirarlo lógicamente. Esas acciones se muestran únicamente sobre
borradores propios cuando el actor conserva gestión efectiva; la consulta de
borradores ajenos autorizada por B-1 no habilita su mutación.

Los formularios transportan UUID idempotentes ocultos generados en backend. La
clave se conserva después de un error de validación y se renueva solo tras una
operación exitosa, por lo que reintentar o reenviar el mismo formulario no
duplica actuaciones ni respuestas. El envío directo y el envío de un borrador
convergen en una única respuesta oficial, inmutable y no eliminable.

La presentación mantiene separados borradores internos y respuestas enviadas,
no expone rutas físicas ni datos técnicos del ledger y conserva los contratos
`404` contextual y `403` efectivo. C.3.5.1 está cerrado, aprobado e integrado;
C.3.5.2 permanece no iniciado.

## Actualización de interfaz — Sprint 12

El Sprint 12 no realizó un rediseño general, pero incorporó mejoras presentacionales que afectan la arquitectura visible del producto:

- se normalizaron estados vacíos mediante un componente reutilizable `empty-state`;
- se normalizaron mensajes de éxito y error mediante un componente reutilizable `notice`;
- se verificó la consistencia entre las páginas de error 403 y 404 mediante la creación de una vista `404.blade.php` coherente;
- se redujo lógica Blade repetida relevante al trasladar la consulta de vigencia documental al modelo `Documento::versionVigente()`;
- el build frontend se mantuvo correcto después de las actualizaciones de `postcss` y `nanoid`.

Estas mejoras no constituyen un nuevo diseño visual, pero sí formalizan una base más consistente para estados vacíos, avisos y manejo de errores en la interfaz.

## Actualización de interfaz — Sprint 13

El Sprint 13 simplificó la experiencia visual mediante reorganización de navegación, homogeneización de componentes y establecimiento de paleta institucional fija:

- **Navegación superior modular:** el sidebar permanente se reemplazó por navegación superior con menús desplegables (PQRS, Personas, Administración) en desktop. El drawer se conserva para móvil/tablet.
- **Application Shell unificado:** los layouts principal y administrativo comparten el mismo patrón de topbar.
- **Dashboard operativo:** muestra 4 métricas clave (vencidas, próximas a vencer, pendientes, total) con jerarquía visual clara.
- **Componentes reutilizables:** se crearon `x-page-heading`, `x-metric-card` y `x-badge`. No se crearon `x-data-table`, `x-form-layout`, `x-detail-layout`.
- **Normalización administrativa:** las vistas de Organizaciones, Copropiedades, Membresías y Usuarios globales usan los componentes nuevos.
- **Paleta institucional fija:** `SiteSetting::COLOR_INSTITUCIONAL = '#1e3a5f'`. El usuario no puede alterar el color. El campo `color_principal` se conserva solo por compatibilidad.
- **Eliminación del selector de color:** el formulario de configuración ya no expone el campo. El controlador y el caso de uso fuerzan el valor institucional.
- **Uso de `CalendarioLaboralColombia::hoy()`:** la métrica de vencidas y los filtros temporales utilizan el calendario laboral colombiano.

### Verificación final Sprint 13

- 484 tests, 479 passed, 5 skipped, 2116 aserciones, 0 fallos;
- `npm run build` correcto;
- `composer audit` y `npm audit` sin vulnerabilidades;
- `git diff --check` limpio.

## Control documental

- **Versión:** v1.2
- **Fecha de creación:** 4 de agosto de 2026
- **Fecha de última actualización:** 30 de agosto de 2026
