# ADR-010 — Implementar IA asistiva con RAG y revisión humana obligatoria

## Control documental

- **Identificador:** ADR-010
- **Título:** Implementar IA asistiva con RAG y revisión humana obligatoria
- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha:** 31 de julio de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## Contexto

Resuelve incorporará Inteligencia Artificial para asistir al Administrador con
información normativa autorizada. La IA no puede reemplazar su criterio ni su
responsabilidad y debe respetar el aislamiento de Organización y Copropiedad.

## Problema

Una respuesta generativa sin fuentes, contexto o control humano puede contener
errores, utilizar información de otro tenant o ser interpretada como una
decisión administrativa.

## Alternativas evaluadas

1. Generación libre sin recuperación documental.
2. RAG con uso automático de la respuesta.
3. RAG contextual con citas, trazabilidad y revisión humana obligatoria.

## Decisión

La IA será asistiva y se implementará mediante RAG sobre Documentos autorizados.
La recuperación filtrará previamente por ámbito y permisos; cada respuesta
incluirá citas y trazabilidad de fuentes, modelo y contexto. Toda salida quedará
pendiente de revisión y requerirá una acción humana explícita para aprobarla,
modificarla o rechazarla. La IA no decidirá ni ejecutará por sí sola actuaciones
del Administrador.

## Justificación

La combinación de fuentes gobernadas, aislamiento previo a la recuperación,
citas y revisión humana reduce el riesgo de fuga de datos y uso acrítico de
respuestas generadas.

## Consecuencias positivas

- Asistencia apoyada en documentación identificable.
- Administrador responsable de la decisión final.
- Trazabilidad de consulta, fuentes, respuesta y revisión.
- Independencia del proveedor mediante contratos y adaptadores.

## Consecuencias negativas

- Mayor latencia y costo por recuperación, generación y revisión.
- La aprobación humana limita automatizaciones completamente autónomas.
- Se requiere almacenar metadatos y estados adicionales.

## Riesgos

- Alucinaciones aun cuando existan fuentes.
- Prompt injection contenida en documentos.
- Filtros incorrectos que recuperen fragmentos de otra Copropiedad.
- Citas insuficientes o desalineadas con la respuesta.
- Uso informal de una salida antes de completar su revisión.

## Relación con otros ADR

- Depende de ADR-009 para disponer de fuentes gobernadas.
- Aplica el aislamiento de ADR-003, los ámbitos de ADR-004 y la autorización de
  ADR-005 y ADR-006.
- Puede invocar casos de uso según ADR-008, sin omitir revisión humana.

## Referencias documentales

- [Arquitectura objetivo](../05-arquitectura/arquitectura-objetivo.md), sección 13.
- [Visión del Producto](../01-producto/vision-del-producto.md), principios 4 y 7.
- [Dominio del Negocio](../01-producto/dominio-del-negocio.md), sección 3.6.
- [Product Backlog](../04-desarrollo-agil/product-backlog.md), épica Inteligencia Artificial.
