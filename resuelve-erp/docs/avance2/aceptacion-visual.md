# Aceptación visual — Cierre del Sprint 19

Pasos exactos para verificar en navegador el flujo integrado sobre la base
aislada de demostración. **No se sustituye ni modifica la base habitual**: la
aplicación se abre contra `resuelve_sprint19_demo` mediante variables de entorno
en un proceso separado.

## 1. Abrir la aplicación con la base aislada

```bash
cd resuelve-erp

# Base aislada (solo si aún no existe): crear con root del contenedor MySQL de
# Sail y autorizar al usuario de la aplicación.
docker exec resuelve-erp-mysql-1 sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e \
  "CREATE DATABASE IF NOT EXISTS resuelve_sprint19_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   GRANT ALL PRIVILEGES ON resuelve_sprint19_demo.* TO '\''sail'\''@'\''%'\'';"'

# Migrar y cargar datos (catálogo base + usuarios demo + fixture de integración)
DB_HOST=127.0.0.1 DB_PORT=3308 DB_DATABASE=resuelve_sprint19_demo \
  RESUELVE_DEMO_PASSWORD=<tu-password-demo> php artisan migrate:fresh --seed --force
DB_HOST=127.0.0.1 DB_PORT=3308 DB_DATABASE=resuelve_sprint19_demo \
  RESUELVE_DEMO_FIXTURE=1 php artisan db:seed --class=DemoIntegracionFixtureSeeder --force

# Sincronizar el RBAC contextual a Spatie (idempotente; requerido para los permisos)
DB_HOST=127.0.0.1 DB_PORT=3308 DB_DATABASE=resuelve_sprint19_demo \
  php artisan resuelve:migrar-rbac-spatie

# Abrir la aplicación SOLO contra la base aislada (la base habitual no se toca)
DB_HOST=127.0.0.1 DB_PORT=3308 DB_DATABASE=resuelve_sprint19_demo \
  php artisan serve --host=127.0.0.1 --port=8199
```

Abrir `http://127.0.0.1:8199/iniciar-sesion`.

## 2. Usuarios y roles de demostración

Las contraseñas **no se publican en documentación versionada**: son el valor de
`RESUELVE_DEMO_PASSWORD` definido localmente (por ejemplo en `resuelve-erp/.env`,
que no se versiona) al ejecutar el seed.

| Usuario (correo) | Nombre | Rol contextual | Uso en la demostración |
| --- | --- | --- | --- |
| `gestionpqrs7@gmail.com` | Gestión PQRS | Administrador | Gestión del flujo completo y exportación del PDF |
| `andrea.ruiz@example.com` | Andrea Ruiz | Residente | Radicadora de la PQRS del flujo; consulta sin información interna |
| `residentepqrs@gmail.com` | Laura Gómez | Residente | Consulta de PQRS propias |
| `carlos.mejia@example.com` | Carlos Mejía | Residente | Consulta de PQRS propias |

Roles disponibles en el catálogo: `admin` (Administrador), `gestor`, `apoyo`,
`auditor` y `residente`, con 25 permisos contextuales.

## 3. Flujo completo (rol Administrador)

Datos de partida del fixture: **24 PQRS, 12 mantenimientos, 12 documentos** con
6 evidencias. La PQRS del recorrido es la #13 «Fuga de agua en sótano 2».

1. **Panel** (`/panel`): comprobar los microindicadores de integración en las
   tarjetas: «N con mantenimiento vinculado» (PQRS) y «N vinculados · N abiertos
   · N con evidencia» (Mantenimiento).
2. **PQRS → generar mantenimiento** (`/pqrs/13`): bloque «Mantenimientos
   vinculados» y botón «Generar mantenimiento». Registrar título, descripción y
   fecha. Pulsar dos veces «Generar»: se crea **un solo** mantenimiento (clave de
   idempotencia por solicitud).
3. **Asignar responsable y fecha** en «Gestionar solicitud» y pasar el estado a
   «En proceso».
4. **Registrar resultado**: «Resultado público» (comunicable) y «Resultado
   interno» (reservado), visibles como campos separados.
5. **Vincular evidencia aprobada**: elegir una versión aprobada (por ejemplo
   «Informe de inspección de ascensores — versión 1» y «Informe financiero
   mensual — versión 1», este último de nivel administrativo) y vincularla. La
   evidencia fija la versión exacta; el documento conserva su ciclo documental.
6. **Finalizar**: la confirmación advierte «La PQRS no se cerrará
   automáticamente». Tras finalizar, el estado queda «Finalizado» con fecha y el
   resultado pasa a ser inmutable.
7. **Responder y cerrar la PQRS**: desde el expediente, «Enviar respuesta» con el
   resultado; luego la acción rápida del listado (`/pqrs`, «Acción rápida») con
   estado «Cerrada». La PQRS queda cerrada por decisión explícita del gestor.
8. **Expediente PDF**: botón «Expediente PDF» en el encabezado del expediente
   (requiere `informes.exportar`). El PDF integra PQRS, mantenimientos, evidencia
   visible y respuestas oficiales.

## 4. Verificación como residente

Ingresar como `andrea.ruiz@example.com` y abrir `/pqrs/13`:

- el mantenimiento vinculado muestra **estado, fechas y resultado público**
  («Fuga reparada…»);
- el **resultado interno no aparece** en ninguna parte de su expediente;
- la evidencia restringida se informa como «Evidencia documental disponible solo
  para personal autorizado» **sin título, versión, archivo ni fechas**;
- el título interno del mantenimiento no se expone en el expediente.

## 5. Verificación ya realizada (automatizada)

| Verificación | Resultado |
| --- | --- |
| Flujo completo del punto 3 en navegador real contra `resuelve_sprint19_demo` | Verificado: mantenimiento `/mantenimiento/13` generado, en proceso, con resultado, 2 evidencias y finalizado; PQR-13 cerrada con respuesta oficial enviada; actuaciones `mantenimiento_generado`, `mantenimiento_finalizado`, `sent_reply`, `quick_action` |
| No cierre automático de la PQRS | Verificado en navegador: tras finalizar el mantenimiento, la PQRS permaneció «Radicada» |
| PDF del expediente | Verificado: HTTP 200, `application/pdf`, cabecera `%PDF-` |
| Visibilidad del residente (punto 4) | Verificado con peticiones reales de la sesión residente: resultado público visible; interno, etiqueta interna y metadatos del documento restringido ausentes |
| Concurrencia real MySQL (dos conexiones, misma clave) | Verificado: `MantenimientoIdempotencyMysqlConcurrencyTest` en verde contra `resuelve_concurrencia_test` (1 mantenimiento único; reutilización de clave con otra PQRS o datos incompatibles rechazada sin crear ni modificar recursos incorrectos) |
| Suite completa | 719 pruebas: 710 en verde, 9 omitidas por requerir MySQL, 3.304 assertions |
| MER en PNG | Regenerado desde `erDiagram.mmd` (incluye `fija_version_exacta`) |

Pendiente de aceptación del Product Owner: capturas de pantalla y video (guion
disponible en [guion-video.md](guion-video.md)); las capturas requieren una
ventana de escritorio visible, por lo que se solicitan como parte de esta
aceptación visual.
