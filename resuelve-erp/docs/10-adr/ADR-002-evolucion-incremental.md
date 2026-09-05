# ADR-002 — Evolucionar el sistema de forma incremental sin reescritura total

## Control documental

- **Identificador:** ADR-002
- **Título:** Evolucionar el sistema de forma incremental sin reescritura total
- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha:** 31 de julio de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## Contexto

El sistema actual ya ofrece autenticación, gestión de PQRS, respuestas,
adjuntos, notificaciones, informes y configuración para una sola Copropiedad.
La arquitectura objetivo incorpora multi-tenancy, modularidad, Gestión
Documental, API e IA.

## Problema

La evolución debe cerrar la distancia entre el estado actual y la plataforma
objetivo sin interrumpir innecesariamente el producto ni sustituir de una vez
capacidades funcionales existentes.

## Alternativas evaluadas

1. Reescritura completa y sustitución en una única liberación.
2. Mantener indefinidamente la estructura actual.
3. Evolución incremental mediante compatibilidad, migración por fases y retiro
   gradual de mecanismos heredados.

## Decisión

Resuelve evolucionará incrementalmente. Los nuevos límites y casos de uso se
incorporarán alrededor de los flujos existentes; los datos se contextualizarán
por etapas y los mecanismos heredados se retirarán solo después de verificar su
reemplazo. No se realizará una reescritura total.

## Justificación

La estrategia reduce riesgo funcional y de datos, permite validar cada cambio y
preserva el valor implementado mientras se construyen los fundamentos SaaS.

## Consecuencias positivas

- Entregas más pequeñas y verificables.
- Menor riesgo de pérdida de comportamiento o datos.
- Capacidad de priorizar fundamentos antes que módulos futuros.
- Transición reversible en las etapas críticas.

## Consecuencias negativas

- Existirán periodos de compatibilidad y doble representación.
- La transición puede requerir adaptadores y backfills temporales.
- El avance depende de retirar oportunamente la deuda transitoria.

## Riesgos

- Mantener indefinidamente mecanismos temporales.
- Activar múltiples Copropiedades antes de cerrar consultas globales.
- Mezclar refactorización arquitectónica con cambios funcionales no aprobados.

## Relación con otros ADR

- Aplica ADR-001 sobre el monolito existente.
- Define la forma de introducir ADR-003 a ADR-010.

## Referencias documentales

- [Arquitectura objetivo](../05-arquitectura/arquitectura-objetivo.md), secciones 3, 16 y 18.
- [Arquitectura actual](../05-arquitectura/arquitectura-actual.md).
- [Línea base](../01-producto/linea-base-estado-actual.md).
