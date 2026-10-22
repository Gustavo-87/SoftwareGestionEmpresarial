# Avance 2 — Integración de módulos (PQRS · Mantenimiento · Documentos)

Entregables académicos del segundo corte de Resuelve ERP. Documentan la
integración de tres módulos mediante un proceso completo y verificable:
**el expediente de una PQRS que origina mantenimiento, se evidencia con
documentos (versión exacta), se finaliza con resultado separado y se exporta
como expediente integrado en PDF.**

## Contenido

| Documento | Propósito |
| --- | --- |
| [integracion-de-modulos.md](integracion-de-modulos.md) | Flujo verificable paso a paso y reproducción de la demostración con datos reales. |
| [aceptacion-visual.md](aceptacion-visual.md) | Apertura con la base aislada, usuarios/roles de demostración (sin contraseñas) y pasos exactos de aceptación visual, con la verificación ya realizada. |
| [matriz-de-reglas.md](matriz-de-reglas.md) | Reglas de negocio verificables con su ubicación y pruebas asociadas. |
| [guion-video.md](guion-video.md) | Guion de la demostración en video (≤10 minutos). **Estado: solo guion; video pendiente de grabación.** |
| [adaptacion-de-la-guia.md](adaptacion-de-la-guia.md) | Adaptación de los requisitos comerciales de la guía, autorizada por el profesor. |

## Alcance del corte

- **Módulos integrados:** PQRS (radicación y gestión), Gestión de mantenimiento
  (solicitudes, responsables y ejecución) y Gestión Documental (versiones,
  aprobación y evidencia). Los enlaces entre módulos se realizan con las
  migraciones `add_integracion_to_mantenimientos_table` y
  `create_mantenimiento_documento_evidencia_table`.
- **Fuera de alcance (autorizado):** ventas, compras e inventario. Ver
  [adaptacion-de-la-guia.md](adaptacion-de-la-guia.md).
- **Base de datos:** la demostración y los datos reproducibles se cargan
  **únicamente** en una base aislada (`resuelve_sprint19_demo`); la base de
  desarrollo no recibe seeders ni saneamientos.

## Entregables separados (control de corte)

| Tipo | Entregable | Estado |
| --- | --- | --- |
| Software | Historias HU-PQRS-03, HU-MAN-03/04, HU-DOC-03, HU-REP-03/04/05 | Implementadas; cierre formal pendiente de aprobación |
| Académico | Este directorio (`docs/avance2/`), MER y diccionario actualizados, fixture de datos, guion de video | Listos para revisión |
| Separado | Pendientes del Sprint 18 (envío real de correos, saneamiento de datos RBAC) | No se mezclan con el alcance del corte; ver sprint-18 |
