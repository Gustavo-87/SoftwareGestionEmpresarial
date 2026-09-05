# Modelo de datos futuro

## Control documental

- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha:** 31 de julio de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## 1. Propósito y alcance

Este documento define el esquema relacional objetivo del núcleo inicial de
Resuelve para su evolución multi-copropiedad. Describe tablas, claves,
nulabilidad, restricciones, índices y estrategia de migración sin crear
modelos Eloquent ni migraciones.

El alcance comprende Organización, Copropiedad, Usuario, Membresías separadas
por ámbito, Roles, Permisos, Unidades Privadas, Personas, Vínculos con Unidad,
PQRS contextualizadas y configuración. No incluye Suscripciones, capacidades
comerciales, Gestión Documental, Inteligencia Artificial, API ni aplicaciones
móviles.

El diseño desarrolla el
[modelo del dominio futuro](modelo-del-dominio-futuro.md), la
[arquitectura objetivo](../05-arquitectura/arquitectura-objetivo.md) y los ADR
003 a 006. El [modelo de datos actual](modelo-de-datos.md) continúa describiendo
el esquema implementado.

## 2. Principios relacionales

1. La base de datos y el esquema son compartidos.
2. `Organización` es el tenant comercial y `Copropiedad` el ámbito operativo.
3. Organización y Copropiedad son agregados independientes aunque exista una
   relación entre sus tablas.
4. Toda tabla operativa de Copropiedad incluye `organizacion_id` y
   `copropiedad_id`.
5. Las claves redundantes permiten claves foráneas compuestas que impiden
   referencias cruzadas.
6. Las Membresías de Organización y Copropiedad se almacenan físicamente por
   separado.
7. Los Roles se asignan mediante relaciones distintas para cada ámbito.
8. `users` conserva su nombre físico heredado.
9. `users.role`, `users.tower` y `users.unit` se conservan temporalmente solo
   para compatibilidad, backfill y rollback.
10. La desactivación o cierre de vigencia sustituye la eliminación cuando
    existe historia.
11. Los cambios iniciales son aditivos; las restricciones fuertes se incorporan
    después del backfill.

## 3. Inventario definitivo de tablas principales

| N.º | Tabla | Estado objetivo | Ámbito |
| ---: | --- | --- | --- |
| 1 | `organizaciones` | Nueva | Plataforma/Organización |
| 2 | `copropiedades` | Nueva | Organización/Copropiedad |
| 3 | `users` | Adaptada, nombre heredado | Plataforma |
| 4 | `membresias_organizacion` | Nueva | Organización |
| 5 | `membresias_copropiedad` | Nueva | Copropiedad |
| 6 | `roles` | Nueva | Plataforma |
| 7 | `permisos` | Nueva | Plataforma |
| 8 | `rol_permiso` | Nueva | Plataforma |
| 9 | `membresia_organizacion_rol` | Nueva | Organización |
| 10 | `membresia_copropiedad_rol` | Nueva | Copropiedad |
| 11 | `personas` | Nueva | Organización |
| 12 | `unidades_privadas` | Nueva | Copropiedad |
| 13 | `vinculos_unidad` | Nueva | Copropiedad |
| 14 | `tipo_pqrs` | Adaptada | Copropiedad |
| 15 | `pqrs` | Adaptada | Copropiedad |
| 16 | `configuraciones_organizacion` | Nueva | Organización |
| 17 | `configuraciones_copropiedad` | Nueva | Copropiedad |

Las asociaciones Rol-Permiso y Membresía-Rol son materializaciones
relacionales; no añaden entidades al modelo conceptual del dominio.

## 4. Tablas de Organización y Copropiedad

### 4.1. `organizaciones`

- **Propósito:** representar al tenant comercial.
- **Clave primaria:** `id`.
- **Columnas:**
  - `id`: no nulo.
  - `nombre`: no nulo.
  - `identificacion_tributaria`: nullable.
  - `email`, `telefono`: nullable.
  - `estado`: no nulo.
  - `desactivada_at`: nullable.
  - `created_at`, `updated_at`: no nulos.
- **Claves foráneas:** ninguna.
- **Restricciones únicas:** identificación tributaria cuando esté informada,
  sujeta a conciliación; `id` ya es único.
- **Índices:** `estado`; `nombre`.
- **Desactivación/eliminación:** desactivación lógica; eliminación restringida
  si existen Copropiedades, Membresías o historia.
- **Ámbito:** plataforma; cada fila abre un ámbito Organización.

La obligatoriedad e interpretación de la identificación tributaria es
**Pendiente de decisión del Product Owner**.

### 4.2. `copropiedades`

- **Propósito:** representar un ámbito operativo independiente.
- **Clave primaria:** `id`.
- **Columnas:**
  - `id`, `organizacion_id`, `nombre`, `estado`: no nulos.
  - `nit`, `representante_legal`, `direccion`, `ciudad`, `telefono`, `email`:
    nullable.
  - `desactivada_at`: nullable.
  - marcas de tiempo: no nulas.
- **Claves foráneas:** `organizacion_id → organizaciones.id`, con eliminación
  restringida.
- **Restricciones únicas:** `(id, organizacion_id)` como clave candidata para
  referencias compuestas; NIT dentro de Organización cuando esté informado.
- **Índices:** `(organizacion_id, estado)`; `(organizacion_id, nombre)`.
- **Desactivación/eliminación:** desactivar; no eliminar con datos operativos.
- **Ámbito:** Organización y Copropiedad.

La transferencia entre Organizaciones es **Pendiente de decisión del Product
Owner**.

## 5. Identidad, Membresías, Roles y Permisos

### 5.1. `users`

- **Propósito:** conservar la identidad autenticable global.
- **Clave primaria:** `id` existente.
- **Columnas conservadas:** `name`, `email`, `email_verified_at`, `password`,
  `remember_token`, marcas de tiempo.
- **Columnas nuevas:** `estado` no nulo; `desactivado_at` nullable.
- **Columnas heredadas temporales:** `role`, `tower`, `unit`.
- **Claves foráneas:** ninguna hacia un tenant.
- **Restricciones únicas:** `email` global y normalizado.
- **Índices:** correo único; `estado`.
- **Desactivación/eliminación:** desactivar; restringir eliminación si existe
  historia o Membresías.
- **Ámbito:** plataforma.

`role`, `tower` y `unit` dejan de ser fuentes futuras de autorización o
residencia, pero no se eliminan durante esta evolución.

### 5.2. `membresias_organizacion`

- **Propósito:** representar la pertenencia autorizada de un Usuario a una
  Organización.
- **Clave primaria:** `id`.
- **Columnas:**
  - `id`, `usuario_id`, `organizacion_id`, `estado`, `vigente_desde`: no nulos.
  - `vigente_hasta`, `creada_por`, `motivo_terminacion`: nullable.
  - marcas de tiempo: no nulas.
- **Claves foráneas:**
  - `usuario_id → users.id`, eliminación restringida.
  - `organizacion_id → organizaciones.id`, eliminación restringida.
  - `creada_por → users.id`, nullable y `nullOnDelete`.
- **Restricciones únicas:** `(usuario_id, organizacion_id)`.
- **Claves candidatas adicionales:** `(id, organizacion_id)` para asignaciones
  compuestas.
- **Índices:** `(organizacion_id, estado)`; `(usuario_id, estado)`;
  `(vigente_hasta, estado)`.
- **Desactivación/eliminación:** finalizar vigencia o desactivar; no eliminar si
  tiene asignaciones o historia.
- **Ámbito:** Organización.

### 5.3. `membresias_copropiedad`

- **Propósito:** representar la pertenencia autorizada de un Usuario a una
  Copropiedad concreta.
- **Clave primaria:** `id`.
- **Columnas:**
  - `id`, `usuario_id`, `organizacion_id`, `copropiedad_id`, `estado`,
    `vigente_desde`: no nulos.
  - `vigente_hasta`, `creada_por`, `motivo_terminacion`: nullable.
  - marcas de tiempo: no nulas.
- **Claves foráneas:**
  - `usuario_id → users.id`, eliminación restringida.
  - `(copropiedad_id, organizacion_id) → copropiedades(id, organizacion_id)`,
    eliminación restringida.
  - `creada_por → users.id`, nullable y `nullOnDelete`.
- **Restricciones únicas:** `(usuario_id, copropiedad_id)`.
- **Claves candidatas adicionales:** `(id, organizacion_id, copropiedad_id)`.
- **Índices:** `(organizacion_id, copropiedad_id, estado)`;
  `(usuario_id, estado)`; `(vigente_hasta, estado)`.
- **Desactivación/eliminación:** finalizar vigencia o desactivar; no eliminar con
  asignaciones o historia.
- **Ámbito:** Copropiedad.

La FK compuesta impide crear una Membresía con una Organización distinta de la
propietaria de la Copropiedad.

La participación de un Usuario en varias Organizaciones es **Pendiente de
decisión del Product Owner**. La estructura física no convierte esa posibilidad
en una regla funcional aprobada.

### 5.4. `roles`

- **Propósito:** agrupar Permisos aplicables a un ámbito.
- **Clave primaria:** `id`.
- **Columnas:** `id`, `clave`, `nombre`, `descripcion` nullable,
  `ambito_aplicable`, `estado` y marcas de tiempo; todas salvo descripción no
  nulas.
- **Claves foráneas:** ninguna.
- **Restricciones únicas:** `clave`; `(id, ambito_aplicable)` como clave
  candidata.
- **Índices:** `(ambito_aplicable, estado)`.
- **Desactivación/eliminación:** desactivar; restringir eliminación con
  asignaciones.
- **Ámbito:** plataforma; `ambito_aplicable` distingue Organización y
  Copropiedad.

Los Roles actuales se cargan como perfiles de compatibilidad. Roles
personalizados y catálogo definitivo son **Pendiente de decisión del Product
Owner**.

### 5.5. `permisos`

- **Propósito:** representar acciones autorizables estables.
- **Clave primaria:** `id`.
- **Columnas:** `id`, `clave`, `modulo`, `accion`, `descripcion` nullable,
  `ambito_aplicable`, `estado` y marcas de tiempo.
- **Claves foráneas:** ninguna.
- **Restricciones únicas:** `clave`; `(id, ambito_aplicable)` como clave
  candidata para relaciones compuestas.
- **Índices:** `(modulo, estado)`; `(ambito_aplicable, estado)`.
- **Desactivación/eliminación:** desactivar; restringir eliminación si pertenece
  a Roles.
- **Ámbito:** plataforma.

El catálogo definitivo es **Pendiente de decisión del Product Owner**.

### 5.6. `rol_permiso`

- **Propósito:** materializar la relación `N:M` entre Roles y Permisos.
- **Clave primaria:** `(rol_id, permiso_id)`.
- **Columnas:** `rol_id`, `permiso_id`, `ambito_aplicable`, no nulas; marcas de
  tiempo opcionales.
- **Claves foráneas:** `(rol_id, ambito_aplicable) → roles(id,
  ambito_aplicable)` y `(permiso_id, ambito_aplicable) → permisos(id,
  ambito_aplicable)`, con eliminación restringida.
- **Restricciones:** Rol y Permiso deben compartir el mismo ámbito aplicable.
- **Restricciones únicas:** clave primaria compuesta.
- **Índices:** índice inverso por `permiso_id`.
- **Desactivación/eliminación:** retirar la relación; auditar el cambio.
- **Ámbito:** plataforma.

### 5.7. `membresia_organizacion_rol`

- **Propósito:** asignar un Rol de Organización a una Membresía de
  Organización.
- **Clave primaria:** `id`.
- **Columnas:**
  - `id`, `membresia_organizacion_id`, `rol_id`, `organizacion_id`,
    `ambito_rol`, `estado`, `vigente_desde`: no nulos.
  - `vigente_hasta`, `asignado_por`: nullable.
  - marcas de tiempo.
- **Claves foráneas:**
  - `(membresia_organizacion_id, organizacion_id) →
    membresias_organizacion(id, organizacion_id)`.
  - `(rol_id, ambito_rol) → roles(id, ambito_aplicable)`.
  - `asignado_por → users.id`, nullable.
- **Restricciones:** `ambito_rol` tiene valor fijo `organizacion`; la FK
  compuesta impide referenciar un Rol de otro ámbito.
- **Restricciones únicas:** una asignación activa equivalente por Membresía y
  Rol; la vigencia histórica se conserva.
- **Índices:** `(membresia_organizacion_id, estado)`;
  `(organizacion_id, rol_id, estado)`.
- **Desactivación/eliminación:** terminar vigencia; no eliminar historia.
- **Ámbito:** Organización.

### 5.8. `membresia_copropiedad_rol`

- **Propósito:** asignar un Rol de Copropiedad a una Membresía de
  Copropiedad.
- **Clave primaria:** `id`.
- **Columnas:**
  - `id`, `membresia_copropiedad_id`, `rol_id`, `organizacion_id`,
    `copropiedad_id`, `ambito_rol`, `estado`, `vigente_desde`: no nulos.
  - `vigente_hasta`, `asignado_por`: nullable.
  - marcas de tiempo.
- **Claves foráneas:**
  - `(membresia_copropiedad_id, organizacion_id, copropiedad_id) →
    membresias_copropiedad(id, organizacion_id, copropiedad_id)`.
  - `(rol_id, ambito_rol) → roles(id, ambito_aplicable)`.
  - `asignado_por → users.id`, nullable.
- **Restricciones:** `ambito_rol` tiene valor fijo `copropiedad`; la FK
  compuesta impide referenciar un Rol de otro ámbito.
- **Restricciones únicas:** una asignación activa equivalente por Membresía y
  Rol, conservando vigencias históricas.
- **Índices:** `(membresia_copropiedad_id, estado)`;
  `(organizacion_id, copropiedad_id, rol_id, estado)`.
- **Desactivación/eliminación:** terminar vigencia; no eliminar historia.
- **Ámbito:** Copropiedad.

La inclusión de Organización y Copropiedad en esta tabla impide que una
asignación se desplace a otro contexto mediante un identificador aislado.

## 6. Personas, Unidades Privadas y Vínculos

### 6.1. `personas`

- **Propósito:** representar sujetos naturales o jurídicos del dominio.
- **Clave primaria:** `id`.
- **Columnas:** `id`, `organizacion_id`, `tipo_persona`, nombre o razón social,
  `estado` y marcas de tiempo no nulos; `usuario_id`, identificación, correo,
  teléfono y `desactivada_at` nullable.
- **Claves foráneas:** `organizacion_id → organizaciones.id`; `usuario_id →
  users.id`, nullable.
- **Restricciones únicas:** `(id, organizacion_id)`; identificación dentro de
  Organización cuando esté informada.
- **Índices:** `(organizacion_id, estado)`; `usuario_id`; identificación.
- **Desactivación/eliminación:** desactivar; no eliminar con Vínculos o historia.
- **Ámbito:** Organización.

La cardinalidad definitiva Persona-Usuario es **Pendiente de decisión del
Product Owner**.

### 6.2. `unidades_privadas`

- **Propósito:** representar bienes privados de una Copropiedad.
- **Clave primaria:** `id`.
- **Columnas:** `id`, `organizacion_id`, `copropiedad_id`, `codigo`,
  `numero_nombre`, `estado` y marcas de tiempo no nulos; `torre_bloque`, `tipo`
  y `desactivada_at` nullable.
- **Claves foráneas:** `(copropiedad_id, organizacion_id) →
  copropiedades(id, organizacion_id)`.
- **Restricciones únicas:** `(id, organizacion_id, copropiedad_id)`;
  `(copropiedad_id, codigo)`.
- **Índices:** `(organizacion_id, copropiedad_id, estado)`;
  `(copropiedad_id, torre_bloque, numero_nombre)` con nombre físico
  `idx_unidades_ubicacion`.
- **Desactivación/eliminación:** desactivar; no eliminar con Vínculos o historia.
- **Ámbito:** Copropiedad.

### 6.3. `vinculos_unidad`

- **Propósito:** relacionar temporalmente una Persona con una Unidad Privada.
- **Clave primaria:** `id`.
- **Columnas:** `id`, `organizacion_id`, `copropiedad_id`, `persona_id`,
  `unidad_privada_id`, `tipo_vinculo`, `estado`, `vigente_desde` y marcas de
  tiempo no nulos; `vigente_hasta`, `fuente`, observación nullable.
- **Claves foráneas:**
  - `(persona_id, organizacion_id) → personas(id, organizacion_id)`.
  - `(unidad_privada_id, organizacion_id, copropiedad_id) →
    unidades_privadas(id, organizacion_id, copropiedad_id)`.
- **Restricciones únicas:** Persona, Unidad, tipo e inicio.
- **Índices:** `(organizacion_id, copropiedad_id, unidad_privada_id, estado)`;
  `(organizacion_id, persona_id, estado)`.
- **Desactivación/eliminación:** cerrar vigencia; no eliminar historia.
- **Ámbito:** Copropiedad.

Las reglas definitivas de Propietarios y Residentes son **Pendiente de decisión
del Product Owner**.

### 6.4. Validación de implementación del Sprint 6

La implementación de las tres tablas se validó en MySQL 8.4 mediante una
migración completa, rollback de las tres migraciones del Sprint 6 y una segunda
migración satisfactoria. Las FKs compuestas rechazaron una asociación cruzada
con `ERROR 1452` y la prueba transaccional de cierre de vigencia conservó el
registro histórico con `vigente_hasta`.

El backfill creó Personas y Unidades Privadas desde `users.tower` y
`users.unit`. No crea Vínculos cuando el legado no permite determinar
`tipo_vinculo`: los omite y registra la inconsistencia para conciliación
posterior, sin inferir `propietario`, `residente` ni `tenedor`.

## 7. PQRS contextualizadas

### 7.1. `tipo_pqrs`

- **Propósito:** clasificar PQRS dentro de una Copropiedad.
- **Clave primaria:** `id` existente.
- **Columnas conservadas:** `nombre`, `descripcion`, marcas de tiempo.
- **Columnas nuevas:** `organizacion_id`, `copropiedad_id`, `estado`; inicialmente
  nullable durante backfill y luego no nulas.
- **Claves foráneas:** `(copropiedad_id, organizacion_id) → copropiedades`.
- **Restricciones únicas:** `(id, organizacion_id, copropiedad_id)`;
  `(copropiedad_id, nombre)`.
- **Índices:** `(organizacion_id, copropiedad_id, estado)`.
- **Desactivación/eliminación:** desactivar; sustituir la cascada que hoy puede
  eliminar PQRS por una restricción.
- **Ámbito:** Copropiedad.

### 7.2. `pqrs`

- **Propósito:** representar el expediente operativo contextualizado.
- **Clave primaria:** `id` existente.
- **Columnas nuevas:** `organizacion_id`, `copropiedad_id`, inicialmente
  nullable y finalmente no nulas.
- **Columnas conservadas:** asunto, descripción, fechas, estado, `user_id`,
  `assigned_to_id`, `tipo_pqr_id`, `last_reminder_at` y marcas de tiempo.
- **Claves foráneas:**
  - `(copropiedad_id, organizacion_id) → copropiedades`.
  - `(tipo_pqr_id, organizacion_id, copropiedad_id) → tipo_pqrs`.
  - `user_id`, `assigned_to_id → users.id`.
- **Restricciones únicas:** `(id, organizacion_id, copropiedad_id)`.
- **Índices:**
  - `(organizacion_id, copropiedad_id, estado)`.
  - `(organizacion_id, copropiedad_id, assigned_to_id, estado)`.
  - `(organizacion_id, copropiedad_id, tipo_pqr_id)`.
  - `(organizacion_id, copropiedad_id, fecha_limite_respuesta, estado)`.
  - `(organizacion_id, copropiedad_id, fecha_radicacion)`.
- **Desactivación/eliminación:** preservar expediente; anulación o archivo
  requieren decisión funcional.
- **Ámbito:** Copropiedad.

Las referencias a Usuarios requieren además validación de Membresía vigente en
los casos de uso. Una FK global a `users` no demuestra autorización contextual.

## 8. Configuración

### 8.1. `configuraciones_organizacion`

- **Propósito:** conservar parámetros explícitos del tenant comercial que no
  forman parte de su identidad.
- **Clave primaria:** `id`.
- **Columnas:** `id`, `organizacion_id` y marcas de tiempo no nulos; parámetros
  aprobados con nulabilidad definida individualmente.
- **Claves foráneas:** `organizacion_id → organizaciones.id`.
- **Restricciones únicas:** `organizacion_id`, una configuración por
  Organización.
- **Índices:** índice único de Organización.
- **Desactivación/eliminación:** conservar con Organización.
- **Ámbito:** Organización.

No se usará una bolsa JSON de parámetros sin gobernanza. El alcance final de la
configuración es **Pendiente de decisión del Product Owner**.

### 8.2. `configuraciones_copropiedad`

- **Propósito:** reemplazar la configuración global por parámetros operativos
  de Copropiedad.
- **Clave primaria:** `id`.
- **Columnas:** `id`, `organizacion_id`, `copropiedad_id`, `color_principal`,
  `dias_respuesta` y marcas de tiempo no nulos; `logo_path` nullable.
- **Claves foráneas:** `(copropiedad_id, organizacion_id) → copropiedades`.
- **Restricciones únicas:** `copropiedad_id`; `(copropiedad_id,
  organizacion_id)` como clave candidata.
- **Índices:** `(organizacion_id, copropiedad_id)`.
- **Desactivación/eliminación:** conservar con Copropiedad.
- **Ámbito:** Copropiedad.

## 9. Contextualización de tablas auxiliares de PQRS

Antes de habilitar una segunda Copropiedad, toda tabla operativa deberá incluir
`organizacion_id` y `copropiedad_id`. Las columnas serán inicialmente nullable,
se poblarán desde la PQRS o Copropiedad propietaria y pasarán a no nulas.

| Tabla actual | Adaptación y restricción principal |
| --- | --- |
| `pqr_attachments` | Añadir contexto y FK `(pqr_id, organizacion_id, copropiedad_id) → pqrs`. |
| `pqr_activities` | Añadir contexto y FK compuesta hacia PQRS; conservar actor global. |
| `pqr_replies` | Añadir contexto y FK compuesta hacia PQRS; conservar autor global. |
| `pqr_internal_comments` | Añadir contexto y FK compuesta hacia PQRS. |
| `satisfaction_surveys` | Añadir contexto y FK compuesta hacia PQRS; validar Usuario mediante Membresía. |
| `pqr_tags` | Añadir contexto; unicidad `(copropiedad_id, name)`. |
| `pqr_pqr_tag` | Añadir contexto y FKs compuestas hacia PQRS y Etiqueta para impedir cruces. |
| `response_templates` | Añadir contexto y unicidad de nombre según Copropiedad. |
| `automation_rules` | Añadir contexto; FKs compuestas hacia Tipo y, cuando sea aplicable, contexto del responsable. |
| `audit_logs` | Añadir `organizacion_id` y `copropiedad_id` nullable para admitir hechos de plataforma u Organización; exigir ambos en hechos de Copropiedad. |

Las notificaciones Laravel conservan su estructura de infraestructura. Las
notificaciones de negocio deben incluir contexto en sus datos y revalidarlo al
abrirse. Si se requieren consultas operativas directas por tenant, se evaluará
añadir columnas contextuales en una evolución específica.

Las tablas de sesión, caché y jobs no son datos de dominio, pero sus claves y
payloads deben incluir y revalidar contexto cuando procesen operaciones de
Copropiedad.

## 10. Relaciones y cardinalidades

```text
Organización 1 ─── N Copropiedad
Organización 1 ─── N MembresíaOrganización
Organización 1 ─── 1 ConfiguraciónOrganización

Copropiedad N ─── 1 Organización
Copropiedad 1 ─── N MembresíaCopropiedad
Copropiedad 1 ─── N UnidadPrivada
Copropiedad 1 ─── N TipoPQRS
Copropiedad 1 ─── N PQRS
Copropiedad 1 ─── 1 ConfiguraciónCopropiedad

Usuario 1 ─── N MembresíaOrganización
Usuario 1 ─── N MembresíaCopropiedad
MembresíaOrganización N ─── M RolOrganización
MembresíaCopropiedad N ─── M RolCopropiedad
Rol N ─── M Permiso

Organización 1 ─── N Persona
Persona N ─── M UnidadPrivada mediante VínculoUnidad

TipoPQRS 1 ─── N PQRS
Usuario 1 ─── N PQRS como radicador
Usuario 1 ─── N PQRS como responsable opcional
```

No existe FK entre Membresía de Organización y Copropiedad ni entre sus tablas
de asignación de Roles. La separación física impide intercambiar sus ámbitos.

## 11. Controles contra mezcla de tenants

### 11.1. Restricciones de base de datos

- `copropiedades` expone `(id, organizacion_id)` como clave candidata.
- Toda tabla de Copropiedad referencia esa clave mediante
  `(copropiedad_id, organizacion_id)`.
- `membresias_copropiedad` incluye Organización y Copropiedad no nulas.
- `unidades_privadas`, `tipo_pqrs` y `pqrs` exponen claves candidatas con ambos
  identificadores.
- `vinculos_unidad` referencia Persona por Organización y Unidad por
  Organización y Copropiedad.
- Las tablas auxiliares de PQRS referencian a su raíz con contexto compuesto.
- Las asignaciones de Rol se separan físicamente y solo admiten Roles del ámbito
  correspondiente.
- No se permiten cascadas que trasladen o eliminen historia entre tenants.

### 11.2. Controles de aplicación requeridos

- Resolver el contexto antes de cargar recursos.
- Verificar Membresía vigente en la tabla correspondiente.
- Validar Rol, Permiso y pertenencia del recurso.
- Restringir route model binding por Organización y Copropiedad.
- Revalidar contexto en jobs, notificaciones y comandos.
- No depender únicamente de global scopes.

La base de datos puede impedir referencias estructurales cruzadas, pero la
autorización de un Usuario global sigue siendo responsabilidad de los casos de
uso.

## 12. Transformación de datos heredados

### 12.1. `users.role`

1. Crear Roles de compatibilidad para `admin`, `gestor`, `apoyo`, `auditor` y
   `residente`, todos inicialmente aplicables a Copropiedad.
2. Crear una Membresía de Copropiedad para cada Usuario en la Copropiedad
   heredada.
3. Crear la asignación equivalente en `membresia_copropiedad_rol`.
4. No crear Membresías de Organización automáticamente; requieren una regla
   funcional explícita.
5. Mantener `users.role` durante doble lectura y comparación.
6. Cambiar autorización a las tablas nuevas.
7. Retirar el campo solo en una fase posterior aprobada.

La matriz actual se reproduce para compatibilidad; sus inconsistencias no se
convierten en permisos definitivos.

### 12.2. `users.tower` y `users.unit`

1. Normalizar espacios, mayúsculas y valores vacíos sin borrar el original.
2. Agrupar combinaciones dentro de la Copropiedad heredada.
3. Crear una Unidad Privada por combinación conciliada.
4. Crear Persona para Usuarios residentes con datos suficientes.
5. Asociar Persona y Usuario cuando la correspondencia sea inequívoca.
6. Crear Vínculo con Unidad de compatibilidad como Residente.
7. Enviar casos ambiguos a conciliación manual.
8. Mantener `tower` y `unit` para trazabilidad y rollback.

No se infiere la condición de Propietario.

### 12.3. `site_settings`

1. Identificar la fila usada por `SiteSetting::current()`.
2. Crear la Organización heredada.
3. Crear la Copropiedad heredada con nombre, NIT, representante, dirección,
   ciudad, teléfono y correo.
4. Crear `configuraciones_copropiedad` con color, logo y días de respuesta.
5. No migrar datos a Configuración de Organización sin una regla de alcance
   aprobada.
6. Clasificar manualmente filas adicionales.
7. Mantener `site_settings` durante doble lectura y rollback.
8. Retirar su consulta global antes de habilitar otra Copropiedad.

### 12.4. PQRS y auxiliares

1. Añadir contexto nullable a Tipos, PQRS y tablas auxiliares.
2. Asignar Organización y Copropiedad heredadas a todos los Tipos.
3. Asignar el mismo contexto a todas las PQRS.
4. Propagar el contexto desde cada PQRS a adjuntos, actividades, respuestas,
   comentarios, encuestas y pivotes.
5. Contextualizar etiquetas, plantillas y reglas automáticas en la Copropiedad
   heredada.
6. Verificar coincidencia entre PQRS, Tipo, Etiquetas y reglas.
7. Crear índices y FKs compuestas.
8. convertir columnas de contexto en no nulas;
9. Mantener identificadores y relaciones actuales.

## 13. Campos y estructuras heredadas temporales

| Origen | Elemento conservado | Motivo |
| --- | --- | --- |
| `users` | `role` | Compatibilidad, comparación y rollback de autorización. |
| `users` | `tower`, `unit` | Trazabilidad de Unidades, Personas y Vínculos. |
| `pqrs` | `user_id`, `assigned_to_id` | Compatibilidad con código y relaciones actuales. |
| `site_settings` | tabla completa | Doble lectura y rollback de configuración. |
| `pqr_replies` | `attachments` JSON | Compatibilidad hasta una evolución posterior de Adjuntos. |

No se fija su eliminación en este documento. Cada retiro exige cobertura
completa, periodo de estabilización y plan propio.

## 14. Orden recomendado de migraciones

1. Crear `organizaciones`.
2. Crear `copropiedades` y su clave candidata compuesta.
3. Crear `configuraciones_organizacion` y
   `configuraciones_copropiedad`.
4. Adaptar `users` con estado y desactivación.
5. Crear `roles` y `permisos`.
6. Crear `rol_permiso`.
7. Crear `membresias_organizacion`.
8. Crear `membresias_copropiedad`.
9. Crear `membresia_organizacion_rol`.
10. Crear `membresia_copropiedad_rol`.
11. Crear `personas`.
12. Crear `unidades_privadas`.
13. Crear `vinculos_unidad`.
14. Añadir contexto nullable a `tipo_pqrs` y `pqrs`.
15. Añadir contexto nullable a las tablas auxiliares de PQRS.
16. Crear Organización y Copropiedad heredadas.
17. Ejecutar backfill de configuración, identidad, Membresías y Roles.
18. Ejecutar backfill de Personas, Unidades y Vínculos.
19. Ejecutar backfill de PQRS y auxiliares.
20. Conciliar excepciones.
21. Crear índices, claves candidatas y FKs compuestas.
22. Convertir contexto operativo en no nulo.
23. Desplegar lectura contextual y doble escritura.
24. Eliminar consultas globales.
25. Habilitar el selector de Copropiedad.
26. Autorizar una segunda Copropiedad solo después de validar aislamiento.

Las migraciones de estructura y los procesos de backfill deben separarse para
evitar transacciones extensas y permitir reanudación.

## 15. Estrategia de backfill

- Crear identificadores estables para el tenant y ámbito heredados.
- Ejecutar procesos idempotentes, por lotes y reanudables.
- No modificar claves primarias existentes.
- No borrar ni sobrescribir datos fuente.
- Registrar conteos, rechazos y correspondencias por lote.
- Usar las FKs fuertes después de resolver datos inválidos.
- Detener registros ambiguos para conciliación manual.
- Comparar autorización nueva con `users.role` antes de cambiar lecturas.
- Comparar Unidad y Vínculo con `tower` y `unit`.
- Bloquear la creación de una segunda Copropiedad durante el proceso.
- Verificar archivos y relaciones dependientes antes de declarar cobertura.

## 16. Validaciones posteriores

1. Toda Copropiedad tiene Organización.
2. Toda fila operativa de Copropiedad tiene ambos identificadores no nulos.
3. Toda FK compuesta resuelve dentro de la misma Organización y Copropiedad.
4. No hay PQRS cuyo Tipo pertenezca a otro contexto.
5. No hay auxiliares de PQRS con contexto diferente de su raíz.
6. No hay etiquetas cruzadas mediante `pqr_pqr_tag`.
7. Toda Membresía de Copropiedad referencia una Copropiedad de su Organización.
8. Toda asignación de Rol referencia el tipo de Membresía correspondiente.
9. Ningún Rol de Organización aparece en asignaciones de Copropiedad y
   viceversa.
10. No existen Membresías duplicadas por Usuario y ámbito.
11. Todo Vínculo une Persona y Unidad compatibles.
12. Cada Usuario conserva acceso equivalente al estado previo.
13. Cada combinación conciliada de torre y unidad tiene correspondencia.
14. Todas las filas de `site_settings` fueron migradas o clasificadas.
15. Los conteos de PQRS y dependientes coinciden antes y después.
16. No quedan consultas de catálogos operativos sin contexto antes de activar
    multi-copropiedad.

## 17. Estrategia de rollback

- Mantener cambios aditivos hasta finalizar la estabilización.
- Conservar `users.role`, `users.tower`, `users.unit` y `site_settings`.
- Mantener una bandera que impida activar múltiples Copropiedades.
- Poder volver temporalmente a lecturas heredadas antes de aceptar datos de una
  segunda Copropiedad.
- Revertir asignaciones nuevas sin eliminar datos fuente.
- No retirar columnas contextuales si ya contienen datos de más de una
  Copropiedad.
- Después de activar multi-copropiedad, realizar rollback compensatorio; nunca
  colapsar todos los datos al tenant heredado.
- Usar restauración de respaldo únicamente ante corrupción, no como flujo
  ordinario.

## 18. Datos que requieren conciliación manual

- Filas adicionales de `site_settings`.
- Usuarios con torre o unidad vacía.
- Variantes ortográficas de una misma Unidad.
- Usuarios residentes que podrían representar la misma Persona.
- Personas sin identificación suficiente.
- Usuarios no residentes con torre o unidad.
- valores de `users.role` distintos de los reconocidos;
- Tipos, Etiquetas o Plantillas duplicadas semánticamente.
- Responsables de PQRS sin pertenencia clara al nuevo contexto.
- Reglas automáticas cuyo responsable no deba recibir Membresía.
- Configuración sin alcance claro entre Organización y Copropiedad.
- Archivos registrados cuya ruta física no exista.

## 19. Conservación, adaptación y reemplazo de tablas actuales

### 19.1. Se conservan inicialmente

- `password_reset_tokens`
- `sessions`
- `cache`, `cache_locks`
- `jobs`, `job_batches`, `failed_jobs`
- `notifications`

Sus claves o payloads deberán incorporar contexto cuando participen en una
operación de Copropiedad.

### 19.2. Se adaptan

- `users`
- `tipo_pqrs`
- `pqrs`
- `pqr_attachments`
- `pqr_activities`
- `pqr_replies`
- `pqr_internal_comments`
- `pqr_tags`
- `pqr_pqr_tag`
- `response_templates`
- `satisfaction_surveys`
- `automation_rules`
- `audit_logs`

### 19.3. Se reemplaza gradualmente

- `site_settings`, por `copropiedades` y
  `configuraciones_copropiedad`.

No se elimina ninguna tabla heredada en la primera evolución.

## 20. Riesgos

- Asignar Organización o Copropiedad incorrecta durante backfill.
- Crear Membresías duplicadas o asignar el Rol en la tabla de ámbito errónea.
- Consolidar como definitivos los Roles actuales.
- Duplicar Unidades por normalización insuficiente.
- Vincular una Persona equivocada con un Usuario.
- Añadir contexto a la PQRS pero omitir una tabla auxiliar.
- Dejar Etiquetas, Plantillas o reglas automáticas globales.
- Crear FKs compuestas sin índices o con orden incompatible.
- Mantener doble escritura divergente.
- Ejecutar jobs o notificaciones con contexto obsoleto.
- Activar una segunda Copropiedad antes de cerrar consultas globales.
- Intentar rollback destructivo después de recibir datos multi-copropiedad.

## 21. Decisiones pendientes

Las siguientes materias no se aprueban en este documento:

1. Participación de un Usuario en varias Organizaciones — **Pendiente de
   decisión del Product Owner**.
2. Transferencia de una Copropiedad entre Organizaciones — **Pendiente de
   decisión del Product Owner**.
3. Roles personalizados — **Pendiente de decisión del Product Owner**.
4. Catálogo definitivo de Roles y Permisos — **Pendiente de decisión del
   Product Owner**.
5. Reglas definitivas de Propietarios y Residentes — **Pendiente de decisión
   del Product Owner**.
6. Relación máxima entre Persona y Usuario — **Pendiente de decisión del
   Product Owner**.
7. Identificación obligatoria de Organización y Copropiedad — **Pendiente de
   decisión del Product Owner**.
8. Ciclo de vida, anulación y eliminación de PQRS — **Pendiente de decisión del
   Product Owner**.
9. Alcance definitivo de Configuración de Organización — **Pendiente de
   decisión del Product Owner**.
10. Numeración visible de PQRS por Copropiedad — **Pendiente de decisión del
    Product Owner**.
11. Tratamiento de Usuarios sin Unidad identificable — **Pendiente de decisión
    del Product Owner**.

## 22. Referencias documentales

- [Modelo del dominio futuro](modelo-del-dominio-futuro.md).
- [Arquitectura objetivo](../05-arquitectura/arquitectura-objetivo.md).
- [ADR-003 — Multi-tenancy con esquema compartido](../10-adr/ADR-003-multitenancy-esquema-compartido.md).
- [ADR-004 — Organización y Copropiedad](../10-adr/ADR-004-organizacion-tenant-copropiedad-ambito.md).
- [ADR-005 — Usuario global y membresías](../10-adr/ADR-005-usuario-global-membresias.md).
- [ADR-006 — Roles, permisos y capacidades](../10-adr/ADR-006-roles-permisos-capacidades.md).
- [Modelo de datos actual](modelo-de-datos.md).
- [Roles y permisos actuales](../02-funcional/roles-y-permisos.md).
- [Gestión de PQR](../02-funcional/gestion-pqr.md).
