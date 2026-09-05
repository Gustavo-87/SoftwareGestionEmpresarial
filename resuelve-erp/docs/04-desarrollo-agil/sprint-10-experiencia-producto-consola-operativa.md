# Sprint 10 — Experiencia de Producto y Consola Operativa

## Estado

**Completado.** Sprint 10 consolidó la experiencia de producto y la consola
operativa con los bloques C.1 hasta C.3.6, sin incorporar lógica de negocio
nueva, IA, indicadores, permisos, entidades, multicopropiedad visible ni
cambios arquitectónicos.

## Objetivo

Convertir las capacidades existentes de Resuelve en una experiencia web
visible, coherente y comercializable, consolidando la consola operativa, PQRS,
Gestión Documental y Notificaciones sin crear lógica de negocio nueva.

## Justificación estratégica

Los Sprints 1–9 establecieron una base contextual, autorizada y trazable. Antes
de incorporar Inteligencia Artificial, el producto necesita facilitar que sus
Usuarios comprendan el contexto, la información disponible, los estados y las
acciones existentes. Esta consolidación aplica los criterios transversales de
[Experiencia de Producto](../01-producto/experiencia-de-producto.md) y prepara
patrones visibles reutilizables posteriormente por IA.

La [línea base de la interfaz web](../03-tecnica/interfaz-web.md) formaliza el
inventario funcional visible aprobado antes de iniciar el Bloque 10.1. Sus
hallazgos orientan la consolidación de la experiencia sin modificar el alcance
aprobado de este Sprint.

Este Sprint:

- no constituye una nueva Épica ni una línea frontend independiente;
- no modifica ni reordena el Product Backlog;
- mantiene Inteligencia Artificial como la siguiente Épica pendiente;
- no modifica la arquitectura actual, la arquitectura objetivo ni los ADR;
- conserva la separación Presentación → Aplicación → Dominio.

## Alcance por bloques

### Bloque 10.1 — Fundamentos de interfaz y consola

- consolidar navegación y jerarquía visual de las capacidades existentes;
- ajustar el layout común sin introducir un nuevo framework de interfaz;
- comunicar claramente la Copropiedad activa;
- presentar en la consola información ya disponible de PQRS, pendientes,
  vencimientos, Notificaciones y acceso a Documentos;
- normalizar estados visibles de carga, vacío, éxito, error y acceso
  restringido.

No incorpora indicadores nuevos ni selección multicopropiedad visible.

### Bloque 10.2 — Experiencia PQRS

- mejorar el listado y detalle con la información existente;
- hacer comprensibles estado, prioridad, fechas y responsable;
- representar el seguimiento y las actuaciones actuales como línea de tiempo;
- comunicar próximos pasos únicamente cuando puedan derivarse de reglas ya
  aprobadas;
- mantener todas las acciones conectadas con los casos de uso y la
  autorización existentes.

### Bloque 10.3 — Documentos y Notificaciones

- consolidar la biblioteca documental contextual;
- representar tipo, categoría, estado, vigencia, propietario y versiones con
  datos ya disponibles;
- conservar las acciones documentales existentes y su autorización backend;
- integrar bandeja y contador de Notificaciones;
- diferenciar estados de lectura y clarificar apertura, lectura individual y
  marcado masivo existentes.

### Bloque 10.4 — Cierre UX

- validar comportamiento responsive de los flujos incluidos;
- aplicar accesibilidad básica a navegación, formularios, estados y acciones;
- ejecutar regresión funcional, contextual y de seguridad;
- comprobar que la interfaz no duplique reglas ni autorización;
- actualizar la documentación funcional y técnica afectada y registrar el
  cierre del Sprint únicamente después de superar las validaciones.

## Exclusiones

- Inteligencia Artificial, RAG, embeddings o indexación;
- nuevas reglas de negocio o indicadores;
- nuevos permisos, Roles o capacidades comerciales;
- nuevas entidades o cambios del modelo de dominio;
- cambios de arquitectura o de ADR;
- operación o selección multicopropiedad visible;
- nuevos canales de Notificación;
- una Épica, aplicación o arquitectura frontend independiente.

## Dependencias y reglas de implementación

- casos de uso y consultas de Aplicación existentes;
- consultas contextuales de PQRS, Documentos y Notificaciones;
- `ContextoOperativo`, `AutorizacionContextual`, Membresía vigente, permisos y
  pertenencia del recurso;
- Blade, layout y componentes visuales actuales del monolito Laravel;
- documentación funcional y técnica de los Sprints 1–9.

La Presentación captura intención, invoca Aplicación y representa resultados.
Blade y JavaScript no pueden ejecutar consultas de negocio ni convertirse en
fuente de autorización. Ocultar o deshabilitar acciones es solo una ayuda
visual; el backend mantiene todos los controles efectivos.

## Definition of Done

El Sprint podrá declararse Completado cuando:

- los cuatro bloques estén implementados y validados;
- la consola comunique el contexto activo y reúna únicamente información ya
  disponible y autorizada;
- PQRS, Documentos y Notificaciones tengan experiencias visibles coherentes
  con sus estados, actuaciones y acciones existentes;
- carga, vacío, éxito, error y acceso restringido estén representados sin
  revelar información sensible;
- los flujos incluidos sean utilizables en los tamaños de pantalla acordados y
  cumplan accesibilidad básica;
- no existan reglas, consultas contextuales ni autorización duplicadas en la
  interfaz;
- las pruebas funcionales, responsive, de autorización, aislamiento, IDOR,
  CSRF y regresión completa finalicen sin fallos;
- no se introduzcan IA, indicadores, permisos, entidades, multicopropiedad
  visible ni cambios arquitectónicos;
- la documentación oficial afectada quede sincronizada.

## Ejecución verificada de los Bloques 10.1–10.4

Los cuatro bloques fueron implementados y validados en el árbol de trabajo sin
incorporar entidades, rutas de negocio, permisos, casos de uso,
multicopropiedad visible ni cambios arquitectónicos.

- **10.1:** consola `/panel`, navegación contextual y Copropiedad activa.
- **10.2:** jerarquía visual y trazabilidad de listado y detalle PQRS.
- **10.3:** biblioteca, detalle y versiones documentales; bandeja contextual
  de Notificaciones.
- **10.4:** consistencia de componentes, mensajes, foco visible y patrones
  responsive de los flujos incluidos.

Resultados: pruebas focalizadas **38 aprobadas, 141 aserciones**; suite
completa **211 aprobadas, 885 aserciones**; `npm run build` y
`git diff --check` correctos.

La sincronización contextual idempotente se ejecutó en Sail contra MySQL local.
En la primera ejecución creó **5 Permisos** y **12 relaciones rol-permiso**,
preservando 5 Roles, 4 Membresías y 4 asignaciones existentes. La segunda
ejecución creó 0 registros y confirmó 18 Permisos y 50 relaciones existentes.
La verificación posterior informó: `Equivalencia completa verificada: 0
divergencias. La autorización contextual puede activarse.` con código de
salida 0.

Limitación pendiente: el propietario documental conserva su identificador
numérico. El backend valida su Membresía vigente contextual; no existe una
consulta reutilizable para un selector sin crear lógica nueva.

## Cierre del Sprint 10

El 20 de agosto de 2026 se formaliza la corrección documental que registra
Sprint 10 como completado, después de integrar B-3 y consolidar el cierre de
C.3.5.1. La decisión anterior de reapertura de aceptación se resolvió con el
incremento correctivo aprobado y su posterior integración.

El 25 de agosto de 2026 se corrige la documentación para separar el alcance
de Sprint 10 y Sprint 11. Los bloques C.3.7.1–C.3.7.3 se reasignan al
Sprint 11 oficial (Multi-copropiedad y Consola Administrativa). Sprint 10
termina en C.3.6.

## Incremento correctivo — Bloque C.1 aprobado

El 4 de agosto de 2026 el Product Owner aprobó visualmente el **Bloque C.1 —
Shell y fundamentos visuales esenciales**. Esta aprobación se limita al bloque
y no cierra la aceptación integral de Sprint 10 ni autoriza C.2.

### Alcance implementado

- login institucional que presenta Resuelve como plataforma de gestión de
  copropiedades;
- shell lateral institucional en escritorio;
- menú modal accesible en tableta y móvil;
- navegación agrupada, sin rutas nuevas, en Inicio, Operación, Comunidad,
  Administración y Configuración;
- Organización, Copropiedad activa, Usuario y Roles contextuales visibles;
- estado activo acompañado por texto y `aria-current`;
- fundamentos aplicados directamente al shell y login: superficies,
  espaciado, radios, contraste, foco y estados transversales mínimos;
- cierre del menú móvil por botón, enlace, fondo o Escape, con trampa y retorno
  de foco;
- corrección del desbordamiento móvil sin ocultarlo mediante `overflow`.

No se rediseñaron los contenidos internos de Panel, PQRS, Documentos,
Notificaciones o administración. No se crearon rutas, módulos, reglas,
permisos, dependencias ni selección multicopropiedad.

### Archivos modificados por C.1

- `app/Providers/AppServiceProvider.php`;
- `resources/css/app.css`;
- `resources/js/app.js`;
- `resources/views/layouts/app.blade.php`;
- `resources/views/auth/login.blade.php`;
- `tests/Feature/PanelOperativoTest.php`;
- evidencia generada bajo `storage/evidence/c1/`.

El ajuste en `AppServiceProvider` se limitó a entregar al layout la
Organización ya disponible en el contexto. No se modificaron controladores,
rutas, modelos, Policies, migraciones ni lógica de negocio como parte de C.1.

### Resultados verificados

- autenticación, Panel, Documentos y Notificaciones: **25 pruebas aprobadas,
  121 aserciones**;
- regresión de PQRS, autorización documental y catálogo contextual: **20
  pruebas aprobadas, 93 aserciones**;
- validación final de `PanelOperativoTest` y `AuthenticationTest` tras las
  correcciones responsive: **14 pruebas aprobadas, 81 aserciones**;
- `npm run build`: correcto;
- `git diff --check`: correcto.

Con un Administrador local respondieron HTTP 200: Panel, PQRS, Radicar PQRS,
Documentos, Notificaciones, Perfil, Residentes, Carga del equipo, Herramientas,
Auditoría, Configuración general y Usuarios. Un Auditor sin permiso documental
continuó recibiendo 403 en Documentos.

### Evidencia visual

Línea base de login:

- `storage/evidence/c1/before/login-1440x900.png`;
- `storage/evidence/c1/before/login-768x1024.png`;
- `storage/evidence/c1/before/login-390x844.png`.

Resultado final:

- `storage/evidence/c1/after/login-1440x900.png`;
- `storage/evidence/c1/after/login-768x1024.png`;
- `storage/evidence/c1/after/login-390x844.png`;
- `storage/evidence/c1/after/panel-1440x900.png`;
- `storage/evidence/c1/after/panel-768x1024.png`;
- `storage/evidence/c1/after/panel-390x844.png`;
- `storage/evidence/c1/after/panel-320x568.png`;
- `storage/evidence/c1/after/mobile-menu-390x844.png`.

La verificación final de viewport confirmó igualdad entre `scrollWidth` y
`clientWidth`: 1440/1440, 768/768, 390/390 y 320/320. La captura de 320 px fue
regenerada con emulación CSS correcta después de detectar que una captura
anterior usaba una ventana física recortada sobre un viewport de 500 px.

### Correcciones responsive

- se eliminó el carrusel móvil heredado de métricas que imponía tarjetas de
  210 px y un contenedor de 882 px;
- las métricas móviles usan una columna con ancho reducible;
- encabezado, título, acción principal, accesos y contexto permiten reducción
  y envoltura de texto;
- no se utiliza `overflow-x: hidden` para encubrir desbordamientos;
- a 320 px se priorizan área actual y Copropiedad, mientras la Organización
  completa permanece en el contexto institucional del menú;
- el logo se oculta a 320 px para conservar menú, Notificaciones y tema con
  áreas operables;
- el menú mantiene desplazamiento vertical y recorrido con Tab y Shift+Tab.

### Riesgos pendientes

- no existe captura autenticada del shell anterior: las credenciales demo no
  coincidían con los hashes locales; la evidencia previa disponible se limita
  al login;
- C.1 no corrige todavía la jerarquía interna ni la madurez visible de Panel,
  PQRS, Documentos, Notificaciones o módulos administrativos;
- los estados transversales quedaron normalizados en su base visual, pero su
  aplicación integral pertenece a bloques posteriores;
- la aceptación integral de Sprint 10 continúa abierta.

Sprint 11 permanece **Pausado. No Completado**. No se inició C.3.5.2, C.3.6 ni Sprint 11.

## Incremento correctivo — Bloque C.2 aprobado

El 4 de agosto de 2026 el Product Owner aprobó visualmente el **Bloque C.2 —
Panel ejecutivo y consola operativa**. La aprobación se limita al Panel y no
autoriza el inicio de C.3 ni cierra todavía la aceptación integral de Sprint 10.

### Alcance implementado

El Panel quedó organizado en:

1. **Panorama general autorizado:** total de PQRS visibles y Notificaciones no
   leídas.
2. **Prioridades operativas:** PQRS pendientes y próximas a vencer, con motivo
   comprensible y enlace a los filtros existentes.
3. **Accesos frecuentes:** Documentos, Notificaciones y Carga del equipo solo
   cuando la autorización contextual vigente lo permite.

Se cubrieron permisos completos y parciales; cero PQRS, pendientes, próximas a
vencer y Notificaciones; ausencia de acceso a PQRS; y acceso restringido 403
en español, sin información sensible y con orientación para volver al Panel.
Los valores cero usan lenguaje neutral y no se representan como alarma.

No se incorporaron métricas, indicadores, reglas, rutas, permisos, consultas o
cambios de controlador.

### Archivos modificados por C.2

- `resources/views/pqrs/panel.blade.php`;
- `resources/views/errors/403.blade.php`;
- `resources/css/app.css`;
- `tests/Feature/PanelOperativoTest.php`;
- evidencia generada bajo `storage/evidence/c2/`.

### Consultas y autorización reutilizadas

- `ConsultaPqrsContextuales`: total autorizado, pendientes y próximas a
  vencer, respetando Organización, Copropiedad y visibilidad propia o total;
- `ConsultaNotificacionesContextuales`: contador contextual de no leídas;
- Policies y `AutorizacionContextual` existentes para PQRS, radicación,
  Documentos, Notificaciones y Carga del equipo;
- filtros ya soportados por el listado PQRS: `estado=pendientes` y
  `estado=por_vencer`.

La Presentación se limitó a representar los resultados autorizados.

### Resultados verificados

- Panel, PQRS, Documentos, Notificaciones, autenticación y autorización
  contextual: **38 pruebas aprobadas, 158 aserciones**;
- validación final de `PanelOperativoTest` después de incorporar el 403:
  **3 pruebas aprobadas, 20 aserciones**;
- `npm run build`: correcto;
- `git diff --check`: correcto.

La matriz funcional verificó permisos completos y parciales, ausencia de
acceso a PQRS, Documentos permitidos y denegados, Notificaciones permitidas y
denegadas, radicación autorizada, Carga del equipo autorizada, aislamiento
entre Copropiedades, filtros existentes, destinos ocultos e inexistencia de
errores 500 en enlaces administrativos visibles.

### Evidencia visual

Línea base C.1:

- `storage/evidence/c2/before/panel-1440x900.png`;
- `storage/evidence/c2/before/panel-768x1024.png`;
- `storage/evidence/c2/before/panel-390x844.png`;
- `storage/evidence/c2/before/panel-320x568.png`.

Panel aprobado con datos:

- `storage/evidence/c2/after/admin-1440x900.png`;
- `storage/evidence/c2/after/admin-768x1024.png`;
- `storage/evidence/c2/after/admin-390x844.png`;
- `storage/evidence/c2/after/admin-320x568.png`.

Permisos y estados:

- `storage/evidence/c2/after/partial-390x844.png`;
- `storage/evidence/c2/after/access-restringido-1440x900.png`;
- `storage/evidence/c2/after/access-restringido-390x844.png`;
- `storage/evidence/c2/after/cero-1440x900.png`;
- `storage/evidence/c2/after/cero-390x844.png`.

También se conservaron las respuestas HTML usadas para comprobar los
escenarios en `storage/evidence/c2/after/`.

Las mediciones finales confirmaron `scrollWidth === clientWidth` y
`scrollX = 0` en 1440×900, 768×1024, 390×844 y 320×568.

### Correcciones realizadas durante la revisión

- se añadió una vista 403 segura en español, manteniendo el código HTTP 403 y
  ofreciendo retorno al Panel;
- se regeneraron las capturas de acceso parcial y restringido con emulación de
  viewport CSS real, después de detectar que Brave había usado tamaño físico
  de ventana con un viewport efectivo mayor;
- se verificó que el supuesto recorte no correspondía a overflow real;
- se generaron escenarios controlados sin cambios persistentes para demostrar
  simultáneamente cero PQRS, pendientes, próximas a vencer y Notificaciones;
- se revisó que los estados cero fueran neutrales y orientativos.

### Riesgos pendientes

- C.2 no corrige todavía la experiencia interna de los módulos enlazados;
- las capturas específicas de acceso parcial, 403 y estado cero no cubren los
  cuatro tamaños individualmente, aunque el Panel principal sí fue validado en
  1440, 768, 390 y 320 px;
- la vista 403 normaliza el acceso restringido, pero otros estados de error
  transversales requieren validación en bloques posteriores;
- la aceptación integral de Sprint 10 continúa abierta.

Sprint 11 permanece **Pausado. No Completado**. No se inició C.3.5.2, C.3.6 ni Sprint 11.

## Incremento correctivo — Bloque C.3.1 aprobado

El 4 de agosto de 2026 el Product Owner aprobó visualmente el **Bloque C.3.1 —
Listado y bandeja operativa de PQRS**. La aprobación se limita al listado; no
autoriza C.3.2 ni cierra todavía la aceptación integral del Sprint 10.

### Resultado aprobado

- la bandeja de PQRS fue reorganizada como entrada operativa, sin alterar
  consultas, rutas, filtros, estados ni reglas de negocio;
- los filtros existentes ganaron jerarquía visual y conservan sus parámetros,
  combinación y paginación;
- escritorio conserva tabla operativa y móvil usa tarjetas con radicado,
  asunto, tipo, estado, vencimiento, responsable y acceso al expediente;
- `ConsultaPqrsContextuales`, `PqrPolicy` y `AutorizacionContextual` siguen
  aislando Organización y Copropiedad; las acciones continúan condicionadas por
  permisos efectivos en backend;
- se diferenciaron listado vacío y filtros sin resultados;
- los plazos futuros, vencidos y pasados se comunican como `Vence en N día(s)`,
  `Plazo vencido` y `Venció hace N día(s)`, respectivamente, sin números
  negativos;
- la acción rápida móvil presenta Estado, Responsable y Aplicar en filas
  legibles y con controles táctiles de 44 px;
- foco visible, navegación con teclado, ausencia de recortes y ausencia de
  desplazamiento horizontal quedaron verificados en 1440×900, 768×1024,
  390×844 y 320×568.

### Validación y evidencia aprobadas

- pruebas focales de bandeja y aislamiento contextual: **9 aprobadas, 50
  aserciones**;
- `npm run build`: correcto;
- `git diff --check`: correcto;
- evidencia antes y después: `storage/evidence/c3-1/`; evidencia final móvil de
  plazos y acción rápida: `after/past-neutral-390x844.png`,
  `after/overdue-390x844.png`, `after/upcoming-390x844.png`,
  `after/quick-action-390x844.png` y `after/quick-action-320x568.png`.

### Mejora menor no bloqueante

En un bloque posterior debe sustituirse la expresión técnica `día(s)` por
singular y plural naturales cuando corresponda. No afecta autorización,
aislamiento ni el cierre visual de C.3.1.

Sprint 11 permanece **Pausado. No Completado**. No se inició C.3.5.2, C.3.6 ni Sprint 11.

## Incremento correctivo — Bloque C.3.2 cerrado y aprobado

El 5 de agosto de 2026 el Product Owner otorgó aprobación visual y funcional
explícita al **Bloque C.3.2 — Expediente de PQRS**. El cierre no reabre ni
atribuye a este bloque cambios preexistentes de C.1, C.2, C.3.1, Sprint 11 u
otros incrementos.

### Resultado aprobado

- expediente reorganizado con jerarquía operativa;
- radicado, asunto, estado, plazo, responsable y próximo paso visibles al
  abrir;
- descripción, respuestas, adjuntos, comentarios internos, historial y
  satisfacción separados semántica y visualmente;
- acción principal autorizada claramente identificable;
- información interna ausente para residentes y actores no autorizados;
- aislamiento contextual y contratos 403/404 preservados;
- adjuntos privados y acciones autorizadas efectivamente en backend;
- plazos sin valores negativos y con pluralización natural: `Vence hoy`,
  `Vence en 1 día`, `Vence en N días`, `Venció hace 1 día`, `Venció hace N
  días` y `Plazo vencido`;
- tiempos relativos localizados en español;
- respuestas, comentarios internos e historial claramente diferenciados;
- responsive aprobado en 1440, 768, 390 y 320 px, sin recortes ni
  desplazamiento horizontal;
- foco, navegación por teclado y controles táctiles preservados.

### Archivos y evidencia propios del bloque

- `resources/views/pqrs/show.blade.php`;
- `resources/css/app.css`;
- `tests/Feature/PqrExpedientePresentationTest.php`;
- evidencia bajo `storage/evidence/c3-2/`.

### Verificación final aprobada

- pruebas focales de presentación, aislamiento, autorización, regresión C.3.1,
  adjuntos y comentarios: **23 aprobadas, 120 aserciones**;
- compilación de vistas Blade: correcta;
- limpieza posterior de caché: correcta;
- `npm run build`: correcto;
- `git diff --check`: correcto.

### Nota operativa no bloqueante

La eliminación final del perfil temporal de automatización no produjo salida
observable. No se autoriza manipular directamente la tabla `sessions`.
Cualquier sesión residual debe expirar mediante el comportamiento normal de
Laravel. Esta nota no bloquea el cierre de C.3.2 ni representa un defecto
funcional del producto.

### Estado oficial del plan correctivo

```text
C.1: Cerrado y aprobado
C.2: Cerrado y aprobado
C.3.1: Cerrado y aprobado
C.3.2: Cerrado y aprobado
C.3.3: Cerrado y aprobado
```

Sprint 11 permanece **Pausado. No Completado**. No se inició C.3.5.2, C.3.6 ni Sprint 11.

## Incremento correctivo — Bloque C.3.3 cerrado y aprobado

El 8 de agosto de 2026 el Product Owner otorgó aprobación visual y funcional
explícita al **Bloque C.3.3 — Radicación clara y confiable de PQRS**. El cierre
se limita a creación, validación, soportes y confirmación; no inicia edición,
respuestas, comentarios, etiquetas ni otros bloques.

### Resultado aprobado

- el formulario de creación quedó separado del formulario de edición;
- propósito, identificación, descripción, fechas, soportes y acciones tienen
  jerarquía visible;
- los límites y formatos de adjuntos se explican antes de seleccionar;
- la lista progresiva muestra nombre y tamaño de cada archivo seleccionado;
- los errores incluyen resumen navegable, asociación con campos y foco visible
  en el primer error;
- los mensajes backend están localizados en español y usan atributos públicos,
  sin exponer identificadores como `tipo_pqr_id`;
- los valores ingresados se conservan después de una validación fallida;
- antes de enviar se exige confirmación mediante un diálogo accesible con
  `Cancelar` y `Sí, radicar PQRS`, junto con el aviso explícito de que después
  de radicar no podrá modificarse;
- el diálogo admite Escape y operación por teclado, y devuelve el foco al
  control que lo abrió al cancelar;
- el botón adopta el estado visual `Radicando…` después de confirmar para
  prevenir interacción repetida; esto no acredita idempotencia del backend;
- el comportamiento responsive fue validado en 1440×900, 768×1024, 390×844 y
  320×568;
- el overflow fue diagnosticado y corregido en el control de archivos sin usar
  `overflow-x: hidden`;
- la confirmación posterior conduce al expediente y muestra exactamente
  `PQR radicada correctamente. Ya no puede ser modificada.`

La evidencia funcional de denegación 403 se conserva en la prueba Feature. La
creación local autorizada usada como evidencia produjo la PQRS con ID **49**,
radicado **PQR-0049**, una actividad `created` y cero adjuntos.

### Regla funcional confirmada

Quien crea una PQRS no puede editarla después de enviarla. La administración
puede gestionar el seguimiento mediante cambio de estado, asignación o cambio
de responsable y etiquetas. Las etiquetas pueden modificarse posteriormente,
incluido su color. `PqrPolicy::update()`, basada en `pqrs.gestionar`, es
coherente con esta regla para la gestión administrativa y no autoriza ampliar
la edición al creador.

### Verificación aprobada

- verificación focal y regresiones aprobadas: **23 pruebas aprobadas, 113
  aserciones**;
- verificaciones responsive y de overflow: conformes;
- diálogo aprobado visualmente en 1440, 390 y 320 px;
- compilación y limpieza de caché Blade: correctas;
- `npm run build`: correcto;
- `git diff --check`: correcto;
- evidencia bajo `storage/evidence/c3-3/`.

Estado oficial:

```text
C.1: Cerrado y aprobado
C.2: Cerrado y aprobado
C.3.1: Cerrado y aprobado
C.3.2: Cerrado y aprobado
C.3.3: Cerrado y aprobado
```

Sprint 11 permanece **Pausado. No Completado**. No se inició C.3.5.2, C.3.6 ni Sprint 11.

## Incremento correctivo — Bloque C.3.4.1 cerrado y aprobado

El 12 de agosto de 2026 se formaliza la aprobación visual y funcional de
**C.3.4.1 — Acceso claro a gestión administrativa de PQRS**. En el expediente
se añadió una tarjeta `Gestión administrativa`, completamente ausente para
residentes, auditores y usuarios sin gestión y visible solo con autorización
efectiva. La tarjeta muestra estado y responsable actuales —incluido el
escenario `Sin asignar`—, explica que la gestión permite actualizarlos y que
los cambios quedan registrados en el historial, y reutiliza el enlace existente
hacia la edición completa en `/pqrs/{pqr}/edit`.

La acción duplicada del encabezado fue eliminada. Gestión administrativa,
respuestas y comentarios internos quedaron diferenciados. No se incorporaron
selectores dentro del expediente, no se añadieron consultas ni se modificó
`PqrController::show()`. Tampoco se modificaron rutas, Policies, permisos,
casos de uso, estados, transiciones, validaciones ni los contratos backend 403
y 404.

### Corrección transversal de overflow aprobada

La pantalla de edición ampliaba horizontalmente `documentElement` porque
`input#adjuntos.sr-only` recibía el ancho de la regla genérica
`.field input { width: 100%; }`, que sobrescribía el ancho accesible del input
oculto. Se corrigió el elemento causante con:

```css
.field input[type="file"].sr-only {
 width: 1px;
 min-width: 1px;
 max-width: 1px;
 padding: 0;
 border: 0;
}
```

No se utilizó `overflow-x: hidden` ni se rediseñó la pantalla de edición.
Creación, expediente y edición quedaron sin overflow.

### Evidencia y verificación aprobadas

La evidencia reproducible se conserva bajo `storage/evidence/c3-4-1/` e
incluye gestor autorizado, estado y responsable, `Sin asignar`, móvil
390 × 844 y 320 × 568, foco visible, enlace a `/pqrs/{pqr}/edit`, edición sin
overflow en 1440, 768, 390 y 320 px, diagnóstico DOM y metadatos.

La verificación final registró **23 pruebas aprobadas y 150 aserciones**. La
compilación y limpieza de vistas Blade, `npm run build` y `git diff --check`
fueron correctos. Se preservaron autorización y aislamiento; ningún formulario
fue enviado y ningún dato fue modificado.

### Estado oficial

```text
C.1: Cerrado y aprobado
C.2: Cerrado y aprobado
C.3.1: Cerrado y aprobado
C.3.2: Cerrado y aprobado
C.3.3: Cerrado y aprobado
C.3.4.1: Cerrado y aprobado
C.3.4-F: Cerrado y aprobado
C.3.4.2: Cerrado y aprobado
C.3.4.3: Cerrado y aprobado
C.3.5: Propuesto. No iniciado
```

Sprint 11 permanece **Pausado. No completado.** No se inició C.3.5.2, C.3.6 ni Sprint 11.

## Bloque funcional — C.3.4-F cerrado y aprobado

El 12 de agosto de 2026 el Product Owner aprobó explícitamente
**C.3.4-F — Cálculo protegido del plazo máximo de respuesta**. La fecha de
radicación se fija en backend con la fecha civil de `America/Bogota` y la fecha
límite se calcula desde el día siguiente, contando exclusivamente días hábiles
de Colombia: lunes a viernes, sin sábados, domingos ni festivos oficiales. El
calendario local determinista incluye fechas fijas, festivos trasladables según
la Ley 51 de 1983 y festivos relativos a Pascua; no utiliza internet, API
externa ni dependencia adicional.

El número de días proviene de `configuraciones_copropiedad.dias_respuesta` para
la Organización y Copropiedad activas, con el valor predeterminado institucional
como respaldo. El cálculo ocurre exclusivamente en `PresentarPqrs`; solo asunto,
descripción y tipo se aceptan como datos funcionales del radicador. Cualquier
`fecha_radicacion` o `fecha_limite_respuesta` enviada mediante formulario,
DevTools o petición manual se ignora.

Creación ya no presenta inputs de fechas y explica el cálculo automático. La
edición administrativa muestra ambas fechas como información de solo lectura;
`PqrController::update()` y `ActualizarPqrs` tampoco aceptan ni persisten
alteraciones de esas fechas. No se creó excepción administrativa: el plazo
máximo queda calculado e inmutable.

`is_overdue`, `elapsed_days` y `remaining_days` usan el mismo calendario hábil
colombiano. Bandeja y expediente comunican días hábiles y conservan la fecha
persistida como fuente de verdad. Los contratos 403/404, la autorización y el
aislamiento entre Organización y Copropiedad permanecen vigentes. El campo
continúa siendo SQL `DATE`; no hubo migración ni recalculo de PQRS históricas.

### Evidencia aprobada

La evidencia se conserva bajo `storage/evidence/c3-4-f/` e incluye:

- creación sin campos de fecha en 1440, 768, 390 y 320 px;
- explicación de cálculo automático y foco visible;
- edición con fechas de solo lectura en escritorio y móvil;
- expediente con plazo y diferencia en días hábiles;
- fechas localizadas en español;
- ausencia de overflow y metadatos de viewport y DOM.

### Estado oficial posterior a C.3.4-F

```text
C.1: Cerrado y aprobado
C.2: Cerrado y aprobado
C.3.1: Cerrado y aprobado
C.3.2: Cerrado y aprobado
C.3.3: Cerrado y aprobado
C.3.4.1: Cerrado y aprobado
C.3.4-F: Cerrado y aprobado
C.3.4.2: Cerrado y aprobado
C.3.4.3: Cerrado y aprobado
C.3.5: Propuesto. No iniciado
```

Sprint 11 permanece **Pausado. No completado.** No se inició C.3.5.2, C.3.6 ni Sprint 11.

## Cierre aprobado — C.3.4.2 Asignación de etiquetas existentes

El Product Owner aprobó el cierre de C.3.4.2. El expediente permite asignar y
retirar etiquetas existentes solo a quien posee autorización efectiva sobre la
PQRS. La autorización precede a la validación; se preservan 403 para gestión no
autorizada y 404 contextual para recursos externos.

El backend normaliza identificadores, impide duplicados y rechaza atómicamente
etiquetas de otra Organización o Copropiedad. Una selección idéntica es un no-op
sin actividad; cada cambio real produce una sola actuación. La relación
`Pqr::tags()` ofrece resultados equivalentes mediante carga directa, lazy y
eager sin N+1.

La experiencia distingue selección guardada y cambios pendientes, permite
restaurar, confirma exclusivamente retiros y previene doble envío. Incluye
errores accesibles y localizados, estados sin etiquetas asignadas y sin
catálogo disponible, teclado, foco, contraste y responsive. La evidencia se
conserva localmente en `storage/evidence/c3-4-2/`.

### Verificación aprobada

- focal final: **35 pruebas y 260 aserciones**;
- regresión PQRS: **98 pruebas y 578 aserciones**;
- dos suites completas consecutivas: **275 pruebas y 1.281 aserciones**, exit 0
  en ambas;
- Blade, `npm run build` y `git diff --check`: correctos;
- datos demo sin cambios: 17 PQRS, 4 etiquetas, 1 asociación y 29 actividades;
- sin migraciones, seeders, commit, push ni tag;
- Sprint 11 ausente del entorno oficial y **Pausado. No completado.** C.3.5.2 es el siguiente bloque y Sprint 10 queda completado.

## Cierre aprobado — C.3.4.3 Administración del catálogo de etiquetas

Estado: **Cerrado y aprobado**. La implementación conserva una identidad única
por nombre y Copropiedad, incluso cuando la etiqueta está inactiva; normaliza
espacios, limita el nombre a 60 caracteres y canoniza el color como `#RRGGBB`.
Permite crear, editar nombre y color, desactivar y reactivar sin eliminación
física y registra trazabilidad específica para cada cambio real.

La autorización ocurre antes de resolver la etiqueta y antes de validar. Se
preservan los contratos 302, 403, 404 y 419, el aislamiento por Organización y
Copropiedad y la protección contra IDOR y manipulación del contexto. Los
conflictos de nombre se presentan de forma localizada sin error 500; una
operación idéntica es un no-op sin escritura ni auditoría.

### 1. Objetivo

Permitir administrar el ciclo de vida del catálogo contextual sin mezclarlo con
la asignación de etiquetas dentro del expediente ni perder trazabilidad.

### 2. Regla funcional aprobada

`/gestion/herramientas` permite crear, editar, desactivar y reactivar etiquetas
bajo `gestion.herramientas_gestionar`. La asignación a PQRS continúa separada y
cerrada en C.3.4.2.

### 3. Brechas cerradas

Nombre, color y disponibilidad pueden administrarse sin destruir asociaciones
ni historial. El estado es visible, las colisiones se traducen a errores
localizados y la auditoría distingue creación, edición, desactivación y
reactivación.

### 4. Actores autorizados

Administrador y gestor con `gestion.herramientas_gestionar` efectivo en la
Organización y Copropiedad activas. Apoyo, auditor, residente, membresía
inactiva y usuario sin el permiso no pueden ver ni ejecutar controles.

### 5. Crear etiquetas

La tarea conserva nombre y color validados, asociación obligatoria al contexto
activo, resultado visible y separación del expediente.

### 6. Editar nombre y color

Ambos cambios operan con autorización contextual, validación accesible y
comunicación del efecto visual en PQRS ya asociadas, sin modificar sus pivotes.

### 7. Desactivar y reactivar

Una etiqueta desactivada deja de ofrecerse para nuevas asignaciones y puede
reactivarse. El estado y las acciones son visibles e inequívocos; la
desactivación no se presenta como eliminación.

### 8. Etiquetas ya asignadas

Permanecen visibles en las PQRS donde ya estaban asignadas y en el historial.
Una etiqueta inactiva puede conservarse o retirarse, pero no incorporarse como
asignación nueva. Después de reactivarla puede asignarse nuevamente.

### 9. Prohibición de eliminación física

Ninguna etiqueta se elimina físicamente, tenga o no asociaciones. El contrato
uniforme conserva identidad, trazabilidad e históricos.

### 10. Unicidad contextual

El nombre permanece reservado dentro de la Copropiedad también para etiquetas
inactivas. Se normalizan espacios y los conflictos al crear, reactivar o
renombrar se presentan sin error 500. Organizaciones o Copropiedades distintas
permanecen independientes.

### 11. Aislamiento

Toda lectura y mutación debe resolver la etiqueta dentro de la Organización y
Copropiedad activas, ocultando recursos externos mediante el contrato contextual
que determine la auditoría y sin selector de Copropiedad.

### 12. Autorización y trazabilidad

La autorización ocurre antes de revelar validaciones o existencia. Crear,
editar, desactivar y reactivar registran actor, contexto, acción y cambios
relevantes sin datos sensibles ni duplicación por no-op.

### 13. Persistencia y archivos

La migración `2026_08_13_000000_add_activo_to_pqr_tags.php` incorpora `activo
BOOLEAN NOT NULL DEFAULT TRUE` y un índice por Organización, Copropiedad y
estado, preservando la unicidad del nombre. La implementación comprende modelo,
consulta y caso de uso contextuales, controlador, rutas, Herramientas,
expediente, CSS, JavaScript y pruebas específicas.

La migración se validó mediante aplicación, rollback y reaplicación sobre MySQL
desechable; también tuvo migración y rollback correctos en SQLite. Se aplicó
exclusivamente sobre MySQL demo en el lote 20, sin perder asociaciones ni
históricos. Sprint 11 no incorporó ni ejecutó migración alguna.

### 14. Verificación aprobada

- focal C.3.4.2/C.3.4.3 y expediente: **42 pruebas y 303 aserciones**;
- dos suites completas: **284 pruebas y 1.344 aserciones**, exit 0 en ambas;
- caché y limpieza de Blade, build y `git diff --check`: correctos;
- migración y rollback SQLite: correctos;
- migración sobre MySQL desechable: correcta.

### 15. Evidencia visual

La evidencia local en `storage/evidence/c3-4-3/` incluye catálogo, creación,
edición, cambio de color, nombre largo, confirmación de desactivación, estado
desactivado, reactivación, éxito, error por duplicado, escritorio, tableta, 390
y 320 px, ausencia de overflow horizontal, teclado y foco. La captura móvil de
página completa documenta el flujo vertical y no implica que todo el catálogo
deba caber simultáneamente en 844 px.

### 16. Criterios de aceptación

- tareas del catálogo separadas de la asignación a PQRS;
- autorización e aislamiento efectivos en backend;
- nombre y color editables sin perder asociaciones ni historial;
- desactivación excluye nuevas asignaciones y conserva usos existentes;
- reactivación segura bajo unicidad contextual;
- no hay eliminación física de etiquetas usadas;
- trazabilidad, accesibilidad y responsive aprobados.

### 17. Exclusiones

Asignación de etiquetas en expediente, cambios de estado o responsable de
PQRS, permisos nuevos, selector de Copropiedad, notificaciones nuevas, datos
demo, importaciones, Sprint 11, C.3.5 y C.3.6.

### 18. Datos finales aprobados

- 17 PQRS;
- 4 etiquetas: 4 activas y 0 inactivas;
- 4 asociaciones y 32 actividades;
- asociación `PQR 35 → etiqueta 6` intacta;
- etiqueta 3: `#F2DE02`;
- etiqueta 4: `#FF0000`;
- etiqueta 5: `#1F6B57`;
- etiqueta 6: `#0160F9`.

Las asociaciones y actividades adicionales se atribuyen a validaciones
controladas de Dev. Los colores fueron confirmados por el Product Owner y no
deben revertirse. Las auditorías de acceso y notificaciones sin atribución
individual no modificaron catálogo, asociaciones ni actividades y son no
bloqueantes.

## C.3.5 — Comunicación operativa de PQRS

### C.3.5.1A — Persistencia segura e idempotencia

Estado: **Cerrado, aprobado e integrado**.
Se incorporaron respuesta oficial única por PQRS, coherencia entre borrador y
fecha de envío, FK restrictiva y un ledger de idempotencia global para
respuestas y comentarios. El ledger usa identidad técnica aleatoria y opaca,
huella inmutable, resultados tipados, matriz de `CHECK` y FKs compuestas que
mantienen las referencias dentro de la PQRS de la operación.

La migración detecta y recupera estados parciales. SQLite conserva su respaldo
hasta comprobar igualdad exacta y aborta ante estados ambiguos; MySQL dispone
de rollback reintentable que restaura la FK original. La validación final
registró: SQLite, 19 pruebas y 127 aserciones con dos casos exclusivos de MySQL
omitidos; MySQL desechable, 17 pruebas y 83 aserciones con dos casos exclusivos
de SQLite omitidos; regresión relacionada, 51 pruebas y 183 aserciones; suite,
303 pruebas y 1.471 aserciones con dos casos exclusivos de MySQL omitidos.
`git diff --check` fue correcto y la concurrencia real usó dos conexiones.

La migración se aplicó en MySQL demo en el lote 21 y el ledger permanece vacío.

### C.3.5.1B-1 — Autorización, privacidad y conservación del expediente

Estado: **Cerrado, aprobado e integrado**.
Los controladores de respuesta y comentario aplican `404` contextual, `403`
efectivo, validación y ejecución. La visibilidad triestado de borradores se
centraliza según autor, rol y gestión efectiva: administrador y gestor pueden
consultar borradores ajenos, apoyo solo los propios y el autor revocado recibe
`403`, mientras el borrador ajeno invisible produce `404`.

Comentarios, adjuntos y actuaciones internas se filtran en backend. El
historial público usa una allowlist cerrada y trata acciones desconocidas como
privadas. Las descargas no exponen rutas físicas. Una PQRS con respuestas,
borradores, comentarios, actuaciones u operaciones del ledger no se elimina;
el rechazo vuelve explícitamente a edición con un mensaje no revelador.

La validación focal final registró 8 pruebas y 122 aserciones; la regresión de
respuestas, comentarios, expediente, autorización y aislamiento registró 38
pruebas y 210 aserciones. B-1 está integrado en `main`.

### C.3.5.1B-2 — Ciclo seguro de borradores y respuesta oficial única

Estado: **Cerrado, aprobado e integrado**.
B-2 permite al autor con gestión efectiva crear, editar, enviar y retirar
lógicamente sus borradores. La respuesta oficial —enviada desde borrador o de
forma directa— es única, inmutable y no eliminable. La consulta de borradores
ajenos no concede mutación: se preservan `404` contextual, `403` efectivo,
validación y ejecución.

Las cinco operaciones usan UUID ocultos, huellas canónicas y resultados
tipados en el ledger. El replay equivalente recupera el resultado sin duplicar
efectos y una clave reutilizada fuera de su actor, PQRS, operación o payload se
rechaza. PQRS y borrador se bloquean antes de mutar; la unicidad de base de
datos resuelve la carrera final por la respuesta oficial.

Los adjuntos se escriben en rutas deterministas por UUID y posición, con
SHA-256. El reemplazo registra un manifiesto limitado a referencias exactas;
la reconciliación no escanea rutas, verifica pertenencia y huella y conserva el
estado pendiente para reintento ante cualquier duda.

La migración `2026_08_13_180000_add_safe_draft_lifecycle.php` añade
`deleted_at`, el índice de borradores, el `CHECK` que solo permite retirar
borradores no enviados, `cleanup_manifest` y las operaciones/resultados B-2.
SQLite conserva respaldos hasta igualdad exacta. MySQL valida esquema, datos y
una gramática limitada de `CHECK` antes de DDL y recupera estados parciales. La
migración integrada se aplicó una sola vez en MySQL demo, en el lote 22;
C.3.5.1A continúa en lote 21 y el ledger demo está vacío.

La validación final de B-2 registró en SQLite 17 pruebas aprobadas, 153
aserciones y 3 casos exclusivos de MySQL omitidos; en MySQL, 14 pruebas
aprobadas, 118 aserciones y 6 casos exclusivos de SQLite omitidos. La regresión
de B-1, respuestas, expediente, autorización y aislamiento aprobó 38 pruebas y
257 aserciones. La compilación y limpieza de vistas y `git diff --check` fueron
correctos.

### C.3.5.1B-3 — Notificación verificable y reintentos seguros

Estado: **Cerrado, aprobado e integrado**.
La notificación posterior a `send_reply` y `send_draft` se reconcilia desde el
ledger con los estados `pending`, `completed` y `no_recipient`. La notificación
`database`, el job `database` y el resultado técnico se confirman atómicamente;
un replay o reintento no duplica respuesta, actuación, notificación ni job.

La reconciliación periódica procesa solo operaciones B-3 pendientes, cada cinco
minutos y sin solapamiento. Los fallos definitivos retornan únicamente su
operación a `pending`. Los 17 jobs históricos permanecen compatibles y fuera
del reconciliador.

El destinatario se revalida por contexto, membresía y permiso. Un usuario
revocado, externo o fuera de contexto no recibe información y la operación
termina en `no_recipient`. Los mensajes web son neutrales y nunca prometen
entrega de correo. Con la integración de B-3, C.3.5.1 queda completo y cerrado.

La validación focal de B-3 y notificaciones aprobó 20 pruebas y 82 aserciones;
la regresión relacionada de respuestas, B-1/B-2, autorización y aislamiento
aprobó 64 pruebas y 442 aserciones. La compilación y limpieza de vistas y
`git diff --check` fueron correctos.

## Cierre aprobado — C.3.5.2 Comunicación visual de PQRS

Estado: **Cerrado, aprobado e integrado**. Merge commit `2aabd6b`. La
comunicación visual de PQRS quedó consolidada: estados, prioridades, fechas y
responsable se representan de forma clara y consistente en listado y expediente.
No se incorporaron reglas de negocio nuevas, permisos, entidades ni cambios
arquitectónicos.

## Cierre aprobado — C.3.6 Prioridad y auditoría comprensible de PQRS

Estado: **Cerrado, aprobado e integrado**. Merge commit `be55a69`, commit
funcional `264e12f`. La prioridad de PQRS y la auditoría de actuaciones
quedaron comprensibles para el usuario: fechas, tiempos y acciones se
comunican con lenguaje claro y localizado. No se incorporaron reglas de
negocio nuevas, permisos, entidades ni cambios arquitectónicos.


## Estado oficial

```text
C.1: Cerrado y aprobado
C.2: Cerrado y aprobado
C.3.1: Cerrado y aprobado
C.3.2: Cerrado y aprobado
C.3.3: Cerrado y aprobado
C.3.4.1: Cerrado y aprobado
C.3.4-F: Cerrado y aprobado
C.3.4.2: Cerrado y aprobado
C.3.4.3: Cerrado y aprobado
C.3.5.1A: Cerrado, aprobado e integrado
C.3.5.1B-1: Cerrado, aprobado e integrado
C.3.5.1B-2: Cerrado, aprobado e integrado
C.3.5.1B-3: Cerrado, aprobado e integrado
C.3.5.1: Cerrado, aprobado e integrado
C.3.5.2: Cerrado, aprobado e integrado
C.3.6: Cerrado, aprobado e integrado
```

Los bloques C.3.7.1–C.3.7.3 corresponden al Sprint 11 y se documentan en
[sprint-11-multi-copropiedad-consola-administrativa.md](sprint-11-multi-copropiedad-consola-administrativa.md).

Sprint 10 permanece **Completado.**
