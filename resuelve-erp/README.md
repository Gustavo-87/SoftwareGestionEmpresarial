# Resuelve ERP

Sistema de gestión empresarial orientado a la administración de propiedad horizontal. Su propósito es centralizar la gestión de PQRS, documentos y actividades de mantenimiento de las copropiedades.

## Datos generales de la empresa

- **Nombre:** Resuelve ERP
- **Giro del negocio:** Administración de copropiedades residenciales.
- **Tamaño:** Pequeña empresa, definida como caso de estudio académico.

## Alcance actual

El proyecto integra PQRS, gestión documental y mantenimiento, con aislamiento por organización y copropiedad y acceso según roles y permisos. Incluye API REST v1 con Laravel Sanctum, informes PDF y Excel e importación de Unidades Privadas desde XLSX.

## Módulos y funcionalidades

### Gestión de PQRS

- Radicación y consulta de peticiones, quejas, reclamos y solicitudes.
- Asignación de responsables y seguimiento de estados.
- Control de vencimientos y recordatorios.
- Registro de respuestas, archivos adjuntos y comentarios internos.
- Historial de actuaciones y notificaciones.
- Consulta de indicadores y generación de informes.

### Gestión documental

- Registro y clasificación de documentos de la copropiedad.
- Carga y seguimiento de versiones.
- Revisión, aprobación y rechazo de versiones.
- Consulta y descarga según los permisos del usuario.
- Archivo de documentos y registro de actuaciones.

### Administración del sistema

- Gestión de organizaciones, copropiedades y usuarios.
- Control de acceso mediante roles, permisos y membresías.
- Configuración de la información institucional.
- Inicio de sesión y recuperación de contraseña.

### Gestión de mantenimiento

- Registro y consulta de solicitudes, asignación de responsables y programación de fechas.
- Gestión de estados, resultados públicos e internos y finalización con evidencia documental aprobada.
- Generación de mantenimiento desde una PQRS y consulta de los vínculos desde ambos módulos.
- Evidencia fijada a una versión documental exacta y expediente integrado en PDF.

El módulo fue completado en el Sprint 14 y su integración con PQRS y Documentos se cerró técnicamente en el Sprint 19. Ver [Integración de módulos](docs/avance2/integracion-de-modulos.md).

### API REST v1

Autenticación con Laravel Sanctum, selección de rol activo, cierre de sesión, listado y detalle contextual de PQRS y radicación. Son cinco endpoints bajo `/api/v1/`; los endpoints de PQRS revalidan usuario, membresía y permisos en cada petición. Los adjuntos en la radicación por API siguen pendientes. Ver [Contrato y alcance de la API](docs/07-api/estado-actual-api.md).

## Documentación

- [Análisis del negocio y entrega académica](docs/integracion-academica.md)
- [Documentación funcional y técnica](docs/README.md)
- [Integración continua y resultados verificados](docs/08-despliegue/integracion-continua.md)
- [Auditoría y bitácora de seguridad](docs/03-tecnica/auditoria-y-trazabilidad.md)
- [Colección de pruebas API para Postman](docs/07-api/coleccion-postman.json)
- [Correo, Mailpit y automatización](docs/08-despliegue/correo-y-automatizacion.md)
- [Matriz de requisitos 1–14 y límites de cumplimiento](docs/04-desarrollo-agil/sprint-26-cierre-guia-hasta14.md)
- [Evidencias de la Clase 4](../Clase4/docs.md)

## Requisitos

- PHP 8.3 o superior y Composer.
- Git.
- Docker Desktop en ejecución.

## Instalación con Laravel Sail

Desde la carpeta `resuelve-erp`:

### 1. Preparar la configuración

```bash
composer install
cp .env.example .env
```

Si ya existe `.env`, conserva su contenido y ajusta únicamente los valores necesarios.

### 2. Configurar el entorno

Establecer en `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=resuelve_erp
DB_USERNAME=sail
DB_PASSWORD=clave_local
APP_PORT=8083
APP_URL=http://localhost:8083
VITE_PORT=5175
FORWARD_DB_PORT=3308
```

Asignar también a `RESUELVE_DEMO_PASSWORD` una contraseña de demostración de al menos 12 caracteres.

### 3. Iniciar la aplicación

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

### 4. Acceder

Abrir [Resuelve ERP](http://localhost:8083) en el navegador.

## Pruebas

Ejecutar las pruebas automatizadas:

```bash
./vendor/bin/sail artisan test
```

La ejecución verificada del CI del PR #3 y sus advertencias están registradas en [Integración continua](docs/08-despliegue/integracion-continua.md). Los resultados locales de cada sprint se conservan en sus respectivas fichas.

## Correo de demostración

Remitente preparado: `gestionpqrs7@gmail.com`. Se mantiene `MAIL_MAILER=log`
hasta disponer de credenciales SMTP. El flujo de eventos, campana y correo se
verificó mediante Mailpit local; la recepción externa en Gmail queda pendiente.
La [guía de correo](docs/08-despliegue/correo-y-automatizacion.md) permite
repetir la prueba sobre una base aislada y habilitar después el proveedor.

## Seguridad

- No se incluye el archivo `.env` ni credenciales en el repositorio.
- Se utilizan datos sinteticos para las demostraciones académicas.
- se mantiene el acceso controlado mediante roles y permisos.

