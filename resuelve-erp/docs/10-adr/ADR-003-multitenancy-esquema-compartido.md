# ADR-003 — Implementar multi-tenancy con base de datos y esquema compartidos

## Control documental

- **Identificador:** ADR-003
- **Título:** Implementar multi-tenancy con base de datos y esquema compartidos
- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha:** 31 de julio de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## Contexto

La implementación actual representa una sola Copropiedad y sus entidades no
contienen claves de tenant. La plataforma objetivo debe atender múltiples
Organizaciones y Copropiedades con aislamiento seguro y evolución operativa
simple.

## Problema

Se necesita una estrategia de persistencia multi-tenant que aísle los datos sin
multiplicar prematuramente bases de datos, migraciones, conexiones y procesos
operativos.

## Alternativas evaluadas

1. Base de datos independiente por Organización.
2. Esquema independiente por Organización.
3. Base de datos y esquema compartidos con claves de contexto.

## Decisión

Resuelve utilizará una base de datos y un esquema compartidos. La información
se aislará mediante `organizacion_id` y `copropiedad_id`, según su ámbito,
acompañados por consultas contextualizadas, autorización, restricciones e
índices compuestos y segmentación de recursos de infraestructura.

## Justificación

El esquema compartido facilita una adopción incremental desde el modelo actual,
mantiene una operación sencilla y es suficiente para el alcance conocido si se
aplica defensa en profundidad.

## Consecuencias positivas

- Una sola secuencia de migraciones y operación de base de datos.
- Consultas consolidadas autorizadas entre Copropiedades de una Organización.
- Incorporación gradual de claves de contexto en los datos existentes.
- Uso eficiente de infraestructura compartida.

## Consecuencias negativas

- Toda consulta operativa debe incluir el contexto correcto.
- Un defecto de autorización o filtrado puede exponer datos entre tenants.
- Índices y restricciones deben diseñarse con claves compuestas.

## Riesgos

- Consultas, cachés, archivos o jobs sin tenant.
- Route model binding que resuelva recursos globalmente.
- Confiar únicamente en global scopes de Eloquent.

## Relación con otros ADR

- ADR-004 define el significado de las claves de contexto.
- ADR-005 define quién puede actuar dentro de esos contextos.
- ADR-002 determina su incorporación gradual.

## Referencias documentales

- [Arquitectura objetivo](../05-arquitectura/arquitectura-objetivo.md), sección 8.
- [Modelo de datos actual](../03-tecnica/modelo-de-datos.md), secciones 5 y 7.
- [Dominio del Negocio](../01-producto/dominio-del-negocio.md), principios 1 a 4.
