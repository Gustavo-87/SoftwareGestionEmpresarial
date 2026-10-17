# Sprint 17 — Eliminación segura de Copropiedades

Estado: completado. Aprobación del Product Owner: 3 de octubre de 2026
(alcance). Cierre formal: 17 de octubre de 2026.

## Alcance aprobado

Desarrolla HU-ORG-03 (reformulada): eliminar de forma segura una Copropiedad
que todavía no tenga información operativa, para poder crearla nuevamente en la
Organización correcta mediante el CRUD actual (sin flujo especial de recreación).

> **Nota de alcance:** la transferencia de Copropiedades entre Organizaciones
> fue **descartada** por incompatibilidad con el modelo referencial actual
> (columnas generadas STORED, restricciones CHECK y claves compuestas
> `ON UPDATE`/`NO ACTION` que impiden actualizar ámbitos sin
> `FOREIGN_KEY_CHECKS`). Las 18 claves foráneas compuestas no se modifican.

## Comportamiento

- **Eliminación segura:** solo autoridad de plataforma (`es_administrador_sistema`),
  con verificación obligatoria en backend y respuesta 403 ante otras identidades.
- **Comprobación de dependencias reales:** se bloquea la eliminación si existen
  PQRS, etiquetas o sus asignaciones, documentos/versiones/actuaciones,
  mantenimientos, unidades privadas, vínculos de personas, membresías o
  asignaciones de roles. El mensaje identifica cada dependencia y su cantidad.
  No se aplican cascadas destructivas.
- **Copropiedad vacía:** se eliminan únicamente sus relaciones auxiliares
  (`configuraciones_copropiedad`, `site_settings`) y la propia Copropiedad, con
  auditoría `copropiedad.delete`.
- **Interfaz:** acción "Eliminar" secundaria y destructiva en la ficha y en el
  listado de Copropiedades, con confirmación explícita, explicación de la regla
  y mensaje claro del bloqueo cuando aplica.

## Criterios de aceptación

1. Solo autoridad de plataforma; 403 en backend para otras identidades.
2. Bloqueo con mensaje claro por cada tipo de información operativa.
3. Eliminación de Copropiedad vacía con limpieza de auxiliares y auditoría.
4. Confirmación explícita y mensajes de éxito/error en la interfaz.
5. Sin eliminación accidental de datos relacionados.
6. Pruebas específicas y suite completa sin regresiones.

## Registro de ejecución

- **Implementación del alcance (3 de octubre de 2026):** caso de uso
  `EliminarCopropiedad` (transaccional, con bloqueo de fila y verificación de
  11 dependencias operativas), acción `destroy` en la consola administrativa,
  interfaz en ficha y listado, auditoría y 6 pruebas funcionales. Suite
  completa: 546 pruebas, 540 aprobadas, 6 omitidas, 2480 aserciones.
- **Cierre formal (17 de octubre de 2026):** acta de cierre registrada y
  verificación re-ejecutada el mismo día (suite completa y diagnóstico RBAC;
  resultados reales en el acta).

## Acta de cierre (17 de octubre de 2026)

- **Fecha de cierre:** 17 de octubre de 2026.
- **Aprobación del cierre:** Product Owner (17 de octubre de 2026).
- **Historia de Usuario:** HU-ORG-03 (reformulada) completada.
- **Evidencia de la suite (re-ejecutada el 17 de octubre de 2026, fecha de
  cierre):** `./vendor/bin/sail artisan test` completada sin regresiones:
  **588 pruebas aprobadas, 6 omitidas, 2698 aserciones** (18,23 s). El total
  creció frente al 3 de octubre porque la suite incorpora las pruebas nuevas
  del trabajo en curso de reportes, también aprobadas. Las 6 pruebas
  funcionales de `EliminarCopropiedad` siguen aprobadas. Ejecución histórica
  del 3 de octubre de 2026: 546 pruebas, 540 aprobadas, 6 omitidas, 2480
  aserciones.
- **Evidencia del diagnóstico RBAC (re-ejecutado el 17 de octubre de 2026):**
  `resuelve:verificar-equivalencia-autorizacion-contextual` reportó
  divergencias en la **base de datos de desarrollo local**: 10 entre
  `users.role` y el rol contextual vigente (incluidos usuarios sin Membresía
  vigente) y 22 entre el RBAC de Spatie y el espejo legado (roles y
  asignaciones creados manualmente en la BD local que no corresponden al
  espejo). Es deriva de datos locales del entorno de desarrollo —riesgo ya
  documentado en ADR-012 ("deriva del espejo legado")—, no una regresión del
  alcance del sprint: la suite completa pasa y la última verificación limpia
  (0 divergencias) corresponde al 3 de octubre de 2026 (cierre del Sprint 16).
  **Hallazgo no bloqueante:** queda propuesta la resincronización del espejo
  local mediante `resuelve:migrar-rbac-spatie` cuando el Product Owner lo
  autorice.
- **Condiciones 1–6 cumplidas** según la ejecución registrada del 3 de octubre
  de 2026:
  1. Solo autoridad de plataforma, con verificación obligatoria en backend y
     respuesta 403 para otras identidades.
  2. Bloqueo con mensaje claro por cada tipo de información operativa (11
     dependencias verificadas, con identificación y cantidad).
  3. Eliminación de Copropiedad vacía con limpieza de auxiliares
     (`configuraciones_copropiedad`, `site_settings`) y auditoría
     `copropiedad.delete`.
  4. Confirmación explícita y mensajes de éxito/error en la interfaz.
  5. Sin eliminación accidental de datos relacionados (sin cascadas
     destructivas).
  6. Pruebas específicas del alcance (6) y suite completa sin regresiones.
- **Constancia de la decisión negativa:** la transferencia de Copropiedades
  entre Organizaciones queda **descartada** por incompatibilidad referencial
  con el modelo actual (columnas generadas STORED, restricciones CHECK y
  claves compuestas `ON UPDATE`/`NO ACTION`; las 18 claves foráneas compuestas
  no se modifican). No se implementará flujo de transferencia: la creación en
  la Organización correcta se realiza mediante el CRUD actual.
- **Estado final del sprint:** completado.
