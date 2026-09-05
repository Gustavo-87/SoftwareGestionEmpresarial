# Túnel temporal de demostración

Esta herramienta expone temporalmente la aplicación local de Resuelve mediante
un túnel rápido de Cloudflare. Solo funciona en macOS y requiere `zsh`,
`launchctl`, `cloudflared` y `rg`.

> **Riesgo:** al iniciar el túnel, el servicio disponible en
> `http://localhost:80` queda accesible desde Internet mediante una URL pública.
> La herramienta no agrega autenticación. Detén el túnel inmediatamente después
> de la demostración.

## Uso

Desde la raíz del proyecto:

```shell
scripts/demo-tunnel.sh start
scripts/demo-tunnel.sh status
scripts/demo-tunnel.sh stop
```

`start` muestra una advertencia y requiere confirmación interactiva. Si el
arranque falla o no aparece una URL nueva en 30 segundos, descarga el servicio.
El servicio no se inicia al cargar su configuración ni se reinicia
automáticamente.

## Archivos locales

El script detecta las rutas del proyecto y de `cloudflared`, y genera estos
archivos locales ya ignorados por las reglas de `storage/`:

- `storage/app/demo-tunnel/com.resuelve-pqrs.demo-tunnel.plist`
- `storage/logs/cloudflared-demo.log`

La plantilla versionada
`scripts/com.resuelve-pqrs.demo-tunnel.plist.example` contiene únicamente
marcadores portables; no debe reemplazarse por un plist con rutas personales.
