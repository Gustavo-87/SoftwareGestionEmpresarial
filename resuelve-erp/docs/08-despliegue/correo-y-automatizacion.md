# Correo y automatización

## Estado verificado

El remitente preparado es `gestionpqrs7@gmail.com`, con nombre «Resuelve ERP».
No hay una contraseña de aplicación disponible. La configuración local y
`.env.example` mantienen `MAIL_MAILER=log`: Gmail no está habilitado ni se
acredita recepción en su bandeja real. No se versionan credenciales.

El 5 de noviembre de 2026 se verificó el flujo completo con SQLite aislada,
cola síncrona y **SMTP real hacia Mailpit local**: radicación, cambio de estado
y recordatorio produjeron tres correos y tres avisos de campana. Remitente y
destinatario sintético fueron `gestionpqrs7@gmail.com`; Mailpit captura esos
mensajes localmente y no los reenvía a Gmail. El segundo intento del
recordatorio produjo cero avisos nuevos.

| Comprobación | Resultado observado |
| --- | --- |
| Correo «Nueva solicitud radicada» | Capturado por Mailpit. |
| Correo «Estado de solicitud actualizado» | Capturado por Mailpit. |
| Correo «Solicitud próxima a vencer» | Capturado por Mailpit. |
| Notificaciones persistidas de campana | 3. |
| Recordatorio, primera ejecución / repetición el mismo día | 1 / 0. |
| Recepción externa en Gmail | Pendiente de autenticar la cuenta. |

## Repetir la demostración local

Desde `resuelve-erp/`, con Docker y las dependencias instaladas:

```bash
docker compose --profile correo-pruebas up -d --wait mailpit
php scripts/probar-notificaciones-mailpit.php
```

El script usa PHP del equipo, SQLite **en memoria**, cola síncrona y SMTP en
`127.0.0.1:1025`; no usa los datos de desarrollo ni procesa su cola. Comprueba
ese aislamiento antes de migrar. El buzón se consulta en
[Mailpit local](http://127.0.0.1:8025); sus puertos se publican solo en la
interfaz local. La imagen de Docker está fijada por digest en `compose.yaml`.

Para enviar únicamente un mensaje de diagnóstico desde Sail:

```bash
./vendor/bin/sail artisan resuelve:verificar-correo --mailer=mailpit --enviar
```

Dentro de Sail el servidor es `mailpit:1025`. Desde PHP del equipo se usa
`MAILPIT_HOST=127.0.0.1`. El comando rechaza Mailpit en producción. La
demostración completa usa los casos de uso, eventos, listeners, notificación y
job existentes; no los sustituye por simulaciones de correo.

## Habilitar Gmail cuando exista una credencial

Conservar en el `.env` privado los valores preparados:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=gestionpqrs7@gmail.com
MAIL_FROM_ADDRESS=gestionpqrs7@gmail.com
MAIL_FROM_NAME="Resuelve ERP"
```

Asignar `MAIL_PASSWORD` a una contraseña de aplicación válida de esa cuenta,
fuera del repositorio y del chat. Google exige verificación en dos pasos para
usar [contraseñas de aplicación](https://support.google.com/accounts/answer/185833).
El transporte SMTP negocia TLS; el tiempo de conexión está limitado a 15
segundos. `MAIL_MAILER=log` sirve para desarrollo, no demuestra entrega.

Después de cambiar la configuración:

```bash
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan resuelve:verificar-correo
./vendor/bin/sail artisan resuelve:verificar-correo --enviar
```

Sin `--enviar` solo se diagnostica. La aceptación del proveedor debe
complementarse con comprobar la recepción del mensaje en el buzón destino.
El comando no imprime credenciales ni detalles privados de errores SMTP.

## Procesos de operación

Los avisos se guardan en campana antes de encolar el correo. En despliegue se
requiere un proceso supervisado `php artisan queue:work`, reiniciado con
`php artisan queue:restart` tras publicar cambios, y el planificador de Laravel
ejecutado cada minuto con `php artisan schedule:run`. Antes de iniciar un
trabajador sobre una base existente, revisar los mensajes pendientes y la
configuración efectiva del proveedor.

`routes/console.php` programa:

- `pqrs:send-reminders`: diariamente a las 08:00, con reserva diaria por PQRS.
- `pqrs:reconcile-response-notifications`: cada cinco minutos y sin
  superposición, para recuperar avisos de respuestas pendientes.

`php artisan schedule:list` permite verificar ambas definiciones. La prueba
local invocó el recordatorio directamente; no acredita que un servidor
desplegado tenga activos el cron o el trabajador de cola.

## Evidencia

- `app/Console/Commands/VerificarCorreo.php`, `config/mail.php`, `.env.example`
  y `compose.yaml`.
- `scripts/probar-notificaciones-mailpit.php` (demostración optativa local).
- `app/Events/PqrRadicada.php`, `app/Events/PqrEstadoActualizado.php` y sus
  listeners `NotificarPqrRadicada` / `NotificarPqrEstadoActualizado`.
- `app/Application/Notificaciones/EmitirNotificacionPqrs.php`,
  `app/Notifications/PqrEventNotification.php`,
  `app/Jobs/EnviarCorreoNotificacionPqrs.php`, `app/Mail/PqrEventMail.php`.
- `tests/Feature/VerificarCorreoTest.php`, `EventosNotificacionTest.php`,
  `EmisionNotificacionesPqrsTest.php` y `NotificacionesContextualesTest.php`.

## Control documental

- **Versión:** v1.0
- **Fecha:** 6 de noviembre de 2026 (America/Bogota)
