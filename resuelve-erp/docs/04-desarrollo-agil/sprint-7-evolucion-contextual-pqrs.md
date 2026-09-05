# Sprint 7 — Evolución contextual del módulo PQRS

## Control documental

- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha de aprobación:** 3 de agosto de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## 1. Objetivo

Consolidar el módulo PQRS sobre el modelo contextual del dominio mediante la
extracción incremental de sus flujos críticos a casos de uso reutilizables e
incorporar Personas, Unidades Privadas y Vínculos como contexto complementario
de lectura, sin regresiones ni cambios en el alcance funcional aprobado.

## 2. Alcance y exclusiones

El sprint comprende:

- presentación y gestión de PQRS dentro de la Copropiedad activa;
- extracción de lógica de aplicación desde los controladores afectados;
- reutilización de consultas y autorización contextuales existentes;
- consulta contextual de la participación comunitaria del radicador;
- autorización uniforme de actualización, asignación, respuestas, comentarios
  internos y etiquetas;
- conservación transaccional del historial funcional.

Se mantienen las rutas, vistas y capacidades actuales. No se reimplementará el
módulo PQRS ni se incorporarán API, IA, Gestión Documental, nuevos reportes,
nuevas notificaciones, estados adicionales, selección visible de Unidad
Privada o cambios visibles multicopropiedad.

## 3. Historias de Usuario

### HU-PQRS-01 — Presentación de una PQRS

Como Residente o Propietario
Quiero presentar una PQRS relacionada con mi Copropiedad
Para comunicar formalmente una petición, queja, reclamo o sugerencia.

### HU-PQRS-02 — Gestión de una PQRS

Como Administrador
Quiero gestionar las PQRS de una Copropiedad y conservar su Historial
Para darles atención con trazabilidad.

## 4. Decisiones arquitectónicas aprobadas

1. La evolución será incremental sobre el monolito Laravel y seguirá el
   ADR-008; no se reconstruirá el módulo.
2. No se agregarán `persona_id` ni `unidad_privada_id` a `pqrs`.
3. `Pqr` conservará `user_id` como radicador.
4. `Persona`, `UnidadPrivada` y `VinculoUnidad` se utilizarán únicamente como
   contexto complementario de lectura. No otorgarán permisos, no sustituirán
   la Membresía y no se inferirán vínculos ausentes.
5. La ausencia de Persona o vínculo no bloqueará una PQRS válida y autorizada.
6. `ContextoOperativo` y `AutorizacionContextual` continuarán siendo
   obligatorios para las operaciones del módulo.
7. `ConsultaPqrsContextuales` seguirá siendo el punto único para construir y
   resolver consultas de PQRS dentro del contexto activo.
8. Se creará una consulta específica de participación comunitaria, sin
   repositorios genéricos ni contratos anticipados para canales inexistentes.
9. Las tablas auxiliares continuarán protegidas mediante su PQRS contextual y
   las restricciones ya implementadas.
10. No se requieren migraciones ni backfill para este sprint.

## 5. Casos de uso a extraer

| Caso de uso | Responsabilidad |
| --- | --- |
| `PresentarPqrs` | Autorizar y coordinar la radicación contextual, regla automática, adjuntos e historial dentro de una transacción. |
| `ActualizarPqrs` | Autorizar la edición y validar Tipo y responsable dentro de la Copropiedad activa. |
| `AplicarAccionRapidaPqrs` | Actualizar parcialmente estado o responsable y registrar una única actuación. |
| `RegistrarRespuestaPqrs` | Unificar borrador y envío con autorización contextual y escritura transaccional. |
| `RegistrarComentarioInternoPqrs` | Registrar el comentario y su actuación con la misma regla contextual de gestión. |
| `SincronizarEtiquetasPqrs` | Sincronizar únicamente etiquetas pertenecientes al contexto de la PQRS. |
| `RegistrarActuacionPqrs` | Centralizar la escritura uniforme y de solo adición del historial funcional. |

Los controladores conservarán exclusivamente validación de transporte,
invocación del caso de uso y transformación de la respuesta web.

## 6. Componentes a crear o modificar

### Nuevos

- Los siete casos de uso enumerados en la sección anterior.
- `ConsultaParticipacionContextual`, para obtener la Persona del Usuario y sus
  Vínculos y Unidades Privadas vigentes dentro de la Copropiedad activa.

La consulta de participación no decidirá autorización, no inferirá vínculos y
no devolverá datos de otras Personas asociadas con una Unidad.

### A modificar

- `PqrController`.
- `PqrQuickActionController`.
- `PqrReplyController`.
- `PqrInternalCommentController`.
- Las acciones de etiquetas de `ComplementaryController`.
- `PqrPolicy` y `AutorizacionContextual`, únicamente cuando sea necesario para
  aplicar de forma uniforme reglas ya aprobadas.
- `ConsultaPqrsContextuales` y los bindings de `{pqr}`, sin introducir una vía
  de resolución global.

No se prevén cambios estructurales en los modelos ni en la base de datos.

## 7. Estrategia de pruebas

1. **Caracterización:** preservar contratos HTTP, validaciones y efectos de
   presentación, actualización, acciones rápidas, respuestas, comentarios y
   etiquetas.
2. **Casos de uso:** comprobar autorización, atomicidad, validación contextual
   de recursos y registro único de actuaciones.
3. **Participación comunitaria:** cubrir Persona con vínculos, ausencia de
   vínculo, vínculos múltiples y recursos pertenecientes a otro contexto.
4. **Aislamiento:** con dos Organizaciones y Copropiedades, verificar que una
   PQRS externa sea indistinguible de una inexistente y que no puedan cruzarse
   responsables, Personas, Unidades, Vínculos, respuestas, comentarios o
   etiquetas.
5. **Regresión:** ejecutar la suite completa, la verificación de equivalencia
   contextual con cero divergencias y la validación relacional sobre MySQL 8.4.

## 8. Implementación por bloques

1. **Caracterización:** completar cobertura de los flujos que se extraerán y
   establecer escenarios con dos contextos.
2. **Consultas:** consolidar `ConsultaPqrsContextuales`, crear
   `ConsultaParticipacionContextual` y contextualizar responsables.
3. **Presentación y actualización:** extraer radicación, edición y acciones
   rápidas sin cambiar los contratos web.
4. **Expediente:** extraer respuestas, comentarios, etiquetas y registro de
   actuaciones con transacciones y autorización uniforme.
5. **Seguridad y cierre:** ejecutar pruebas de aislamiento, revisar consultas
   globales residuales, ejecutar la suite completa y actualizar la
   documentación afectada.

## 9. Definition of Done

El Sprint 7 podrá marcarse como Completado cuando:

- HU-PQRS-01 y HU-PQRS-02 estén implementadas dentro de la Copropiedad activa;
- los flujos críticos definidos utilicen casos de uso reutilizables;
- los controladores afectados se limiten a responsabilidades HTTP;
- `ConsultaPqrsContextuales`, `ContextoOperativo` y
  `AutorizacionContextual` se apliquen en todos los flujos afectados;
- `Pqr` conserve `user_id` y no incorpore `persona_id` ni
  `unidad_privada_id`;
- Personas, Unidades Privadas y Vínculos se usen solo como lectura contextual y
  su ausencia no bloquee una PQRS válida;
- las escrituras compuestas y el historial sean transaccionales;
- respuestas y comentarios internos apliquen autorización contextual uniforme;
- las pruebas de acceso cruzado, la suite completa y la equivalencia de
  autorización finalicen sin fallos ni divergencias;
- no existan migraciones innecesarias, regresiones ni funcionalidades de
  épicas posteriores;
- la documentación oficial afectada quede actualizada.

Este documento registra el diseño aprobado. No acredita todavía su
implementación ni la finalización del Sprint 7.

## 10. Limitaciones técnicas conocidas

- `AutomationRule` no tiene contexto físico de Organización o Copropiedad. Su
  selección global se conserva por compatibilidad durante este Sprint y deberá
  tratarse como una limitación explícita hasta que exista un alcance aprobado
  para evolucionarla.
- Las transacciones revierten las escrituras de base de datos de PQRS. Un
  archivo físico que ya haya sido almacenado no puede revertirse
  automáticamente si falla una operación posterior.
