# ADR-008 — Reutilizar casos de uso entre web, API, jobs y aplicaciones móviles

## Control documental

- **Identificador:** ADR-008
- **Título:** Reutilizar casos de uso entre web, API, jobs y aplicaciones móviles
- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha:** 31 de julio de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## Contexto

Blade es el núcleo operativo inicial. Resuelve incorporará posteriormente API y
aplicaciones móviles, y utilizará jobs y comandos para procesos no interactivos.
Actualmente parte de la lógica se encuentra en controladores web.

## Problema

Implementar las mismas reglas en cada canal produciría duplicación,
comportamientos inconsistentes y controles de seguridad diferentes.

## Alternativas evaluadas

1. Mantener la lógica en controladores Blade y duplicarla para otros canales.
2. Convertir primero la aplicación en una SPA.
3. Extraer casos de uso independientes del canal y reutilizarlos desde sus
   adaptadores de entrada.

## Decisión

La lógica de aplicación se organizará en casos de uso reutilizables por
controladores Blade, controladores API, jobs y comandos. Las futuras
aplicaciones móviles consumirán esos mismos casos de uso mediante API. Blade se
mantendrá como núcleo web inicial.

## Justificación

Una sola implementación de cada operación conserva reglas, autorización y
transacciones consistentes y permite añadir canales sin reescribir el dominio.

## Consecuencias positivas

- Reglas de negocio y seguridad consistentes entre canales.
- Controladores más pequeños y enfocados en transporte.
- Preparación gradual para API y móviles sin exigir una SPA.
- Casos de uso invocables desde procesamiento asíncrono.

## Consecuencias negativas

- Requiere extraer gradualmente lógica de los controladores actuales.
- Los casos de uso deben evitar dependencias de HTTP o Blade.
- Deben definirse entradas, resultados y errores consistentes.

## Riesgos

- Crear una segunda capa que solo delegue sin separar responsabilidades.
- Permitir que API o jobs omitan autorización contextual.
- Diseñar anticipadamente contratos móviles aún no priorizados.

## Relación con otros ADR

- Implementa los límites internos de ADR-001 mediante la estrategia de ADR-002.
- Debe aplicar el contexto y autorización definidos por ADR-003 a ADR-006.

## Referencias documentales

- [Arquitectura objetivo](../05-arquitectura/arquitectura-objetivo.md), secciones 4, 6 y 11.
- [Visión del Producto](../01-producto/vision-del-producto.md), principios 5 y 7.
- [Arquitectura actual](../05-arquitectura/arquitectura-actual.md), sección 3.3.
