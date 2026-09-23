# Resuelve ERP

Sistema de gestión empresarial orientado a la administración de propiedad horizontal. Su propósito es centralizar la gestión de PQRS, documentos y actividades de mantenimiento de las copropiedades.

## Datos generales de la empresa

- **Nombre:** Resuelve ERP
- **Giro del negocio:** Administración de copropiedades residenciales.
- **Tamaño:** Pequeña empresa, definida como caso de estudio académico.

## Alcance actual

El proyecto cuenta con funcionalidades de gestión de PQRS y gestión documental, apoyadas en una estructura de organizaciones, copropiedades y usuarios. El módulo de mantenimiento está previsto para una etapa posterior.


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

### Gestión de mantenimiento — En construcción

Módulo en construcción, orientado al registro y seguimiento de las actividades de mantenimiento de las zonas comunes, equipos e instalaciones de las copropiedades.


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

## Seguridad

- No incluir el archivo `.env` ni credenciales en el repositorio.
- Utilizar datos ficticios para las demostraciones académicas.
- Mantener el acceso controlado mediante roles y permisos.

