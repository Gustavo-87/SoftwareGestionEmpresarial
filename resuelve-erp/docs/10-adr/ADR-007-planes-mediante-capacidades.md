# ADR-007 — Desacoplar los planes Básico y Pro mediante capacidades habilitadas

## Control documental

- **Identificador:** ADR-007
- **Título:** Desacoplar los planes Básico y Pro mediante capacidades habilitadas
- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha:** 31 de julio de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## Contexto

Resuelve tendrá inicialmente los planes Básico y Pro. Los nombres, composición
y condiciones comerciales pueden cambiar, mientras que los módulos necesitan
identificadores técnicos estables para determinar funciones disponibles.

## Problema

Condicionar el comportamiento a expresiones como `plan == pro` acoplaría la
arquitectura a nombres comerciales y dificultaría excepciones, cambios de oferta
o incorporación de nuevos planes.

## Alternativas evaluadas

1. Consultar directamente el nombre del plan en cada módulo.
2. Mantener configuraciones booleanas independientes sin catálogo.
3. Traducir las ofertas comerciales a capacidades habilitadas para la
   Organización.

## Decisión

Básico y Pro serán ofertas comerciales asociadas con conjuntos de capacidades.
Los módulos consultarán claves estables de capacidad habilitada para la
Organización y no el nombre del plan. Las excepciones o vigencias se expresarán
en la habilitación efectiva.

## Justificación

La decisión permite evolucionar el modelo comercial sin cambiar las reglas ni
la estructura de los módulos funcionales.

## Consecuencias positivas

- Desacoplamiento entre producto comercial y arquitectura funcional.
- Soporte futuro para nuevas ofertas y excepciones controladas.
- Evaluación uniforme de funciones habilitadas.
- Trazabilidad de cambios de capacidad por Organización.

## Consecuencias negativas

- Se requiere resolver la capacidad efectiva de la Organización.
- Deben mantenerse sincronizadas ofertas, suscripciones y habilitaciones.
- La caché de capacidades necesita invalidación contextual correcta.

## Riesgos

- Reintroducir condiciones por nombre de plan en controladores o vistas.
- Usar capacidades para reemplazar permisos de Usuario.
- Habilitaciones inconsistentes durante cambios de suscripción.

## Relación con otros ADR

- Aplica la separación establecida en ADR-006.
- Usa Organización como titular comercial según ADR-004.

## Referencias documentales

- [Arquitectura objetivo](../05-arquitectura/arquitectura-objetivo.md), sección 10.
- [Visión del Producto](../01-producto/vision-del-producto.md), secciones 8 y 9.
- [Modelo de Negocio](../01-producto/modelo-de-negocio.md), sección 5.
