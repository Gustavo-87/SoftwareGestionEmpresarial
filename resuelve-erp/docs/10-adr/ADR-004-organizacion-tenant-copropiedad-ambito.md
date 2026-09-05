# ADR-004 — Usar Organización como tenant comercial y Copropiedad como ámbito operativo

## Control documental

- **Identificador:** ADR-004
- **Título:** Usar Organización como tenant comercial y Copropiedad como ámbito operativo
- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha:** 31 de julio de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## Contexto

El cliente de Resuelve es una Organización que administra una o varias
Copropiedades. Cada Copropiedad conserva sus Unidades Privadas, PQRS,
Documentos, configuración y trazabilidad.

## Problema

La arquitectura debe distinguir la frontera comercial del cliente de la
frontera operativa donde se producen y aíslan la mayoría de los datos, sin
reducir ambos conceptos a una sola entidad.

## Alternativas evaluadas

1. Tratar cada Copropiedad como cliente y tenant independiente.
2. Usar únicamente Organización como frontera de todos los datos.
3. Usar Organización como tenant comercial y Copropiedad como ámbito operativo
   y frontera principal de datos.

## Decisión

La Organización será el tenant comercial y el contexto principal de la
suscripción. Una Organización agrupará una o varias Copropiedades. La
Copropiedad será el ámbito operativo y la frontera principal para datos propios
de su gestión.

## Justificación

La separación refleja el modelo de negocio aprobado y permite gestión
centralizada sin mezclar la información operativa de las Copropiedades.

## Consecuencias positivas

- Correspondencia directa entre arquitectura y cliente comercial.
- Administración multicopropiedad dentro de una Organización.
- Aislamiento explícito de PQRS, Unidades, Documentos y configuración.
- Posibilidad de reportes consolidados sujetos a autorización.

## Consecuencias negativas

- Todo dato debe declarar si pertenece a plataforma, Organización o
  Copropiedad.
- Los flujos deben resolver correctamente ambos niveles de contexto.
- Algunas entidades requerirán ambas claves para reforzar integridad.

## Riesgos

- Confundir pertenencia comercial con autorización operativa.
- Crear catálogos globales sin definir conscientemente su ámbito.
- Permitir cambio de Copropiedad sin revalidar la membresía del Usuario.

## Relación con otros ADR

- Especializa el modelo de aislamiento de ADR-003.
- Proporciona los ámbitos usados por ADR-005, ADR-006 y ADR-007.

## Referencias documentales

- [Arquitectura objetivo](../05-arquitectura/arquitectura-objetivo.md), secciones 2, 7 y 8.
- [Visión del Producto](../01-producto/vision-del-producto.md), secciones 2, 5 y 8.
- [Dominio del Negocio](../01-producto/dominio-del-negocio.md), secciones 2 y 3.
