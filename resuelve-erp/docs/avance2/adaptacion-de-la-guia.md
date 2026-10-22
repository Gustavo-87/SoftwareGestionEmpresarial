# Adaptación de la guía comercial — Autorización del profesor

## Requisito original de la guía

La guía del curso plantea un ERP comercial con procesos integrados de
**ventas, compras e inventario**, exigiendo demostrar un proceso completo que
atraviese al menos tres módulos con datos reales, reglas de negocio
verificables y evidencia documental.

## Autorización

El profesor autorizó adaptar la guía al negocio real de **Resuelve**: gestión
residencial (copropiedades). En este dominio **no se requieren ventas, compras
ni inventario**; el equivalente funcional es la operación de la copropiedad.

## Equivalencia de módulos

| Guía comercial | Adaptación en Resuelve ERP | Módulo implementado |
| --- | --- | --- |
| Ventas (solicitud del cliente → pedido → entrega) | Solicitud del residente → gestión → respuesta | **PQRS** (radicación, respuesta oficial, cierre) |
| Compras (orden → proveedor → recepción) | Solicitud de trabajo → responsable → ejecución | **Gestión de Mantenimiento** (solicitudes, responsables, fechas, resultados) |
| Inventario / trazabilidad de lotes | Trazabilidad documental de la operación | **Gestión Documental** (versiones, aprobación, evidencia exacta) |

El proceso integrado del corte —**PQRS → mantenimiento → evidencia documental →
resultado → expediente con PDF**— cumple el objetivo pedagógico de la guía: un
proceso completo de negocio atravesando tres módulos, con reglas de autorización,
trazabilidad y estados verificables.

## Conservación de los requisitos exigenciales

La adaptación cambia el **dominio**, no el rigor exigido:

- **Proceso completo y verificable:** flujo documentado en
  [integracion-de-modulos.md](integracion-de-modulos.md), cubierto por pruebas
  automatizadas (ver matriz de reglas).
- **Datos reales:** fixture reproducible con ≥10 registros por módulo principal
  (`DemoIntegracionFixtureSeeder`), sin tablas artificiales.
- **Reglas de negocio:** 11 reglas verificables con pruebas de denegación,
  cruce de contexto, reintentos y atomicidad
  ([matriz-de-reglas.md](matriz-de-reglas.md)).
- **Evidencia documental:** versión exacta utilizada como prueba, sin alterar el
  ciclo documental (equivalente al control de lotes/recibos de la guía).

## Entregables de software vs. académicos

| Software | Académico |
| --- | --- |
| Historias implementadas con interfaz, backend y pruebas (HU-PQRS-03, HU-MAN-03/04, HU-DOC-03, HU-REP-03/04/05) | Este directorio, MER y diccionario actualizados, guion de video, fixture reproducible |
| Pendientes del Sprint 18 (envío real de correos, saneamiento RBAC) **separados** del alcance | Control de corte explícito en [README](README.md) |
