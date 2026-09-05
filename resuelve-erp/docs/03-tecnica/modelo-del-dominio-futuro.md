# Modelo del dominio futuro

## Control documental

- **Versión:** v1.0
- **Estado:** Aprobado
- **Fecha:** 31 de julio de 2026
- **Responsable:** Arquitectura Resuelve
- **Aprobación:** Product Owner

## 1. Propósito y alcance

Este documento define el modelo conceptual objetivo de Resuelve antes de
diseñar cambios de persistencia. Describe entidades, responsabilidades,
atributos conceptuales, relaciones, cardinalidades, propiedad del dato y reglas
principales. No define modelos Eloquent, tablas, columnas, tipos SQL ni
migraciones.

Las 26 entidades documentadas representan el **modelo conceptual objetivo** de
la plataforma y no el alcance de una sola fase de implementación. Su
incorporación será incremental y estará sujeta a las dependencias y decisiones
funcionales indicadas en este documento.

El modelo se basa en la
[arquitectura objetivo](../05-arquitectura/arquitectura-objetivo.md), los ADR
aprobados y la documentación funcional del estado actual. El
[modelo de dominio actual](modelo-de-dominio.md) continúa describiendo la
implementación existente.

## 2. Criterios de modelado

1. **Modelo conceptual:** una entidad expresa identidad y ciclo de vida en el
   dominio; no implica automáticamente una tabla independiente.
2. **Agregados acotados:** las transacciones preservan invariantes dentro de una
   raíz sin cargar grafos completos.
3. **Contexto explícito:** los datos pertenecen a plataforma, Organización o
   Copropiedad según su responsabilidad.
4. **Separación de conceptos:** identidad, sujeto, pertenencia, autorización y
   disponibilidad comercial no se mezclan.
5. **Historia preservada:** la desactivación es preferible a la eliminación
   cuando una entidad participa en actuaciones históricas.
6. **Evolución incremental:** el modelo futuro se introduce alrededor del
   comportamiento existente sin reescritura total.

## 3. Límites de agregados

### 3.1. Agregado Organización

`Organización` es la raíz del tenant comercial. Administra su identidad
comercial y coordina Suscripciones y Habilitaciones de Capacidad mediante casos
de uso del módulo Organización y Suscripción.

No administra internamente los datos operativos de sus Copropiedades. La
relación `Organización 1:N Copropiedad` expresa pertenencia comercial, no un
único agregado ni una operación transaccional conjunta.

### 3.2. Agregado Copropiedad

`Copropiedad` es una raíz independiente. Administra su identidad operativa,
configuración, Unidades Privadas y contexto funcional. PQRS, Documentos y demás
entidades operativas referencian su identidad, pero conservan sus propios
límites de agregado cuando tienen ciclo de vida independiente.

Una Copropiedad se consulta y modifica sin cargar ni modificar el agregado
Organización. La coherencia con la Organización se verifica mediante
identificadores y reglas de aplicación.

### 3.3. Otros agregados

| Agregado | Raíz | Contenido o entidades relacionadas |
| --- | --- | --- |
| Identidad | Usuario | Credenciales y estado de la identidad autenticable. |
| Acceso | Membresía | Asignación de Roles aplicables al ámbito. |
| Autorización | Rol | Relación con Permisos estables. |
| Suscripción | Suscripción | Oferta contratada y origen de habilitaciones. |
| Comunidad | Persona | Vínculos con Unidades Privadas. |
| PQRS | PQRS | Respuestas, Comentarios Internos, Adjuntos y Actuaciones. |
| Gestión Documental | Documento | Versiones y Fragmentos Documentales. |
| Inteligencia Artificial | Solicitud de IA | Resultado IA, Fuentes Recuperadas y Revisiones Humanas. |

Los agregados colaboran mediante identificadores, casos de uso y eventos. Una
referencia entre ellos no autoriza modificaciones directas sobre la información
de la otra raíz.

## 4. Separación de responsabilidades

| Concepto | Responsabilidad exclusiva |
| --- | --- |
| Usuario | Identidad autenticable global. |
| Persona | Sujeto natural o jurídico del dominio. |
| Membresía | Pertenencia autorizada de un Usuario a un ámbito. |
| Rol y Permiso | Acciones que el Usuario puede realizar en ese ámbito. |
| Capacidad y Suscripción | Funciones comercialmente disponibles para la Organización. |

Una Persona puede existir sin Usuario. Un Usuario no obtiene acceso sin
Membresía. Una Membresía no otorga acciones sin Rol y Permiso. Un Permiso no
habilita una función que la Organización no tenga disponible. Una Capacidad
habilitada no concede permisos a un Usuario.

## 5. Entidades del contexto Organización y Copropiedades

### 5.1. Organización

- **Propósito:** representar al cliente comercial de Resuelve.
- **Responsabilidades:** conservar identidad y estado comercial; servir como
  contexto principal de Suscripciones y capacidades; agrupar las
  Copropiedades administradas sin incorporarlas a su agregado.
- **Atributos principales:** identificador, nombre, identificación tributaria
  opcional, datos de contacto, estado y marcas de tiempo.
- **Relaciones:** administra Copropiedades; posee Membresías de Organización,
  Suscripciones y Habilitaciones de Capacidad.
- **Cardinalidades:** `1:N` Copropiedad; `1:N` Membresía; `0:N` Suscripción;
  `0:N` Habilitación de Capacidad.
- **Propietario del dato:** módulo Organización y Suscripción; ámbito
  Organización.
- **Reglas principales:** toda información de negocio debe poder atribuirse a
  una Organización; su identidad comercial no concede permisos; no se elimina
  físicamente mientras conserve historia; una modificación de Organización no
  modifica automáticamente sus Copropiedades.

### 5.2. Copropiedad

- **Propósito:** representar la comunidad de propiedad horizontal administrada.
- **Responsabilidades:** conservar identidad operativa y configuración; agrupar
  Unidades Privadas; delimitar el contexto funcional y la frontera principal de
  datos.
- **Atributos principales:** identificador, identificador de Organización,
  nombre, identificación tributaria opcional, dirección, ciudad, contacto,
  estado y configuración operativa.
- **Relaciones:** pertenece comercialmente a una Organización; contiene
  Unidades Privadas; contextualiza Membresías, PQRS, Documentos y solicitudes
  de IA.
- **Cardinalidades:** Organización `1:N` Copropiedad; Copropiedad `1:N` Unidad
  Privada, Membresía, PQRS y Documento.
- **Propietario del dato:** módulo Copropiedades; ámbito Copropiedad.
- **Reglas principales:** pertenece a una Organización; mantiene aislamiento de
  las demás Copropiedades; su Organización debe coincidir con la de toda entidad
  contextual; su desactivación conserva la historia; se modifica como agregado
  independiente de Organización.

La transferencia de una Copropiedad entre Organizaciones es **Pendiente de
decisión del Product Owner**.

## 6. Entidades de identidad y autorización

### 6.1. Usuario

- **Propósito:** representar una identidad autenticable global.
- **Responsabilidades:** autenticación, recuperación de acceso, credenciales,
  estado de cuenta y datos mínimos de identificación de acceso.
- **Atributos principales:** identificador, nombre visible, correo normalizado,
  credencial, estado, verificación y fechas de acceso.
- **Relaciones:** posee Membresías; puede asociarse con Personas; actúa como
  radicador, responsable, autor o revisor.
- **Cardinalidades:** `0:N` Membresía; `0:N` actuaciones en los módulos; relación
  opcional con Persona.
- **Propietario del dato:** módulo Identidad y Acceso; ámbito plataforma.
- **Reglas principales:** el correo autenticable es único; la identidad global
  no concede acceso global; una cuenta con historia se desactiva en lugar de
  eliminarse; Usuario no representa la condición de Propietario o Residente.

La participación de un Usuario en varias Organizaciones es **Pendiente de
decisión del Product Owner**. El modelo no la presume como regla aprobada.

### 6.2. Membresía

- **Propósito:** representar la pertenencia autorizada de un Usuario a una
  Organización o Copropiedad.
- **Responsabilidades:** conservar ámbito, vigencia y Roles asignados.
- **Atributos principales:** identificador, Usuario, tipo e identificador de
  ámbito, estado, inicio, fin opcional, creador y motivo de terminación
  opcional.
- **Relaciones:** pertenece a Usuario y a un ámbito; se relaciona con uno o
  varios Roles.
- **Cardinalidades:** Usuario `1:N` Membresía; Organización o Copropiedad `1:N`
  Membresía; Membresía `N:M` Rol.
- **Propietario del dato:** módulo Identidad y Acceso; ámbito de la Membresía.
- **Reglas principales:** referencia exactamente un ámbito; una Membresía de
  Copropiedad debe corresponder a la Organización propietaria; solo una
  Membresía vigente interviene en autorización; no habilita capacidades
  comerciales; su terminación conserva historia.

### 6.3. Rol

- **Propósito:** agrupar Permisos bajo una responsabilidad reconocible.
- **Responsabilidades:** definir un conjunto de Permisos compatible con un tipo
  de ámbito y permitir su asignación a Membresías.
- **Atributos principales:** identificador, clave estable, nombre, descripción,
  tipo de ámbito, estado y origen.
- **Relaciones:** agrupa Permisos y se asigna a Membresías.
- **Cardinalidades:** `N:M` Permiso; `N:M` Membresía.
- **Propietario del dato:** módulo Identidad y Acceso; ámbito plataforma salvo
  decisión futura diferente.
- **Reglas principales:** su clave es estable; solo se asigna a ámbitos
  compatibles; no reemplaza capacidades; no se infieren facultades a partir del
  nombre del Rol.

Los Roles personalizados y el catálogo definitivo de Roles son **Pendiente de
decisión del Product Owner**.

### 6.4. Permiso

- **Propósito:** representar una acción autorizable estable.
- **Responsabilidades:** identificar la acción, el módulo y el tipo de ámbito al
  que puede aplicarse.
- **Atributos principales:** identificador, clave técnica, módulo, acción,
  descripción, tipo de ámbito y estado.
- **Relaciones:** se incorpora a uno o varios Roles.
- **Cardinalidades:** Permiso `N:M` Rol.
- **Propietario del dato:** módulo Identidad y Acceso; ámbito plataforma.
- **Reglas principales:** su clave es única y estable; no se asigna a planes;
  disponer de un Permiso no omite Membresía, pertenencia del recurso, Capacidad
  ni reglas del caso de uso.

El catálogo definitivo de Permisos y su asignación a Roles son **Pendiente de
decisión del Product Owner**.

## 7. Entidades comerciales

### 7.1. Suscripción

- **Propósito:** representar la relación comercial de una Organización con una
  Oferta Comercial.
- **Responsabilidades:** conservar oferta, vigencia, estado e historial del
  origen comercial de las capacidades.
- **Atributos principales:** identificador, Organización, Oferta Comercial,
  estado, inicio, fin opcional y referencia comercial externa opcional.
- **Relaciones:** pertenece a Organización y Oferta Comercial; origina
  Habilitaciones de Capacidad.
- **Cardinalidades:** Organización `0:N` Suscripción; Oferta `1:N` Suscripción;
  Suscripción `1:N` Habilitación.
- **Propietario del dato:** módulo Organización y Suscripción; ámbito
  Organización.
- **Reglas principales:** pertenece a una Organización; conserva historial; el
  nombre de la Oferta no condiciona módulos funcionales; la disponibilidad
  efectiva se consulta mediante habilitaciones.

Los estados definitivos, periodicidad, límites y cuotas de los planes son
**Pendiente de decisión del Product Owner**.

### 7.2. Oferta Comercial

- **Propósito:** representar Básico, Pro y ofertas futuras.
- **Responsabilidades:** identificar una oferta y asociar su conjunto
  predeterminado de Capacidades.
- **Atributos principales:** identificador, clave, nombre comercial,
  descripción, estado y vigencia.
- **Relaciones:** contiene Capacidades y es contratada mediante Suscripciones.
- **Cardinalidades:** `N:M` Capacidad; `1:N` Suscripción.
- **Propietario del dato:** módulo Organización y Suscripción; ámbito
  plataforma.
- **Reglas principales:** el nombre comercial no se consulta desde los módulos;
  los cambios de composición conservan trazabilidad; la Oferta por sí sola no
  concede permisos.

La composición definitiva de Básico y Pro es **Pendiente de decisión del
Product Owner**.

### 7.3. Capacidad

- **Propósito:** representar una función comercial habilitable.
- **Responsabilidades:** proporcionar una clave estable independiente de los
  nombres de las Ofertas.
- **Atributos principales:** identificador, clave técnica, nombre, descripción,
  módulo, estado y tipo de habilitación.
- **Relaciones:** pertenece a Ofertas y origina Habilitaciones por Organización.
- **Cardinalidades:** `N:M` Oferta; `1:N` Habilitación.
- **Propietario del dato:** módulo Organización y Suscripción; ámbito
  plataforma.
- **Reglas principales:** la clave no incluye nombres de plan; una Capacidad no
  concede permisos; los módulos consultan su habilitación efectiva; su retiro
  conserva historia.

### 7.4. Habilitación de Capacidad

- **Propósito:** representar la disponibilidad efectiva de una Capacidad para
  una Organización.
- **Responsabilidades:** conservar origen, vigencia y estado de la habilitación.
- **Atributos principales:** identificador, Organización, Capacidad,
  Suscripción opcional, origen, estado, inicio y fin opcional.
- **Relaciones:** pertenece a Organización y Capacidad; puede derivarse de una
  Suscripción.
- **Cardinalidades:** Organización `1:N`; Capacidad `1:N`; Suscripción `0:N`.
- **Propietario del dato:** módulo Organización y Suscripción; ámbito
  Organización.
- **Reglas principales:** es la fuente consultada por los módulos; su
  Organización debe coincidir con la Suscripción; no concede autorización a un
  Usuario; conserva la causa de habilitación.

## 8. Entidades de comunidad

### 8.1. Persona

- **Propósito:** representar a un sujeto natural o jurídico del dominio.
- **Responsabilidades:** conservar identificación y contacto funcional y
  participar en relaciones con Unidades Privadas.
- **Atributos principales:** identificador, Organización, tipo de persona,
  nombres o razón social, identificación opcional, contacto y estado.
- **Relaciones:** puede asociarse con Usuario; se relaciona con Unidades
  Privadas mediante Vínculos con Unidad; puede participar en PQRS.
- **Cardinalidades:** Organización `1:N` Persona; Persona `N:M` Unidad Privada;
  asociación opcional con Usuario.
- **Propietario del dato:** módulo Residentes y Propietarios; ámbito
  Organización con acceso restringido por Copropiedad.
- **Reglas principales:** puede existir sin Usuario; asociarla con Usuario no
  concede acceso; no se comparte automáticamente entre Organizaciones; su
  condición funcional proviene de Vínculos con Unidad.

### 8.2. Unidad Privada

- **Propósito:** representar un bien privado de una Copropiedad.
- **Responsabilidades:** conservar su identidad operativa y servir de extremo a
  los Vínculos con Unidad.
- **Atributos principales:** identificador, Organización, Copropiedad, código,
  torre o bloque opcional, número o nombre, tipo opcional y estado.
- **Relaciones:** pertenece a Copropiedad y se relaciona con Personas mediante
  Vínculos.
- **Cardinalidades:** Copropiedad `1:N` Unidad Privada; Unidad `N:M` Persona.
- **Propietario del dato:** agregado Copropiedad; ámbito Copropiedad.
- **Reglas principales:** pertenece a una Copropiedad; su Organización debe
  coincidir con ella; su identificación es única dentro de la Copropiedad; no
  almacena directamente al propietario o residente actual.

### 8.3. Vínculo con Unidad

- **Propósito:** representar la relación temporal de una Persona con una Unidad
  Privada.
- **Responsabilidades:** conservar tipo de vínculo, vigencia, estado y
  procedencia.
- **Atributos principales:** identificador, Persona, Unidad Privada, tipo de
  vínculo, inicio, fin opcional, estado y observación opcional.
- **Relaciones:** pertenece a Persona y Unidad Privada.
- **Cardinalidades:** Persona `1:N` Vínculo; Unidad `1:N` Vínculo.
- **Propietario del dato:** módulo Residentes y Propietarios; ámbito
  Copropiedad.
- **Reglas principales:** Persona y Unidad corresponden a la misma Organización;
  el vínculo no crea Usuario, Membresía ni Rol; su terminación conserva historia.

Las reglas definitivas de Propietarios y Residentes, incluidos simultaneidad,
vigencias, solapamientos y porcentajes, son **Pendiente de decisión del Product
Owner**.

## 9. Entidades del agregado PQRS

### 9.1. PQRS

- **Propósito:** representar una petición, queja, reclamo o sugerencia de una
  Copropiedad.
- **Responsabilidades:** conservar radicación, clasificación, atención,
  responsable, estado, fechas e historial.
- **Atributos principales:** identificador, Organización, Copropiedad, código
  visible, asunto, descripción, fechas, estado, Tipo de PQRS, radicador y
  responsable opcional.
- **Relaciones:** pertenece a Copropiedad; referencia Tipo, Usuario radicador y
  responsable; contiene Respuestas, Comentarios Internos, Adjuntos y
  Actuaciones.
- **Cardinalidades:** Copropiedad `1:N` PQRS; Tipo `1:N` PQRS; Usuario `1:N`
  radicadas y `0:N` asignadas; PQRS `1:N` entidades internas.
- **Propietario del dato:** módulo PQRS; ámbito Copropiedad.
- **Reglas principales:** debe estar contextualizada por Organización y
  Copropiedad; sus referencias deben pertenecer al ámbito autorizado; la
  radicación produce una Actuación; los cambios se realizan mediante casos de
  uso autorizados; la historia no depende de mantener activa la cuenta del
  actor.

Los estados actuales son una línea base de compatibilidad. El ciclo de vida
definitivo y las reglas de eliminación son **Pendiente de decisión del Product
Owner**.

### 9.2. Tipo de PQRS

- **Propósito:** clasificar una PQRS.
- **Responsabilidades:** mantener nombre, descripción y disponibilidad del
  catálogo contextual.
- **Atributos principales:** identificador, Organización, Copropiedad, nombre,
  descripción y estado.
- **Relaciones:** pertenece a Copropiedad y clasifica PQRS.
- **Cardinalidades:** Copropiedad `1:N` Tipo; Tipo `1:N` PQRS.
- **Propietario del dato:** módulo PQRS; ámbito Copropiedad.
- **Reglas principales:** es único dentro del ámbito definido; su retiro no
  elimina PQRS históricas; toda PQRS referencia un Tipo válido.

### 9.3. Actuación de PQRS

- **Propósito:** conservar un hecho semántico del historial de una PQRS.
- **Responsabilidades:** registrar acción, actor, descripción, metadatos, fecha
  y correlación.
- **Atributos principales:** identificador, PQRS, actor opcional, acción,
  descripción, metadatos, fecha y correlación.
- **Relaciones:** pertenece a PQRS y puede referenciar Usuario.
- **Cardinalidades:** PQRS `1:N` Actuación; Usuario `0:N` Actuación.
- **Propietario del dato:** agregado PQRS; ámbito Copropiedad.
- **Reglas principales:** es de solo adición; hereda el contexto de la PQRS; el
  actor histórico se conserva aunque su cuenta se desactive.

### 9.4. Respuesta de PQRS

- **Propósito:** representar un borrador o una respuesta emitida.
- **Responsabilidades:** conservar autor, contenido, estado, fecha de envío y
  Adjuntos.
- **Atributos principales:** identificador, PQRS, autor, contenido, estado,
  fecha de creación y fecha de envío opcional.
- **Relaciones:** pertenece a PQRS y Usuario autor; contiene Adjuntos.
- **Cardinalidades:** PQRS `1:N` Respuesta; Usuario `1:N` Respuesta; Respuesta
  `0:N` Adjunto.
- **Propietario del dato:** agregado PQRS; ámbito Copropiedad.
- **Reglas principales:** hereda el contexto de PQRS; una respuesta enviada
  conserva contenido, autor y fecha; su visibilidad depende de autorización;
  su emisión produce una Actuación.

### 9.5. Comentario Interno

- **Propósito:** registrar comunicación privada del equipo de gestión.
- **Responsabilidades:** conservar autor, contenido y fecha sin exponerlo como
  respuesta al radicador.
- **Atributos principales:** identificador, PQRS, autor, contenido y fecha.
- **Relaciones:** pertenece a PQRS y Usuario autor.
- **Cardinalidades:** PQRS `1:N`; Usuario `1:N`.
- **Propietario del dato:** agregado PQRS; ámbito Copropiedad.
- **Reglas principales:** hereda el contexto de PQRS; requiere autorización
  específica; su creación produce una Actuación; no se convierte implícitamente
  en Respuesta.

### 9.6. Adjunto

- **Propósito:** representar uniformemente un archivo asociado con una PQRS o
  Respuesta.
- **Responsabilidades:** conservar metadatos, ubicación, integridad y ámbito de
  acceso.
- **Atributos principales:** identificador, propietario documental, nombre
  original, ubicación, hash, tipo MIME, tamaño, cargador y fecha.
- **Relaciones:** pertenece exactamente a PQRS o Respuesta.
- **Cardinalidades:** PQRS `0:N` Adjunto; Respuesta `0:N` Adjunto.
- **Propietario del dato:** agregado PQRS; ámbito Copropiedad.
- **Reglas principales:** tiene un solo propietario; hereda su contexto; la
  descarga revalida autorización; el archivo físico y sus metadatos mantienen
  correspondencia; no se representa como JSON opaco.

## 10. Entidades de Gestión Documental

### 10.1. Documento

- **Propósito:** representar una unidad documental gobernada.
- **Responsabilidades:** conservar título, clase, ámbito, acceso, estado y
  conjunto de Versiones.
- **Atributos principales:** identificador, tipo de ámbito, Organización o
  Copropiedad según corresponda, clase, título, descripción, estado y nivel de
  acceso.
- **Relaciones:** contiene Versiones de Documento y puede aportar fuentes para
  IA.
- **Cardinalidades:** Documento `1:N` Versión; plataforma, Organización o
  Copropiedad `0:N` Documento.
- **Propietario del dato:** módulo Gestión Documental; ámbito declarado por el
  Documento.
- **Reglas principales:** tiene exactamente un ámbito válido; Reglamento y
  Manual de Convivencia son propios de Copropiedad; la Ley 675 se representa
  como fuente normativa de plataforma; solo versiones autorizadas pueden
  habilitarse para RAG.

### 10.2. Versión de Documento

- **Propósito:** representar una versión identificable del contenido.
- **Responsabilidades:** conservar archivo, hash, origen, vigencia, estado,
  responsables y procesamiento.
- **Atributos principales:** identificador, Documento, número de versión,
  archivo, hash, tipo MIME, tamaño, origen, vigencia, estado, cargador,
  aprobador opcional y estado de procesamiento.
- **Relaciones:** pertenece a Documento; contiene Fragmentos; puede ser citada
  por Fuentes Recuperadas.
- **Cardinalidades:** Documento `1:N` Versión; Versión `0:N` Fragmento y
  `0:N` Fuente Recuperada.
- **Propietario del dato:** agregado Documento; hereda su ámbito.
- **Reglas principales:** no cambia de ámbito; el hash identifica el contenido;
  una versión aprobada no se sobrescribe; archivo, extracción y fragmentos
  mantienen correspondencia; solo una versión autorizada puede usarse en RAG.

### 10.3. Fragmento Documental

- **Propósito:** representar una sección indexable de una Versión.
- **Responsabilidades:** conservar texto, orden, ubicación y metadatos
  necesarios para recuperación y cita.
- **Atributos principales:** identificador, Versión, secuencia, texto,
  ubicación, metadatos de ámbito e identificador del índice.
- **Relaciones:** pertenece a Versión y puede ser referenciado por Fuentes
  Recuperadas.
- **Cardinalidades:** Versión `1:N` Fragmento; Fragmento `0:N` Fuente.
- **Propietario del dato:** agregado Documento; hereda su ámbito.
- **Reglas principales:** no cambia de Versión ni ámbito; debe permitir ubicar
  la fuente original; su retiro del índice no elimina la Versión documental.

El catálogo documental, niveles de acceso, vigencia, aprobación, retención y
fuente oficial de la Ley 675 constituyen la política documental y son
**Pendiente de decisión del Product Owner**.

## 11. Entidades de Inteligencia Artificial

### 11.1. Solicitud de IA

- **Propósito:** representar una petición explícita de asistencia.
- **Responsabilidades:** conservar solicitante, contexto, propósito, consulta,
  estado y correlación.
- **Atributos principales:** identificador, Organización, Copropiedad, Usuario
  solicitante, propósito, consulta, estado, fecha y configuración aplicable.
- **Relaciones:** pertenece al contexto de Organización y Copropiedad; la
  realiza Usuario; produce cero o un Resultado IA.
- **Cardinalidades:** Usuario `1:N` Solicitud; Copropiedad `1:N`; Solicitud
  `1:0..1` Resultado.
- **Propietario del dato:** módulo Inteligencia Artificial; ámbito
  Copropiedad.
- **Reglas principales:** contexto, Usuario y Membresía deben ser coherentes;
  valida Capacidad y Permiso antes de recuperar fuentes; su contexto no cambia
  durante la ejecución; no constituye una decisión administrativa.

### 11.2. Resultado IA

- **Propósito:** representar la salida generada para una Solicitud.
- **Responsabilidades:** conservar contenido original, proveedor, modelo,
  configuración, fuentes, estado y métricas.
- **Atributos principales:** identificador, Solicitud, contenido, proveedor,
  modelo, versión de configuración, estado, fecha, métricas y error opcional.
- **Relaciones:** pertenece a Solicitud; contiene Fuentes Recuperadas; recibe
  Revisiones Humanas.
- **Cardinalidades:** Solicitud `1:0..1` Resultado; Resultado `1:N` Fuente;
  Resultado `1:0..N` Revisión.
- **Propietario del dato:** agregado Solicitud de IA; hereda su ámbito.
- **Reglas principales:** utiliza solo fuentes autorizadas del contexto;
  conserva citas; comienza pendiente de revisión cuando finaliza; no ejecuta
  cambios de negocio; el contenido original no se reemplaza por el contenido
  humano.

### 11.3. Fuente Recuperada

- **Propósito:** registrar un Fragmento utilizado para generar un Resultado.
- **Responsabilidades:** conservar orden, puntuación, cita y referencia
  documental reproducible.
- **Atributos principales:** identificador, Resultado, Versión, Fragmento,
  orden, puntuación y cita.
- **Relaciones:** pertenece a Resultado y referencia Versión y Fragmento.
- **Cardinalidades:** Resultado `1:N` Fuente; Fragmento `0:N` Fuente.
- **Propietario del dato:** agregado Solicitud de IA; ámbito del Resultado.
- **Reglas principales:** comparte el contexto autorizado; debe permitir
  reconstruir la cita; no referencia versiones no autorizadas; conserva la
  evidencia usada aunque el índice cambie posteriormente.

### 11.4. Revisión Humana

- **Propósito:** registrar el juicio explícito de un Administrador sobre un
  Resultado IA.
- **Responsabilidades:** conservar aprobación, modificación o rechazo,
  responsable, fecha y contenido revisado cuando corresponda.
- **Atributos principales:** identificador, Resultado, Usuario revisor,
  decisión, contenido revisado opcional, observación opcional y fecha.
- **Relaciones:** pertenece a Resultado y Usuario revisor.
- **Cardinalidades:** Resultado `1:0..N` Revisión; Usuario `1:N` Revisión.
- **Propietario del dato:** agregado Solicitud de IA; ámbito Copropiedad.
- **Reglas principales:** el revisor necesita Membresía y Permiso vigentes; la
  revisión no altera fuentes ni contenido generado; una modificación conserva
  ambos contenidos; toda revisión queda auditada; ninguna salida produce
  efectos de negocio sin acción humana válida.

Las acciones permitidas a la IA y los efectos funcionales de aprobar,
modificar o rechazar son **Pendiente de decisión del Product Owner**.

## 12. Inventario de las 26 entidades conceptuales

| N.º | Entidad | Ámbito principal | Agregado o módulo propietario |
| ---: | --- | --- | --- |
| 1 | Organización | Organización | Organización y Suscripción |
| 2 | Copropiedad | Copropiedad | Copropiedades |
| 3 | Usuario | Plataforma | Identidad |
| 4 | Membresía | Organización o Copropiedad | Acceso |
| 5 | Rol | Plataforma | Autorización |
| 6 | Permiso | Plataforma | Autorización |
| 7 | Suscripción | Organización | Suscripción |
| 8 | Oferta Comercial | Plataforma | Organización y Suscripción |
| 9 | Capacidad | Plataforma | Organización y Suscripción |
| 10 | Habilitación de Capacidad | Organización | Organización y Suscripción |
| 11 | Persona | Organización | Comunidad |
| 12 | Unidad Privada | Copropiedad | Copropiedad |
| 13 | Vínculo con Unidad | Copropiedad | Comunidad |
| 14 | PQRS | Copropiedad | PQRS |
| 15 | Tipo de PQRS | Copropiedad | PQRS |
| 16 | Actuación de PQRS | Copropiedad | PQRS |
| 17 | Respuesta de PQRS | Copropiedad | PQRS |
| 18 | Comentario Interno | Copropiedad | PQRS |
| 19 | Adjunto | Copropiedad | PQRS |
| 20 | Documento | Variable | Gestión Documental |
| 21 | Versión de Documento | Heredado del Documento | Gestión Documental |
| 22 | Fragmento Documental | Heredado del Documento | Gestión Documental |
| 23 | Solicitud de IA | Copropiedad | Inteligencia Artificial |
| 24 | Resultado IA | Copropiedad | Inteligencia Artificial |
| 25 | Fuente Recuperada | Copropiedad | Inteligencia Artificial |
| 26 | Revisión Humana | Copropiedad | Inteligencia Artificial |

Las relaciones Membresía-Rol, Rol-Permiso y Oferta-Capacidad forman parte del
modelo conceptual, pero no se cuentan como entidades del dominio. Su eventual
representación física se decidirá durante el diseño de persistencia.

## 13. Relaciones y cardinalidades consolidadas

```text
Organización 1 ─── N Copropiedad
Organización 1 ─── N Suscripción
Organización 1 ─── N Persona
Organización 1 ─── N HabilitaciónCapacidad

Usuario 1 ─── N Membresía
Membresía N ─── M Rol
Rol N ─── M Permiso

OfertaComercial N ─── M Capacidad
Suscripción N ─── 1 OfertaComercial
HabilitaciónCapacidad N ─── 1 Capacidad

Copropiedad 1 ─── N UnidadPrivada
Persona N ─── M UnidadPrivada mediante VínculoConUnidad

Copropiedad 1 ─── N PQRS
PQRS N ─── 1 TipoPQRS
PQRS N ─── 1 Usuario como radicador
PQRS N ─── 0..1 Usuario como responsable
PQRS 1 ─── N Actuación
PQRS 1 ─── N Respuesta
PQRS 1 ─── N ComentarioInterno
PQRS 1 ─── N Adjunto
Respuesta 1 ─── N Adjunto

Documento 1 ─── N VersiónDocumento
VersiónDocumento 1 ─── N FragmentoDocumental

SolicitudIA 1 ─── 0..1 ResultadoIA
ResultadoIA 1 ─── N FuenteRecuperada
ResultadoIA 1 ─── 0..N RevisiónHumana
```

## 14. Reglas transversales

1. Toda entidad contextual conserva una Organización válida.
2. Toda entidad de Copropiedad corresponde a su Organización propietaria.
3. Una referencia recibida del cliente no determina por sí sola el tenant.
4. Usuario global no equivale a acceso global.
5. Persona, Usuario, Membresía, Rol, Permiso, Capacidad y Suscripción no se
   sustituyen entre sí.
6. Las operaciones entre agregados utilizan casos de uso y referencias, no
   modificaciones directas del grafo completo.
7. Los datos históricos se conservan al desactivar Usuarios, catálogos,
   Copropiedades o capacidades.
8. Todo archivo conserva metadatos, ámbito e integridad verificable.
9. Toda fuente de IA debe ser autorizada dentro del contexto.
10. Todo Resultado IA conserva contenido original, fuentes y revisiones.
11. Ningún Resultado IA produce efectos de negocio sin Revisión Humana válida.

## 15. Implementación incremental

La clasificación expresa orden arquitectónico, no compromisos de una única
entrega. Las entidades de soporte de PQRS evolucionan junto con su raíz cuando
el flujo correspondiente sea adaptado.

### 15.1. Núcleo inicial

- Organización
- Copropiedad
- Usuario
- Membresía
- Unidad Privada
- Persona
- Vínculo con Unidad
- PQRS contextualizada

Este núcleo introduce contexto, pertenencia y aislamiento sin activar todavía
todas las capacidades comerciales, documentales o de IA. Rol y Permiso se
incorporan como soporte de autorización en la medida necesaria para reemplazar
las cadenas de rol heredadas. Tipo de PQRS, Actuación, Respuesta, Comentario
Interno y Adjunto evolucionan con la contextualización de PQRS.

### 15.2. Evolución comercial

- Suscripción
- Oferta Comercial
- Capacidad
- Habilitación de Capacidad

Esta etapa se apoya en Organización y en la separación previa entre permiso y
disponibilidad comercial.

### 15.3. Evolución documental

- Documento
- Versión de Documento
- Fragmento Documental

Esta etapa requiere contexto multi-tenant y autorización estables. Debe
completarse antes de habilitar RAG.

### 15.4. Evolución IA

- Solicitud de IA
- Resultado IA
- Fuente Recuperada
- Revisión Humana

Esta etapa depende de Gestión Documental, capacidades, autorización contextual,
auditoría y procesamiento asíncrono.

## 16. Decisiones pendientes

Las siguientes materias no se convierten en reglas aprobadas en este documento:

1. Participación de un Usuario en varias Organizaciones — **Pendiente de
   decisión del Product Owner**.
2. Transferencia de una Copropiedad entre Organizaciones — **Pendiente de
   decisión del Product Owner**.
3. Roles personalizados — **Pendiente de decisión del Product Owner**.
4. Catálogo definitivo de Roles y Permisos — **Pendiente de decisión del
   Product Owner**.
5. Capacidades incluidas en Básico y Pro, límites y cuotas — **Pendiente de
   decisión del Product Owner**.
6. Reglas definitivas de Propietarios y Residentes — **Pendiente de decisión
   del Product Owner**.
7. Política documental, incluidos acceso, vigencia, aprobación y retención —
   **Pendiente de decisión del Product Owner**.
8. Acciones permitidas a la IA y efectos de la Revisión Humana — **Pendiente de
   decisión del Product Owner**.
9. Ciclo de vida definitivo y eliminación de PQRS — **Pendiente de decisión del
   Product Owner**.
10. Retención de Personas, documentos, solicitudes de IA y auditoría —
    **Pendiente de decisión del Product Owner**.

## 17. Referencias documentales

- [Arquitectura objetivo](../05-arquitectura/arquitectura-objetivo.md).
- [ADR-003 — Multi-tenancy con esquema compartido](../10-adr/ADR-003-multitenancy-esquema-compartido.md).
- [ADR-004 — Organización y Copropiedad](../10-adr/ADR-004-organizacion-tenant-copropiedad-ambito.md).
- [ADR-005 — Usuario global y membresías](../10-adr/ADR-005-usuario-global-membresias.md).
- [ADR-006 — Roles, permisos y capacidades](../10-adr/ADR-006-roles-permisos-capacidades.md).
- [ADR-007 — Planes mediante capacidades](../10-adr/ADR-007-planes-mediante-capacidades.md).
- [ADR-008 — Casos de uso compartidos](../10-adr/ADR-008-casos-de-uso-compartidos.md).
- [ADR-009 — Gestión Documental antes de IA](../10-adr/ADR-009-gestion-documental-antes-de-ia.md).
- [ADR-010 — IA asistiva con RAG](../10-adr/ADR-010-ia-rag-revision-humana.md).
- [Dominio del Negocio](../01-producto/dominio-del-negocio.md).
- [Modelo del Dominio](../01-producto/modelo-del-dominio.md).
- [Gestión de PQR](../02-funcional/gestion-pqr.md).
- [Roles y permisos actuales](../02-funcional/roles-y-permisos.md).
