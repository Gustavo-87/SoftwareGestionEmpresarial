# Clase 4 — Práctica de autenticación

La práctica de Laravel Breeze se encuentra en `sge/`. Se trasladó desde
`Clase2/sge` conservando el registro, inicio y cierre de sesión, recuperación
de contraseña, perfil, panel y pruebas asociadas. Clase 2 conserva su versión
registrada en Git antes de estos cambios.

El traslado local conserva también las dependencias y la configuración del
entorno, excluidas de Git por el proyecto. Si había un contenedor iniciado
desde la ubicación anterior, debe revisarse su montaje antes de volver a usarlo.

Las dos pruebas ExampleTest.php se ajustaron al formato PHPUnit, coherente
con las dependencias del proyecto. Verificación: 25 pruebas aprobadas y
61 aserciones, usando SQLite en memoria sin modificar la base de datos local.

Para repetir la verificación desde `Clase4/sge`:

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php vendor/bin/phpunit --no-logging --do-not-cache-result
```

El proyecto académico principal Resuelve ERP permanece en `../resuelve-erp/`.
Esta carpeta conserva la práctica de Breeze como evidencia de Clase 4.
