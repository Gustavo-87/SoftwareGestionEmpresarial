# Integración académica de Resuelve ERP

Fecha: 5 de septiembre de 2026.
Actualización: 17 de octubre de 2026.

El proyecto se incorpora como carpeta normal de SoftwareGestionEmpresarial,
a partir del commit 5e6996f de resuelve-pqrs-copropiedades, con las adaptaciones
locales del DatabaseSeeder para la organización y copropiedad de demostración.
La guía de Clase 4 está pendiente de revisión; esta integración no acredita
cumplimiento de requisitos aún no contrastados.

## Entorno local

Las dependencias, el archivo .env, los datos locales y los assets compilados
no se incluyen en Git. Cada equipo debe instalar las dependencias para su
propio sistema operativo. No se debe copiar node_modules entre Linux y macOS.
Después de actualizar las fuentes de la interfaz, ejecutar npm run build.
El entorno académico existente utiliza Laravel Sail y http://localhost:8083.
Las credenciales se configuran localmente, incluida RESUELVE_DEMO_PASSWORD.

## Verificación de esta integración

La compilación de Vite terminó correctamente y el manifest generado referencia
assets existentes con los estilos de navegación superior. Esta incorporación
no cambia la lógica funcional ni vuelve a ejecutar la suite completa.

El historial Git del clon original se conserva exclusivamente en este equipo,
en .resuelve-erp-git-backup dentro de la carpeta SoftwareGestionEmpresarial,
excluida del repositorio. No es un submódulo.

## Ajustes de presentación del login académico

El acceso presenta Resuelve ERP con el logo y los textos ampliados. El bloque
de marca se desplaza ligeramente hacia abajo; el título y el párrafo descriptivo
se alinean a la izquierda con el mismo ancho disponible. Los beneficios
permanecen centrados. En pantallas pequeñas se reduce el tamaño de la marca.
Se conservan las rutas y el comportamiento de autenticación.

La marca del login muestra el nombre de la organización asociada a la
configuración institucional debajo de Resuelve ERP, en lugar de la copropiedad.
El nombre se obtiene de la relación existente organizacion y se escapa al
renderizar. Sin organización asociada se muestra Gestión empresarial.

## Documentación del primer corte

| Requisito | Documento |
| --- | --- |
| Datos generales y procesos clave | [Modelo de negocio](01-producto/modelo-de-negocio.md) |
| Entidades y relaciones | [Modelo de dominio](03-tecnica/modelo-de-dominio.md) |
| Diccionario de datos | [Modelo de datos](03-tecnica/modelo-de-datos.md) |
| Diagrama entidad-relación | [MER Resuelve ERP](03-tecnica/mer-resuelve-erp.md) |
| Imagen del diagrama | [Ver MER en PNG](03-tecnica/diagrama_mer.png) |
| Fuente editable del diagrama | [Archivo Mermaid](03-tecnica/erDiagram.mmd) |

El MER representa el diseño integral de Resuelve ERP, incluido mantenimiento.
El modelo de datos describe las tablas implementadas y documenta los
pendientes identificados. El módulo de mantenimiento fue completado en el
Sprint 14 (cierre el 2 de octubre de 2026).

## Módulo de mantenimiento

El módulo de mantenimiento se encuentra completado: fue implementado en el
Sprint 14 y cerrado formalmente el 2 de octubre de 2026 (ver
[Sprint 14 — Gestión de mantenimiento](04-desarrollo-agil/sprint-14-gestion-mantenimiento.md)).
Incluye registro y consulta de solicitudes, asignación de responsable,
programación de fecha y gestión de estados, con permisos `mantenimiento.*` y
aislamiento por Copropiedad activa (ver
[Roles y permisos](02-funcional/roles-y-permisos.md)).

## Revisión de la guía "Roles, Permisos y Rol Activo" (Clase 10)

El 17 de octubre de 2026 se realizó la revisión de cumplimiento de la guía de
Clase 10 sobre roles, permisos y rol activo. Las brechas identificadas fueron
aprobadas por el Product Owner para implementarse en el Sprint 18: rol activo
con segregación de funciones, permisos directos por usuario con origen visible,
matriz global de permisos "ver como usuario", administración de permisos, ciclo
de vida de usuarios por desactivación y endurecimiento de las reglas de roles.
El alcance, los criterios de aceptación y la adaptación de la guía al proyecto
(permisos "modulo.accion", roles base y personalizados, regla "≥1 miembro con
`usuarios.gestionar`" y rutas en español) se documentan en
[Sprint 18 — Seguridad de acceso](04-desarrollo-agil/sprint-18-seguridad-acceso.md)
y en [ADR-013](10-adr/ADR-013-rol-activo-segregacion-funciones.md). Estas
capacidades fueron implementadas y verificadas el 20 de octubre de 2026
(HU-USR-04 a HU-USR-09, Sprint 18): rol activo persistido por
Membresía/Copropiedad y revalidado, permisos directos con anti-escalada y
auditoría, matriz global "ver como usuario" con origen de cada permiso,
catálogo de permisos con eliminación lógica protegida, ciclo de vida de
cuentas por desactivación y reglas de roles endurecidas con fixture de
demostración (`DemoRbacFixtureSeeder`). La verificación registró 88 pruebas
del sprint y una suite completa de 699 pruebas sin fallos; el cierre formal
del Sprint 18 fue aprobado por el Product Owner el 21 de octubre de 2026 (ver
[Sprint 18](04-desarrollo-agil/sprint-18-seguridad-acceso.md)), con la entrega
real de la recuperación de contraseña pendiente de un proveedor de correo.
