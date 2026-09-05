# ADR-006 — Separar Roles, permisos y capacidades comerciales

## Control documental

- **Identificador:** ADR-006
- **Título:** Separar Roles, permisos y capacidades comerciales
- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha:** 31 de julio de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## Contexto

La autorización actual combina cadenas de rol, métodos de `User`, una policy y
validaciones directas. La plataforma SaaS también necesita limitar funciones
según la suscripción de la Organización.

## Problema

Los permisos de una persona y la disponibilidad comercial de una función son
preguntas diferentes. Unificarlas produciría roles acoplados a planes y podría
otorgar acceso únicamente porque una capacidad esté contratada.

## Alternativas evaluadas

1. Usar los nombres de planes como mecanismo de autorización.
2. Incorporar capacidades comerciales dentro de los Roles.
3. Separar Roles, permisos y capacidades, combinándolos al autorizar un caso de
   uso.

## Decisión

Los permisos representarán acciones autorizables estables. Los Roles agruparán
permisos y se asignarán mediante membresías dentro de un ámbito. Las capacidades
representarán funciones comerciales habilitadas para la Organización. La
autorización efectiva exigirá capacidad cuando corresponda, membresía vigente,
permiso, pertenencia del recurso y reglas contextuales.

## Justificación

La separación evita mezclar el modelo comercial con la seguridad y permite que
cada dimensión evolucione sin alterar la otra.

## Consecuencias positivas

- Autorización coherente por ámbito y recurso.
- Roles independientes de los planes comerciales.
- Una capacidad contratada no concede acceso a un Usuario sin permiso.
- Cambios comerciales sin modificar las reglas internas de autorización.

## Consecuencias negativas

- Cada caso de uso debe combinar varias condiciones.
- Se requiere un catálogo estable de permisos y capacidades.
- La interfaz debe distinguir falta de permiso de capacidad no habilitada.

## Riesgos

- Tratar capacidad y permiso como sinónimos en código o documentación.
- Mantener comprobaciones directas de roles que omitan el ámbito.
- Distribuir la autorización nuevamente entre controladores y vistas.

## Relación con otros ADR

- Se apoya en las membresías de ADR-005 y los ámbitos de ADR-004.
- ADR-007 especializa el tratamiento comercial de las capacidades.

## Referencias documentales

- [Arquitectura objetivo](../05-arquitectura/arquitectura-objetivo.md), secciones 9 y 10.
- [Roles y permisos actuales](../02-funcional/roles-y-permisos.md).
- [Modelo del Dominio](../01-producto/modelo-del-dominio.md), secciones 2.3 y 4.
