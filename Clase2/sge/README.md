# ERP para Administración de Propiedad Horizontal

Proyecto académico desarrollado para la asignatura **Software de Gestión Empresarial**.

El sistema tiene como objetivo apoyar la gestión administrativa de organizaciones encargadas de administrar una o varias copropiedades, centralizando diferentes procesos mediante una arquitectura modular.

## Módulos iniciales

El ERP contempla inicialmente tres módulos:

- **Gestión de PQRS:** registro, clasificación, seguimiento, respuesta y cierre de peticiones, quejas, reclamos y sugerencias.
- **Gestión de Mantenimiento:** registro y seguimiento de solicitudes y órdenes de mantenimiento.
- **Gestión Documental:** registro, clasificación, almacenamiento y consulta de documentos asociados a las copropiedades.

La estructura del sistema se plantea de forma flexible para permitir la incorporación de nuevos módulos en futuras etapas del proyecto.

---

## Instalación de Laravel

### Requisitos

Para el desarrollo del proyecto se utilizaron las siguientes herramientas:

- PHP 8.5
- Composer 2
- Laravel 13
- Git
- Visual Studio Code

### Crear el proyecto

El proyecto Laravel fue creado mediante Composer:

```bash
composer create-project laravel/laravel sge
```

Ingresar al directorio del proyecto:

```bash
cd sge
```

### Verificar la estructura

Para comprobar que Laravel fue instalado correctamente:

```bash
ls -la
```

La instalación genera, entre otros, los siguientes directorios:

```text
app/
bootstrap/
config/
database/
public/
resources/
routes/
storage/
vendor/
```

### Ejecutar Laravel

Laravel incluye un servidor de desarrollo que puede iniciarse mediante Artisan:

```bash
php artisan serve
```

El servidor queda disponible por defecto en:

```text
http://127.0.0.1:8000
```

Al acceder desde el navegador se debe visualizar la pantalla de bienvenida de Laravel.

Para detener el servidor se utiliza:

```text
Ctrl + C
```

---

## Clase 2

Durante la Clase 2 se realizó:

- Validación de PHP y Composer.
- Instalación de Laravel.
- Validación de la estructura del proyecto.
- Primera ejecución del servidor de desarrollo de Laravel.
- Definición inicial del ERP.
- Identificación de los módulos principales.
- Análisis inicial de entidades y relaciones.
- Elaboración del primer Modelo Entidad-Relación (MER).

La documentación y evidencias correspondientes se encuentran en:

```text
Clase2/
├── ANALISIS_EMPRESA.md
└── evidencias/
```

## Modelo inicial del ERP

La estructura general planteada para el sistema es:

```text
Organización
    │
    └── Copropiedades
            │
            ├── Gestión de PQRS
            ├── Gestión de Mantenimiento
            └── Gestión Documental
```

Cada copropiedad podrá tener diferentes módulos habilitados, permitiendo ampliar las funcionalidades del ERP en futuras etapas.
