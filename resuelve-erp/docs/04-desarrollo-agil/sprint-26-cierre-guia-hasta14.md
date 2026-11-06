# Sprint 26 — Auditoría y cierre técnico hasta el requisito 14

## Autorización y alcance

El Product Owner solicitó el 5 de noviembre de 2026 implementar y versionar
autónomamente los pendientes hasta el punto 14 de `Lineamientos_Proyecto_Final.pdf`,
con `gestionpqrs7@gmail.com`, y autorizó las solicitudes necesarias para este
proyecto. Informó que no dispone de contraseña de aplicación de Gmail.
La autorización comprende implementación y versionado mediante rama, PR y CI
antes de integrar a `main`; no amplía el dominio hacia Droguería.

Este bloque desarrolla HU-AUD-02 del Product Backlog y completa evidencias
transversales de los requisitos 3, 8, 12 y 14. Parte de `main` (`5cab345`),
posterior a la actualización documental del PR #4, en
`feat/cierre-guia-1-14`. No implementa los requisitos 16 y 17 ni el informe
final, video o etiqueta de entrega.

## Implementación

1. Activitylog sobre cinco modelos y bitácora web/API de accesos correctos,
   fallidos, bloqueados, inactivos y cierres. Consulta contextual de auditoría
   y consulta global de plataforma, filtros y valores antes/después, con
   privacidad y aislamiento comprobados. Justificación: HU-AUD-02 y punto 10.
2. Middleware de autorización en `PqrController`, `MantenimientoController`
   y `DocumentoController`, reutilizando policies y casos de uso existentes.
3. Pruebas de las cinco PQRS recientes del panel: orden, límite, contexto,
   visibilidad propia y enlaces sujetos a permiso del expediente.
4. Colección Postman v2.1 importable: login, listado, radicación, detalle,
   rechazo sin token y logout, con variables locales y sin credenciales.
5. Configuración preparada del remitente, diagnóstico explícito de correo y
   Mailpit optativo para demostrar SMTP, campana y automatización en una
   base aislada. Gmail real permanece pendiente de una credencial válida.
6. Actualizaciones compatibles de dependencias PHP para corregir avisos de
   seguridad detectados: Laravel 13.40.0, PhpSpreadsheet 5.10.0 y dependencias
   transitivas; `composer audit` queda sin avisos. PHP mínimo 8.3 conservado
   mediante `config.platform.php`; Activitylog 4.12.3 es compatible con él.
   El cierre incluye las correcciones de desarrollo frontend detectadas al
   revisar el CI: Vite 8.3.6, concurrently 9.2.5, shell-quote 1.12.0,
   source-map-js 1.2.2 y PostCSS 8.5.32; `npm audit` reporta cero vulnerabilidades.
7. El CI prepara un `.env` temporal desde el ejemplo, conservando las variables
   aisladas de pruebas y su `APP_KEY` efímera. El registro histórico del PR #3
   con advertencias se conserva y la siguiente ejecución se evalúa por separado.

La migración aditiva `2026_11_05_230000` se aplicó en MySQL local después de
verificar un respaldo SQL de 207.551 bytes. SHA-256 del respaldo:
`653ad84cd101002da36c9b27bcc18b2660ed3fc76ee592ea56bd5bb3902ada26`.
No se reinicializó la base existente ni se ejecutaron seeders sobre ella.

## Matriz de requisitos 1–14

El estado distingue el criterio literal de las adaptaciones residenciales ya
documentadas. Las rutas de evidencia son relativas a `resuelve-erp/`, salvo
cuando se indica la raíz del repositorio. No se declara instalado Breeze.

| # | Estado | Evidencia concreta y alcance |
| --- | --- | --- |
| 1 | Parcial en literalidad; funcionalidad cubierta | `AuthController`, recuperación de contraseña y `User.estado`; `AuthenticationTest`, `AccesoIntentosFallidosTest`, `RecuperacionContrasenaTest`, `UsuarioDesactivacionHuUsr08Test`. Autenticación propia conservada según la adaptación de Clase 4 en `../README.md`; Breeze no está instalado. |
| 2 | Cumple con baja lógica equivalente | Tipos de PQRS, Etiquetas, Unidades y Personas con Form Requests, baja, papelera y restauración: `CicloVidaCatalogosTest`, `Admin/TipoPqrCatalogoTest`, `sprint-24-catalogos-ciclo-vida.md`. Usa los campos de desactivación propios del dominio; no el trait `SoftDeletes`. |
| 3 | Cumple | Cuatro roles base y permisos por acción: `roles`/Spatie; middleware de los tres controladores principales, policies y `@can` en vistas. `RolesSemanticaPermisosTest`, `PqrPermissionsTest`, `DocumentoContextualAuthorizationTest`, `MantenimientoTest`. |
| 4 | Cumple | `GestionarRolActivo`, roles múltiples, permisos directos, matriz y protecciones. `RolActivoHuUsr04Test`, `PermisosDirectosHuUsr05Test`, `MatrizPermisosHuUsr06Test`, `RolesEndurecimientoHuUsr09Test`. |
| 5 | Cumple por adaptación residencial | PQRS y respuestas/adjuntos como cabecera/detalle del servicio; radicado secuencial, transacciones y anulación inmutable de respuesta oficial. `PresentarPqrs`, `GestionarCicloRespuestaPqrs`; `PqrCommunicationPersistenceTest`, `PqrSafeDraftLifecycleTest`, `AnulacionRespuestaTest`. Decisión del PO: Sprint 25. |
| 6 | No aplica por dominio | Resuelve administra copropiedades; no incluye compras/abastecimiento ni recepción comercial. No se inventa un módulo de inventario para acreditar este requisito. |
| 7 | Cumple | Ocho reglas trazadas en la tabla siguiente, implementadas en servicios/casos de uso y probadas. |
| 8 | Cumple | `PqrController::panel`, `resources/views/pqrs/panel.blade.php`: indicadores por permisos/contexto (totales/pendientes/vencidas/próximas y estados de los tres módulos), tabla de cinco PQRS recientes con acceso autorizado; `PanelIndicadoresTest`, `PanelOperativoTest`, `PanelPqrsRecientesTest`. |
| 9 | Cumple | `ReportController` y plantillas PDF con identidad institucional: informes de mantenimiento y documentos, además del expediente integrado. `MantenimientoReportPdfTest`, `DocumentoReportPdfTest`, `ExpedienteIntegradoTest`. |
| 10 | Cumple | `AuditaCambiosDeModelo` en `Copropiedad`, `UnidadPrivada`, `PqrTag`, `Mantenimiento`, `Pqr`; `RegistrarEventoSeguridad`, `ConsultaAuditoria`, `AuditoriaSeguridadTest` y documento técnico de auditoría. |
| 11 | Cumple por historial equivalente | El recurso documental se controla por versiones, transiciones y actuaciones; la comunicación oficial tiene ledger e inmutabilidad. `CargarVersionDocumento`, `AprobarVersionDocumento`, `RegistrarActuacionDocumento`, `GestionarCicloRespuestaPqrs`; `DocumentoVersionWorkflowTest`, `PqrCommunicationPersistenceTest`, `PqrSafeDraftLifecycleTest`. No existe kardex de mercancías. |
| 12 | Cumple | Cinco endpoints `/api/v1` con Sanctum, rol activo y escritura contextual; `Api/ApiAuthTest`, `Api/ApiPqrsTest`; colección `docs/07-api/coleccion-postman.json` con comprobaciones. |
| 13 | Cumple | Exportaciones XLSX en `ReportController`; importación fila a fila de Unidades en `app/Application/Residencia/ImportarUnidadesPrivadas.php`; `UnidadesPrivadasImportacionTest`, `PqrPriorityFeaturesTest` y `ReportIndexTest`. |
| 14 | Cumple técnicamente en entorno local | Dos eventos/listeners (`PqrRadicada`/`NotificarPqrRadicada`, `PqrEstadoActualizado`/`NotificarPqrEstadoActualizado`); correo y campana comprobados para ambos y recordatorio; `routes/console.php` con tareas programadas, `EventosNotificacionTest`, `VerificarCorreoTest`. SMTP real capturado en Mailpit. Recepción en Gmail y procesos activos de producción aún no acreditados. |

### Ocho reglas del requisito 7

| Regla | Servicio/caso de uso | Prueba |
| --- | --- | --- |
| Calcular vencimiento con días hábiles colombianos y plazo de la Copropiedad | `PresentarPqrs`, `CalendarioLaboralColombia`, `ResolverDiasRespuestaContextual` | `CalendarioLaboralColombiaTest`, `PqrDeadlineProtectionTest` |
| Impedir acceso/operación fuera de Organización y Copropiedad | `ConsultaPqrsContextuales`, `AutorizacionContextual` | `PqrContextIsolationTest`, `PqrsApplicationFlowsTest` |
| Rechazar etiquetas de otra Copropiedad | `SincronizarEtiquetasPqrs` | `PqrTagContextTest`, `PqrTagAssignmentExperienceTest` |
| Impedir edición de una respuesta oficial ya comunicada | `GestionarCicloRespuestaPqrs` | `PqrSafeDraftLifecycleTest` |
| No duplicar comunicaciones al repetir la clave de operación | `GestionarCicloRespuestaPqrs`, `PqrCommunicationOperationRepository` | `PqrCommunicationPersistenceTest` |
| Exigir motivo y preservar contenido al anular una respuesta oficial | `GestionarCicloRespuestaPqrs::annul` | `AnulacionRespuestaTest` |
| Finalizar mantenimiento únicamente con resultado y evidencia válida | `FinalizarMantenimiento`, `VincularEvidenciaMantenimiento` | `EvidenciaMantenimientoTest` |
| Conservar versiones documentales numeradas y controlar aprobación | `CargarVersionDocumento`, `SometerVersionDocumento`, `AprobarVersionDocumento` | `DocumentoVersionWorkflowTest`, `DocumentoManagementFlowsTest` |

## Verificación

- Suite local completa: **802 pruebas; 793 aprobadas, 9 omitidas por MySQL;
  3.860 aserciones**, sin fallos ni advertencias de configuración.
- `vendor/bin/pint --test`, `composer validate`, `composer audit` y compilación
  Vite satisfactorios; sin avisos de vulnerabilidades conocidas en Composer.
- Interfaz de auditoría comprobada en escritorio y móvil, temas claro/oscuro,
  con filtros, estados vacíos y ámbitos operativo/plataforma.
- Flujo SMTP local: **3 correos, 3 campanas y recordatorio repetido 0**.
- Migración aplicada en MySQL local; nueve pruebas de concurrencia específicas
  de MySQL siguen omitidas en la ejecución SQLite y no se declaran aprobadas.
- Publicación y CI se registran en la descripción del PR y en
  [Integración continua](../08-despliegue/integracion-continua.md).

## Límites y continuación

Este cierre no marca completada la HU-AUD-01 refinada del plan del Sprint 20:
sus filtros separados por acción y recurso siguen pendientes. HU-AUD-02 y
el mínimo técnico del requisito 10 sí quedan cubiertos por este bloque.

La autenticación equivalente existente no satisface literalmente la mención
de Breeze. La baja lógica de maestros usa la solución documentada del dominio.
La recepción externa en Gmail necesita una credencial válida; no puede
reemplazarse por una declaración de entrega ni por el transporte `log`.
Los datos de prueba por rol (16) y la retroalimentación original del avance 2
(17) quedan para el siguiente alcance, al igual que los entregables finales.

## Control documental

- **Versión:** v1.1
- **Fecha:** 6 de noviembre de 2026 (America/Bogota)
