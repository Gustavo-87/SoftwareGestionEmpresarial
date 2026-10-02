# Gestión de mantenimiento

## Alcance implementado

En la Copropiedad activa, administrador y gestor pueden registrar, consultar
y gestionar todas las solicitudes. Los residentes registran y consultan las
propias. Apoyo y auditor no reciben permisos de mantenimiento por defecto.

Acceso desde **Mantenimiento** en el menú: listado paginado, nueva solicitud y
detalle. El registro requiere título (máximo 180 caracteres) y descripción
(máximo 5000). El solicitante y el contexto proceden del servidor; el estado
inicial es pendiente. En el detalle, administrador y gestor pueden asignar
responsable, programar fecha y seleccionar pendiente, en proceso o finalizado.
Responsable y fecha son opcionales; no se impone una secuencia de transiciones.
El responsable debe ser un Usuario activo con membresía vigente local.

Las vistas muestran ausencia de solicitudes, datos sin asignar o sin programar,
validaciones y confirmaciones de éxito. No incluye eliminación, inventario,
costos, proveedores, mantenimiento recurrente ni notificaciones.

## Seguridad y persistencia

Permisos contextuales: mantenimiento.crear, mantenimiento.ver_propias,
mantenimiento.ver_todas y mantenimiento.gestionar. Los roles admin y gestor
reciben los cuatro; residente recibe los dos primeros. Las solicitudes ajenas
al alcance visible se responden con 404. La interfaz consume consultas y casos
de uso; las autorizaciones y validaciones efectivas están en backend.

Tabla mantenimientos: Organización, Copropiedad, solicitante, responsable
opcional, título, descripción, fecha programada opcional, estado y marcas de
tiempo. Claves foráneas preservan referencias y pertenencia institucional.
AuditMutations conserva la auditoría HTTP existente; no se agrega historial
específico ni se duplican sus reglas.

## Evidencia

- routes/web.php: cinco rutas de mantenimiento.
- app/Models/Mantenimiento.php y app/Policies/MantenimientoPolicy.php.
- app/Application/Mantenimiento/: consulta contextual y casos de uso.
- app/Http/Controllers/MantenimientoController.php.
- resources/views/mantenimiento/ y resources/views/layouts/app.blade.php.
- database/migrations/2026_10_01_100000_create_mantenimientos_table.php.
- tests/Feature/MantenimientoTest.php: registro, gestión, vistas, permisos,
  visibilidad propia, aislamiento y rechazo de responsables externos.

## Activación

Migración aplicada en el MySQL local de Docker el 1 de octubre de 2026. Agrega
los permisos a los roles existentes. El comando de identidad contextual inicial
incluye los permisos para instalaciones nuevas. Gestión Documental conserva su
acceso en el menú y sus permisos existentes; sus pruebas pasan.

## Ajuste de presentación

El detalle distribuye resumen y gestión en dos tarjetas con márgenes internos,
con datos organizados y campos espaciados. En pantallas estrechas las tarjetas
se apilan. El formulario de registro limita su ancho y utiliza los mismos
márgenes y espacios. No se modifican permisos ni operaciones.
