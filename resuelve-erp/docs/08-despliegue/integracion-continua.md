# Integración continua

## 1. Propósito

Este documento describe el flujo de integración continua (CI) de Resuelve, su
ubicación en el repositorio, el entorno aislado de ejecución y la equivalencia
de sus pasos con la verificación local.

## 2. Ubicación del workflow

El workflow activo es `.github/workflows/ci.yml` en la **raíz del repositorio**
`SoftwareGestionEmpresarial`, porque GitHub Actions solo detecta workflows en
`.github/workflows/` de la raíz y la aplicación vive en la subcarpeta
`resuelve-erp/`. El workflow anterior,
`resuelve-erp/.github/workflows/ci.yml`, se conserva como referencia histórica
y **no se ejecuta** por su ubicación.

Los pasos operan sobre `resuelve-erp/` mediante
`defaults.run.working-directory: resuelve-erp`. Las claves de caché y las
rutas de dependencias se acotan a `resuelve-erp/composer.lock` y
`resuelve-erp/package-lock.json` para no depender de otros archivos del
repositorio.

## 3. Disparador y entorno

- **Disparador:** `pull_request` hacia `main`, con cancelación de ejecuciones
  superadas por la misma solicitud (`concurrency`).
- **Permisos:** solo lectura de contenidos (`contents: read`).
- **Entorno aislado:** `APP_ENV=testing`, base de datos SQLite en memoria,
  caché, sesión y cola en `array`/`sync` y correo `array`: la ejecución no
  toca la base de datos ni el correo reales. Se genera un `APP_KEY` temporal
  que no se persiste.

## 4. Pasos del flujo

1. Checkout del repositorio (historia completa para el control del diff).
2. Configuración de PHP 8.3 con las extensiones requeridas.
3. Preparación de `.env` temporal desde `.env.example`, seguida de generación
   y validación de un `APP_KEY` efímero. Las variables aisladas del workflow
   prevalecen sobre el ejemplo; no se usa el `.env` del equipo.
4. Restauración de la caché de Composer acotada a `resuelve-erp/composer.lock`
   e instalación de dependencias PHP.
5. **Comprobación de estilo de código:** `vendor/bin/pint --test` (sin
   reescritura de archivos).
6. Configuración de Node 22 con caché acotada a
   `resuelve-erp/package-lock.json`, `npm ci` y `npm run build`.
7. Comprobación de formato del diff (`git diff --check`), con una excepción:
   `docs/04-desarrollo-agil/product-backlog.md` queda excluido de esta
   comprobación porque sus saltos de línea de historias usan dos espacios
   finales intencionales (Markdown), que `git diff --check` reporta como
   *trailing whitespace*. La comprobación sigue aplicándose al resto de
   archivos del diff mediante el pathspec
   `':(top,exclude)resuelve-erp/docs/04-desarrollo-agil/product-backlog.md'`.
8. Compilación y limpieza de vistas Blade (`php artisan view:cache` /
   `view:clear`).
9. Ejecución de la suite de pruebas (`php artisan test`) sobre SQLite en
   memoria.

## 5. Verificación local equivalente

Desde `resuelve-erp/`:

```bash
vendor/bin/pint --test
npm run build
php artisan view:cache && php artisan view:clear
php artisan test
git diff --check -- ':(top,exclude)resuelve-erp/docs/04-desarrollo-agil/product-backlog.md'
```

### Resultado local previo (5 de noviembre de 2026)

Ejecutado sobre el árbol de trabajo con la configuración aislada de pruebas
(`phpunit.xml`: SQLite en memoria):

- `vendor/bin/pint --test`: **en verde** (tras el saneamiento de formato de
  los archivos PHP reportados por Pint; solo formato, sin cambios de lógica).
- `php artisan test`: **en verde** — 733 pruebas, 724 aprobadas y 9 omitidas
  (MySQL), 3483 aserciones.
- `git diff --check`: **sin errores** con la excepción documentada del
  Product Backlog (§4, paso 7).
- `npm run build` y `php artisan view:cache`/`view:clear`: verificados en la
  corrección del flujo CI.

Estos resultados conservan la **verificación local previa**. La ejecución
posterior en GitHub Actions se registra por separado a continuación.

## 6. Ejecución verificada en GitHub Actions

- **Fecha:** 5 de noviembre de 2026, entre las 20:46 y las 20:47
  (America/Bogota).
- **Solicitud:** [PR #3 — Panel y bloqueo de acceso](https://github.com/Gustavo-87/SoftwareGestionEmpresarial/pull/3),
  revisión de origen `4ff5e5d735c1a0b96147a57abf7dd65b67f1f375`; fusionada
  en `main` como `a8d8166eea20f63cc24c58713208d47cbc8e8ba1`. La ejecución
  corresponde a la comprobación del PR.
- **Ejecución:** [CI #5, 42146003145](https://github.com/Gustavo-87/SoftwareGestionEmpresarial/actions/runs/42146003145),
  tarea `test` con conclusión `success` (resultado satisfactorio).
- **Pint:** aprobado sobre **411 archivos**.
- **Pruebas:** el resumen del registro distingue **24 aprobadas y 762 con
  advertencias**, con **3.763 aserciones** y duración de 28,73 segundos.
  El resultado satisfactorio del CI no acredita una suite libre de
  advertencias; en esa ejecución su diagnóstico y corrección seguían pendientes. La
  corrección posterior se registra en el Sprint 26 (§7–8).
- **Otros pasos:** instalación de dependencias, compilación de Vite,
  comprobación del diff y compilación/limpieza de vistas Blade satisfactorias.

Las cifras de cada sprint corresponden a sus verificaciones locales
históricas; no se sustituyen por este resultado posterior. La ejecución usa
SQLite en memoria y correo de pruebas: no acredita entrega real de correo
ni una ejecución de concurrencia sobre MySQL.

## 7. Verificación local del Sprint 26

El 5 de noviembre de 2026 se verificaron **802 pruebas: 793 aprobadas y 9
omitidas por MySQL, 3.860 aserciones**, sin advertencias de configuración de las pruebas. Pint,
compilación Vite, validación Composer y auditoría de dependencias PHP
satisfactorios. `composer audit` no reportó avisos de vulnerabilidades conocidas.

La preparación de `.env` temporal en el workflow corrige la configuración que
faltaba durante las pruebas históricas. El resultado de Actions se registra por separado a continuación; no modifica
las cifras históricas del PR #3.

## 8. Ejecución verificada del PR #5

- **Fecha:** 6 de noviembre de 2026, 08:35–08:36 (America/Bogota).
- **Solicitud:** [PR #5 — Auditoría y evidencias hasta el requisito 14](https://github.com/Gustavo-87/SoftwareGestionEmpresarial/pull/5).
- **Revisión de origen:** `4b410c7d65f0481106241767878a9a3f7f47264d`.
- **Ejecución:** [CI #7, 42226657327](https://github.com/Gustavo-87/SoftwareGestionEmpresarial/actions/runs/42226657327),
  tarea `test` con resultado `success`.
- **Pruebas:** **793 aprobadas y 9 omitidas**, 3.860 aserciones, 41,89 segundos
  sobre PHP 8.3/SQLite. No se repiten las advertencias por falta de `.env`;
  las nueve omisiones corresponden a pruebas específicas de MySQL.
- **Otros pasos:** instalación PHP, Pint, instalación de frontend, compilación
  Vite, diff y compilación/limpieza de Blade satisfactorios.

El registro conserva advertencias de obsolescencia del entorno JavaScript de
las acciones de GitHub (Node 20 forzado a 24, `punycode` y `url.parse`). No son
fallos de la aplicación y no se declara un registro completamente sin avisos.

La instalación de frontend de esa ejecución también reportó cuatro alertas de
seguridad de dependencias. Antes de integrar se corrigieron de forma
compatible: Vite **8.3.6**, concurrently **9.2.5**, shell-quote **1.12.0**,
source-map-js **1.2.2** y PostCSS **8.5.32** en el archivo de bloqueo. La
comprobación local posterior `npm audit` reporta **0 vulnerabilidades** y
`npm run build` pasa. Referencias de los avisos corregidos:
[shell-quote](https://github.com/advisories/GHSA-pqg4-j6r4-53mv),
[source-map-js](https://github.com/advisories/GHSA-68fv-2mgg-jv7q) y
[Vite](https://github.com/advisories/GHSA-rq7h-c2jc-7f22).
La comprobación del siguiente commit se verifica antes de fusionar y se
enlaza en la descripción del PR; no se atribuye a este resultado histórico.

## 9. Evidencia

- Workflow activo: `.github/workflows/ci.yml` (raíz del repositorio).
- Workflow anterior conservado: `resuelve-erp/.github/workflows/ci.yml`.
- Entorno aislado de pruebas: `resuelve-erp/phpunit.xml`.
- Estilo de código: `resuelve-erp/vendor/bin/pint` (Laravel Pint, en
  `require-dev` de `composer.json`).

## Control documental

- **Versión:** v1.4
- **Fecha de creación:** 5 de noviembre de 2026
- **Fecha de última actualización:** 6 de noviembre de 2026
