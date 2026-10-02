# Sprint 14 — Gestión de mantenimiento

Estado: completado. Aprobación del Product Owner: 1 de octubre de 2026.

Desarrolla HU-MAN-01 y HU-MAN-02 del Product Backlog: registro, listado,
asignación de responsable, fecha programada y estados pendiente, en proceso y
finalizado. Administrador y gestor gestionan todas las solicitudes del contexto;
el residente registra y consulta las propias. No incluye eliminación, inventario,
costos, proveedores, recurrencia ni notificaciones.

Interfaz Blade con navegación, listado paginado, detalle y formularios. Cada
solicitud conserva título, descripción, solicitante, responsable opcional,
fecha opcional, estado y contexto de Organización/Copropiedad. Al registrar
siempre inicia pendiente; únicamente los gestores pueden modificar asignación,
fecha y estado. El responsable es un Usuario activo con membresía vigente local.

Se reutilizan ContextoOperativo, AutorizacionContextual, políticas, Eloquent y
casos de uso de Aplicación. Los identificadores de otro contexto se ocultan con
404; los permisos se validan en backend. Nuevos permisos: mantenimiento.crear,
mantenimiento.ver_propias, mantenimiento.ver_todas y mantenimiento.gestionar.
No cambia la arquitectura ni agrega dependencias. Los roles admin y gestor
reciben los cuatro permisos; residente recibe crear y ver_propias.

Verificación: flujo completo, vistas, acceso sin permiso, aislamiento entre
Copropiedades, visibilidad propia y rechazo de responsables externos.
Gestión Documental conserva sus rutas, permisos y funcionamiento existentes.

## Verificación final

- Suite completa: 498 pruebas, 493 aprobadas, 5 omitidas, 2190 aserciones.
- Pruebas de mantenimiento y documentos: 35 aprobadas, 138 aserciones.
- Compilación de plantillas Blade correcta; revisión de diferencias sin errores.
- Migración aplicada en MySQL local de Docker. Se verificaron claves foráneas
  y permisos de los roles existentes. La migración permite completar una
  ejecución interrumpida después de crear la tabla, sin eliminar datos.
- No se realizaron commits, cambios de rama ni publicación remota.

## Cierre formal (2 de octubre de 2026)

Validación de cierre ejecutada el 2 de octubre de 2026 sobre el árbol de
trabajo sin commit, con Laravel Sail y la base de datos del contenedor.

Pruebas ejecutadas y resultados:

- `./vendor/bin/sail artisan test --filter='Mantenimiento|Documento'`:
  35 pruebas, 35 aprobadas, 138 aserciones.
- `./vendor/bin/sail artisan test --filter='Mantenimiento|Documento|CrearIdentidadContextualInicial'`:
  42 pruebas, 42 aprobadas, 193 aserciones.
- Suite completa `./vendor/bin/sail artisan test`: 498 pruebas, 493
  aprobadas, 5 omitidas (omitidas preexistentes desde el Sprint 8), 2190
  aserciones.

Criterios verificados: flujo completo, vistas, acceso sin permiso,
aislamiento entre Copropiedades, visibilidad propia de residente, rechazo de
responsables externos, integración con la identidad contextual y
conservación de Gestión Documental. La compilación de plantillas Blade se
comprobó mediante las pruebas que renderizan el listado, el formulario y el
detalle. No se encontraron regresiones ni problemas de seguridad.

### Hallazgos no bloqueantes

1. La migración `2026_10_01_100000_create_mantenimientos_table.php` inserta
   datos de catálogo (`permisos` y `rol_permiso`) dentro de la migración de
   esquema; es la única migración con este comportamiento y obligó a ajustar
   aserciones de `tests/Feature/CrearIdentidadContextualInicialCommandTest.php`
   (el catálogo parte de 4 permisos tras migrar). Comportamiento coherente con
   la sección Activación; se registra sin corregir.
2. `app/Models/Mantenimiento.php` no declara las relaciones `organizacion()`
   y `copropiedad()`, a diferencia de otros modelos contextuales como `Pqr`
   o `Documento`. Las consultas filtran por columnas directas; no hay
   defecto funcional.
3. `app/Models/Mantenimiento.php` incluye `estado`, `responsable_id` y
   `fecha_programada` en `$fillable`. Los flujos actuales validan y
   restringen los datos, pero amplían la superficie de asignación masiva
   ante un uso futuro de `create($request->all())`.

Conclusión: el Sprint 14 queda cerrado como completado. No se realizaron
commits, cambios de rama ni publicación remota durante la validación ni el
cierre.
