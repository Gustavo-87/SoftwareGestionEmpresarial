# Sprint 23 — Eventos de dominio con listeners (HU-NOT-03)

## Estado y control documental

- **Estado:** Completado. HU-NOT-03 implementada de principio a fin y
  verificada localmente el 5 de noviembre de 2026. La ejecución real de
  GitHub Actions queda **pendiente** y fuera de esta verificación.
- **Fecha de cierre técnico:** 5 de noviembre de 2026.
- **Origen:** HU-NOT-03 (Product Backlog, Épica 7 — Notificaciones),
  incorporada y aprobada por el Product Owner el 5 de noviembre de 2026 para el
  requisito de automatización de la guía final.
- **Base:** cierre del Sprint 22 (HU-RYP-03).
- **Número de sprint:** 23 (siguiente disponible tras el Sprint 22).

## 1. Objetivo y alcance

Incorporar el patrón Laravel Event/Listener a la emisión de Notificaciones sin
cambiar sus efectos: dos eventos de dominio con listeners síncronos que
reutilizan `EmitirNotificacionPqrs` (campana `database` + correo vía job).

- `PqrRadicada` → `NotificarPqrRadicada` invoca
  `EmitirNotificacionPqrs::ejecutar($contexto, $pqr, 'pqr_creada')`,
  sustituyendo la invocación directa en `PresentarPqrs`.
- `PqrEstadoActualizado` → `NotificarPqrEstadoActualizado` conserva el string
  `pqr_estado_actualizado`, sustituyendo las invocaciones de
  `AplicarAccionRapidaPqrs` y `ActualizarPqrs`.
- Descubrimiento automático de Laravel; despacho síncrono en el mismo punto
  posterior al commit; sin doble emisión.

## 2. Exclusiones

`pqr_asignada`, recordatorios y respuestas (flujo B-3) conservan su
implementación actual. No se modifican `PqrEventNotification`, el job de
correo `EnviarCorreoNotificacionPqrs`, el scheduler ni el flujo de
respuestas.

## 3. Criterios de aceptación

1. Dos clases de evento y dos listeners (`app/Events`, `app/Listeners`),
   registrados por descubrimiento automático.
2. Sustitución 1:1 de las invocaciones directas, en el mismo punto posterior
   al commit, sin doble emisión.
3. Efectos idénticos: mismos strings funcionales, destinatarios, canales y
   orden; las pruebas de emisión existentes pasan sin modificaciones.
4. Un rollback de la transacción no dispara el evento ni sus efectos.
5. Suite completa sin regresiones y Pint aprobado.
6. Documentación técnica actualizada e índice al día.

## 4. Verificación (5 de noviembre de 2026)

| Verificación | Resultado |
| --- | --- |
| Pruebas de eventos (`tests/Feature/EventosNotificacionTest.php`) | **3 pruebas aprobadas** (9 aserciones): despacho de los dos eventos, emisión real de campana y correo sin `Event::fake` y rollback sin evento ni efectos |
| Pruebas de emisión existentes (`EmisionNotificacionesPqrsTest.php`) | **8 pruebas aprobadas sin modificaciones** (garantía de efectos idénticos) |
| Suite completa (configuración aislada, SQLite en memoria) | **764 pruebas: 755 aprobadas y 9 omitidas (MySQL)**, 3624 aserciones, sin regresiones |
| `vendor/bin/pint --test` | **Aprobado** |

**Ámbito:** verificación **local**. La **ejecución real de GitHub Actions no
está verificada** (el workflow activo se ejecuta con un `pull_request` hacia
`main`); resultado, fecha y revisión siguen pendientes.

## 5. Evidencia

- Eventos y listeners: `app/Events/PqrRadicada.php`,
  `app/Events/PqrEstadoActualizado.php`,
  `app/Listeners/NotificarPqrRadicada.php`,
  `app/Listeners/NotificarPqrEstadoActualizado.php` (descubrimiento automático).
- Sustituciones 1:1 tras el commit: `app/Application/Pqrs/PresentarPqrs.php`,
  `app/Application/Pqrs/AplicarAccionRapidaPqrs.php`,
  `app/Application/Pqrs/ActualizarPqrs.php` (esta conserva la invocación
  directa de `pqr_asignada`, fuera de alcance).
- Emisión reutilizada sin cambios: `app/Application/Notificaciones/EmitirNotificacionPqrs.php`.
- Pruebas: `tests/Feature/EventosNotificacionTest.php`.
- Documentación: [Notificaciones y automatizaciones](../03-tecnica/notificaciones-y-automatizaciones.md).

## Control documental

- **Versión:** v1.0
- **Fecha de creación:** 5 de noviembre de 2026
- **Fecha de última actualización:** 5 de noviembre de 2026
