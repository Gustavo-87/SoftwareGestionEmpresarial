# IA — Asistencia documental con revisión humana (HU-IA-01)

## Estado

**Trabajo futuro. Pausado.** La propuesta funcional y el Diseño Técnico
Definitivo permanecen aprobados, pero el Product Owner pausó su implementación
el 4 de agosto de 2026 hasta corregir y aceptar explícitamente la experiencia
visible heredada del Sprint 10.

Este documento no pertenece a ningún Sprint. Fue reclasificado desde la
carpeta de desarrollo ágil el 25 de agosto de 2026 para separarlo del Sprint 11
oficial (Multi-copropiedad y Consola Administrativa).

No debe reanudarse IA ni modificarse o ejecutarse su migración mientras la
pausa esté vigente. Los cambios locales existentes deben preservarse: no se
eliminan, sobrescriben ni revierten como consecuencia de esta decisión.

El diseño se formaliza en
[IA — Asistencia documental con revisión humana](../../03-tecnica/ia-asistencia-documental.md).

## Objetivo

Entregar el primer incremento visible de Inteligencia Artificial asistiva de
Resuelve: permitir que un Administrador contextual autorizado analice un único
Documento normativo de la Copropiedad activa, reciba consideraciones
preliminares con referencias verificables y registre obligatoriamente su
revisión humana.

La asistencia no constituye concepto jurídico, decisión administrativa ni
actuación de negocio.

## HU-IA-01 refinada — Asistencia documental autorizada

Como Administrador contextual autorizado
Quiero consultar disposiciones de un Documento normativo aprobado y vigente de
la Copropiedad activa
Para analizar una situación administrativa con apoyo documental verificable,
sin transferir mi criterio ni responsabilidad a la Inteligencia Artificial.

## Alcance

- acceso exclusivo para Administradores contextuales autorizados;
- selección explícita de un único Documento y su Versión elegible;
- formulación de una consulta concreta;
- identificación de disposiciones relacionadas;
- explicación de consideraciones preliminares;
- advertencia expresa cuando no exista evidencia suficiente;
- referencias verificables por página o sección;
- revisión humana mediante aprobación, modificación o rechazo;
- conservación diferenciada del resultado original y el contenido humano
  modificado;
- flujo web completo en Blade y trazabilidad contextual.

No se producen efectos sobre PQRS, Documentos, Notificaciones ni otros módulos.

## Experiencia visible obligatoria

El flujo funcional será:

```text
Seleccionar Documento
        ↓
Formular consulta
        ↓
Visualizar procesamiento
        ↓
Recibir análisis generado
        ↓
Consultar fuentes y referencias
        ↓
Aprobar, modificar o rechazar
```

La experiencia debe comunicar Copropiedad activa, Documento, tipo, Versión,
vigencia y nivel de acceso. Debe distinguir claramente contenido generado,
fuentes, referencias, advertencias, evidencia insuficiente y revisión humana.

Toda salida se identifica como contenido generado por IA, comienza en
`pendiente_revision` y advierte que no constituye concepto jurídico ni
decisión administrativa. Deben representarse como mínimo los estados:

- sin Documentos elegibles;
- consulta incompleta;
- procesamiento;
- `pendiente_revision`;
- `aprobada`;
- `modificada`;
- `rechazada`;
- `fallida`;
- evidencia insuficiente;
- acceso restringido o autorización revocada.

El Sprint no se considerará completo con infraestructura exclusivamente
backend.

## Fuentes documentales permitidas

Cada solicitud utilizará exactamente un Documento de la Copropiedad activa y
su Versión aprobada y vigente. Los únicos tipos permitidos son:

- Reglamento;
- Manual de Convivencia.

El Documento debe estar activo y su nivel de acceso debe ser compatible con la
autorización del Administrador. La identidad exacta del Documento y la Versión
utilizada se conserva aunque posteriormente sea sustituida o pierda vigencia.

No se admiten Internet, Ley 675 no gobernada, múltiples Documentos, búsqueda
global, Versiones no aprobadas o no vigentes, Documentos archivados, fuentes de
otra Copropiedad ni información suministrada por el Usuario como si fuera
evidencia documental.

Toda afirmación documental debe enlazarse con una referencia verificable de
página o sección. Si no se localiza respaldo suficiente, el resultado debe
declararlo expresamente.

## Autorización contextual

La autorización efectiva combina:

- Usuario autenticado;
- `ContextoOperativo` válido;
- Membresía vigente;
- permiso específico de asistencia IA;
- permiso documental aplicable;
- pertenencia del Documento a la Copropiedad activa;
- tipo y estado del Documento;
- aprobación, vigencia y nivel de acceso de la Versión.

`users.role`, la visibilidad en Blade y los identificadores recibidos desde el
cliente no constituyen autorización. Las condiciones deben revalidarse al
iniciar la solicitud, consultar el resultado y registrar la revisión. Los
recursos externos, revocados e inexistentes deben resolverse uniformemente sin
revelar su existencia.

La capacidad comercial solo se aplicará si existe infraestructura real
reutilizable. En caso contrario quedará como dependencia futura de
Configuración, sin solución provisional.

## Revisión humana obligatoria

- **Aprobar:** registra que el Administrador revisó y aceptó el análisis para
  uso informativo.
- **Modificar:** conserva el resultado original y registra una versión humana
  diferenciada.
- **Rechazar:** descarta el resultado y registra una observación.

La generación nunca implica aprobación. La revisión exige una acción humana
explícita y autorización vigente; no altera fuentes ni contenido original y no
ejecuta actuaciones en nombre del Administrador.

## Trazabilidad mínima

Debe conservarse:

- Usuario solicitante;
- Organización y Copropiedad;
- Documento y Versión exactos;
- consulta;
- resultado original;
- resultado humano modificado, si existe;
- decisión de revisión;
- observación de rechazo;
- fechas y estados.

No se almacenarán secretos, telemetría innecesaria del proveedor ni datos
personales adicionales. La trazabilidad debe permitir reconstruir la consulta,
la fuente utilizada, el resultado generado y la decisión humana.

## Exclusiones

- `HU-IA-02`;
- chat abierto, Agentes y memoria conversacional;
- entrenamiento;
- embeddings y base vectorial;
- RAG completo, búsqueda global o entre múltiples fuentes;
- Internet y Ley 675 como fuente no gobernada;
- OCR o tecnología de extracción seleccionada anticipadamente;
- automatizaciones y procesamiento programado o masivo;
- respuesta o borrador automático de PQRS;
- ejecución de actuaciones o modificación de otros módulos;
- nuevos indicadores, reportes o Notificaciones;
- operación multicopropiedad;
- gestores, apoyo, auditores, residentes, propietarios y procesos automáticos;
- integraciones externas distintas del proveedor mínimo que posteriormente sea
  aprobado;
- proveedor, modelo o infraestructura definitiva.

## Riesgos

1. Afirmaciones no respaldadas o referencias desalineadas.
2. Interpretación del resultado como concepto jurídico definitivo.
3. Uso informal del contenido antes de la revisión.
4. Instrucciones maliciosas contenidas en el Documento.
5. Fuga de información entre Copropiedades.
6. Uso de una fuente no elegible o con autorización revocada.
7. Inclusión innecesaria de datos personales en la consulta.
8. Pérdida de correspondencia entre archivo, contenido y referencias.
9. Fallos parciales que aparenten un resultado válido.
10. Dependencia prematura de una tecnología o proveedor.

## Definition of Done

El Sprint podrá declararse Completado cuando:

- exista el flujo funcional completo en Blade;
- solo un Administrador contextual autorizado pueda utilizarlo;
- la Copropiedad activa y la fuente exacta sean explícitas;
- únicamente se admitan Reglamentos y Manuales de Convivencia elegibles;
- cada solicitud use un solo Documento y una sola Versión;
- contexto, Membresía, permisos, pertenencia, estado y vigencia se validen en
  backend;
- una consulta produzca análisis preliminar o declare evidencia insuficiente;
- toda afirmación documental tenga referencia verificable por página o sección;
- el contenido generado se identifique inequívocamente como IA;
- toda salida comience en `pendiente_revision` y soporte los cinco estados de
  revisión y fallo aprobados;
- puedan registrarse aprobación, modificación y rechazo mediante acción humana;
- resultado original y modificación humana permanezcan diferenciados;
- se conserve la trazabilidad mínima aprobada sin información innecesaria;
- ninguna salida produzca efectos de negocio;
- carga, vacío, procesamiento, éxito, evidencia insuficiente, fallo y acceso
  restringido sean visibles;
- el flujo cumpla accesibilidad básica y comportamiento responsive de la línea
  base del Sprint 10;
- las pruebas cubran aislamiento, IDOR, CSRF, permisos, revocación, fuentes no
  elegibles, referencias, estados y revisión humana;
- la regresión completa finalice sin fallos;
- la documentación oficial quede sincronizada;
- no se incorpore ningún elemento excluido.

## Diseño Técnico Definitivo aprobado

El diseño aprobado establece:

- Laravel como producto responsable de Blade, autorización contextual,
  persistencia, colas, idempotencia, estados y revisión humana;
- un prototipo Python académico, separado y reemplazable, que demuestra los
  contratos en modo determinista sin LLM y sin acceso directo a datos de
  Resuelve;
- los contratos `RecuperadorEvidenciaDocumental` y
  `GeneradorAsistenciaDocumental`, independientes de proveedor y tecnología;
- procesamiento asíncrono e idempotente con resultados atómicos;
- los estados `procesando`, `pendiente_revision`, `aprobada`, `modificada`,
  `rechazada` y `fallida`, con transiciones explícitas;
- persistencia contextual de solicitud, resultado, revisión, manifiesto y
  referencias verificables;
- límites operativos configurables y el permiso
  `ia.asistencia_documental`;
- aislamiento por Organización y Copropiedad, fuente privada autorizada y
  revisión humana sin efectos de negocio;
- una matriz mínima de pruebas funcionales, contractuales, contextuales, de
  seguridad, idempotencia, referencias y experiencia Blade.

No existen decisiones bloqueantes para implementar el alcance aprobado.
Framework, proveedor, modelo, OCR, extracción definitiva, valores operativos e
infraestructura final permanecen deliberadamente sin seleccionar.
