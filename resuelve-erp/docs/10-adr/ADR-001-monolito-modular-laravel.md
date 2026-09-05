# ADR-001 — Adoptar un monolito modular Laravel

## Control documental

- **Identificador:** ADR-001
- **Título:** Adoptar un monolito modular Laravel
- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha:** 31 de julio de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## Contexto

Resuelve es actualmente una aplicación monolítica Laravel con Blade, Eloquent y
una única unidad de despliegue. Debe evolucionar hacia una plataforma SaaS
multi-copropiedad incorporando nuevos dominios sin desechar las capacidades de
PQRS ya construidas.

## Problema

Se requiere delimitar responsabilidades y permitir la evolución independiente
de capacidades funcionales sin asumir el costo operativo y la complejidad de
un sistema distribuido.

## Alternativas evaluadas

1. Mantener el monolito actual sin límites internos explícitos.
2. Evolucionar hacia un monolito modular Laravel.
3. Reemplazar el sistema por microservicios.

## Decisión

Resuelve adoptará un monolito modular Laravel. Los módulos tendrán límites y
dependencias explícitos dentro de una sola aplicación desplegable. No se
utilizarán microservicios en esta etapa.

## Justificación

La decisión conserva la plataforma existente, reduce riesgo de transición y
permite mejorar cohesión y desacoplamiento sin introducir coordinación
distribuida antes de contar con una necesidad verificable.

## Consecuencias positivas

- Reutilización del conocimiento, código e infraestructura actuales.
- Transacciones y operación más simples que en una arquitectura distribuida.
- Límites funcionales que facilitan mantenimiento y evolución.
- Posibilidad futura de extraer un módulo si existe justificación suficiente.

## Consecuencias negativas

- Los módulos comparten proceso, despliegue y base de código.
- Se requiere disciplina para impedir dependencias internas indebidas.
- Una falla no aislada puede afectar toda la aplicación.

## Riesgos

- Crear módulos solo nominales mientras persiste el acoplamiento actual.
- Convertir componentes compartidos en dependencias centrales excesivas.
- Introducir abstracciones sin valor como sustituto de límites reales.

## Relación con otros ADR

- Fundamenta ADR-002 y ADR-008.
- Es compatible con ADR-003, que mantiene infraestructura de datos compartida.

## Referencias documentales

- [Arquitectura objetivo](../05-arquitectura/arquitectura-objetivo.md), secciones 2 a 6.
- [Arquitectura actual](../05-arquitectura/arquitectura-actual.md), secciones 2 y 3.
- [Visión del Producto](../01-producto/vision-del-producto.md), principios 5 y 6.
