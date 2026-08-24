# Entorno de Desarrollo - Software de Gestión Empresarial

Este repositorio contiene la configuración base del entorno de desarrollo usando **Docker**. 
Con solo unos comandos se tendrá un servidor web (PHP 8.2), una base de datos (MariaDB) y phpMyAdmin funcionando en la máquina local, sin necesidad de instalar XAMPP ni WAMP.

---

## Requisitos previos

1. **Docker Desktop** (con el backend de WSL2 activado si usa Windows).  
   [Descargar Docker Desktop](https://www.docker.com/products/docker-desktop/)
2. **Git** (para clonar el repositorio).
3. **Visual Studio Code** (recomendado) con la extensión "Remote - Containers" o "Dev Containers" (opcional).

---

## Paso a paso para levantar el entorno

### 1. Clonar el repositorio
Abrir la terminal (WSL2 / PowerShell / Bash) y ejecuta:
```bash
git clone https://github.com/jamescanos/SoftwareGestionEmpresarial.git
cd entorno-sge
```

### 2. Estructura Inicial
Dentro de la carpeta, crea una carpeta llamada src
```bash
mkdir src
```

### 3. Levantar los contenedores
Ejecutar el siguiente comando en la raíz del proyecto (donde está el docker-compose.yml):
```bash
docker-compose up -d
```

El flag -d significa "detached" (corre en segundo plano). Si se desean ver los logs en vivo, se quita el -d.

### 4. Verificar que todo funciona
PHP/Apache: Abrir el navegador y acceder a http://localhost:8080. 
```
Se debe ver página de información de PHP (phpinfo()).
```

phpMyAdmin: Acceder a http://localhost:8081. 
```
Usuario: root, Contraseña: root_password.
```

Base de datos: conectarse desde phpMyAdmin o desde su código PHP usando:

```
   Host: db (el nombre del servicio en el compose)
   Usuario: root (o dev_user)
   Contraseña: root_password (o dev_password)
   Base de datos: seminario_db
```
---

# Clase 2 - Framework Laravel

Durante la Clase 2 se realizó la instalación y configuración inicial de **Laravel** como framework para el desarrollo del sistema ERP.

El proyecto propuesto corresponde a un **ERP para la Administración de Propiedad Horizontal**, orientado a organizaciones que administran una o varias copropiedades.

Inicialmente, el ERP contempla tres módulos principales:

- Gestión de PQRS.
- Gestión de mantenimiento.
- Gestión documental.

La estructura se plantea de forma modular para permitir la incorporación de nuevos módulos en futuras etapas del proyecto.

## Instalación de Laravel

### Requisitos

Para el desarrollo se utilizaron las siguientes herramientas:

- PHP 8.5
- Composer 2
- Laravel 13
- Git
- Visual Studio Code

### Crear el proyecto Laravel

El proyecto fue creado mediante Composer:

```bash
composer create-project laravel/laravel sge
```

Ingresar al proyecto:

```bash
cd sge
```

### Verificar la estructura

Para comprobar la estructura generada por Laravel:

```bash
ls -la
```

Entre los principales directorios creados se encuentran:

```text
app/
bootstrap/
config/
database/
public/
resources/
routes/
storage/
```

### Ejecutar Laravel

El servidor de desarrollo de Laravel se inicia mediante Artisan:

```bash
php artisan serve
```

Por defecto, la aplicación queda disponible en:

```text
http://127.0.0.1:8000
```

Para detener el servidor:

```text
Ctrl + C
```

## Análisis del ERP

El análisis inicial del proyecto se encuentra en:

```text
Clase2/ANALISIS_EMPRESA.md
```

La estructura general propuesta es:

```text
Organización
    │
    └── Copropiedad
            │
            ├── Gestión de PQRS
            ├── Gestión de Mantenimiento
            └── Gestión Documental
```

## Modelo Entidad-Relación

El primer Modelo Entidad-Relación (MER) del ERP se encuentra en:

```text
Clase2/evidencias/MER_ERP.png
```

## Evidencias

Las evidencias correspondientes a la Clase 2 se encuentran en:

## Evidencias Clase 2

### Validación de PHP y Composer

![PHP y Composer](Clase2/evidencias/01_php_composer.png)

### Estructura del proyecto Laravel

![Estructura Laravel](Clase2/evidencias/02_estructura_laravel.png)

### Laravel funcionando

![Página Laravel](Clase2/evidencias/03_paginalaravel.png)

### Modelo Entidad-Relación del ERP

![MER ERP](Clase2/evidencias/MER_ERP.png)

Incluyen:

- Validación de PHP y Composer.
- Estructura del proyecto Laravel.
- Página inicial de Laravel funcionando.
- Primer Modelo Entidad-Relación del ERP.
