# ADR-009 — Implementar Gestión Documental antes de incorporar IA

## Control documental

- **Identificador:** ADR-009
- **Título:** Implementar Gestión Documental antes de incorporar IA
- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha:** 31 de julio de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## Contexto

La IA de Resuelve deberá consultar documentos normativos autorizados, incluidos
el Reglamento, el Manual de Convivencia y la Ley 675 de 2001. El sistema actual
no representa Documentos, versiones, vigencia, acceso ni procesamiento
documental.

## Problema

Incorporar IA antes de gobernar sus fuentes impediría asegurar procedencia,
vigencia, autorización, aislamiento y citas verificables.

## Alternativas evaluadas

1. Cargar documentos directamente en el componente de IA sin dominio
   documental.
2. Desarrollar Gestión Documental e IA simultáneamente.
3. Implementar primero Gestión Documental y usar únicamente documentos
   aprobados como fuentes posteriores de IA.

## Decisión

Resuelve implementará Gestión Documental antes de incorporar IA. Los Documentos
conservarán ámbito, clase, versión, vigencia, estado, archivo original, hash,
origen, acceso y responsables. Solo versiones autorizadas y aprobadas podrán
habilitarse para RAG.

## Justificación

La IA necesita fuentes gobernadas para respetar el aislamiento entre
Copropiedades, recuperar contenido vigente y producir respuestas trazables.

## Consecuencias positivas

- Fuentes documentales identificables, versionadas y autorizadas.
- Base común para consulta humana y RAG.
- Aislamiento de Reglamentos y Manuales por Copropiedad.
- Tratamiento trazable de la Ley 675 como fuente normativa de plataforma.

## Consecuencias negativas

- La incorporación de IA depende de completar primero este fundamento.
- Se requiere almacenar y procesar versiones y metadatos adicionales.
- Los documentos necesitan un ciclo de estado antes de ser indexados.

## Riesgos

- Indexar versiones no aprobadas, vencidas o sin ámbito.
- Mezclar fuentes particulares y de plataforma.
- Perder correspondencia entre archivo, texto extraído y fragmentos indexados.

## Relación con otros ADR

- Usa el aislamiento de ADR-003 y los ámbitos de ADR-004.
- Es prerrequisito directo de ADR-010.

## Referencias documentales

- [Arquitectura objetivo](../05-arquitectura/arquitectura-objetivo.md), sección 12.
- [Dominio del Negocio](../01-producto/dominio-del-negocio.md), secciones 2.12 a 2.13 y 3.4.
- [Product Backlog](../04-desarrollo-agil/product-backlog.md), épica Gestión Documental.
