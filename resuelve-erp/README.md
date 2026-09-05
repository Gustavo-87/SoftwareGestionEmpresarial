# Resuelve PQRS Copropiedades

Aplicación web para administrar peticiones, quejas, reclamos y sugerencias en conjuntos residenciales. Desarrollada con Laravel, MySQL y Vite.

## Funcionalidades

- Radicación y seguimiento de PQRS.
- Roles de administrador, gestor, apoyo, auditor y residente.
- Asignación de responsables, estados, vencimientos y recordatorios.
- Respuestas, adjuntos, comentarios internos, etiquetas y auditoría.
- Panel con estadísticas, filtros e indicadores de cumplimiento.
- Informes profesionales en PDF y Excel.
- Administración de usuarios y configuración de la copropiedad.
- Notificaciones y recuperación de contraseña.
- Diseño adaptable para escritorio y dispositivos móviles.

## Requisitos

- PHP 8.3 o superior.
- Composer.
- Node.js y npm.
- MySQL 8.4 o una base compatible.
- Docker Desktop, opcionalmente mediante Laravel Sail.

## Instalación local

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Con Laravel Sail:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

La aplicación estará disponible en `http://localhost` cuando se utilice la configuración Docker incluida.

## Pruebas

```bash
php artisan test
```

## Seguridad

- No publiques el archivo `.env`.
- Cambia las credenciales de demostración antes de exponer la aplicación.
- Usa `APP_ENV=production` y `APP_DEBUG=false` en producción.
- Configura almacenamiento persistente para logos y adjuntos.
- Realiza copias de seguridad periódicas de la base de datos y archivos.

## Licencia

Proyecto académico y de demostración. Antes de utilizarlo con información real, revisa las obligaciones de protección de datos aplicables.
