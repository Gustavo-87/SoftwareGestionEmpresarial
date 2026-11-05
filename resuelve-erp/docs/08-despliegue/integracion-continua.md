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
3. Generación y validación de un `APP_KEY` temporal.
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

### Resultado local (5 de noviembre de 2026)

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

Estos resultados son **verificación local**; la ejecución real de GitHub
Actions sigue **pendiente** de confirmar con un `pull_request` hacia `main`.

## 6. Evidencia

- Workflow activo: `.github/workflows/ci.yml` (raíz del repositorio).
- Workflow anterior conservado: `resuelve-erp/.github/workflows/ci.yml`.
- Entorno aislado de pruebas: `resuelve-erp/phpunit.xml`.
- Estilo de código: `resuelve-erp/vendor/bin/pint` (Laravel Pint, en
  `require-dev` de `composer.json`).

## Control documental

- **Versión:** v1.1
- **Fecha de creación:** 5 de noviembre de 2026
- **Fecha de última actualización:** 5 de noviembre de 2026
