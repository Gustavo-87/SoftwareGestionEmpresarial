# Experiencia de Producto

## Control documental

- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha de creación:** 3 de agosto de 2026
- **Última actualización:** 3 de agosto de 2026
- **Responsable:** Product Owner de Resuelve

## Regla de gobernanza documental

Este documento establece criterios transversales de producto. Ningún agente de
Arquitectura, Desarrollo o Documentación podrá modificarlo sin una instrucción
explícita del Product Owner.

## 1. Propósito

Establecer la Experiencia de Producto como una condición transversal para la
evolución de Resuelve, de modo que las capacidades con interacción humana sean
comprensibles, utilizables y coherentes con el dominio y los casos de uso.

La Experiencia de Producto forma parte del valor entregado. No es una etapa
posterior de presentación ni una responsabilidad separada del incremento
funcional.

## 2. Alcance

Estos criterios aplican a toda capacidad web o futura experiencia móvil que
presente información, solicite datos o permita actuaciones de un Usuario.

Este documento no define un sistema de diseño, identidad visual, catálogo de
componentes, maquetas, tecnología frontend ni una arquitectura de tipo SPA.
Tampoco crea un dominio o módulo funcional nuevo.

## 3. Relación con el dominio y los casos de uso

La experiencia expresa las necesidades y resultados del producto a través de
la capa de Presentación. La Presentación captura la intención del Usuario,
invoca consultas o casos de uso de Aplicación y representa sus resultados. Los
casos de uso coordinan el Dominio, las transacciones y los controles
correspondientes.

```text
Experiencia de Producto
          │
          ▼
Presentación
          │
          ▼
Consultas y casos de uso de Aplicación
          │
          ▼
Dominio
```

La interfaz puede ofrecer validación inmediata y representar permisos,
capacidades y estados, pero no constituye la fuente de verdad de las reglas de
negocio ni de la autorización.

## 4. Principios normativos

1. **Frontend como parte del producto:** toda capacidad con interacción humana
   debe considerar su experiencia visible dentro del mismo alcance funcional.
2. **Coherencia con el dominio:** la interfaz utiliza el lenguaje, los estados y
   las acciones definidos por el negocio; no introduce reglas alternativas.
3. **Una sola lógica de negocio:** la Presentación consume consultas y casos de
   uso existentes. Cuando resulte necesaria una nueva operación, debe definirse
   en Aplicación o Dominio según su responsabilidad, no únicamente en la
   interfaz.
4. **Autorización en backend:** ocultar o deshabilitar una acción mejora la
   experiencia, pero no autoriza. El backend conserva la validación efectiva de
   contexto, membresía, permiso, capacidad y pertenencia del recurso.
5. **Contexto explícito:** la experiencia debe comunicar el ámbito de
   Organización o Copropiedad en el que el Usuario consulta o actúa cuando sea
   relevante para evitar ambigüedad.
6. **Retroalimentación comprensible:** las interacciones deben representar los
   resultados relevantes, incluidos éxito, ausencia de información, error y
   acceso no autorizado, sin exponer información sensible.
7. **Evolución incremental:** estos principios se aplican a los flujos que se
   incorporen o modifiquen, sin exigir un rediseño general de la aplicación.
8. **Consistencia entre canales:** web, API y futuros clientes móviles pueden
   presentar experiencias diferentes, pero deben compartir reglas, casos de
   uso y autorización.

## 5. Criterio transversal de refinamiento

Toda Épica, Historia de Usuario o Sprint con interacción humana debe precisar,
en proporción a su alcance:

- actor, objetivo y contexto operativo;
- punto de acceso y acciones disponibles;
- información necesaria para comprender o completar la tarea;
- estados visibles relevantes, incluidos vacío, éxito, error y acceso
  restringido;
- confirmación y consecuencias visibles de acciones sensibles;
- consultas y casos de uso que soportan la interacción;
- permisos y capacidades requeridos;
- trazabilidad funcional cuando corresponda.

Una capacidad interactiva no debe considerarse completa por disponer
únicamente de comportamiento backend cuando el alcance aprobado requiera que
un Usuario la consulte o ejecute.

## 6. Aplicación en futuras Épicas

- **Inteligencia Artificial:** debe distinguir contenido generado, fuentes,
  citas, estado de revisión y acciones humanas de aprobación, modificación o
  rechazo.
- **Reportes e Indicadores:** debe hacer explícitos ámbito, filtros, periodo,
  ausencia de datos y correspondencia entre consulta visible y exportación.
- **Configuración:** debe diferenciar parámetros de Organización y
  Copropiedad, comunicar sus efectos y prevenir cambios accidentales.
- **Integraciones:** debe comunicar vinculación, autorización, estado,
  sincronización, fallos y desconexión sin exponer secretos.
- **Auditoría y Trazabilidad:** debe facilitar la comprensión de actor, acción,
  recurso, contexto, origen y correlación según la autorización del Usuario.

Estas consideraciones refinan las Épicas existentes y no modifican su orden ni
crean una Épica de frontend independiente.

## 7. Criterio de mantenimiento

Este documento debe revisarse cuando una decisión aprobada cambie la relación
entre experiencia, canales, casos de uso o responsabilidades de la capa de
Presentación. Las decisiones tecnológicas estructurales continuarán sujetas al
proceso arquitectónico y a los ADR cuando corresponda.
