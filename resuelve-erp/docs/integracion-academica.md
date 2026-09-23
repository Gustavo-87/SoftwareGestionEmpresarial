# Integración académica de Resuelve ERP

Fecha: 5 de septiembre de 2026.

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
pendientes identificados. El módulo de mantenimiento se encuentra en
construcción.