#!/bin/zsh

set -eu

script_dir="$(cd "$(dirname "$0")" && pwd)"
project_dir="$(cd "$script_dir/.." && pwd)"
template_file="$script_dir/com.resuelve-pqrs.demo-tunnel.plist.example"
state_dir="$project_dir/storage/app/demo-tunnel"
log_dir="$project_dir/storage/logs"
service_file="$state_dir/com.resuelve-pqrs.demo-tunnel.plist"
log_file="$log_dir/cloudflared-demo.log"
service_label="com.resuelve-pqrs.demo-tunnel"
service_domain="gui/$(id -u)"
action="${1:-status}"

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Falta el comando requerido: $1" >&2
        return 1
    fi
}

is_loaded() {
    launchctl print "$service_domain/$service_label" >/dev/null 2>&1
}

is_running() {
    local service_info

    service_info="$(launchctl print "$service_domain/$service_label" 2>/dev/null)" || return 1
    print -r -- "$service_info" | rg -q '^[[:space:]]*pid = [0-9]+'
}

current_url() {
    [[ -f "$log_file" ]] || return 0
    rg -o 'https://[-a-z0-9]+\.trycloudflare\.com' "$log_file" | tail -n 1 || true
}

xml_escape() {
    local value="$1"

    value="${value//&/&amp;}"
    value="${value//</&lt;}"
    value="${value//>/&gt;}"
    print -r -- "$value"
}

generate_service_file() {
    local project_xml cloudflared_xml log_xml line temporary_file

    [[ -f "$template_file" ]] || {
        echo "No existe la plantilla: $template_file" >&2
        return 1
    }

    mkdir -p "$state_dir" "$log_dir"
    [[ -d "$state_dir" && -w "$state_dir" ]] || {
        echo "No se puede escribir en: $state_dir" >&2
        return 1
    }
    [[ -d "$log_dir" && -w "$log_dir" ]] || {
        echo "No se puede escribir en: $log_dir" >&2
        return 1
    }

    project_xml="$(xml_escape "$project_dir")"
    cloudflared_xml="$(xml_escape "$cloudflared_path")"
    log_xml="$(xml_escape "$log_file")"
    temporary_file="$(mktemp "$state_dir/.demo-tunnel.plist.XXXXXX")"

    while IFS= read -r line || [[ -n "$line" ]]; do
        line="${line//__PROJECT_DIR__/$project_xml}"
        line="${line//__CLOUDFLARED_PATH__/$cloudflared_xml}"
        line="${line//__LOG_FILE__/$log_xml}"
        print -r -- "$line"
    done < "$template_file" > "$temporary_file" || {
        rm -f "$temporary_file"
        return 1
    }

    mv "$temporary_file" "$service_file" || {
        rm -f "$temporary_file"
        return 1
    }

    plutil -lint "$service_file" >/dev/null
}

unload_service() {
    if is_loaded; then
        launchctl bootout "$service_domain/$service_label" >/dev/null 2>&1 || {
            echo "No se pudo descargar el servicio $service_label." >&2
            return 1
        }

        for _ in {1..10}; do
            if ! is_loaded; then
                return 0
            fi
            sleep 1
        done

        echo "El servicio $service_label no terminó de descargarse después de 10 segundos." >&2
        return 1
    fi
}

remove_local_artifacts() {
    rm -f "$service_file" "$log_file"
    rmdir "$state_dir" 2>/dev/null || true
}

cleanup_failed_start() {
    local exit_code="${1:-1}"

    trap - EXIT INT TERM HUP
    unload_service || true
    remove_local_artifacts
    echo "El arranque falló; el servicio y sus archivos locales fueron limpiados para evitar exposición accidental." >&2
    (( exit_code == 0 )) && exit_code=1
    exit "$exit_code"
}

case "$action" in
    start)
        [[ "$(uname -s)" == "Darwin" ]] || {
            echo "Esta herramienta solo es compatible con macOS." >&2
            exit 1
        }
        require_command launchctl
        require_command cloudflared
        require_command plutil
        require_command rg
        cloudflared_path="$(command -v cloudflared)"

        if is_running; then
            echo "El túnel ya está activo. Usa 'status' para consultar su URL." >&2
            exit 1
        fi

        if is_loaded; then
            unload_service
        fi

        echo "ADVERTENCIA: la aplicación local quedará expuesta temporalmente a Internet."
        [[ -t 0 ]] || {
            echo "El arranque requiere confirmación desde una terminal interactiva." >&2
            exit 1
        }
        read -r "confirmation?¿Continuar? [s/N] "
        [[ "$confirmation" == [sS] ]] || {
            echo "Arranque cancelado."
            exit 1
        }

        generate_service_file
        : > "$log_file"
        trap 'cleanup_failed_start $?' EXIT
        trap 'exit 130' INT
        trap 'exit 143' TERM
        trap 'exit 129' HUP
        launchctl bootstrap "$service_domain" "$service_file"
        launchctl kickstart -k "$service_domain/$service_label"

        process_started=false
        for _ in {1..10}; do
            if is_running; then
                process_started=true
                break
            fi

            if ! is_loaded; then
                echo "El servicio se descargó antes de iniciar cloudflared." >&2
                exit 1
            fi

            sleep 1
        done

        if [[ "$process_started" != true ]]; then
            echo "cloudflared no publicó un PID después de 10 segundos." >&2
            exit 1
        fi

        for _ in {1..30}; do
            if ! is_running; then
                echo "cloudflared terminó antes de publicar una URL." >&2
                exit 1
            fi

            tunnel_url="$(current_url)"
            if [[ -n "$tunnel_url" ]]; then
                trap - EXIT INT TERM HUP
                echo "Túnel temporal activo. Deténlo con: scripts/demo-tunnel.sh stop"
                echo "$tunnel_url"
                exit 0
            fi
            sleep 1
        done

        echo "El servicio inició, pero no entregó una URL en 30 segundos." >&2
        exit 1
        ;;
    status)
        [[ "$(uname -s)" == "Darwin" ]] || {
            echo "Esta herramienta solo es compatible con macOS." >&2
            exit 1
        }
        require_command launchctl
        require_command rg

        if is_running; then
            tunnel_url="$(current_url)"
            echo "Túnel temporal activo."
            if [[ -n "$tunnel_url" ]]; then
                echo "$tunnel_url"
            else
                echo "El servicio está activo, pero todavía no publicó una URL." >&2
                exit 1
            fi
        else
            echo "Túnel inactivo."
            exit 1
        fi
        ;;
    stop)
        [[ "$(uname -s)" == "Darwin" ]] || {
            echo "Esta herramienta solo es compatible con macOS." >&2
            exit 1
        }
        require_command launchctl

        if is_loaded; then
            unload_service
            remove_local_artifacts
            echo "Túnel detenido."
        else
            remove_local_artifacts
            echo "Túnel inactivo; no había ningún servicio que detener."
        fi
        ;;
    *)
        echo "Uso: $0 {start|status|stop}" >&2
        exit 2
        ;;
esac
