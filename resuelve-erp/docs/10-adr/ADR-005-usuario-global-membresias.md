# ADR-005 — Mantener Usuario global con membresías por ámbito

## Control documental

- **Identificador:** ADR-005
- **Título:** Mantener Usuario global con membresías por ámbito
- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha:** 31 de julio de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## Contexto

El modelo actual concentra identidad, rol y datos de residencia en `User`. En
la plataforma objetivo, una persona puede actuar dentro de ámbitos diferentes y
su identidad autenticable no debe confundirse con su pertenencia o función en
una Copropiedad.

## Problema

Duplicar cuentas por Copropiedad fragmentaría la identidad y dificultaría que
un Usuario autorizado trabaje en distintos ámbitos. Mantener un único rol
global tampoco permite expresar autorizaciones contextuales.

## Alternativas evaluadas

1. Crear una cuenta independiente por Copropiedad.
2. Mantener Usuario global con un único rol global.
3. Mantener identidad global y representar la participación mediante
   membresías por ámbito.

## Decisión

`Usuario` será una identidad autenticable global. Su participación se expresará
mediante membresías vigentes de Organización o Copropiedad, a las cuales se
asignarán los Roles aplicables. La condición de Propietario o Residente se
representará en el dominio y no se confundirá automáticamente con un Rol.

## Justificación

La decisión separa autenticación, pertenencia y autorización, y permite que una
misma identidad opere únicamente en los contextos que tenga autorizados.

## Consecuencias positivas

- Una sola identidad y credencial por Usuario.
- Autorización diferente según Organización o Copropiedad.
- Menor duplicación de cuentas y datos de autenticación.
- Separación entre Usuario técnico, Persona y vínculos con Unidades Privadas.

## Consecuencias negativas

- Toda operación debe resolver una membresía vigente.
- El cambio de contexto añade un paso explícito a la experiencia de Usuario.
- La migración desde `users.role`, `tower` y `unit` requiere compatibilidad
  temporal.

## Riesgos

- Autorizar por identidad global sin comprobar membresía.
- Confundir una relación de residencia o propiedad con permisos del sistema.
- Conservar indefinidamente el rol global heredado.

## Relación con otros ADR

- Utiliza los ámbitos definidos por ADR-004 y aislados por ADR-003.
- Proporciona la base de asignación de Roles de ADR-006.

## Referencias documentales

- [Arquitectura objetivo](../05-arquitectura/arquitectura-objetivo.md), secciones 7 y 9.
- [Dominio del Negocio](../01-producto/dominio-del-negocio.md), secciones 2.3 a 2.9.
- [Modelo de dominio actual](../03-tecnica/modelo-de-dominio.md), secciones 3.1 y 6.
