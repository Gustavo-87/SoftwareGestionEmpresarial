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
