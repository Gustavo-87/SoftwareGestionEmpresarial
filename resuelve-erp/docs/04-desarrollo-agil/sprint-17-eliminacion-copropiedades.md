# Sprint 17 — Eliminación segura de Copropiedades

Estado: en curso (alcance implementado y verificado; cierre pendiente).
Aprobación del Product Owner: 3 de octubre de 2026.

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
