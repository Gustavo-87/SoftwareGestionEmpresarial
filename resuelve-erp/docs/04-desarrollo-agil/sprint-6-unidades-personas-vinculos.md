# Sprint 6 — Unidades Privadas, Personas y Vínculos Contextuales

## Control documental

- **Versión:** v1.1
- **Estado:** Completado
- **Fecha de creación:** 2 de agosto de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

---

## 1. Objetivo y resumen ejecutivo

El **Sprint 6** amplía el núcleo relacional de la plataforma Resuelve introduciendo la representación formal de **Unidades Privadas**, **Personas** y sus **Vínculos** dentro del contexto multi-tenant de `Organizacion` y `Copropiedad`.

Este sprint desarrolla la Épica 4 (*Residentes y Propietarios*) del Product Backlog oficial y da cumplimiento a la Sección 6 del Modelo de Datos Futuro (`docs/03-tecnica/modelo-de-datos-futuro.md`). Todo el alcance se ejecuta sin alterar la experiencia funcional observable para el usuario ni romper la compatibilidad con el sistema de autorización contextual activado en el Sprint 5.

---

## 2. Principio de implementación

> **Principio Fundamental:** Se priorizará siempre la solución más simple que preserve la integridad arquitectónica, la seguridad aislada por contexto y el rendimiento del sistema.

No se introducirán abstracciones innecesarias, patrones complejos de diseño ni dependencias adicionales que no estén justificadas por el modelo del dominio objetivo.

---

## 3. Alcance e Historias de Usuario

### HU-S6-01 — Estructura de Unidades Privadas por Copropiedad

* **Objetivo:** Implementar la tabla relacional, modelo de dominio e integridad referencial para `unidades_privadas` pertenecientes a una Copropiedad (`organizacion_id`, `copropiedad_id`).
* **Justificación:** Cumple con la HU-RYP-01 y la Sección 6.2 de `modelo-de-datos-futuro.md`. Delimita físicamente los bienes privados (torres, bloques, unidades) dentro del ámbito de cada Copropiedad.
* **Criterios de Aceptación:**
  1. La migración crea la tabla `unidades_privadas` con clave foránea compuesta hacia `copropiedades(id, organizacion_id)`.
  2. La unicidad del código de unidad se restringe al ámbito `(copropiedad_id, codigo)`.
  3. Permite almacenar opcionalmente `torre_bloque`, `numero_nombre`, `tipo` y marcas de desactivación lógica.
  4. La funcionalidad web actual se mantiene idéntica y sin cambios visibles para el usuario.
* **Dependencias:** Sprint 1 completado (`organizaciones` y `copropiedades`).
* **Impacto Arquitectónico:** Incorpora la entidad `UnidadPrivada` al agregado `Copropiedad` en la capa de Infraestructura y Dominio.
* **Riesgos:** Unidades con códigos idénticos entre diferentes bloques. (Mitigación: índice compuesto `(copropiedad_id, torre_bloque, numero_nombre)`).

---

### HU-S6-02 — Representación de Personas por Organización

* **Objetivo:** Implementar la tabla y modelo `personas` bajo el ámbito del tenant `Organizacion`.
* **Justificación:** Desacopla la identidad legal y personal de los sujetos del dominio respecto a la tabla de usuarios del sistema (`users`), permitiendo registrar propietarios y residentes que no posean cuenta de acceso.
* **Criterios de Aceptación:**
  1. La migración crea la tabla `personas` con FK hacia `organizaciones.id` y FK nullable hacia `users.id`.
  2. Aplicación de unicidad sobre la identificación dentro de la Organización cuando esté informada.
  3. Restricción de eliminación física si la persona cuenta con vínculos o historial en la copropiedad.
  4. La vinculación con `usuario_id` es opcional e inmutable desde formularios genéricos.
* **Dependencias:** Sprint 2 completado (`users` e identidad global).
* **Impacto Arquitectónico:** Agrega la entidad `Persona` al ámbito de la Organización, separando los datos personales/jurídicos del sistema de autenticación.
* **Riesgos:** Documentos de identificación duplicados o no informados en registros legados. (Mitigación: campo de identificación nullable en esta fase).

---

### HU-S6-03 — Vínculos de Personas con Unidades Privadas

* **Objetivo:** Crear la tabla `vinculos_unidad` para asociar temporalmente una `Persona` con una `UnidadPrivada` mediante un tipo de relación (`propietario`, `residente`, `tenedor`).
* **Justificación:** Cumple con la Sección 6.3 de `modelo-de-datos-futuro.md`, garantizando el seguimiento del historial de ocupación y propiedad.
* **Criterios de Aceptación:**
  1. La migración crea `vinculos_unidad` con FKs compuestas obligatorias hacia `personas(id, organizacion_id)` y `unidades_privadas(id, organizacion_id, copropiedad_id)`.
  2. Las restricciones relacionales impiden asociar Personas o Unidades pertenecientes a otros ámbitos.
  3. Los cambios de vinculación no eliminan registros; concluyen la vigencia poblando `vigente_hasta`.
  4. Mantiene absoluta compatibilidad con la operación web y el módulo de PQRS.
* **Dependencias:** HU-S6-01 y HU-S6-02.
* **Impacto Arquitectónico:** Establece la relación many-to-many temporal entre personas y bienes privados en el agregado Copropiedad.
* **Riesgos:** Intento de asignaciones cruzadas entre copropiedades. (Mitigación: FKs compuestas que incluyen `organizacion_id` y `copropiedad_id`).

---

### HU-S6-04 — Backfill Idempotente de Personas y Unidades

* **Objetivo:** Implementar un comando Artisan idempotente (`php artisan resuelve:contextualizar-unidades-y-personas`) que cree `unidades_privadas` y `personas` a partir de los campos legados `users.tower` y `users.unit`, sin inferir tipos de vínculo no presentes en el legado.
* **Justificación:** Garantiza la migración de los datos históricos existentes sin pérdida de información ni interrupciones en el servicio.
* **Criterios de Aceptación:**
  1. El comando procesa transaccionalmente todos los usuarios con datos de unidad en la copropiedad inicial (`SiteSetting::current()`).
  2. Genera los registros de `Persona` y `UnidadPrivada` sin duplicarlos en ejecuciones sucesivas.
  3. Es idempotente y ofrece un modo de diagnóstico previo (`--dry-run`).
  4. Omite y reporta los Vínculos cuyo `tipo_vinculo` no puede determinarse de manera verificable desde el legado.
* **Dependencias:** HU-S6-01, HU-S6-02 y HU-S6-03.
* **Impacto Arquitectónico:** Asegura la transición limpia de datos legados hacia el esquema multi-tenant objetivo.
* **Riesgos:** Usuarios con campos de unidad nulos o con formatos inconsistentes. (Mitigación: validación en fase diagnóstica previa a la transacción).

---

## 4. Fase de diseño técnico

### 4.1. Esquema relacional
Se estructuran las migraciones aditivas de acuerdo con la Sección 6 de `docs/03-tecnica/modelo-de-datos-futuro.md`:

1. **`unidades_privadas`**:
   * Clave primaria: `id`.
   * Columnas: `id`, `organizacion_id`, `copropiedad_id`, `codigo`, `numero_nombre`, `torre_bloque`, `tipo`, `estado`, `desactivada_at`, `created_at`, `updated_at`.
   * Clave foránea compuesta: `(copropiedad_id, organizacion_id) → copropiedades(id, organizacion_id)`.
   * Clave única candidata: `(id, organizacion_id, copropiedad_id)`.
   * Restricción única de negocio: `(copropiedad_id, codigo)`.

2. **`personas`**:
   * Clave primaria: `id`.
   * Columnas: `id`, `organizacion_id`, `usuario_id`, `tipo_persona`, `nombre_razon_social`, `identificacion`, `email`, `telefono`, `estado`, `desactivada_at`, `created_at`, `updated_at`.
   * Claves foráneas: `organizacion_id → organizaciones.id` y `usuario_id → users.id` (nullable).
   * Clave única candidata: `(id, organizacion_id)`.

3. **`vinculos_unidad`**:
   * Clave primaria: `id`.
   * Columnas: `id`, `organizacion_id`, `copropiedad_id`, `persona_id`, `unidad_privada_id`, `tipo_vinculo`, `estado`, `vigente_desde`, `vigente_hasta`, `fuente`, `observacion`, `created_at`, `updated_at`.
   * Claves foráneas compuestas:
     * `(persona_id, organizacion_id) → personas(id, organizacion_id)`.
     * `(unidad_privada_id, organizacion_id, copropiedad_id) → unidades_privadas(id, organizacion_id, copropiedad_id)`.

### 4.2. Capa de modelos y scopes
* Se crean los modelos Eloquent `UnidadPrivada`, `Persona` y `VinculoUnidad`.
* Se aplican scopes globales/locales de pertenencia contextual a `Organizacion` y `Copropiedad` resueltos desde `ContextoOperativo`.

---

## 5. Requisitos de seguridad del Sprint

1. **Validación Obligatoria de Contexto en Backend:** Toda consulta o escritura de `unidades_privadas`, `personas` o `vinculos_unidad` debe validar e inyectar explícitamente `organizacion_id` y `copropiedad_id` desde `ContextoOperativo`. Ningún identificador de contexto se aceptará desde entradas HTTP manipulables.
2. **Cumplimiento de Autorización Contextual Vigente:** Toda operación estará sujeta a la comprobación de permisos a través de `AutorizacionContextual`.
3. **Prevención de Fugas de Datos (*Cross-tenant Data Leakage*):** Las claves foráneas compuestas en la base de datos y los scopes en Eloquent impedirán que un usuario consulte o asocie unidades o personas pertenecientes a otra Copropiedad u Organización.
4. **Buenas Prácticas OWASP:** Validar y sanitizar la totalidad de las entradas en controladores y comandos; garantizar la inmutabilidad de los registros históricos.
5. **Cero Deuda de Seguridad:** No se permitirán soluciones temporales que omitan comprobaciones de permisos o permitan accesos globales sin validar el ámbito operativo.

---

## 6. Fase de validación y pruebas

Para garantizar el cumplimiento de este sprint sin introducir regresiones, se ejecutarán tres niveles de verificación:

1. **Pruebas Integrales de Migración y Modelos:** Validar que las migraciones corran correctamente en SQLite y MySQL, asegurando que las restricciones de claves foráneas compuestas rechacen inserciones cruzadas entre copropiedades.
2. **Prueba de Idempotencia del Comando de Backfill:** Ejecutar en el entorno de pruebas el comando `resuelve:contextualizar-unidades-y-personas`:
   * `--dry-run`: diagnostica tres Personas y tres Unidades Privadas por crear, sin escribir.
   * Primera ejecución: crea tres Personas y tres Unidades Privadas; omite y reporta tres Vínculos indeterminables porque `users.tower` y `users.unit` no definen `tipo_vinculo`.
   * Segunda ejecución consecutiva: retorna cero creaciones, tres Personas y tres Unidades existentes y los mismos tres Vínculos omitidos.
3. **Prueba de No Regresión respecto al Sprint 5:** Ejecutar la suite completa de pruebas funcionales y el comando de equivalencia:
   ```bash
   php artisan resuelve:verificar-equivalencia-autorizacion-contextual
   ```
   El comando deberá reportar cero divergencias de autorización.

---

## 7. Validación ejecutada

La validación técnica se ejecutó sobre MySQL 8.4 en la base aislada
`laravel_testing`:

1. `php artisan migrate` completó las 30 migraciones, incluidas las tablas
   `personas`, `unidades_privadas` y `vinculos_unidad`.
2. `php artisan migrate:rollback --step=3` revirtió esas tres migraciones y una
   segunda ejecución de `php artisan migrate` las aplicó nuevamente sin errores.
3. El índice de ubicación de `unidades_privadas` usa el nombre explícito
   `idx_unidades_ubicacion`, compatible con el límite de identificadores de
   MySQL.
4. El `--dry-run` informó tres Personas y tres Unidades Privadas por crear,
   cero registros existentes, tres Vínculos omitidos sin tipo y tres
   inconsistencias registradas.
5. La primera ejecución creó tres Personas y tres Unidades Privadas; la segunda
   creó cero, encontró tres de cada una existentes y no produjo duplicados.
6. Los tres Vínculos indeterminables se omitieron y reportaron sin crearse; no
   se infirió `propietario`, `residente` ni `tenedor` desde datos insuficientes.
7. Una inserción transaccional cruzada fue rechazada por MySQL con `ERROR 1452`
   mediante la FK compuesta `vinculos_persona_organizacion_foreign`. Un vínculo
   temporal concluido conservó su fila, `vigente_desde` y `vigente_hasta`; la
   transacción de comprobación se revirtió y no dejó vínculos persistidos.
8. Las pruebas específicas aprobaron 5 pruebas y 27 aserciones; la suite
   completa aprobó 142 pruebas y 615 aserciones.

## 8. Definition of Done (Criterios de finalización)

El Sprint 6 está completado:

* Las cuatro historias de usuario (HU-S6-01 a HU-S6-04) fueron implementadas.
* Las migraciones relacionales se ejecutaron y revirtieron limpiamente en MySQL 8.4.
* El comando de backfill fue verificado como idempotente y transaccional.
* Las pruebas automatizadas fueron satisfactorias, sin regresiones funcionales.
* La documentación oficial en `docs/` fue actualizada.
* Se mantuvo la compatibilidad con la funcionalidad existente.
